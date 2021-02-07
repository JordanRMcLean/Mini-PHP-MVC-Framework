<?php

namespace system;
use \system\Template as Template;

//For use with Template.php class
//PHP 7+ required.


class TemplateParser
{
	/* this is an option when using the template parser independently
	*  for it to handle errors for you.
	*  However it is explictily set to false here, as we have an error handler in place.
	*/
	private $handle_errors = false;

	/* All regex used in the template parsing.
	*  Can be edited to suit needs.
	*/
	private $regex = array(
		'var_match'			=> '#\\{(?:C:|(?:[0-9a-z_]+\\.)+)?[0-9A-Z_]{2,}(:[0-9A-Z_]+)*\\}#',
		'condition_match'	=> '#\\{(IF|ELSEIF):\\s(.+?)\\}#',
		'loop_match'		=> '#\\{LOOP: ([0-9a-z_\\.]{2,})\\}(?:.|[\\n\\r])*?\\{/LOOP: \\1\\}#',
		'include_match'		=> '#\\{INCLUDE: (.*?)\\}#',
		'ignore_match'		=> '#\\{IGNORE\\}((?:.|[\\r\\n])*?)\\{/IGNORE\\}#',


		'operator'			=> '#^([!=<>]=?|&&|(?:\\|\\|)|neq|eq|and|not|or|gt|lt|gte|lte)$#',
		'condition_break'	=> '#^[\\(\\)\s!]$#',
		'var'				=> '#^(C:)?[0-9A-Z_]{2,}(:[0-9A-Z_]+)*$#',
		'constant'			=> '#^C:[A-Z0-9_:]+$#',
		'loop_var'			=> '#^((?:[0-9a-z_]+\\.)+)[0-9A-Z_]{2,}(?::[0-9A-Z_]+)*$#',

		'endif'				=> '#\\{/IF\\}#',
		'else'				=> '#\\{ELSE:\\s?\\}#',
		'ignore_tags'		=> '#\\{/?IGNORE\\}#',
	);

	/* Static shortuct for creation of new template.
	*  Template Includes use this function to create a new template and get its contents.
	*/
	public static function new($file = null) {
		return new Template($file);
	}

	/*  Set handle_errors option through construct.
	*/
	function __construct($handle_errors = null) {
		if($handle_errors !== null) {
			$this->handle_errors = $handle_errors;
		}
	}


	public function compile(&$template) {
		if( !is_object($template) || !($template instanceof Template) ) {
			return $this->error('Compiler must receive Template object to compile.');
		}

		//removed to allow 're-compiling'
		/*if($template->is_compiled()) {
			return $template->get_compiled();
		}*/

		//if it hasn't been parsed yet we must do that first.
		//which is the long bit, which gets cached.
		if(!$template->is_parsed()) {
			$this->parse($template);
		}

		$parsed_content = $template->get_parsed();
		$vars = $template->get_vars();

		//Start output buffering
		ob_start();

		try {
			//yes. eval is evil... but given that we have set the eval'd content
			//the only vulnerabilities lie in the author of the template.
			//presumably the owner of the site.

			//we're going to temporarily remove E_NOTICE for parsing the template.
			//so people can use vars in the template if they don't exist.

			$current_error_level = error_reporting();
			error_reporting($current_error_level & ~E_NOTICE);

			eval('?>' . $parsed_content);

			//return the error reporting.
			error_reporting($current_error_level);
		}
		catch(\Throwable $e) {

			//parsing has failed which means the template wasn't written correctly.
			$lines = explode("\n", $parsed_content);
			$line = $e->getLine();
			$error_line = $lines[$line];

			//move backwards until we have a line with content.
			while( empty($error_line) || preg_match('#^[\s\t\r\n]+$#', $error_line) ) {
				$error_line = $lines[ --$line ];

				if($line === 0) {
					break;
				}
			}

			$message = 'Error compiling template: ' . $e->getMessage() . "\n at line " . $e->getLine() . ': ' . htmlspecialchars($error_line);
			$this->error($message);
		}

		$compiled_content = ob_get_clean();

		$template->set_compiled($compiled_content);

		return $compiled_content;
	}


