<?php

namespace Bundles\Action;
use Exception;
use stack;
use e;

class Bundle {
	
	private static $actions = array();

	public function _on_after_framework_loaded() {

		e::configure('action')->activeAdd('class_formats', '\\Action\\%');

		$portal_dirs = array();
		foreach(array_reverse(e::configure('portal')->activeGet('locations')) as $portal) {
			foreach(glob($portal.'/portals/*') as $portal)
				$portal_dirs[] = $portal;
		}

		foreach ($portal_dirs as $portal)
			e::configure('action')->activeAdd('locations', $portal);
	}
	
	public function _on_portal_route($path, $dir) {
		$this->route($path, array($dir));
	}
	
	public function _on_router_route($path) {
		$this->route($path, e::configure('action')->locations);
	}
	
	public function __callBundle($action, $init = false) {
		if($action) {
			$action = str_replace('.','\\', $action);
			$r = $this->load(\Bundles\Portal\Bundle::$currentPortalName, $action, $init);
			return $r;
		}
		
		return $this;
	}
	
	public function load($portal = false, $action, $init = false) {
		if(!empty($portal)) {

			/**
			 * Handle Portal Locations
			 */
			$dirs = array();
			foreach(e::configure('portal')->activeGet('locations') as $portalLoc)
				$dirs[] = $portalLoc.'/portals/'.$portal;
		}
		
		$name = str_replace('\\', '/', strtolower($action));
		$name = explode('/', $name);

		$this->test = true;

		return $this->route($name, isset($dirs) ? $dirs : null, true, $init);
	}
	
	public function route($path, $dirs = null, $load = false, $init = false) {
		// If dirs are not specified, use defaults
		if(is_null($dirs))
			$dirs = e::configure('action')->locations;
			
		// Make sure path contains valid action name
		if((empty($path) || !is_array($path) || $path[0] !== 'do') && !$load)
			return;

		/**
		 * Take off the /do
		 */
		if(!$load) array_shift($path);
		
		// Get the action name
		$name = strtolower(implode('/',$path));
		
		// Check all dirs for a matching action
		foreach($dirs as $dir) {

			// Look in action folder
			if(basename($dir) !== 'actions')
				$dir .= '/actions';
			
			// Skip if missing
			if(!is_dir($dir))
				continue;
				
			// File to check
			$file = "$dir/$name.php";
			
			// Skip if missing file
			if(!is_file($file))
				continue;
			
			// Load action if not already loaded
			if(!isset(self::$actions[$file])) {
				
				// Require the controller
				require_once($file);
				
				// action class
				$classFormats = e::configure('action')->class_formats;
				
				// Check each class format
				$found = false;
				foreach($classFormats as $format) {
					
					// Format class with action name
					$class = str_replace(array('%', '/'), array($name, '\\'), $format);
					
					// Check if this is a valid class
					if(class_exists($class, false)) {
						$found = true;
						break;
					}
				}
				
				// Maybe we just ran out of formats to check
				if(!$found) {
					$classes = implode('`, `', $classFormats);
					$classes = str_replace(array('%', '/'), array($name, '\\'), $classes);
					throw new Exception("None of the possible action classes: `$classes` are defined in `$file`");
				}
				
				// Load action
				if(!$load) self::$actions[$file] = new $class;
				else self::$actions[$file] = new $class(array(), true, $init);
			}

			/**
			 * If called by load return the object
			 */
			if($load) return self::$actions[$file];
            
            // Complete the current binding queue
            e\Complete();
		}
	}
}