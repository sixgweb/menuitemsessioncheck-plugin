<?php namespace Sixgweb\MenuItemSessionCheck;

use Auth;
use Event;
use Backend;
use Cms\Classes\Layout;
use Cms\Classes\Page as CmsPage;
use System\Classes\PluginBase;
use RainLab\Pages\Classes\Router;
use RainLab\Pages\Classes\Page as StaticPage;

/**
 * MenuItemSessionCheck Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['RainLab.Pages','RainLab.User'];
    
    private $userGroups; //Store user groups for subsequent checks
        
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
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->setUserGroups();
        
        Event::listen('pages.menuitem.resolveItem', function($type, $item, $currentUrl, $theme){
            $page = $this->getPage($theme, $item);
            $layout = $this->getLayout($page, $item, $theme);
            $security = $this->getSecurity($page, $layout);
            $allowedUserGroups = $this->getAllowedUserGroups($page, $layout);
            if ($this->shouldHideMenuItem($security, $allowedUserGroups)) {
                $item->viewBag['isHidden'] = '1';
            }
        });

    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate
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
        if (Auth::check()) {
            $this->userGroups = Auth::getUser()->groups->lists('code');
        }
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
        if ($security = array_get($page->settings, 'components.session.security')) {
            return $security;
        }
	    
	    //No page security.  Check layout security.
        if ($security = array_get($layout->settings, 'components.session.security')) {
            return $security;
        }
	   
        return false;
    }

    private function getAllowedUserGroups($page, $layout)
    {
        //Page security prioritized first
        if ($allowedUserGroups = array_get($page->settings, 'components.session.allowedUserGroups')) {
            return $allowedUserGroups;
        }
	    
	    //No page security.  Check layout security next.
        if ($allowedUserGroups = array_get($layout->settings, 'components.session.allowedUserGroups')) {
            return $allowedUserGroups;
        }
	   
        return false;
    }    
}
