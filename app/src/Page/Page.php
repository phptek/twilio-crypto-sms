<?php

use SilverStripe\CMS\Model\SiteTree;

class Page extends SiteTree
{
    private static $db = [];

    private static $table_name = 'Page';

    public function getControllerName()
    {
        parent::getControllerName();

        return PageController::class;
    }
}
