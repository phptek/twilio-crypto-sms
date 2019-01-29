<?php

/**
 * @author  Russell Michell 2019 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\API;

use SMSCryptoApp\Crypto\CryptoCurrency;

/**
 * Base class for common payment logic.
 */
abstract class PaymentClientAPI
{
    /**
     * @var CryptoCurrency
     */
    protected $currency;
    
    /**
     * Get the current currency.
     */
    public function setCurrency(string $name) : void
    {
        $class = ucfirst(strtolower($name));
        $fqcn = 'SMSCryptoApp\Crypto\\' . $class;

        if (!class_exists($fqcn)) {
            throw new \Exception(sprintf('Cryptocurrency "%s" was not found!', $name));
        }

        $this->currency = new $fqcn();
    }

    /**
     * Get the currently set currency.
     */
    public function getCurrency() : CryptoCurrency
    {
        return $this->currency;
    }
    
    /**
     * Generates a new address on each call.
     *
     * IMPORTANT: in a production app you would manage your own software wallet
     * using a SECRET private key, stored OFFLINE WITH BACKUPS. Remember the golden
     * rule: "Not your keys. Not your coins".
     *
     * @return string
     */
    public function getAddress() : string;
    
}