	/* Parses the template, turning it into executable PHP.
	*  Returns the instance of this object so can chain the compile function ->parse()->compile()
	*/
	public function parse(&$template) {
		if( !is_object($template) || !($template instanceof Template) )	{
			return $this->error('Compiler must receive Template object to compile.');
		}

		if($template->is_parsed()) {
			return $this;
		}

		$content = $template->get_content();

		//Steps to parsing:
		//1. remove ignore blocks.
		//2. include any include templates
		//3. replace all output variables
		//4. sort conditions out
		//5. replace all loop blocks
		//6. return ignore blocks


		//1. remove ignore blocks and store them until the end.
		$ignore_blocks_storage = array();
		$ignore_blocks = $this->matches('ignore_match', $content);
		$replacement_key = 'IGNORE_BLOCK_' . time() . '_';
		$i = 0;

		foreach($ignore_blocks as $blok) {
			//what we will be replacing it with. the @ stops it being matched as output.
			$replacement_var = '{@' . $replacement_key . ($i++) .'@}';
			$replacement_content = preg_replace($this->regex['ignore_match'], '$1', $blok);

			//store the ignore content
			$ignore_blocks_storage[ $replacement_var ] = $replacement_content;

			//Replace the ignore content with unique replacement.
			$content = str_replace($blok, $replacement_var, $content);
		}


		//2. include any include templates
		//we use a while loop so that any includes within includes are also included.
		while( $this->match('include_match', $content) ) {
			$include_vars = $this->matches('include_match', $content);

			foreach($include_vars as $i_var) {
				$replacement = $this->parse_include_block($i_var);
				$content = str_replace($i_var, $replacement, $content);
			}
		}


		//3. Replace all template vars
		$output_vars = $this->matches('var_match', $content);

		foreach($output_vars as $t_var) {
			$replacement = $this->parse_output_block($t_var);
			$content = str_replace($t_var, $replacement, $content);
		}


		//4. Sort conditions out
		$condition_blocks = $this->matches('condition_match', $content);

		foreach($condition_blocks as $c_var) {
			$replacement = $this->parse_condition_block($c_var);
			$content = str_replace($c_var, $replacement, $content);
		}

		$content = preg_replace($this->regex['else'], '<?php else: ?>', $content);
		$content = preg_replace($this->regex['endif'], '<?php endif; ?>', $content);


		//5. Replace all loop blocks
		$loop_blocks = $this->matches('loop_match', $content);

		foreach($loop_blocks as $l_var) {
			$replacement = $this->parse_loop_block($l_var);
			$content = str_replace($l_var, $replacement, $content);
		}

		//6. return ignore blocks
		foreach($ignore_blocks_storage as $unique_key => $ignored_content) {
			$content = str_replace($unique_key, $ignored_content, $content);
		}

		//set the templates parsed content. and cache it.
		$template->set_parsed($content);

		//check if there is a cache method as isnt included in interface.
		if( method_exists($template, 'cache') ) {
			$template->cache();
		}

		//return this object in order to optionally chain compile method straight after.
		return $this;
	}

	//---------------------------------------------
	//Private functions for parsing from here on.
	//---------------------------------------------

	/* Parse a template variable to return its correct PHP equivalent. Not its value.
	*/
	private function parse_var($block, $top_level = '$vars') {

		if( $this->match('constant', $block) ) {
			return substr($block, 2);
		}

		//check if its a loop var.
		if( $this->match('loop_var', $block) ) {

			//if so then we need to extract the loop name
			$loop_name = preg_replace($this->regex['loop_var'], '$1', $block);

			//remove the loop name from the block.
			$block = str_replace($loop_name, '', $block);

			//change the top level to this loop.
			//regex removes the trailing period at the end of the loop name.
			$top_level = $this->convert_loop_name( preg_replace('#\\.$#', '', $loop_name) );
		}

		if( $this->match('var', $block) ) {
			//check if its a namespaced var
			if( is_int( strpos($block, ':') ) ) {

				$varnames = explode(':', $block); // split = NAMESPACE1 NESTED NESTED2 VAR

				foreach($varnames as $nest) {
					$top_level .= "['$nest']";
				}

				return $top_level;
			}
			else {
				return $top_level . '[\'' . $block .'\']';
			}
		}

		return '';
	}


	private function parse_include_block($block, $template_directory = '') {
		//use the template class to load the include.
		//this way it uses the same load function that will check
		//for a cached version.

		$filename = preg_replace($this->regex['include_match'], '$1', $block);
		$template = self::new($filename);
		$content = null;

		if($template->is_parsed()) {
			$content = $template->get_parsed();
		}
		else {
			$content = $template->get_content();
		}

		unset($template);

		return $content ?: '';
	}



	private function parse_output_block($block, $top_level = '$vars') {

		//remove any braces.
		$block = preg_replace('#^\{|\}$#', '', $block);
		$var_alias = $this->parse_var($block, $top_level);

		//if its a constant we must use defined before output.
		if( strpos($block, 'C:') === 0 ) {
			return "<?php if(defined('$var_alias')): echo $var_alias; endif; ?>";
		}
		else {
			return "<?php echo isset($var_alias) ? $var_alias : '' ?>";
		}
	}



