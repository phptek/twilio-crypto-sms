<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\Crypto;

// Application: Crypto
use SMSCryptoApp\Crypto\Currency;

/**
 * Represents the Bitcoin cryptocurrency within the app.
 */
class Bitcoin implements Currency
{
    const PAYMENT_AMOUNT = '0.00001'; // 0.00001 BTC = 1,000 Satoshi

    /**
     * {@inheritdoc}
     * @see https://en.bitcoin.it/wiki/BIP_0021
     */
    public function uriScheme(string $address, string $amount) : string
    {
        return sprintf('bitcoin:%s?amount=%s', $address, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function name() : string
    {
        return 'bitcoin';
    }

    /**
     * {@inheritdoc}
     */
    public function network() : string
    {
        return 'test3';
    }

    /**
     * {@inheritdoc}
     */
    public function symbol() : string
    {
        return 'btc';
    }

    /**
     * {@inheritdoc}
     */
    public function iso4217() : string
    {
        return 'XBT';
    }

}
