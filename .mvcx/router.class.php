<?php

class router {
	/*
	* @the registry
	*/
	private $app;
	private $load;
	
	/*
	* @the controller path
	*/
	public $path;
	public $args = array();
	public $x = false;
	public $file;
	public $controller;
	public $controllerObject;
	public $modelObject;
	public $action;
	
	function __construct($app,$load) {
		$this->app = $app;
		$this->load = $load;
	}

	/**
	*
	* @set controller directory path
	*
	* @param string $path
	*
	* @return void
	*
	*/
	function setPath($path) {
		/*** check if path is a directory ***/
		if (is_dir($path) == false) {
			throw new Exception ('Invalid controller path: `' . $path . '`');
		}
		/*** set the path ***/
		$this->path = $path;
	}
	
	public function redirect($url) {
		header('Location: '.$url);
	}


	/**
	*
	* @load the controller
	*
	* @access public
	*
	* @return void
	*
	*/
	public function loader() {
		/*** check the route ***/
		$this->getController();
		
		
		/*** include the controller ***/
		include $this->file;
		
		/*** a new controller class instance ***/
		$class = ucfirst($this->controller).'Controller';
		$controller = new $class($this->app, $this->load);
		$this->controllerObject = $controller;

		/*** check if the action is callable ***/
		if (is_callable(array($controller, $this->action)) == false) {
			$action = 'index';
		} else {
			$action = $this->action;
		}
		/*** run the action ***/
		if (db::table_exists($this->controller)) {
			$this->app->load->model($this->controller);

		}
		
		
		if (count($this->args) == 0) {
			$controller->$action();
		}
		if (count($this->args) == 1) {
			$controller->$action($this->args[0]);
		}
		if (count($this->args) == 2) {
			$controller->$action($this->args[0],$this->args[1]);
		}
		if (count($this->args) > 2) {
			call_user_func_array(array($controller,$action),$this->args);
		}
		
		if ($controller->autoPersist == true) {
			if ($this->action == 'index') {
				$data = $this->modelObject->getAll();
				$this->controllerObject->set('data',$data);
			}
			if (!empty($this->args[0]) && $this->action == 'view') {
				$data = $this->modelObject->getAllById($this->args[0]);
				$this->controllerObject->set($data);
			}
			if (!empty($this->args[0]) && $this->action == 'edit') {
				$this->controllerObject->parentEdit($this->args[0]);
				$data = $this->modelObject->getAllById($this->args[0]);
				$this->controllerObject->set($data);
			}
			if ($this->action == 'add') {
				$this->controllerObject->parentAdd();
				$cols = $this->modelObject->getColumns();
				foreach($cols as &$c) $c = '';
				if(!empty($_POST)) {
					foreach ($cols as $k=>$v)
						if (!empty($_POST[$k])) {
							$cols[$k] = $_POST[$k];
						}
				}
				$data = $cols;
				$this->controllerObject->set($data);
			}
		}

		if ($controller->autoRender == true) {
			$this->app->load->view($this->controller.DS.$this->action);
		}
	}
	
	
	
	private function getExtensions($dir) {
		$extension_paths = array();
		if (file_exists($dir) && is_dir($dir)) {
			foreach(glob($dir.'/*', GLOB_ONLYDIR) as $dir) {
				if (file_exists($dir.'/xconfig.php')) {
					$extension_paths[] = $dir;
				} else {
					foreach(glob($dir.'/*', GLOB_ONLYDIR) as $dir) {
						if (file_exists($dir.'/xconfig.php')) {
							$extension_paths[] = $dir;
						}
					}
				}
			}
		}
		return $extension_paths;
	}


	/**
	*
	* @get the controller
	*
	* @access private
	*
	* @return void
	*
	*/
	private function getController() {
		
	
	
		/*** get the route from the url ***/
		$route = (empty($_GET['rt'])) ? '' : $_GET['rt'];
		$route = trim(str_replace($this->app->uri,'',$route),'/');
		if (empty($route)) {
			$route = 'page/index';
		}
		
		

		

		/*** get the parts of the route ***/
		$parts = explode('/', $route);
		if ($parts[0] == 'x') {
			if (isset($parts[1])) {
				$this->x = $parts[1];
				if (isset($parts[2])) {
					array_shift($parts);
				}
				array_shift($parts);
			}
		}
		$this->controller = $parts[0];
		
		if(isset( $parts[1]))
		{
			$this->action = $parts[1];
		}
		
		if(isset( $parts[2])) {
			for ($i = 2; $i < count($parts); $i++ ) {
				$this->args[($i-2)] = $parts[$i];	
			}
		}

	
		if (empty($this->controller))
		{
			$this->controller = 'page';
		}
	
		/*** Get action ***/
		if (empty($this->action))
		{
			$this->action = 'index';
		}
	
		/*** set the file path ***/
		
		//$filepath = $this->path . DS . $this->controller . '.php';
		
		// graceful loading degradation X > APP > MVC core
		
		$tryorder = array(
			'extensions' => SITE_PATH.DS.'app'.DS.$this->app->dir.DS.DIRNAME_X,
			'app' => SITE_PATH.DS.'app'.DS.$this->app->dir,
			'mvc' => SITE_PATH.DS.'.mvcx'.DS.'mvc'
		);

		$tryorder['extensions'] = $this->getExtensions($tryorder['extensions']);
		
		$filepath = '';
		foreach ($tryorder as $try) {
			if (is_array($try)) {
				if ($this->x !== false) {
					$trypath = SITE_PATH.DS.'app'.DS.$this->app->dir.DS.DIRNAME_X.DS.$this->x.DS.'controller'.DS.$this->controller.'.php';
					
					if (is_readable($trypath) == true) {
						if (stripos(file_get_contents($trypath),'extends xcontroller') !== false) {
							$filepath = $trypath;
							$this->setPath(SITE_PATH.DS.'app'.DS.$this->app->dir.DS.DIRNAME_X.DS.$this->x);
							break;
						}

					} 	
				} else {
					foreach ($try as $x) {
						$trypath = $x . DS . 'controller'. DS . $this->controller . '.php';
						if (is_readable($trypath) == true) {
							if (stripos(file_get_contents($trypath),'extends controller') !== false) {
								$filepath = $trypath;
								$this->setPath($x);
								break 2;
							}
	
						} 
					}
				}
			} else {
				$trypath = $try . DS . 'controller'. DS . $this->controller . '.php';
				
				if (is_readable($trypath) == true) {
					$filepath = $trypath;
					$this->setPath($try);
					break;
				} 
			}
		}
		
		
		if (!empty($filepath)) {
			$this->file = $filepath;
		} else {
			// Show error page
			foreach ($tryorder as $type => $try) {
				if ($type == 'extensions') {
					continue;	
				}
				$trypath = $try . DS . 'controller'. DS . 'error.php';
				
				if (is_readable($trypath) == true) {
					$filepath = $trypath;
					break;
				} 

			}
			if (!empty($filepath)) {
				$this->file = $filepath;
				$this->controller = 'error';
				$this->action = 'notfound';
			} else {
				throw new Exception ('Error with unspecified page.');
			}
		}
	}

}
