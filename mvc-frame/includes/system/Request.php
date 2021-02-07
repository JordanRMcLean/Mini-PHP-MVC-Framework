<?php
// Request Controller, parses the URL or current request URI and runs the correct controller.
//requires Folder to have been loaded.

/*  STATIC FUNCTIONS
*  get_var(varname, default) - gets the var returning the default if doesnt exist. Returns only if matches type of default.
*  confirmed() - true if there was a confirmation in the request, using the controller confirmation method.
*  submitted() - true if there was a form submitted in the request, using a 'submit' named input.
*/

namespace system;

//required constant for checking if we're on the index.
if(!defined('INDEX_ROOT')) {
	define('INDEX_ROOT', '/');
}


class Request {

	public $controller = null;
	public $module = null;
	public $id = null;
	public $index = false;

	private $controller_class = '';
	private $controller_file;
	private $valid_request = false;
	private $validated = false;

	private $reroutes;

	function __construct($url = null) {
		$path = parse_url( $url ?: $_SERVER['REQUEST_URI'], PHP_URL_PATH );

		//if it matches the index, then we don't need to split the URL.
		if( $this->is_index($path) ) {
			$this->set_controller('index');
		}
		else {
			$this->split($path);
		}
	}

	public function set_controller($controller, $module = null, $id = null) {
		$this->controller = $controller;
		$this->module = $module;
		$this->id = $id;

		if(!$controller || empty($controller)) {
			$this->validated = false;
			return;
		}

		//this is the alteration we make to the pathname to create the controller name.
		// index => IndexController
		$alteration = ucfirst($this->controller) . 'Controller';

		$this->controller_class = '\controllers\\' . $alteration;
		$this->controller_file = Loader::controllers($alteration . '.php');

		//must re-validate after manual setting.
		$this->validated = false;
		$this->validate();
	}

	//run the current controller
	public function run_controller() {

		if( $this->controller && $this->valid() ) {
			$controller = $this->controller_class;
			$active_controller = new $controller($this->id, $this->module);

			$active_controller->set_module($this->module);
			$active_controller->set_id($this->id);

			//we check if the method exists here rather than in the validate function
			//as this will ensure the module is passed to the constructor even if its invalid.
			//this allows us to do things like controller/this-is-not-a-module
			//and 'this-is-not-a-module' will be passed in the constructor, but we won't be trying to run it.
			if($this->module && method_exists($active_controller, $this->module)) {
				$active_controller->{$this->module}($this->id);
			}

			//the controller should have output the page. So if we get to here
			//then the render method wasn't called of the controller.
			$active_controller->render();
		}

	}

	//public method to check if this is a valid request.
	//Can then do what we want aka display 404 or return to index.
	public function valid() {
		if( !$this->validated ) {
			$this->validate();
		}

		return $this->valid_request;
	}

	//compare the defined index root with the current request
	//to see if we should display the index.
	//this should allow the app to run in a sub-directory if needed.
	//for instance if the app is runnning in directory /app/
	//we need to recognise that /app is the index not 'app' controller.
	private function is_index($path) {

		//we need to remove any trailing slash on the root
		//except if its empty in which case it needs the slash.
		$index_root = preg_replace('#/$#', '', INDEX_ROOT);

		//then do the same with the current path.
		$current = preg_replace('#/$#', '', $path);

		//now they've both been normalised we can compare them.
		$this->index = ($current === $index_root);

		return $this->index;
	}

