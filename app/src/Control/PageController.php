<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

// SilverStripe: Environment, Control and Requests
use SilverStripe\Core\Environment as Env;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

// SilverStripe: Forms, fields and validation
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\HiddenField;

// Endroid: QR Code generation
use Endroid\QrCode\QrCode;

// Twilio: API Client
use Twilio\Rest\Client as TwilioClient;

// App: Message data-model and validation
use SMSCryptoApp\Model\Message;
use SilverStripe\Forms\RequiredFields;

/**
 * Page controller complete with SMS Sending form.
 */
class PageController extends ContentController
{
    const TX_INIT = 0;
    const TX_NOT_BROADCAST = 1;
    const TX_UNCONFIRMED = 2;
    const TX_CONFIRMED = 3;
    const TX_ERROR = 4;
    
    /**
     * @var array
     */
    private static $allowed_actions = [
        'TwilioSMSForm',
        'cbtwilio',
        'cbconfirmedpayment',
        'twiliosend',
        'trigger',
    ];

    public function init()
    {
        parent::init();

        // Ensure the expected env vars are available to us
        foreach (['BLOCKCYPHER_TOK', 'TWILIO_PHONE_FROM', 'TWILIO_SID', 'TWILIO_TOK'] as $var) {
            if (!Env::getEnv($var)) {
                throw new \Exception(sprintf('Environment variable %s is not set!', $var));
            }
        }

        $this->paymentClient->setCurrency($this->data()->Currency ?: 'Bitcoin');
        
        // Payment UI interactions
        Requirements::css('client/css/dist/ui.css');
        Requirements::javascript('client/js/lib/jquery/jquery-3.3.1.min.js');
        Requirements::javascript('client/js/dist/ui.js');
    }

    /**
     * Enter data. Scan code. Hit "Send Message".
     *
     * Performs basic validity checks on fields. The form's validation routine
     * also checks whether an unconfirmed transaction exists for the given wallet
     * address. If a transaction is not yet broadcast, this is caught by custom
     * validation and a message displayed to the user.
     *
     * @return Form
     */
    public function TwilioSMSForm()
    {
        $paymentAmount = $this->paymentClient->getCurrency()::PAYMENT_AMOUNT;
        $paymentAddress = $this->getInvoice();
        $paymentSymbol =  $this->paymentClient->getCurrency()->iso4217();
        $minConfirmations = (int) SiteConfig::current_site_config()->getField('Confirmations') ?: 6;

        // Form fields
        $fields = FieldList::create([
            TextField::create('PhoneTo', 'Send to Phone Number')
                ->setAttribute('placeholder', 'e.g +64 12 123 4567')
                ->addExtraClass('sms-trigger'),
            TextareaField::create('Body', 'Message')
                ->addExtraClass('sms-trigger'),
            TextField::create(
                'Amount',
                sprintf('Price (%s)', $paymentSymbol),
                (string) $paymentAmount
            )->setAttribute('readonly', true),
            TextField::create(
                'Address',
                'Payment Address',
                $paymentAddress
            )->setAttribute('readonly', true),
            HiddenField::create('MinConfs', null, $minConfirmations),
            LiteralField::create(
                'AddressQR',
                $this->qrCodeWrapper($paymentAddress, $paymentAmount)
            ),
        ]);

        // Form validator. There is no manual "submission" here, but SilverStripe
        // marks these fields as required using this process.
        $validator = $this->getValidator(['PhoneTo', 'Body']);

        // Form proper
        return Form::create($this, __FUNCTION__, $fields, FieldList::create(), $validator)
                ->setAttribute('data-uri-confirmation', sprintf('%s/trigger', rtrim($this->Link(), '/')));
    }
    
    /**
     * Fetch an invoice / address for use in QR codes for payments
     * 
     * @return string
     */
    public function getInvoice() : string
    {
        if (ENV::getEnv('APP_PAYMENT_ADDRESS')) {
            $invoice = ENV::getEnv('APP_PAYMENT_ADDRESS');
        } else {
            $invoice = $this->paymentClient->getAddress();
        }
        
        return $invoice;
    }

