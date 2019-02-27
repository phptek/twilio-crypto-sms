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
use SilverStripe\Assets\Image;
use SilverStripe\AssetAdmin\Forms\UploadField;

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
     * @var array
     */
    private static $has_one = [
        'QRCodeLogo' => Image::class,
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
                ->setDescription('The number of Bitcoin transaction confirmations required, before an SMS should be sent.'),
            UploadField::create('QRCodeLogo', 'QR Code Logo')
                ->setDescription('The logo to use in QR payment codes'),
        ]);
    }
    
    /**
     * Return the full-path to a logo for use in payment QR code.
     * 
     * @return string
     */
    public function qrLogo() : string
    {
        if ($this->owner->QRCodeLogo()->exists()) {
            return sprintf(
                '%s/Uploads/%s/%s',
                ASSETS_PATH,
                str_split($this->owner->QRCodeLogo()->getHash(), 10)[0],
                $this->owner->QRCodeLogo()->Name
            );
        }
        
        return '';
    }
    
}
