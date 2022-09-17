<?php

namespace Sixgweb\MenuItemSessionCheck;

use Auth;
use Event;
use Cms\Classes\Layout;
use Cms\Classes\Page as CmsPage;
use System\Classes\PluginBase;
use RainLab\Pages\Classes\Page as StaticPage;

/**
 * MenuItemSessionCheck Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['RainLab.Pages', 'RainLab.User'];

    private $userGroups = []; //Store user groups for subsequent checks
    private $shouldHideIndexes = [];
    private $counter = 0;

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Menu Item Session Check',
            'description' => 'Hide menu item(s) by checking RainLab Session component on referenced CMS layout or page',
            'author'      => 'Ryan Showers',
            'icon'        => 'icon-sitemap'
        ];
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->bindResolveItemEvent();
        $this->bindReferencesGeneratedEvent();
    }

    /**
     * Add event listener fired in RainLab\Pages\Classes\Menu::generateReferences()
     *
     * @return void
     */
    private function bindResolveItemEvent(): void
    {
        Event::listen('pages.menuitem.resolveItem', function ($type, $item, $currentUrl, $theme) {
            $this->setUserGroups();

            //Get the referenced CMS Page
            if (!$page = $this->getPage($theme, $item)) {
                return;
            }

            //Get the referenced CMS Layout
            $layout = $this->getLayout($page, $item, $theme);

            //Get the Session component security (user)
            $security = $this->getSecurity($page, $layout);

            //Get the session component allowed groups
            $allowedUserGroups = $this->getAllowedUserGroups($page, $layout);

            if ($this->shouldHideMenuItem($security, $allowedUserGroups)) {

                //Support older Rainlab.Pages.  No longer works since 2019
                $item->viewBag['isHidden'] = '1';

                /**
                 * $item object no longer used to generate the menu
                 * so modifying $item->viewBag has no effect.
                 * 
                 * As a workaround, we'll keep an internal counter to indicate
                 * the menu item should be hidden, then check this in the 
                 * pages.menu.referencesGenerated event listener.
                 */
                $this->shouldHideIndexes[$this->counter] = true;
            }
            $this->counter++;
        });
    }

    /**
     * Add event listener to RainLab\Pages\Classes\Menu::generateReferences()
     *
     * @return void
     */
    private function bindReferencesGeneratedEvent()
    {
        Event::listen('pages.menu.referencesGenerated', function (&$items) {
            $counter = 0; //closure counter
            $iterator = function ($menuItems) use (&$iterator, &$counter) {
                $result = [];
                foreach ($menuItems as $item) {

                    /**
                     * pages.menuitem.resolveItem is only fired if $item->type != 'url'
                     * Mimic logic here.
                     */
                    if ($item->type != 'url') {
                        if ($this->shouldHideIndexes[$counter] ?? false) {
                            $item->viewBag['isHidden'] = true;
                        }
                        $counter++;
                    }

                    if ($item->items) {
                        $iterator($item->items);
                    }
                    $result[] = $item;
                }
                return $result;
            };
            $items = $iterator($items, $counter);
        });
    }


    private function shouldHideMenuItem($security, $allowedUserGroups)
    {
        if ($security == 'user' && !Auth::check()) {
            return true;
        }

        if ($security == 'guest' && Auth::check()) {
            return true;
        }

        if ($allowedUserGroups && !Auth::check()) {
            return true;
        }

        if ($allowedUserGroups && Auth::check()) {
            if (!count(array_intersect($allowedUserGroups, $this->userGroups))) {
                return true;
            }
        }

        return false;
    }

    private function setUserGroups()
    {
        if ($this->userGroups) {
            return $this->userGroups;
        }

        $this->userGroups = Auth::check() ? Auth::getUser()->groups->lists('code') : [];
    }

    private function getPage($theme, $item)
    {
        switch ($item->type) {
            case 'cms-page':
                $page = CmsPage::loadCached($theme, $item->reference);
                break;
            case 'static-page':
                $page = StaticPage::loadCached($theme, $item->reference);
                break;
            default:
                $page = false;
        }

        return $page;
    }

    private function getLayout($page, $item, $theme)
    {
        switch ($item->type) {
            case 'cms-page':
                $layout = Layout::loadCached($theme, $page->settings['layout']);
                break;
            case 'static-page':
                $layout = Layout::loadCached($theme, array_get($page->settings, 'components.viewBag.layout'));
                break;
            default:
                $layout = false;
        }

        return $layout;
    }

    private function getSecurity($page, $layout)
    {
        //Page security prioritized first
        if ($page && $security = array_get($page->settings, 'components.session.security')) {
            return $security;
        }

        //No page security.  Check layout security.
        if ($layout && $security = array_get($layout->settings, 'components.session.security')) {
            return $security;
        }

        return false;
    }

    private function getAllowedUserGroups($page, $layout)
    {
        //Page security prioritized first
        if ($page && $allowedUserGroups = array_get($page->settings, 'components.session.allowedUserGroups')) {
            return $allowedUserGroups;
        }

        //No page security.  Check layout security next.
        if ($layout && $allowedUserGroups = array_get($layout->settings, 'components.session.allowedUserGroups')) {
            return $allowedUserGroups;
        }

        return false;
    }
}
