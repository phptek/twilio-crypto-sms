<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\API;

// SilverStripe: Environment
use SilverStripe\Core\Environment as Env;

// Bitcoin: BlockCypher SDK
use BlockCypher\Auth\SimpleTokenCredential;
use BlockCypher\Rest\ApiContext;
use BlockCypher\Client\AddressClient;
use BlockCypher\Api\Address;
use BlockCypher\Client\TXClient;
use BlockCypher\Api\WebHook;

// App:
use SMSCryptoApp\Crypto\Currency;

/**
 * An implementation of {@link ClientProvider} for querying the Bitcoin and Ethereum
 * blockchains via the BlockCypher Rest API using its various endpoint-specific
 * clients.
 *
 * You will need an account with BlockCypher in order to make use of its API:
 * - https://accounts.blockcypher.com/signup.
 * - https://www.blockcypher.com/quickstart/
 * - http://blockcypher.github.io/php-client/
 */
class BlockCypherClient
{
    const UNAVAILABLE = 'Unavailable';

    /**
     * @var CryptoCurrency
     */
    protected $currency;

    /**
     * Simple setter.
     *
     * @param  string $name
     * @return void
     * @throws Exception
     */
    public function setCurrency(string $name) : void
    {
        $class = ucfirst(strtolower($name));
        $fqcn = 'SMSCryptoApp\Crypto\\' . $class;

        if (!class_exists($fqcn)) {
            throw new \Exception('Cryptocurrency was not found!');
        }

        $this->currency = new $fqcn();
    }

    /**
     * @return CryptoCurrency
     */
    public function getCurrency() : Currency
    {
        return $this->currency;
    }

    /**
     * Generates a new address on each call.
     *
     * IMPORTANT: in a production app you would manage your own software wallet
     * using a secret private key, stored OFFLINE for security. Remember the golden
     * rule: "Not your keys? Not your coins".
     *
     * Endpoint called: '/v1/btc/test3/addrs'
     *
     * @return string
     */
    public function getAddress() : string
    {
        $context = $this->apiContext();
        $client = new AddressClient($context);

        try {
            return $client->generateAddress()->getAddress();
        } catch (\Exception $e) {
            return self::UNAVAILABLE;
        }
    }

    /**
     * Returns the balance of $address in Satoshi denominations.
     *
     * Endpoint called: '/v1/btc/test3/addrs/<address>/balance'
     *
     * @param  string $address
     * @return string
     */
    public function getBalance(string $address) : string
    {
        $context = $this->apiContext();
        $client = new AddressClient($context);
        $balance = $client->getBalance($address)->getBalance();

        return number_format(round($balance, 8), 8);
    }

    /**
     * Subscribe BlockCypher to a WebHook located within our application.
     *
     * @param  array   $payload    The payload to subscribe with (listen for responses to).
     * @param  string  $hookUrl    The application URL that BlockCypher should call - the hook.
     * @param  array   $hookParams Additional query string params to tack-onto $hookUrl.
     * @return WebHook $response   The response from BlockCypher as an instance of WebHook.
     */
    public function subscribeHook(array $payload, string $hookUrl, array $hookParams = []) : WebHook
    {
        if ($hookParams) {
            $hookUrl = sprintf('%s?%s', $hookUrl, http_build_query($hookParams));
        }

        $context = $this->apiContext();
        $webHook = (new WebHook())
                ->setUrl($hookUrl)
                ->setEvent($payload['event'])
                ->setAddress($payload['address'])
                ->setToken(Env::getEnv('BLOCKCYPHER_TOK'));

        // Subscribe
        return $webHook->create($context);
    }

    /**
     * Queries the network for unconfirmed transactions broadcasted at around the
     * time this method is called. It can be used to filter transactions containing
     * a given address passed-in by: $filter['address'].
     * 
     * In a production system, utilise BlockCypher's "confidence factor" to determine
     * liklihood of double-spends.
     *
     * @param  string  $address
     * @return bool    True if $address is found as an output on a broadcasted
     *                 and unconfirmed transaction. Returns false otherwise.
     * @see    https://www.blockcypher.com/dev/bitcoin/#confidence-factor
     */
    public function isAddressBroadcasted(string $address) : bool
    {
        $context = $this->apiContext();
        $txClient = new TXClient($context);
        $params = ['instart' => 0, 'limit' => 150];

        foreach ($txClient->getUnconfirmed($params) as $tx) {
            foreach ($tx->getOutputs() as $output) {
                if (in_array($address, (array) $output->getAddresses())) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Determine the number of confirmations this address has.
     * 
     * @param  string $address
     * @return int
     */
    public function addressHasConfirmations(string $address) : int
    {
        $context = $this->apiContext();
        $client = new AddressClient();
        $addr = $client->get($address, [], $context);
        
        return $addr->getNTx();
    }

    /**
     * Get an SDK ApiContext for this client.
     *
     * @return ApiContext
     * @see    https://github.com/blockcypher/php-client/wiki/Sandbox-vs-Live
     */
    private function apiContext() : ApiContext
    {
        $config = [
            'mode' => 'sandbox',
            'log.LogEnabled' => true,
            'log.FileName' => '/var/tmp/BlockCypher.log', // <-- use /var/tmp for peristent logging
            'log.LogLevel' => 'DEBUG',
            'validation.level' => 'log',
        ];

        return ApiContext::create(
            $this->currency->network(),
            $this->currency->symbol(),
            'v1',
            new SimpleTokenCredential(Env::getEnv('BLOCKCYPHER_TOK')),
            $config
        );
    }

}
