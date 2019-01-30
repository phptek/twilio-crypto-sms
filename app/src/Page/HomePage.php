<?php

namespace SMSCryptoApp\Page;

use Page;
use SMSCryptoApp\Crypto\Currency;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;

class HomePage extends Page
{
    private static $db = [
        'Currency' => 'Varchar',
    ];

    private static $has_one = [];

    private static $table_name = 'HomePage';

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        // A map of coin classes
        $coins = array_map(function($v) {
                    return ClassInfo::shortName($v);
                }, ClassInfo::implementorsOf(Currency::class)
            );

        $fields = parent::getCMSFields();
        $fields->insertBefore(
            'Content',
            DropdownField::create('Currency', 'Currency', array_combine($coins, $coins))
        );

        return $fields;
    }
    
    public function getControllerName()
    {
        parent::getControllerName();
        
        return HomePageController::class;
    }

}
