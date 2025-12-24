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
        
        // Keep existing Disk Analyser
        $menu->insertAfter(_('Notification'),
            (new CMenuItem(_('Disk Analyser')))->setAction('disk.analyser')
        );
        
        // Add new Storage Analytics menu item
        $menu->insertAfter(_('Disk Analyser'),
            (new CMenuItem(_('Storage Analytics')))->setAction('storage.analytics')
        );
    }
}
