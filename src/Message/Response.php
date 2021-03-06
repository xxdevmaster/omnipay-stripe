<?php

namespace Omnipay\Stripe\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * Stripe Response.
 *
 * This is the response class for all Stripe requests.
 *
 * @see \Omnipay\Stripe\Gateway
 */
class Response extends AbstractResponse
{
    /**
     * Request id
     *
     * @var string URL
     */
    protected $requestId = null;

    /**
     * @var array
     */
    protected $headers = [];

    public function __construct(RequestInterface $request, $data, $headers = [])
    {
        $this->request = $request;
        $this->data = json_decode($data, true);
        $this->headers = $headers;
    }


    /**
     * Is the transaction successful?
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return !isset($this->data['error']);
    }

    /**
     * @return bool
     */
    public function isRedirect()
    {
        if (isset($this->data['object']) && 'source' === $this->data['object']) {
            if ($this->cardCan3DS() || ($this->isThreeDSecureSourcePending() && $this->getRedirectUrl() !== null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the ThreeDSecure source has status pending
     *
     * @return bool
     */
    protected function isThreeDSecureSourcePending()
    {
        if (isset($this->data['type']) && 'three_d_secure' === $this->data['type']) {
            if (isset($this->data['status']) && 'pending' === $this->data['status']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getRedirectUrl()
    {
        if (isset($this->data['object']) && 'source' === $this->data['object'] &&
            isset($this->data['type']) && 'three_d_secure' === $this->data['type'] &&
            !empty($this->data['redirect']['url'])
        ) {
            return $this->data['redirect']['url'];
        }

        return null;
    }

    /**
     * Check if card requires 3DS
     *
     * @return bool
     */
    protected function cardCan3DS()
    {
        if (isset($this->data['type']) && 'card' === $this->data['type']) {
            if (isset($this->data['card']['three_d_secure']) &&
                in_array($this->data['card']['three_d_secure'], ['required', 'optional', 'recommended'], true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the payment need to 3D Secure authentication ?
     *
     * @return bool
     */
    public function is3DSecure()
    {
        return !isset($this->data['error']) && $this->data['status'] == 'requires_action' &&
            $this->data['next_action']['type'] == 'use_stripe_sdk';
    }

    /**
     * Get the charge reference from the response of retrieve PaymentIntent.
     *
     * @return array|null
     */
    public function getChargeReference()
    {
        if (isset($this->data['object']) && $this->data['object'] == 'payment_intent') {
            return $this->data['charges']['data'][0]['id'];
        }

        return null;
    }

    /**
     * Get the transaction reference.
     *
     * @return string|null
     */
    public function getTransactionReference()
    {
        if (isset($this->data['object']) && 'payment_intent' === $this->data['object']) {
            return $this->data['id'];
        }
        if (isset($this->data['error']) && isset($this->data['error']['charge'])) {
            return $this->data['error']['charge'];
        }

        return null;
    }

    /**
     * Get the PaymentMethod reference.
     *
     * @return string|null
     */
    public function getPaymentMethodReference()
    {
        if (isset($this->data['object']) && 'payment_method' === $this->data['object']) {
            return $this->data['id'];
        }

        if (isset($this->data['object']) && 'setup_intent' === $this->data['object']) {
            return $this->data['payment_method'];
        }

        return null;
    }

    /**
     * Get the PaymentIntent reference.
     *
     * @return string|null
     */
    public function getPaymentIntentReference()
    {
        if (isset($this->data['object']) && 'payment_intent' === $this->data['object']) {
            return $this->data['id'];
        }

        return null;
    }

    /**
     * Get a customer reference, for createCustomer requests.
     *
     * @return string|null
     */
    public function getCustomerReference()
    {
        if (isset($this->data['object']) && 'customer' === $this->data['object']) {
            return $this->data['id'];
        }

        if (isset($this->data['object']) && ('payment_method' === $this->data['object'] || 'payment_intent' === $this->data['object'])) {
            if (!empty($this->data['customer'])) {
                return $this->data['customer'];
            }
        }

        return null;
    }

    /**
     * Get the client secret reference.
     *
     * @return string|null
     */
    public function getClientSecret()
    {
        if (isset($this->data['object']) && 'payment_intent' === $this->data['object']) {
            return $this->data['client_secret'];
        }

        if (isset($this->data['object']) && 'setup_intent' === $this->data['object']) {
            return $this->data['client_secret'];
        }

        return null;
    }

    /**
     * Get the card data from the response.
     *
     * @return array|null
     */
    public function getCard()
    {
        if (isset($this->data['card'])) {
            return $this->data['card'];
        }

        return null;
    }


    /**
     * Get the card data from the response of purchaseRequest.
     *
     * @return array|null
     */
    public function getSource()
    {
        if (isset($this->data['source']) && $this->data['source']['object'] == 'card') {
            return $this->data['source'];
        }

        return null;
    }

    /**
     * Get the list object from a result
     *
     * @return array|null
     */
    public function getList()
    {
        if (isset($this->data['object']) && $this->data['object'] == 'list') {
            return $this->data['data'];
        }

        return null;
    }

    /**
     * Get the error message from the response.
     *
     * Returns null if the request was successful.
     *
     * @return string|null
     */
    public function getMessage()
    {
        if (!$this->isSuccessful() && isset($this->data['error']) && isset($this->data['error']['message'])) {
            return $this->data['error']['message'];
        }

        return null;
    }

    /**
     * Get the error message from the response.
     *
     * Returns null if the request was successful.
     *
     * @return string|null
     */
    public function getCode()
    {
        if (!$this->isSuccessful() && isset($this->data['error']) && isset($this->data['error']['code'])) {
            return $this->data['error']['code'];
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getRequestId()
    {
        if (isset($this->headers['Request-Id'])) {
            return $this->headers['Request-Id'][0];
        }

        return null;
    }


}
