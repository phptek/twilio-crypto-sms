<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\Crypto;

/**
 * All concrete classes as CryptoCurrency implementors, should be able to be
 * exchanged for one-another.
 */
interface CryptoCurrency
{
    /**
     * Return a currency-specific populated URI scheme.
     *
     * @param  string $address
     * @param  string  $amount
     * @return string
     */
    public function uriScheme(string $address, string $amount) : string;

    /**
     * Return the name of the current currency.
     *
     * @return string
     */
    public function name() : string;

    /**
     * Return the current currency's symbol.
     *
     * @return string
     */
    public function symbol() : string;

    /**
     * Return this currency's ISO4217 compatible currency code.
     *
     * @see    https://en.wikipedia.org/wiki/ISO_4217#Cryptocurrencies
     * @return string
     */
    public function iso4217() : string;

}
