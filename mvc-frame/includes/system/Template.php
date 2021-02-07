<?php

namespace system;


//Define an interface for templates as all these methods are required by the parser.
interface TemplateInterface {
	public function set($name, $value);
	public function set_loop($name, $values);
	public function set_content($content);
	public function get_content();
	public function get_vars();
	public function set_compiled($content);
	public function get_compiled();
	public function is_compiled();
	public function set_parsed($content);
	public function get_parsed();
	public function is_parsed();
}


class Template implements TemplateInterface {

	/* Template directory.
	*  Should be set directly here normally
	*  but edited to be set dynamically in the construct.
	*/
	protected static $template_directory;

	/* Directory where cached parsed templates are stored.
	* This is created automatically within the template directory
	* and does not need to be defined here.
	*/
	protected static $cache_directory;

	/* Expiry time of templates in seconds.
	*/
	private $max_cache_age = \CONFIG::TEMPLATE_CACHE_TIME;

	/* original filename provided
	*/
	private $filename = '';

	/* FUll filename with directory included.
	*/
	private $filename_full = '';

	/* Filename edited to be safe for caching.
	*/
	private $filename_cache = '';

	/* Flag if the template has been parsed.
	*/
	private $parsed = false;

	/* original unparsed content of template.
	*/
	private $unparsed_content = '';

	/* Parsed content found, after parsing or from cache.
	*/
	private $parsed_content = '';

	/* flag if template has been compiled yet.
	*/
	private $compiled = false;

	/* Content of template after compilation of vars.
	*/
	private $compiled_content = '';

	/* stores all vars, including namespaced and loops.
	*/
	private $vars = array();

	/* Option if vars should be overwritten if using same name
	*/
	public $overwrite_vars = true;

	/* will store templates we've loaded this execution, to prevent double loading.
	*/
	static $template_store = array();

	/* allow for changing the directory during execution.
	*  This can be used for things like ajax or situations we want a
	*  a separate views folder.
	*/
	static public function set_directory($dir) {
		self::$template_directory = preg_replace('#/$#', '', $dir);
		self::$cache_directory = self::$template_directory . '/cached';
	}


	/* construct will load template file if provided.
	*  If not, will need to load manually using load method before parsing.
	*/
	function __construct($templateFile = false) {

		//check if template directory has been set.
		if( !isset(self::$template_directory) ) {
			self::set_directory('../views');
		}

		//check if the cache directory needs creating.
		//but only if caching is enabled.
		if( $this->max_cache_age > 0 && !file_exists(self::$cache_directory) ) {
			//create cache directory.
			@mkdir(self::$cache_directory, 0755);
		}

		//finally if a template has been specified load it.
		if($templateFile) {
			$this->load($templateFile);
		}
	}

	/* Load a template file.
	*  Will load the parsed version if it exists, to be able to skip parse step.
	*/
	public function load(string $templateFile) {

		//loading a new template so must reset these flags.
		$this->parsed = false;
		$this->compiled = false;

		$this->filename = $templateFile;
		$this->filename_cache = 'parsed_' . str_replace('/', '_', str_replace('\\', '_', $templateFile));
		$this->filename_full = self::$template_directory . '/' . $templateFile;

		//first check if has been loaded already.
		if( isset(self::$template_store[$templateFile]) ) {
			return $this->set_content(self::$template_store[$templateFile]);
		}

		//then check for a parsed version.
		$parsed_content = $this->cache();

		if( $parsed_content )	{
			return $this->set_parsed($parsed_content);
		}

		if( file_exists($this->filename_full) ) {
			return $this->set_content( file_get_contents($this->filename_full) );
		}

		throw new \Exception("Could not find Template file [$templateFile]");
	}


	/* Assign vars. If both name and value are strings, one var is set.
	 * If name is an array, then its array full of vars, key = name.
	 * If name is a string and value an array then namespace vars are set.
	 */
	public function set($name, $value = '') {
		if(is_string($name)) {
			$this->_assign_var($name, $value);
		}
		elseif(is_array($name)) {
			foreach($name as $n => $v) {
				$this->_assign_var($n, $v);
			}
		}
	}

