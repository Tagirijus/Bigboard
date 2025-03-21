<?php

namespace Kanboard\Plugin\Bigboard;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Translator;

class Plugin extends Base
{
    public function initialize()
    {
        $this->template->hook->attach('template:project-list:menu:before', 'bigboard:Bigboard');
        $this->template->hook->attach('template:header:dropdown', 'bigboard:header/user_dropdown');
        $this->template->hook->attach('template:dashboard:page-header:menu', 'bigboard:header/bigboard_header_link');
        $this->template->hook->attach('template:project-header:view-switcher', 'bigboard:header/bigboard_header_link');

        if (
            $this->request->getStringParam("plugin") == "Bigboard"
            || strpos($this->request->getUri(), '/bigboard') !== false
        ) {
            $this->template->setTemplateOverride('board/table_container', 'bigboard:board/table_container');
            $this->template->setTemplateOverride('board/table_tasks', 'bigboard:board/table_tasks');
            $this->template->setTemplateOverride('board/table_private', 'bigboard:board/table_private');

            $this->hook->on('template:layout:js', ['template' => 'plugins/Bigboard/Asset/js/BoardDragAndDrop.js']);
            $this->hook->on('template:layout:js', ['template' => 'plugins/Bigboard/Asset/js/BoardPolling.js']);
            $this->hook->on('template:layout:js', ['template' => 'plugins/Bigboard/Asset/js/bigboard-selected.js']);
            $this->hook->on('template:layout:js', ['template' => 'plugins/Bigboard/Asset/js/bigboard-collapsed.js']);
            $this->hook->on('template:layout:css', ['template' => 'plugins/Bigboard/Asset/css/bigboard.css']);
        }
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function getClasses()
    {
        return [
            'Plugin\Bigboard' => [
                'UserSession',
            ],
            'Plugin\Bigboard\Controller' => [
                'Bigboard',
                'BoardAjaxController',
            ],
            'Plugin\Bigboard\Model' => [
                'BigboardModel',
            ],
        ];
    }

    public function getPluginName()
    {
        return 'Bigboard';
    }

    public function getPluginDescription()
    {
        return t('BigBoard: a Kanboard that displays multiple projects');
    }

    public function getPluginAuthor()
    {
        return 'BlueTeck, Thomas Stinner, Pierre Cadeot, PapeCoding, Chaosmeister, Jake G, Tagirijus';
    }

    public function getPluginVersion()
    {
        return '1.6.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/Tagirijus/Bigboard';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.12';
    }
}
