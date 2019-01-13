<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace SMSCryptoApp\API;

/**
 *
 * @author russellm
 */
interface PaymentClientAPI
{
    /**
     * Simple setter for the currency to use.
     *
     * @param  string $name
     * @return void
     * @throws Exception
     */
    public function setCurrency(string $name) : void;
    
    /**
     * @return CryptoCurrency
     */
    public function getCurrency() : string;
    
    /**
     * Generates a new address on each call.
     *
     * IMPORTANT: in a production app you would manage your own software wallet
     * using a secret private key, stored OFFLINE for security. Remember the golden
     * rule: "Not your keys? Not your coins".
     *
     * @return string
     */
    public function getAddress() : string;
    
}
