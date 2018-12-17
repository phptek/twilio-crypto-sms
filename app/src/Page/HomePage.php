<?php

namespace SMSCryptoApp\Page;

use Page;
use SMSCryptoApp\Crypto\CryptoCurrency;
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
        $coins = array_map(function($v) {
                    $parts = explode('\\', $v);
                    return end($parts);
                }, ClassInfo::implementorsOf(CryptoCurrency::class)
            );

        $fields = parent::getCMSFields();
        $fields->insertBefore(
            'Content',
            DropdownField::create('Currency', 'Currency', array_combine($coins, $coins))
        );

        return $fields;
    }

}