	//the function that splits the pathname up and defines the controller/module etc.
	private function split($path) {
		// path = /controller | /controller/module | controller/(int)ID | controller/module/(int)ID

		//remove the index from the path if index is a subdirectory.
		//allowing us to run the app in sub-directories.
		//for example if running in /app/ directory, we want /app/controller
		//to recognise 'controller' as the controller not 'app'
		if(!empty(INDEX_ROOT)) {
			$path = preg_replace('#^' . INDEX_ROOT . '#', '', $path);
		}

		$subs = explode('/', $path);
		$parts = count($subs);

		if($parts > 0) {
			$this->controller = $subs[0];
		}

		if($parts > 1) {
			if( preg_match('#^[0-9]+$#', $subs[1]) ) {
				$this->id = intval($subs[1]);
			}
			else {
				$this->module = $subs[1];
			}
		}

		if($parts > 2) {
			$this->id = preg_match('#^[0-9]+$#', $subs[2]) ? intval($subs[2]) : $subs[2];
		}

		if($parts > 3) {
			//if there are more than 3 parts then, we turn the id into an array.
			$this->id = array_slice($subs, 2);
		}

		//now that weve split the request into controller/module/id
		//check if if matches any re-routing.
		$this->re_route();


		$this->set_controller($this->controller, $this->module, $this->id);
	}

	private function re_route() {
		$routes = $this->get_reroutes();

		if( empty($this->controller) || !$routes ) {
			return;
		}

		//check for controller/module OR controller in the re-routes array
		//then use the new defined route as controller and module

		$module_route = $this->controller . '/' . $this->module;
		$new_route = false;

		//we prioritise a 'controller/module' re-route over just a 'controller'
		//since its a far more specific re-route and this will mean a controller
		//re-route won't override this.
		if( !empty($routes[$module_route]) ) {
			$new_route = $routes[$module_route];
		}
		elseif( !empty($routes[$this->controller]) ) {
			$new_route = $routes[$this->controller];
		}

		//a new route has been found.
		if($new_route) {
			$this->split($new_route);
		}
	}

	private function validate() {
		//check the controller class exists
		//check the module has been defined.
		//but we do not check if the module exists until we run it.
		//this allows the module to be passed into the constructor and things can be done with it.


		//this will call the autoloader so we supress the error if it doesn't exist.
		if($this->controller_file && file_exists($this->controller_file)) {
			if( @class_exists($this->controller_class) ) {
				//the class exists

				//weve got a module to check as well.
				//valid if the module exists within the class
				//and has been defined in the modules array
				if($this->module) {

					if(property_exists($this->controller_class, 'modules') && is_array($this->controller_class::$modules)) {
						$this->valid_request = in_array($this->module, $this->controller_class::$modules);
					}
					else {
						$this->valid_request = false;
					}

				}
				else {
					//no module to check and everything else exists
					$this->valid_request = true;
				}
			}
			else {
				$this->valid_request = false;
			}
		}
		else {
			$this->valid_request = false;
		}


		$this->validated = true;
		return $this->valid_request;

	}

	private function get_reroutes() {
		if(!$this->reroutes) {
			$this->reroutes = include Loader::system('reroutes.php');
		}

		return $this->reroutes;
	}


	/* STATIC FUNCTION */



	/**
	*  Get a request variable and type match it for safety.
	*  @param string var_name
	*  @param mixed default
	*  @param array haystack
	*  @return mixed var
	*/

	public static function get_input(string $input_name, $default = null, $haystack = null) {
		$var = null;

		$haystacks = is_array($haystack) ? [$haystack] : [$_POST, $_GET, $_REQUEST];

		foreach($haystacks as $search) {
			if( isset($search[$input_name]) ) {
				$var = $search[$input_name];
				break;
			}
		}

		if($default === null) {
			return $var;
		}

		if(isset($var)) {
			$type = gettype($default);

			if($type === 'boolean') {
				$var = filter_var($var, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			}

			elseif($type === 'integer') {
				$var = filter_var($var, FILTER_VALIDATE_INT);
			}

			elseif($type === 'double') {
				$var = filter_var($var, FILTER_VALIDATE_FLOAT);
			}

			//we only want to keep the var if it has matched our default type.
			if(gettype($var) === $type) {
				return $var;
			}
		}

		return $default;
	}

	//shortcut for commonly used query parameter that specifies if an action has been confirmed.
	public static function confirmed() {
		return self::get_input('confirm', 0) === 1;
	}

	//shortcut for query param used to tell if a form has been submitted.
	public static function submitted() {
		return !empty( self::get_input('submit', '') );
	}

}
