<?php

/**
 * @author  Russell Michell 2019 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\API;

// LND
use GuzzleHttp\Client as GuzzleClient;
use Lnd\Rest\Configuration;
use Lnd\Rest\Api\LightningApi;
use Lnd\Rest\Model\LnrpcUnlockWalletRequest;
use Lnd\Rest\Model\LnrpcOpenChannelRequest;
use Lnd\Rest\Model\LnrpcInvoice;
use Lnd\Rest\Api\WalletUnlockerApi;
use \Exception;

// App:
use SMSCryptoApp\Crypto\CryptoCurrency;
use SMSCryptoApp\API\PaymentClientAPI;

/**
 * Basic wrapper around ndeet/ln-lnd-rest for interacting with a local or remote
 * lnd daemon over lnd's REST interface.
 * 
 * Tested only on a local instance of lnd 0.5.1-beta.
 * 
 * To make this work, you need a bitcoin full node running locally e.g. bitcoind
 * and an instance of lnd setup to talk to the fullnode using each's RPC interfaces.
 * 
 * Once started, your lnd node has its own wallet which needs to be charged with Bitcoin
 * before a channel can be opened, and any lightning payments made. This is done by
 * first charging the full-node's wallet with BTC (By mining, or on testnet, by paying
 * directly from a faucet). Once charged, lnd connects to your full node, and payments
 * from bitcoind, can be made to lnd and onward to the LN from there.
 * 
 * Example:
 * 
 * #> btcd --simnet --txindex --rpcuser=kek --rpcpass=kek
 * #> mkdir ~/.lnd && cd ~/.lnd && lnd \
 *  --rpclisten=localhost:10001 \
 *  --listen=localhost:10011 \
 *  --restlisten=localhost:8001 \
 *  --datadir=data \
 *  --logdir=log \
 *  --debuglevel=debug \
 *  --bitcoin.simnet \
 *  --bitcoin.active \
 *  --bitcoin.node=btcd \
 *  --btcd.rpcuser=kek \
 *  --btcd.rpcpass=kek \
 *  --externalip=127.0.0.1
 * 
 * @package
 * @author Russell Michell 2019 <russ@theruss.com>
 * @see https://api.lightning.community/rest/index.html#lnd-rest-api-reference
 * @see https://github.com/ndeet/php-ln-lnd-rest
 * @see https://dev.lightning.community/tutorial/
 * 
 * Usage example:
 * 
 * $options = [
    'user' => null,
    'wallet_password' => 'alicetest123',
    'host' => null,
    'port' => 8001,
    'cert_path' => '.lnd/tls.cert',
    'macaroon_path' => '.lnd/data/chain/bitcoin/mainnet/admin.macaroon',
  ];
  echo (new LNDClient($options))->getInfo();
 * 
 * Raw curl example
 * 
 * MACAROON_HEADER="Grpc-Metadata-macaroon: $(xxd -ps -u -c 1000 /home/russellm/.lnd/data/chain/bitcoin/simnet/admin.macaroon)"
 * curl -X POST --cacert /home/russellm/.lnd/tls.cert -d '{"wallet_password":"YWxpY2V0ZXN0MTIz"}' --header "$MACAROON_HEADER" https://localhost:8001/v1/unlockwallet
 */

class LNDClient implements PaymentClientAPI
{
    /**
     * Lock file that tells us whether or not our lnd daemon's wallet is
     * unlocked or not.
     * 
     * @var string
     */
    const LND_LOCK = '/tmp/lnd.lock';

    /**
     * @var array
     */
    protected $options = [];
    protected $apiConfig = null;

    /**
     * @var bool
     */
    protected $lndWalletIsLocked = true;

