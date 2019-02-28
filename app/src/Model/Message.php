<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\Model;

use SilverStripe\ORM\DataObject;

/**
 * A simple SilverStripe data-model for storing and representing a single message
 * and all necessary operations able to be performed on one.
 */
class Message extends DataObject
{
    // Payment status
    const PAY_UNPAID = 'UNPAID';
    const PAY_PENDING = 'PENDING';
    const PAY_PAID = 'PAID';

    // Message status
    const MSG_UNSENT = 'UNSENT';
    const MSG_PENDING = 'PENDING';
    const MSG_SENT = 'SENT';

    /**
     * @var string
     * @config
     */
    private static $table_name = 'Message';

    /**
     * @var string
     * @config
     */
    private static $summary_fields = [
        'Address',
        'MsgStatus' => 'Sent Status',
        'PayStatus' => 'Payment Status',
    ];

    /**
     * @var array
     */
    private static $db = [
        // Message sent status
        'MsgStatus' => "Enum('" . self::MSG_UNSENT . "," . self::MSG_PENDING . "," . self::MSG_SENT . "','" . self::MSG_UNSENT . "')",
        // Payment status
        'PayStatus' => "Enum('" . self::PAY_UNPAID . "," . self::PAY_PENDING . "," . self::PAY_PAID . "','" . self::PAY_UNPAID . "')",
        // The mesage-text itself
        'Body' => 'Text',
        // The Phone Number to which this message was sent
        'PhoneTo' => 'Varchar',
        // The Phone Number from which this message was sent
        'PhoneFrom' => 'Varchar',
        // Store the one-off crypto address used for the msg+payment transaction
        'Address' => 'Text',
        // Store the uniquely generated identifier for the msg+payment transaction
        // that this record represents
        'MsgHash' => 'Varchar',
        // Store the returned message ID from Twilio
        'MsgID' => 'Varchar',
        // The amount that was paid for this message
        'Amount' => 'Varchar',
    ];

    /**
     * @return bool
     */
    public function canEdit($member = null) : bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canDelete($member = null) : bool
    {
        return false;
    }
    
    /**
     * Has this message been paid for?
     *
     * @return bool
     */
    public function hasPaid() : bool
    {
        return $this->PayStatus == self::PAY_PAID;
    }
}
