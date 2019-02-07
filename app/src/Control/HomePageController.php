<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\Page;

// SilverStripe: Environment, Control and Requests
use SilverStripe\Core\Environment as Env;
use SilverStripe\Control\Director;
use PageController;
use SilverStripe\Control\HTTPRequest;

// SilverStripe: Forms, fields and validation
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FormAction;

// Endroid: QR Code generation
use Endroid\QrCode\QrCode;

// Twilio: API Client
use Twilio\Rest\Client as TwilioClient;

// App: Message data-model and validation
use SMSCryptoApp\Model\Message;
use SMSCryptoApp\Form\MessageValidator;

/**
 * Homepage controller complete with SMS Sending form.
 *
 * Exercises for the interested:
 *
 * SilverStripe:
 *
 * 1). Add phone-number validation.
 *   Hint: Search https://addons.silverstripe.org/ and modify $this->getValidator()
 * 2). Programmatically build a "Thanks" page, to avoid reliance on content authors.
 *   Hint: See {link DataObject::requireDefaultRecords()}.
 *
 * Cryptocurrency:
 *
 * 3). Obfuscate or encrypt wallet addresses in the database
 *
 * @todo Add payment confirmation config
*/
class HomePageController extends PageController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'TwilioSMSForm',
        'cbtwilio',
        'cbconfirmedpayment',
        'twiliosend',
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
        $paymentAddress = $this->paymentClient->getAddress();
        $paymentSymbol =  $this->paymentClient->getCurrency()->iso4217();

        // Form fields
        $fields = FieldList::create([
            TextField::create('PhoneTo', 'Send to Phone Number')
                ->setAttribute('placeholder', 'e.g +64 12 123 4567'),
            TextareaField::create('Body', 'Message'),
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
            LiteralField::create(
                'AddressQR',
                $this->qrCodeWrapper($paymentAddress, $paymentAmount)
            ),
        ]);

        // Form actions
        $actions = FieldList::create([
            FormAction::create('smsHandler', 'Send Message')
        ]);

        // Form validator
        $validator = $this->getValidator(['PhoneTo', 'Body']);

        // Form proper
        return Form::create($this, __FUNCTION__, $fields, $actions, $validator);
    }

    /**
     * Called on submission of the form. This will perform the following tasks:
     *
     * - Create a Message object record containing form POST data
     * - Setup an event callback that upon discovery of transactions involving
     *   the POSTed address POSTs to the given callback.
     * - The callback checks the response for a valid balance for the address
     * - If the balance is OK: Sends SMS message, updates Message status, displays confirmation.
     *
     * @param  array $data
     * @param  Form  $form
     * @return mixed null|void
     * @todo   What happens when an API call fails, yet users have already paid?
     *          Use Message record to contact customers and in accordance with
     *          jurisdictional law, reimburse.
     */
    public function smsHandler(array $data, Form $form)
    {
        // Initialise a Message record (And check that one doesn't already exist)
        $hash = $this->generateID($data);
        $message = Message::get()->filter(['MsgHash' => $hash]);

        // Prevent multiple records for the same SMS message
        if ($message && $message->exists()) {
            $form->sessionMessage('You\'ve already sent this message! Please wait while it\'s processed.', 'bad');
            return $this->redirectBack();
        }
        
        $paymentAmount = $this->paymentClient->getCurrency()::PAYMENT_AMOUNT;

        Message::create([
            'Body'      => $data['Body'],
            'PhoneTo'   => $data['PhoneTo'],
            'PhoneFrom' => Env::getEnv('TWILIO_PHONE_FROM'),
            'Address'   => $data['Address'],
            'Amount'    => $paymentAmount,
            'MsgHash'   => $hash,
            'MsgStatus' => Message::MSG_PENDING,
            'PayStatus' => Message::PAY_PENDING,
        ])->write();

        // Initialise a WebHook connection on our web API service
        // We will only receive traffic from Blockcypher to our endpoint, when
        // 2 confirmations on TX's containing $data['Address'] have occurred.
        // The no. confirmations is important. This could be made a SilverStripe
        // "Settings" area parameter.
        $filter = [
            'event' => 'tx-confirmation',
            'confirmations' => 2,
            'address' => $data['Address'],
        ];
        $url = Director::absoluteURL(sprintf('/home/cbconfirmedpayment/%s', $hash));

        try {
            $this->paymentClient->subscribeHook($filter, $url);
            $this->redirect('/thanks');
        } catch (\Exception $e) {
            $form->sessionMessage('Hmmmm. Something went wrong.', 'bad');

            return;
        }
    }

    /**
     * Physically perform the SMS sending procedure via Twilio. Once processed
     * by Twilio, we expect it to call a callback on this controller (See the "statusCallback",
     * key in the Twilio API logic below). This callback will then update the
     * Message record's status (identified by a unique ID which we pass along
     * to Twilio, and which Twilio sends us back.
     *
     * @param  Message A Message record comprising the original POSTed form data.
     * @return string  JSON data containing request message-sent data.
     */
    public function smsSender(Message $message) : string
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
                    '/home/cbtwilio/%s',
                    $this->generateID($message->toMap())
                )),
            ]
        );
    }

    /**
     * Callback endpoint #1: For incoming requests from confirmed TX
     * hook requests.
     *
     * @param  HTTPRequest $request
     * @return mixed null|HTTPResponse
     * @todo $request->getHeader('X-EventType') & $request->getHeader('X-EventId') ??
     */
    public function cbconfirmedpayment(HTTPRequest $request)
    {
        // Basic checks for solid incoming data
        if (
                !$request->isPOST() ||
                !$request->param('ID')
            ) {
            // Bad request
            return $this->httpError('400');
        }

        // Get a message with this ID, establish we have a legit request identifier
        $message = Message::get()
            // Incoming data is escaped automatically when using {@link DataList::filter()}.
            ->filter('MsgHash', $request->param('ID'))
            ->first();

        if (!$message || !$message->exists() || $message->MsgStatus === Message::MSG_SENT) {
            return $this->httpError('404');
        }

        // If we've received the balance, we send the SMS
        // We use the "cached" amount, to hedge against price volatility
        if ($this->hasBalance($message->Address, $message->Amount)) {
            // Update Message record status to PAID
            $message
                ->update(['PayStatus'=> Message::PAY_PAID])
                ->write();

            // Send our SMS, wait for a response at the app's dedicated Twilio callback
            if ($result = $this->smsSender($message)) {
                return $this->getResponse()->setBody($result);
            }
        }

        return $this->httpError('400');
    }

    /**
     * A callback used by the Twilio service once it has received an
     * SMS message payload from us. At this point we know that payment has been
     * received, and that the SMS message has been sent to, and received by Twilio.
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
    public function hasBalance(string $address, float $amount) : bool
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
        $data = $this->getRequest()->postVars();

        return MessageValidator::create($fields, function() use($data) {
            return $this->paymentClient->isAddressBroadcasted($data['Address']);
        });
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

        return $qrCode->writeDataUri();
    }

}
