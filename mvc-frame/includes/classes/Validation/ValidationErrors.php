<?php

namespace Validation;

class ValidationErrors {

	//REMINDER: these messages are if these rules are NOT met.
	//Formatting: https://www.php.net/manual/en/function.sprintf
	public static $errors = array(
		'not_empty'					=> '%s can not be empty',
		'is_int'					=> '%s must be a number',
		'contains_int'				=> '%s must contain a number',
		'is_alpha'					=> '%s must only contain alpha characters',
		'contains_alpha'			=> '%s must contain alpha characters',
		'is_alphanum'				=> '%s must only contain alpha-numeric characters',
		'is_alphanum_extra'			=> '%s must only contain alpha-numeric characters, underscore, period or dash',
		'is_text'					=> '%s must be English-language text characters',
		'is_upper'					=> '%s must be uppercase',
		'contains_upper'			=> '%s must contain an uppercase character',
		'contains'					=> '%1$s must contain %2$s',
		'not_contains'				=> '%1$s must not contain %2$s',
		'contains_special_char'		=> '%s must contain a special character',
		'min_length'				=> '%s must be a minimum of %d characters',
		'max_length'				=> '%s must be a maximum of %d characters',
		'in_range'					=> '%s must be between %d and %d characters',
		'filter'					=> '%s did not meet the criteria', // ??? depends what filter to use
		'is_email'					=> '%s must be a valid email address',
		'is_url'					=> '%s must be a valid URL',
		'matches_regexp'			=> '%s does not match the criteria',
		'is_time'					=> '%s must be a valid time in format HH:MM',
		'is_date'					=> '%s must be a valid date in format %s',
		'is_sql_date'				=> '%s must be a valid date in format yyyy-mm-dd',
		'required'					=> '%s is required',
		'must_match_password'		=> 'both passwords must match'
	);

	public static function format($error, ...$args) {
		$format = self::$errors[$error];

		if(is_callable($format)) {
			$output = call_user_func($format, ...$args);
		}
		else {
			$output = sprintf($format, ...$args);
		}

		return $output;
	}

	public static function format_multi($name, $errors) {
		$return_errors = [];

		foreach($errors as $error => $params) {
			$return_errors[] = self::format($error, $name, ...$params);
		}

		return $return_errors;
	}

}
