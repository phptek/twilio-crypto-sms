<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\Crypto;

// Application: Crypto
use SMSCryptoApp\Crypto\CryptoCurrency;

/**
 * Represents the Ether cryptocurrency within the app.
 */
class Ethereum implements CryptoCurrency
{
    const PAYMENT_AMOUNT = '0.00001'; // 0.00001 ETH = 1 mEth

    /**
     * {@inheritdoc}
     */
    public function uriScheme(string $address, string $amount) : string
    {
        return sprintf('ethereum:%s?amount=%s', $address, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function name() : string
    {
        return 'ethereum';
    }

    /**
     * {@inheritdoc}
     */
    public function network() : string
    {
        return 'test';
    }

    /**
     * {@inheritdoc}
     */
    public function symbol() : string
    {
        return 'beth';
    }

    /**
     * {@inheritdoc}
     */
    public function iso4217() : string
    {
        return 'ETH';
    }

}