    /**
     * Called on submission of the form (if submit button used) or from JavaScript
     * XHR calls. It performs the following tasks:
     *
     * - Sets up a Blockchain event callback that will POST to the given callback
     *   upon discovery of transactions involving the POSTed address.
     * - The callback checks the response for a valid balance for the address
     * - If the following are OK, Sends SMS message + updates Message status + displays confirmation.
     * 1). Address balance is sufficient
     * 2). Address is found in incoming request to cbconfirmedpayment()
     * 3). No. confirmations is sufficient (See: cbconfirmedpayment()).
     *
     * @param  array  $data
     * @param  string $hash
     * @param  string $callback
     * @return mixed null|BlockCypher\Api\WebHook
     */
    public function webhookListen(array $data, string $hash, string $callback)
    {
        // Initialise a WebHook connection on our web API service
        // We will only receive traffic from Blockcypher to our endpoint, when
        // 2 confirmations on TX's containing $data['Address'] have occurred.
        $filter = [
            'event' => 'tx-confirmation',
            'confirmations' => (int) SiteConfig::current_site_config()->getField('Confirmations') ?: 6,
            'address' => $data['Address'],
        ];
        $url = Director::absoluteURL(sprintf('%s/%s', rtrim($callback, '/'), $hash));
        
        return $this->paymentClient->subscribeHook($filter, $url);
    }

    /**
     * Physically performs the SMS sending procedure via Twilio.
     *
     * Once processed by Twilio, we expect it to call a callback on this controller
     * (See the "statusCallback" key in the Twilio API logic below).
     *
     * This callback will then update the Message record's status (identified by
     * a unique ID which we pass along to Twilio, and which Twilio sends us back).
     *
     * @param  Message A Message record comprising the original POSTed form data.
     * @return string  JSON data containing request message-sent data.
     */
    public function doSMSSend(Message $message) : string
    {
        $client = new TwilioClient(
            Env::getEnv('TWILIO_SID'),
            Env::getEnv('TWILIO_TOK')
        );

        return $client->messages->create(
            $message->PhoneTo,
            [
                'body' => $message->Body,
                'from' => Env::getEnv('TWILIO_PHONE_FROM'),
                'statusCallback' => Director::absoluteURL(sprintf(
                    '%s/cbtwilio/%s',
                    rtrim($this->Link(), '/'),
                    $this->generateID($message->toMap())
                )),
            ]
        );
    }

    /**
     * Callback endpoint for incoming requests from TX hook requests.
     *
     * @param  HTTPRequest $request
     * @return mixed null|HTTPResponse
     */
    public function cbconfirmedpayment(HTTPRequest $request)
    {
        // Basic checks for solid incoming data
        if (
                !$request->isPOST() ||
                !$request->param('ID') ||
                !$request->getHeader('x-eventid') ||
                !$request->getHeader('x-eventtype')
            ) {
            return $this->httpError(400, 'Bad request');
        }
        
        // Get a message with this ID, establish we have a legit request identifier
        $message = Message::get()
            // Incoming data is escaped automatically when using {@link DataList::filter()}.
            ->filter('MsgHash', $request->param('ID'))
            ->first();

        if (!$message || !$message->exists() || $message->MsgStatus === Message::MSG_SENT) {
            return $this->httpError(404);
        }

        // Update the message record's PayStatus, and send the SMS only if:
        // 1). Our wallet address has received the balance
        // 2). Our wallet address's TX outputs, comprises our addresse
        // 3). No. confirmations >= no. confs configured in SilverStripe "settings" area
        $tx = json_decode($request->getBody(), true);
        
        // Ideally, we'd load returned JSON into {@link TX} object and call getOutputs() on it
        $txHasAddr = false;
        
        if (in_array($message->Address, $tx['outputs'][0]['addresses'])) {
            $txHasAddr = true;
        }
        
        $txNumCnfs = $tx['confirmations'];
        $paymentReceived = $this->addressHasBalance($message->Address, $message->Amount);
        $minCnfs = (int) SiteConfig::current_site_config()->getField('Confirmations') ?: 6;
        $doSendSMs = ($paymentReceived && $txHasAddr && ($txNumCnfs >= $minCnfs));
        
        if ($doSendSMs) {
            // Update Message record status to PAID
            $message
                ->update(['PayStatus'=> Message::PAY_PAID])
                ->write();

            // Wait for a response at the app's dedicated Twilio callback where we'll
            // also update the message's "Sent" status
            if ($result = $this->doSMSSend($message)) {
                return $this->getResponse()->setBody($result);
            }
        }

        return $this->httpError(400);
    }

