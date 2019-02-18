<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package twilio-sms-app
 */

namespace SMSCryptoApp\Model;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\LiteralField;

/**
 * Simple {@link DataExtension} to illustrate how to extend the CMS interface.
 */
class SettingsExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        'Amount' => 'Varchar',
        'Confirmations' => 'Varchar',
    ];

    /**
     * @return void
     */
    public function updateCMSFields(FieldList $fields) : void
    {
        parent::updateCMSFields($fields);
        
        // Add a field for controlling no. confirmations we require to send a message
        $fields->addFieldsToTab('Root.Payment', [
            LiteralField::create('Intro', '<p class="message notice">Use this '
                . 'section to control how things are paid for.</p>'),
            TextField::create('Amount', 'Amount')
                ->setAttribute('style', 'width:100px')
                ->setAttribute('maxlength', '11')            
                ->setDescription('The amount in Satoshi to charge per SMS'),
            TextField::create('Confirmations')
                ->setAttribute('style', 'width:100px')
                ->setAttribute('maxlength', '1')
                ->setDescription('The number of Bitcoin transaction confirmations required, before an SMS should be sent.')
        ]);
    }
    
}
