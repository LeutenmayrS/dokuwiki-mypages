<?php
/**
 * DokuWiki Plugin mypages (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Stefan Leutenmayr <stefan.leutenmayr@freenet.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_mypages extends DokuWiki_Plugin {

    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
            array(
                'name'   => 'mypages_show',
                'desc'   => 'returns pages that were created or contributed by the current user',
                'params' => 'none',
                'return' => 'result as html'
            )
        );
    }
	
	/**
     * Collect all created or contributed pages
     *
     * @return formatted html result
     */
	public function mypages_show() {
		
		global $ID;
		global $INFO;
		global $conf;
		
		//Remember the original ID for eventual use
		//The ID will be changed to prepare for the html_wikilink function, which otherwise sets the current ID's namespace in Front of the page name.
		$original_ID = $ID;
				
        $pages = file($conf['indexdir'] . '/page.idx');
		
		$username = $INFO['client'];
		
		$stop_words = explode(";",$this->getConf('stop_words'));
		
		
		//Start prossesing Search
		
		foreach ($pages as $id) {
			
			//There are 2 possible ways to ensure the current namespace will not be added in front of the id. Either set $ID on the current value or add a leading ":".
			$ID = $id;
			
			if ( ! page_exists($id) || isHiddenPage($id)) {
				unset($pages[$id]);
				continue;
			}
		   
			foreach ($stop_words as $idx => $name) {
				
				//Debug:
				//$html .= "<span>" . html_wikilink($id) . "</span></br>";
				//$html .= "<span>'" .noNS($id) . "' == '" . $name . "'? ". strcmp(noNS($id),$name.chr(10)) . "</span></br>";
				
				if (strcmp(noNS($id),$name.chr(10)) == 0) {
					unset($pages[$id]);
					continue 2;
				}
			}

			// getting metadata is very time-consuming, hence ONCE per row
			$meta = p_get_metadata($id, '', METADATA_DONT_RENDER);
			
			If ($username == $meta['user']){
				$results['created'][getNS($id)][$id]['link'] = html_wikilink($id);
				$results['created'][getNS($id)][$id]['last_change'] = $meta['last_change'];
			} 
			
			elseif (count($meta['contributor']) > 1)
			{
				$contributors = $meta['contributor'];
				foreach ($contributors as $contributor => $name){
					If ($username == $name){
						$results['contributed'][getNS($id)][$id]['link'] = html_wikilink($id);
						$results['contributed'][getNS($id)][$id]['last_change'] = $meta['last_change'];
					}
				}
			}
		}
		
		$ID = $original_ID;
		
		//Print created pages
		
		$html .= "<h2>" . $this->getLang('created_pages') . "</h2>" . DOKU_LF;

		$html .= $this->render($results['created']);
		
		$html .= "</br></br>";
		
		
		//Print contributed pages
		
		$html .= "<h2>" . $this->getLang('contributed_pages') . "</h2>" . DOKU_LF;

		$html .= $this->render($results['contributed']);

		return $html;
	}
	
	
	/**
     * Apply formatting on the resulting pages
     *
     * @return formatted html result
     */
	private function render($results) {
		
		if (count($results) == 0) {
			$html = "<span>" . $this->getLang('no_Results')."</span>";
		} else {
			ksort($results);
			
			$html = "<ul>";
			$html .= $this->build_tree(0, $results);
			$html .= "</ul>";
		}
		
		//Escaping output
		//$html = hsc($html);
		
		return $html;
	}
	
	
	/**
     * Order all namespaces as treeview
     *
     * @return html result tree
     */
	private function build_tree($level, $results) {
		
		$level += 1;
		
		$iMaxNumberOfArrayElements = $level + 1;
		//The function explode generates a zero-based Array.
		//We want at least 2 elements to have the current namespace followed by the rest of the namespace.
		//As the level goes deeper the number of parent-namespaces in front of the current namespace increases.
		
		$idxCurrentNamespace =  $level - 1;
		//The Array of namespaces is zero-based so the current namespace index is level - 1
		
		$idxRestOfNamespace = $level;

		
		if ($level == $this->getConf('namespace_limit')) {
			
			//This is the last level we will show (although there could be more)
			//Further namespaces will be grouped
			
			foreach ($results as $ns => $pages) {
				
				$array_NS = explode(":",$ns, $iMaxNumberOfArrayElements);
				
				if ($this->isSubNS($array_NS[$idxCurrentNamespace],$ns) == false || $this->getConf('group_last_level') == 0) {
					
					//We reached some pages. Show them!
					
					$html .= $this->getPageList($pages);
					
				} else {
					
					//There are some more namespaces left. Their pages will be grouped.
						
					$html .= $this->getNamespace($array_NS[$idxCurrentNamespace], $array_NS[$idxRestOfNamespace]);
					
					$html .= $this->setGroup($this->getPageList($pages));
					
				}
				
			}
			
		} else {
		
		
			//1st Call: The results array holds all namespaces of either the created or contributed pages
			//n-th Call: The results array holds all namespaces included in the parent namespace
			
			foreach ($results as $ns => $pages) {
				
				$array_NS = explode(":",$ns, $iMaxNumberOfArrayElements);
					
				//Debug:
				//$html .= "<li>".$idxCurrentNamespace.": ". $array_NS[$idxCurrentNamespace]. "(". $ns .")</li>";
				
				if ($this->isSubNS($array_NS[$idxCurrentNamespace],$ns) == false) {
					
					//We reached some pages. Show them!
					
					$html .= $this->getPageList($pages);
					
				} else {
					
					//Collecting all Sub-Namespaces in the header array
					
					$header[$array_NS[$idxCurrentNamespace]][$ns] = $results[$ns];

					//Example: ns1:ns2:start
					//         ns1:ns2:ns3:hello
					//         ns1:ns2:ns4:world
					
					//This is the resulting array:
					//$header[ns2][ns1:ns2    ][ns1:ns2:hello    ]
					//$header[ns2][ns1:ns2:ns3][ns1:ns2:ns3:hello]
					//$header[ns2][ns1:ns2:ns3][ns1:ns2:ns3:world]
					
				}
			}
		
			//All found sub-namespaces will now be shown including their trees.
			//Therefore this function calls itself and moves on to the next level.
		
			if (count($header) != 0) {
				
				ksort($header);

				foreach ($header as $name => $subs){
					
					$html .= $this->getNamespace($name, '');
					
					//recursive call of this function:
					
					$html .= $this->setGroup($this->build_tree($level, $header[$name]));
					
				}
			
			}
		}
		
		return $html;

	}
	
	
	
	/**
     * Apply formatting to the pages
     *
     * @return html list of pages
     */
	private function getPageList($pages) {
		
		ksort($pages);
		
		$html = "";
		
		foreach($pages as $id){
			$html .= "<li>" . $id['link'];
			
			if ($this->getConf("show_last_update") == 1) {
				$html .= " (" . date('d.m.y', $id['last_change']['date']) . " " . $this->getFullname($id['last_change']['user']) . ")";
			}
			
			$html .= "</li>";
		}
		
		return $html;
	}
	
	/**
     * Check if the current result is a Namespace
     *
     * @return true or false
     */
	private function isSubNS($currentNS, $gesNS){
		if (empty($currentNS) OR strcmp($gesNS,'0') == 0) {
			return false;
		} else {
			return true;
		}
	}
	
	/**
     * Apply formatting to the Namespace
     *
     * @return html list of pages
     */
	private function getNamespace($ns, $subNS) {
		
		$html = "<li><span>";
		
		
		if ($ns == '0') {
			
			$html .= $this->getLang('root_namespace');
		
		} else { 
		
			$html .= $ns;
			
			if ($subNS != "") {
				$html .=  ":" . $subNS;
			}
			
		}
		
		$html .= "</span></li>";
		
		return $html;
	}
	
	/**
     * Set List tags before and after the html text
     *
     * @return html enclosed in <ul>
     */
	private function setGroup ($html){
		return "<ul>" . $html . "</ul>";
	}
	
	
	/**
    * Get the fullname for a given login name.
    */
    public function getFullname($loginname){
		global $auth;
		$userdata = $auth->getUserData($loginname);
		return $userdata['name'];
	}

}

// vim:ts=4:sw=4:et:
