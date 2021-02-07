<?php

/*
* Autoloader class.
* fairly simple as its all that is needed.
* When receiving a class we check if it contains the model/controller ns
* and checks several possible whereabouts.
* All methods static.

* Requires set up to be called so folders and locations are set.
*/

namespace system;

class Loader {

	/* Locations for everything. Customised when calling setup method.
	*/
	public static $controllers_path;
	public static $models_path;
	public static $views_path;
	public static $includes_path;
	public static $classes_path;
	public static $system_path;

	/*
	* The namespaces the class loader will check for so we know what we are loading
	* and where to look. Cuts down some possible places to look.
	*/
	protected static $system_ns = 'system\\';
	protected static $controller_ns = 'controllers\\';
	protected static $model_ns = 'models\\';

	/* set up the folder paths.
	*  Customizable by passing an alternative path in an array.
	*  Loader::setup(['controllers' => '../app/controllers/'])
	*/
	public static function setup(array $paths = []) {
		$folders = [
			'controllers',
			'models',
			'views',
			'includes',
			'system'
		];

		foreach($folders as $folder) {
			$var_name = $folder . '_path';
			self::$$var_name = self::remove_slashes( $paths[$folder] ?: "../$folder" );
		}

		//classes folder a little different a resides in includes.
		self::$classes_path = self::remove_slashes($paths['classes'] ?: (self::$includes_path . '/classes'));
	}


	/* Register the autoloader for classes.
	*/
	public static function register() {
		spl_autoload_register(array('\system\Loader', 'load_class'));
	}


	/* In case we want rid of it.
	*/
	public static function unregister() {
		spl_autoload_unregister(array('\system\Loader', 'load_class'));
	}


	/* What the auto loader calls and does the loading.
	*/
	public static function load_class($class) {

		//store various possibilities of where to find the class.
		$possible_pathnames = array();

		//default. We replace the namespace with forward slashes. The intuitive file naming.
		//so default for \controllers\IndexController becomes /controllers/IndexController.php
		$default = str_replace('\\', '/', $class) . '.php';

		//check for controller namespace.
		if( strpos($class, self::$controller_ns) !== false ) {

			//We add to our possiblities the default above within the controllers folder.
			//We also remove the namespace and try it in the controllers folder.
			//These may be the same, but thats okay.

			//possibilities:
			array_push($possible_pathnames,

				//try the default name in the controllers directory.
				self::controllers($default),

				//try the classname without the namespace witin the controllers directory.
				self::controllers(str_replace(self::$controller_ns, '', $class) . '.php')
			);

		}

		//check for a model namespace
		elseif( strpos($class, self::$model_ns) !== false ) {

			//same as above but with models directory.
			array_push($possible_pathnames,
				self::models($default),
				self::models(str_replace(self::$model_ns, '', $class) . '.php')
			);

		}

		//check for system namespace which we know we use.
		//or if using this loader elsewhere, can be changed to something else.
		elseif( strpos($class, self::$system_ns) !== false ) {
			array_push($possible_pathnames,
				self::system($default),
				self::system(str_replace(self::$system_ns, '', $class) . '.php')
			);
		}

		else {
			//this isnt a controller, model or system file
			//as it doesn't include the namespaces we have defined.
			//so now we will try includes and classes both with and without the namespace included.

			array_push($possible_pathnames,

				//try the default in the classes directory.
				self::classes($default),

				//try default in the includes directory. This will find our system files.
				self::includes($default),

				//try just the filename, no ns, in the classes directory.
				self::classes(preg_replace('#.*?([^/]+)$#', '$1', $default))
			);

		}

		//add the last remaining possiblities to look.
		array_push($possible_pathnames,

			//check literally one level up.
			 '../' . $default,

			 //last ditch attempt... after this we've looked everywhere...
			 $default
		 );

		//check all our possibilities and try and load.
		foreach($possible_pathnames as $file) {
			if( file_exists($file) ) {
				require $file;
				return true;
			}
		}

		throw new \Exception('Invalid Class/Controller Called: ' . $class);
		return false;
	}


	/* Public functions for accessing the set paths.
	*  With option to join a file - therefore accessing a file in a specified directory.
	*  Loader::views('index.html')
	*/

	public static function controllers($file = null) {
		return $file ? self::join($file, self::$controllers_path) : self::$controllers_path;
	}

	public static function models($file = null) {
		return $file ? self::join($file, self::$models_path) : self::$models_path;
	}

	public static function views($file = null) {
		return $file ? self::join($file, self::$views_path) : self::$views_path;
	}

	public static function includes($file = null) {
		return $file ? self::join($file, self::$includes_path) : self::$includes_path;
	}

	public static function classes($file = null) {
		return $file ? self::join($file, self::$classes_path) : self::$classes_path;
	}

	public static function system($file = null) {
		return $file ? self::join($file, self::$system_path) : self::$system_path;
	}



	protected static function remove_slashes($file) {
		return preg_replace('#^/|/$#', '', $file);
	}

	//remove any slash from the beginning or end of file and
	//optionally add a directory, ensuring a slash between them
	protected static function join($file, $directory = null) {
		if($directory) {
			$file = self::remove_slashes($directory) . '/' . $file;
		}

		return self::remove_slashes($file);
	}

}
