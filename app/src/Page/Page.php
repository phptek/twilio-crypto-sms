<?php

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DB;

class Page extends SiteTree
{
    private static $db = [];

    private static $table_name = 'Page';

    public function getControllerName()
    {
        parent::getControllerName();

        return PageController::class;
    }
        
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        // Pre-build a one-off 'Thanks' page
        $thanksPageTitle = 'Thanks';
        $pages = Page::get()->filter(['Title' => $thanksPageTitle]);

        if (!$pages->first()) {
            $page = Page::create([
                'Title' => $thanksPageTitle,
                'Content' => '<p>Thanks! Your message is winging its way home</p>',
                'ShowInMenus' => 0,
            ]);
            $page->write();
            $page->publishRecursive();
            DB::alteration_message("Published $thanksPageTitle page.");
        }
    }
}