	private function parse_condition_block($condition) {

		//split the condition block into the type of condition and the condition statements.
		list($condition_type, $condition_parts)
			= explode(' ^#*#^ ', preg_replace($this->regex['condition_match'], '$1 ^#*#^ $2', $condition) );

		$condition_parts = str_split($condition_parts);
		$new_condition_parts = [];
		$parsed_condition_parts = [];
		$current_part = '';
		$in_string = false;

		//go through each single character.
		foreach($condition_parts as $part) {

			//if we're in a string we either end it or continue.
			if($in_string !== false) {
				$current_part .= $part;

				if($part === $in_string) {
					$in_string = false;
					$new_condition_parts[] = $current_part;
					$current_part = '';
				}

				continue;
			}

			//these break up a condition, so stop the current part and add it.
			if( in_array($part, ['(', ')', ' ', '!']) ) {
				if(!empty($current_part)) {
					$new_condition_parts[] = $current_part;
				}
				$new_condition_parts[] = $part;
				$current_part = '';
				continue;
			}

			//beginning of a string.
			if( $this->match('#[\'"`]#', $part) ) {
				if(!empty($current_part)) {
					$new_condition_parts[] = $current_part;
				}
				$in_string = $part;
				$current_part = $part;
				continue;
			}

			$current_part .= $part;
		}

		//add the final part that may have been missed without a last iteration.
		if(!empty($current_part)) {
			$new_condition_parts[] = $current_part;
		}

		//now its been sorted into pieces, we can begin parsing each piece.
		foreach($new_condition_parts as $part) {
			if( $this->match('var', $part) || $this->match('loop_var', $part) ) {
				$parsed_condition_parts[] = $this->parse_var($part);
			}
			elseif( $this->match('operator', $part) ) {
				$parsed_condition_parts[] = $this->parse_operator($part);
			}
			else {
				$parsed_condition_parts[] = $part;
			}
		}

		return '<?php ' . strtolower($condition_type) . ' (' . implode($parsed_condition_parts) . '): ?>';
	}


	private function parse_loop_block($loop, $php_loop_alias = '$vars') {

		//first parse opening tag and get loop name.
		$loop_name = preg_replace($this->regex['loop_match'], '$1', $loop);

		//a loop name for the within the foreach context
		$loop_name_alias = $this->convert_loop_name($loop_name);

		//is this loop nested?
		if( strpos($loop_name, '.') ) {
			$nests = explode('.', $loop_name);
			$php_loop_alias .= '[\'' . $nests[ count($nests) - 1 ] . '\']';
			//$php_loop_alias .= "['" . str_replace('.', "']['", $loop_name) . "']";
		}
		else {
			$php_loop_alias .= "['$loop_name']";
		}

		$loop_start_replacement = "
		<?php if(isset({$php_loop_alias}) && is_array({$php_loop_alias})):
			{$loop_name_alias}_count = count({$php_loop_alias});
			foreach({$php_loop_alias} as {$loop_name_alias}_index => {$loop_name_alias}):
				{$loop_name_alias}['IS_FIRST_ROW'] = ({$loop_name_alias}_index === 0) ? true : false;
				{$loop_name_alias}['IS_ODD_ROW'] = ({$loop_name_alias}_index % 2 === 0) ? true : false;
				{$loop_name_alias}['IS_EVEN_ROW'] = {$loop_name_alias}['IS_ODD_ROW'] ? false : true;
				{$loop_name_alias}['IS_LAST_ROW'] = ({$loop_name_alias}_index === {$loop_name_alias}_count - 1) ? true : false;
		?>";

		//replace the start and end loop tags.
		//We know it has an end tag since it was matched by our regex which includes end tag
		$loop = str_replace("{LOOP: $loop_name}", $loop_start_replacement, $loop);
		$loop = str_replace("{/LOOP: $loop_name}", '<?php endforeach; unset(' . $loop_name_alias . '_count); endif; ?>', $loop);

		//check for any loops nested inside this loop. and send them back through.
		$loop_blocks = $this->matches('loop_match', $loop);

		if($loop_blocks) {
			foreach($loop_blocks as $l) {
				$loop = str_replace($l, $this->parse_loop_block($l, $loop_name_alias), $loop);
			}
		}

		return $loop;
	}

	private function parse_operator($operator_alias) {
		switch($operator_alias) {
			case 'not': return '!';
			case 'and': return '&&';
			case 'or' : return '||';
			case 'eq' : return '==';
			case 'neq': return '!=';
			case 'gt' : return '>';
			case 'lt' : return '<';
			case 'lte': return '<=';
			case 'gte': return '>=';
			default: return $operator_alias;
		}
	}

	//wrapper for preg_match which only returns boolean.
	private function match($preg, $str) {
		if($preg[0] !== '#') {
			$preg = $this->regex[$preg];
		}

		return preg_match($preg, $str) === 1 ? true : false;
	}

	private function matches($preg, $str) {
		$result = [];

		if($preg[0] !== '#') {
			$preg = $this->regex[$preg];
		}

		preg_match_all($preg, $str, $result);

		return $result && $result[0] ? $result[0] : [];
	}

	private function convert_loop_name($raw_name) {
		$raw_name = str_replace('.', '_', $raw_name);

		return '$' . $raw_name . '_loop';
	}

	private function error($message) {
		if($this->handle_errors) {
			//templater handles the errors...
			//need to write some nice function here to output the error.
			die($message);
		}
		else {
			//templater doesn't handle errors so throw an exception and let another handler sort it.
			throw new \ErrorException($message);
		}
	}
};