	/* Adds a new record to a template loop iteration.
	* Loops can be nested and should use lowercase lettering.
	*/
	public function set_loop($name, $values) {
		if( !is_array($values) || !is_string($name) ) {
			return;
		}

		$level = &$this->vars;

		$nests = explode('.', $name); // parentloop innerloop
		$last = $nests[ count($nests) - 1 ]; // innerloop

		//move through the arrays until at the correct nest level.
		foreach($nests as $n) {
			if($n === $last) {
				break;
			}

			if( !is_array($level[$n]) ) {
				return;
			}

			//move to the last iteration added.
			$level = &$level[$n][ count($level[$n]) - 1 ];
		}

		//if the array has been started append to it, if not start it.
		if( is_array($level[$last]) ) {
			$level[$last][] = $values;
		}
		else {
			$level[$last] = array($values);
		}
	}

	/* Add a value to the vars. Can optionally be namespaced using ':'
	 * If the value has already been set, then it will be overwritten
	 * if overwrite_vars is on.
	*/
	private function _assign_var($name, $value) {
		if( !isset($this->vars[$name]) || $this->overwrite_vars ) {
			if( is_int( strpos($name, ':') ) ) {
				$this->_assign_nested_vars($name, $value);
			}
			else {
				$this->vars[ $name ] = $value;
			}
		}
	}

	//SOMENEST:NEXTNEST:NEW_VAR, value
	private function _assign_nested_vars($path, $value)  {
		$current = &$this->vars;
		$split = explode(':', $path);  //SOMENEST NEXTNEST NEW_VAR
		$new_varname = $split[ count($split) - 1 ];

		foreach($split as $n) {
			if($n === $new_varname) {
				$current[$n] = $value;
				break;
			}

			if( !isset($current[$n]) ) {
				$current[$n] = array();
			}

			if( is_array($current[$n]) ) {
				$current = &$current[$n];
			}
			elseif( $this->overwrite_vars ) {
				$current[$n] = array();
				$current = &$current[$n];
			}
			else {
				return;
			}
		}
	}


	/*
	* If the template has been parsed then we cache the parsed template.
	* If it hasn't then we attempt to look for a parsed version
	* return null if not found or is too old.
	*/
	public function cache() {

		//if max age is 0 or less then caching is disabled so
		//no need to waste our time any further.
		if( $this->max_cache_age <= 0 ) {
			return null;
		}

		$cached_file = self::$cache_directory . '/' . $this->filename_cache;

		//if its been parsed then we cache the contents.
		if( $this->is_parsed() ) {
			//supress errors in case file permissions do not allow.
			@file_put_contents($cached_file, $this->get_parsed());
		}
		else {
			//The template has not been parsed so check if a cached version exists and provide it.
			if( file_exists($cached_file) ) {
				//time when cached
				$cached_time = filemtime($cached_file);

				//last modification of the original template.
				$last_modified = filemtime($this->filename_full);

				if($last_modified > $cached_time) {
					//time now - cached_time = the age. If younger than max cache age then good to use.
					if( (time() - $cached_time) < $this->max_cache_age ) {
						return file_get_contents($cached_file);
					}

					//the cached version is older than our max age
					//delete this outdated cache file.
					unlink($cached_file);

					//return null to cause re-parsing
					return null;
				}
				else {
					//Been cached more recently than last modification so return it
					return file_get_contents($cached_file);
				}
			}
		}

		return null;
	}

	//basic get/set/is methods.

	public function get_vars() {
		return $this->vars;
	}

	public function set_content($content = '') {
		$this->unparsed_content = $content;
		self::$template_store[$this->filename] = $content;
	}

	public function get_content() {
		return $this->unparsed_content;
	}

	public function set_parsed($content = '') {
		$this->parsed = true;
		$this->parsed_content = $content;
	}

	public function get_parsed() {
		return $this->parsed_content;
	}

	public function is_parsed() {
		return $this->parsed;
	}

	public function set_compiled($content = '') {
		$this->compiled = true;
		$this->compiled_content = $content;
	}

	public function get_compiled() {
		return $this->compiled_content;
	}

	public function is_compiled() {
		return $this->compiled;
	}

	public function filename($type = null) {
		switch($type) {
			case 'full' :
			case 'f' :
				return $this->filename_full;
			case 'cache' :
			case 'c' :
				return $this->filename_cache;
			default :
				return $this->filename;
		}
	}

}
