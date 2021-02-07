<?php
/* Basic class for holding a string or int value for validation.
* Not using ctype methods to validate as not always supported on shared hosting services.
*/

namespace Validation;

//basic interface if wanting to create custom validation objects.
interface ValidationObject {
	public function valid();
	public function get_errors();
}


class ValidationValue implements ValidationObject {
	protected $value;
	protected $errors = [];
	protected $rules = [];

	//construct with its value and optionally any rules.
	/*
	*  The rules are method names.
	*  If the rule requires a param, use an array with the first item being the method name.
	*  Eg: $rules = ['is_int', ['max_length', 50], ['min_length', 20]]
	*/
	function __construct($value, array $rules = []) {
		$this->value = (is_string($value) || is_int($value)) ? $value : '';
		$this->set_rules($rules);
	}

	public function get_value() {
		return $this->value;
	}

	public function get_errors() {
		return $this->errors;
	}

	public function set_rules(array $rules) {
		foreach($rules as $rule) {
			$params = [];

			if(is_array($rule)) {
				$params = array_slice($rule, 1);
				$rule = $rule[0];
			}

			if(is_string($rule)) {
				$this->rules[$rule] = $params;
				continue;
			}


			throw new \Exception("Invalid rule provided [$rule]");
		}
	}

	// Validate the value against all the rules stored and set.
	public function valid(array $rules = []) {
		$this->set_rules($rules);

		$failed_rules = [];

		foreach($this->rules as $rule => $params) {
			if(method_exists($this, $rule)) {

				$result = $this->{$rule}(...$params);

				//hasn't passed the rule.
				if(!$result) {
					$failed_rules[$rule] = $params;
				}
			}
			else {
				//invalid rule
				throw new \Exception("Invalid rule provided [$rule]");
			}
		}

		$this->errors = $failed_rules;
		return empty($failed_rules) ? true : false;
	}

	/*
	*	Validation functions.
	*   Included in this class rather than validator so can be used on
	*   single values which is often more necessary.
	*/

	public function not_empty() {
		return !empty( trim($this->value) );
	}

	public function required() {
		return $this->not_empty();
	}

	public function is_int() {
		return preg_match('/^[0-9]+$/', $this->value) ? true : false;
	}

	public function contains_int() {
		return preg_match('/[0-9]/', $this->value) ? true : false;
	}

	public function is_alpha() {
		return preg_match('/^[a-zA-Z]+$/', $this->value) ? true : false;
	}

	public function contains_alpha() {
		return preg_match('/[a-zA-Z]/', $this->value) ? true : false;
	}

	public function is_alphanum() {
		return preg_match('/^[a-zA-Z0-9]+$/', $this->value) ? true : false;
	}

	public function is_alphanum_extra() {
		return preg_match('/^[a-zA-Z0-9_\.-]+$/', $this->value) ? true : false;
	}

	public function is_text() {
		return preg_match('/^[a-zA-Z0-9\s_\.-,;\?!]+$/', $this->value) ? true : false;
	}

	public function is_upper() {
		return preg_match('/^[A-Z]+$/', $this->value) ? true : false;
	}

	public function contains_upper() {
		return preg_match('/[A-Z]/', $this->value) ? true : false;
	}

	public function contains(string $char) {
		return is_int(strpos($this->value, $char));
	}

	public function not_contains(string $char) {
		return !contains_char($char);
	}

	public function contains_special_char() {
		return preg_match('/[^a-zA-Z0-9\s]/', $this->value) ? true : false;
	}

	public function min_length(int $min) {
		if(is_string($this->value)) {
			return strlen($this->value) >= $min;
		}

		if(is_int($this->value)) {
			return $this->value >= $min;
		}

		return false;
	}

	public function max_length(int $max) {
		if(is_string($this->value)) {
			return strlen($this->value) <= $max;
		}

		if(is_int($this->value)) {
			return $this->value <= $max;
		}

		return false;
	}

	public function in_range(int $min, int $max) {
		return $this->min_length($min) && $this->max_length($max);
	}

	public function filter(int $filter) {
		return filter_var($this->value, $filter) !== null;
	}

	public function is_email() {
		return $this->filter(FILTER_VALIDATE_EMAIL) ? true : false;
	}

	public function is_url() {
		return $this->filter(FILTER_VALIDATE_URL) ? true : false;
	}

	public function matches_regexp(string $reg) {
		return preg_match($reg, $this->value) ? true : false;
	}

	public function is_time() {
		if(!preg_match('/^\d{2}:\d{2}$/', $this->value)) {
			return false;
		}

		list($h, $m) = explode(':', $this->value);

		$h = intval($h);
		$m = intval($m);

		if($h > 23 || $h < 0) {
			return false;
		}

		if($m > 59 || $m < 0) {
			return false;
		}

		return true;
	}

	public function is_sql_date() {
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->value) && $this->is_date();
	}

	public function is_date(string $format = 'yyyy-mm-dd') {
		$date = \DateTime::createFromFormat($format, $this->value);

		if($date && $date->format($format) === $this->value) {
			return true;
		}

		return false;
	}
}
