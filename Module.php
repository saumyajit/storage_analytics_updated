<?php
namespace Modules\diskanalyser;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

class Module extends CModule {
    public function init(): void {
        $menu = APP::Component()->get('menu.main')
            ->findOrAdd(_('Reports'))
                ->getSubmenu();
        
        // Add new Storage Analytics menu item
        $menu->insertAfter(_('Notification'),
            (new CMenuItem(_('Storage Analytics')))->setAction('storage.analytics')
        );        
    }
}
