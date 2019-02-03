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
    /**
     * The amount in Bitcoin that a single message costs to send.
     * 
     * @todo Take the current miners fee from Blockcypher API and adjust the PAYMENT_AMOUNT
     * so that we always make USD 0.005 or 325 Satoshi each time.
     * @var string
     */
    const PAYMENT_AMOUNT = '0.00000750'; // 750 Satoshi ~= USD 0.03

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