    /**
     * A callback used by the Twilio service once it has received an
     * SMS message payload from us. At this point we know that payment has been
     * received, and that the SMS message has been received by Twilio.
     *
     * We use it to simply update the status of the relevant {@link Message}.
     *
     * We expect x2 requests to this callback from the Twilio service. Each request
     * should have a different value for the incoming payload's "MessageStatus" key:
     *
     * 1). MessageStatus: "sent"
     * 2). MessageStatus: "delivered"
     *
     * @param  HTTPRequest $request
     * @return null
     */
    public function cbtwilio(HTTPRequest $request)
    {
        // Twilio will only ever send legit requests via POST, so we deny anything else;
        if (
                !$request->isPOST() ||
                !$request->postVar('MessageStatus') ||
                !$request->postVar('SmsSid') ||
                !$request->param('ID')) {
            return $this->httpError(400, 'Bad request');
        }

        // Now let's update the status of our message object
        $message = Message::get()->filter('MsgHash', $request->param('ID'))->first();

        if (!$message || !$message->exists() || $message->MsgStatus === Message::MSG_SENT) {
            return $this->httpError(404, 'Message not found.');
        }

        // Let's update our data model. In doing so; Its "verify()" method
        // will be called to hash data and submit it to the Bitcoin blockchain.
        $message->update([
            'MsgStatus'=> strtoupper($request->postVar('MessageStatus')),
            'MsgID' => strtoupper($request->postVar('SmsSid')),
        ])->write();
    }

    /**
     * Checks if the passed $address has received at least the amount passed as
     * $amount.
     *
     * @param  string $address
     * @param  float  $amount
     * @return bool
     */
    public function addressHasBalance(string $address, float $amount) : bool
    {
        $balance = $this->paymentClient->getBalance($address);

        return !empty($balance) && (float) $balance >= $amount;
    }

    /**
     * Validate each form submission. Includes:
     *
     * - Basic empty field validation
     * - That a TX containing $data['address'] as been broadcast. Checks both
     *   unconfirmed and confirmed transactions.
     *
     * @param  array     $fields An array of fields to validate.
     * @return Validator
     */
    public function getValidator(array $fields)
    {
        return RequiredFields::create($fields);
    }
    