    /**
     * @param  array $opts
     * @return void
     * @throws Exception
     */
    public function __construct($opts = [])
    {
        $this->options['lndUser'] = $opts['user'] ?: posix_getpwuid(posix_getuid())['dir'];
        $this->options['lndWalletPassword'] = $opts['wallet_password'];
        $this->options['lndHost'] = $opts['host'] ?: 'localhost';
        $this->options['lndPort'] = $opts['port'] ?: 8080;
        $this->options['lndCertPath'] = $this->lndUser . '/' . $opts['cert_path'];
        $this->options['lndMacaroonPath'] = $this->lndUser . '/' . $opts['macaroon_path'];

        // We need to use Configuration class for the url and can't pass it directly in GuzzleClient.
        $apiConfig = new Configuration();
        $apiConfig->setHost(sprintf('https://%s:%s', $this->lndHost, $this->lndPort));
        $this->apiConfig = $apiConfig;

        try {
            $this->init();
        } catch (Exception $e) {
            throw new Exception('Unable to initialise LND client: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function init(): void
    {
        if (!file_get_contents($this->options['lndCertPath'])) {
            throw new Exception('tls.cert not found');
        }

        if (!file_get_contents($this->options['lndMacaroonPath'])) {
            throw new Exception('macaroon not found');
        }

        $this->unlockWallet($this->lndUser);
    }

    /**
     * Guzzle HTTP client.
     * 
     * @return GuzzleClient
     */
    private function client(): GuzzleClient
    {
        return new GuzzleClient([
            'debug' => true,
            'verify' => false,
            'headers' => [
                'Grpc-Metadata-macaroon' => bin2hex(file_get_contents($this->options['lndMacaroonPath']))
            ]
        ]);
    }

    /**
     * Unlock an encrypted wallet. This needs only run once.
     * Subsequent requests will result in a 404.
     * 
     * @param  string $user
     * @return bool
     */
    public function unlockWallet($user = ''): bool
    {
        if (self::lock_file_exists($user)) {
            return true;
        }

        $walletInstance = new WalletUnlockerApi($this->client(), $this->apiConfig);
        $unlockRequest = new LnrpcUnlockWalletRequest([
            'walletPassword' => base64_encode($this->options['lndWalletPassword'])
        ]);

        try {
            $walletInstance->unlockWallet($unlockRequest);
        } catch (Exception $e) {
            self::log('Unable to run unlockwallet', $e);
           
            // Gives 408 timeout but unlock is successful.
            // Afterwards, subsequnt requests will return 404.
            if (strstr($e->getMessage(), '408') !== false) {
                self::lock_file_add($user);
                
                return true;
            }

            return false;
        }

        self::lock_file_add($this->options['lndHost'], $user);

        return true;
    }

    /**
     * Unlock an encrypted wallet. This needs only run once.
     * Subsequent requests will result in a 404.
     * 
     * @return mixed null|TBC
     */
    public function getInfo()
    {
        $apiInstance = new LightningApi($this->client(), $this->apiConfig);
        $result = null;

        try {
            $result = $apiInstance->getInfo();
        } catch (Exception $e) {
            self::log('Unable to run getinfo', $e);
        }

        return $result;
    }

    /**
     * Generate a lightning invoice.
     * Subsequent requests will result in a 404.
     * 
     * @param  string $memo
     * @return mixed null|TBC
     */
    public function addInvoice($memo = '', $value = 1001, $expiry = 3600)
    {
        $apiInstance = new LightningApi($this->client(), $this->apiConfig);
        $result = null;

        // Let's generate an lightning invoice.
        $invoice = new LnrpcInvoice([
            'memo' => $memo,
            'value' => $value,
            'expiry' => $expiry,
        ]);

        try {
            $result = $apiInstance->addInvoice($invoice);
        } catch (Exception $e) {
            self::log('Exception when calling LightningApi->addInvoice', $e);
        }

        return $result;
    }
    
    /**
     * Open a Lightning Network channel.
     * 
     * @param  string $nodePubKey
     * @param  int    $amount     Amount to "charge-up" the channel in Satoshi.
     * @param  array  $confs      An array whose first value is the min no. confs
     *                            and whose 2nd value is the target no. confs.
     * @param  bool   $isPrivate  Whether or not this is provate channel.
     * @return mixed  \Lnd\Rest\Model\LnrpcChannelPoint
     */
    public function openChannel(string $nodePubKey, int $amount, array $confs, bool $isPrivate = false)
    {
        $apiInstance = new LightningApi($this->client(), $this->apiConfig);
        $request = new LnrpcOpenChannelRequest();
        // TBC: Amount for mutually signed HTLCs (Milli satoshi)
        $request->setMinHtlcMsat(1000);
        // Allow other's to use our payment channel?
        $request->setPrivate($isPrivate);
        $request->setMinConfs($confs['min']);
        // Target no. blocks (confs) before we consider the channel confirmed & open
        $request->setTargetConf(6);
        // TBC: Amount to push into the newly opened channel
        $request->setLocalFundingAmount($amount);
        $request->setNodePubkey($nodePubKey);
        // TBC: Amount to push to the remote side of the channel
        $request->setPushSat();
        
        return $apiInstance->openChannelSync($request);
    }
    
    /**
     * Generate a new lnd wallet address.
     * 
     * @return string
     */
    public function newaddress()
    {
        $apiInstance = new LightningApi($this->client(), $this->apiConfig);
        $response = $apiInstance->newWitnessAddress();
        
        return $response->getAddress();
    }
        
    /**
     * @todo
     * @see https://api.lightning.community/rest/index.html#initwalletrequest
     */
    public function initwalletrequest()
    {
        
    }
    
    /**
     * Get the current balance of a wallet.
     * 
     * @param  string $type confirmed|unconfirmed
     * @return string
     * @throws Exception
     * @see https://api.lightning.community/rest/index.html#walletbalanceresponse
     */
    public function walletbalance(string $type) : int
    {
        $apiInstance = new LightningApi($this->client(), $this->apiConfig);
        $response = $apiInstance->walletBalance();
        $method = sprintf('get%sBalance', ucfirst(strtolower($type)));
        
        if (!method_exists($response, $method)) {
            throw new \Exception('Bad confirmation type passed.');
        }
        
        return $response->$method();
    }
    
    /**
     * List the node's peers.
     * 
     * @return array \Lnd\Rest\Model\LnrpcPeer[]
     * @throws Exception
     * @see https://api.lightning.community/rest/index.html#get-v1-peers
     */
    public function listpeers() : string
    {
        $apiInstance = new LightningApi($this->client(), $this->apiConfig);
        $response = $apiInstance->listPeers();
        
        return $response->getPeers();
    }
    
    /**
     * Alias of initwalletrequest().
     */
    public function createWallet()
    {
        return $this->initwalletrequest();
    }
    
    /**
     * Alas of newaddress().
     */
    public function getAddress() : string
    {
        return $this->newaddress();
    }
    
    /**
     * Alas of walletbalance().
     */
    public function getBalance() : string
    {
        return $this->walletbalance();
    }

    /**
     * @param string    $msg
     * @param Exception $e
     * @param string    $level
     * @return void 
     */
    private static function log($msg, $e, $level = 'INFO'): void
    {
        echo sprintf('[%s] %s (%s)', $level, $msg, $e->getMessage());
    }
    
    /**
     * Writes a lockfile to indicate that an lnd instance is unlocked
     * for $user. Its contents is hashed to avoid accidental user discovery on
     * shared filesystems.
     * 
     * @param  string $host
     * @param  string $user
     * @return void
     */
    private static function lock_file_add(string $host, string $user) : void
    {
        $fh = fopen(self::LND_LOCK, 'a+');
        fwrite($fh, hash('sha256', "{$host}{$user}"));
    }
    
    /**
     * Checks if the lock file exists for the given $user.
     * 
     * @return bool
     */
    private static function lock_file_exists(string $user, string $user) : bool
    {
        $contents = hash('sha256', "{$host}{$user}");
        
        return (
            file_exists(self::LND_LOCK) && 
            strstr(file_get_contents(self::LND_LOCK), $contents) !== false
        );
    }

}
