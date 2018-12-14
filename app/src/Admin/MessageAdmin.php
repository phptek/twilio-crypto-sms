<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace SMSCryptoApp\Admin;

use SilverStripe\Admin\ModelAdmin;
use SMSCryptoApp\Model\Message;

/**
 * Description of MessageAdmin
 *
 * @author russellmichell
 */
class MessageAdmin extends ModelAdmin
{
    /**
     * @var array
     * @config
     */
    private static $managed_models = [
        Message::class,
    ];

    /**
     * @var string
     * @config
     */
    private static $url_segment = 'messages';

    /**
     * @var string
     * @config
     */
    private static $menu_title = 'Messages';
}