    /**
     * Internal endpoint used by client-side logic, used to drive e.g. UI
     * components for user feedback and which:
     *
     * - Creates a {@link Message} database record.
     * - Checks if the payee's TC was broadcasted.
     * - Triggers listening to the Blockcypher API for TX's containing our address.
     * - Returns the state of TX's containing the address as an output.
     *
     * @param  HTTPRequest $request
     * @return mixed null|int Null if invalid params were passed, otherwise 1 or 0
     *                        for confirmed and sent, or not, respectively.
     */
    public function trigger(HTTPRequest $request)
    {
        if (!$request->isAjax() || !$request->isPOST()) {
            return $this->httpError(400, 'Bad request');
        }

        $client = $this->paymentClient;
        
        // Sometimes Blockcypher comes back with a 429
        try {
            $txIsBroadcasted = $client->isAddressBroadcasted($request->postVar('Address'));
        } catch (\Exception $e) {
            return $this->getResponse()->setBody(self::TX_ERROR);
        }
        
        $minConfirmations = (int) SiteConfig::current_site_config()->getField('Confirmations') ?: 6;
        $messageList = Message::get()->filter('Address', $request->postVar('Address'));
        $message = $messageList->first();
        
        // We don't need to use conditionals because each condition returns, but
        // they do improve readability.
        if ($txIsBroadcasted) {
            // Use existence of Message record to ensure we don't repeatedly ping the API
            // for webhook subscription requests
            if (!$message || !$message->exists()) {
                $hash = $this->generateID($request->postVars());
                $this->writeMessage($request->postVars(), $hash);
                $this->webhookListen(
                    $request->postVars(),
                    $hash,
                    sprintf('%s/cbconfirmedpayment', rtrim($this->Link(), '/'))
                );
                
                return $this->getResponse()->setBody(self::TX_INIT);
            }
            
            return $this->getResponse()->setBody(self::TX_UNCONFIRMED);
        } elseif ($message && $message->exists() && $message->hasPaid()) {
            // Message records are set to "PAID" when no. confs >= our requirements
            // ergo the TX's that paid for them are confirmed
            return $this->getResponse()->setBody(self::TX_CONFIRMED);
        } else {
            return $this->getResponse()->setBody(self::TX_NOT_BROADCAST);
        }
    }
    
    /**
     * Write a message record.
     *
     * @param  array  $data
     * @param  string $hash
     * @return void
     */
    public function writeMessage(array $data, string $hash) : void
    {
        Message::create([
            'Body'      => $data['Body'],
            'PhoneTo'   => $data['PhoneTo'],
            'PhoneFrom' => Env::getEnv('TWILIO_PHONE_FROM'),
            'Address'   => $data['Address'],
            'Amount'    => $this->paymentClient->getCurrency()::PAYMENT_AMOUNT,
            'MsgHash'   => $hash,
            'MsgStatus' => Message::MSG_PENDING,
            'PayStatus' => Message::PAY_PENDING,
        ])->write();
    }

    /**
     * Generate a per-message (semi-unique) identifier from the data passed as $input.
     *
     * @param  array $input
     * @return string
     */
    protected function generateID(array $input) : string
    {
        $digest = [
            $input['PhoneTo'],
            $input['Body'],
            $input['Address'],
            $input['Amount'],
        ];

        return hash('sha256', serialize($digest));
    }

    /**
     * Calls {@link $this->qrCodeGenerator()} and returns a base64 encoded PNG for display
     * in a SilverStripe {@link LiteralField}.
     *
     * @param  string $address
     * @param  string $amount
     * @return string
     */
    public function qrCodeWrapper(string $address, string $amount) : string
    {
        if ($address === $this->paymentClient::UNAVAILABLE) {
            return '';
        }

        $qrCode = $this->qrCodeGenerator($address, $amount, 'Scan the code with your Smartphone wallet app.');

        return sprintf('<img src="%s" />', $qrCode);
    }

    /**
     * Generate a QR code using the currently configured Cryptocurrency's URI scheme.
     *
     * @param  string $address
     * @param  string $amount
     * @param  string $message
     * @return string
     */
    protected function qrCodeGenerator(string $address, string $amount, string $message) : string
    {
        $qrCode = new QrCode($this->paymentClient->getCurrency()->uriScheme($address, $amount));
        $qrCode->setSize(400);
        $qrCode->setWriterByName('png');
        $qrCode->setLabel($message, 11);
        $qrCode->setMargin(1);
        
        if ($path = SiteConfig::current_site_config()->qrLogo()) {
            $qrCode->setLogoPath($path);
            $qrCode->setLogoSize(40, 40);
        }

        return $qrCode->writeDataUri();
    }
}
