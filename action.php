<?php
/**
 * DokuWiki Plugin mypages (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Stefan Leutenmayr <stefan.leutenmayr@freenet.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_mypages extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'allow_mypages_show');
	   $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'handle_mypages_show');
   
    }
	
	
	 /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
	 
	public function allow_mypages_show(Doku_Event $event, $param) {
		if($event->data != 'mypages_show') return; 
		$event->preventDefault();
		$event->stopPropagation();
		return true;
	}
 

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_mypages_show(Doku_Event &$event, $param) {
		if($event->data != 'mypages_show') return; 
		$event->preventDefault();
		
		$helper = $this->loadHelper('mypages', true);
		
		print $helper->mypages_show();
		
	}
	


}

// vim:ts=4:sw=4:et:
