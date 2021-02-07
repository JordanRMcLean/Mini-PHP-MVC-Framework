<?php

/*  Class to validate multiple ValidationValue or InputValue objects.
*   Errors are formatted using ValidationErrors class.
*/

namespace Validation;

class FormValidator {

	protected $errors = [];
	protected $fields = [];

	/* construct with an assoc array of name & validationValue object pairs.
	*  The name is used to refer to the field when setting rules or errors.
	*/
	function __construct(array $fields) {
		foreach($fields as $name_key => $valueObj) {
			$this->add_field($name_key, $valueObj);
		}
	}

	public function add_field(string $name_key, $value = null) {
		if(is_string($value)) {
			$value = new InputValue($value);
		}

		if(! $value instanceof ValidationValue) {
			$value = new ValidationValue($value);
		}

		$this->fields[ $name_key ] = $value;
	}

	public function set_field_rules(string $name_key, array $rules) {
		if(array_key_exists($name_key, $this->fields)) {
			$this->fields[$name_key]->set_rules($rules);
		}
	}

	//set rules for multiple values with assoc array.
	public function set_rules(array $rules) {
		foreach($rules as $name => $obj_rules) {
			$this->set_field_rules($name, $obj_rules);
		}
	}

	//validate all the value objects. Ooptionally can set rules here too.
	public function validate(array $rules = []) {
		$this->set_rules($rules);

		$validations = $this->fields;

		foreach($validations as $name => $valueObj) {
			if( !$valueObj->valid() ) {
				$this->errors[ $name ] = $valueObj->get_errors();
			}
		}

		return empty($this->errors) ? true : false;
	}

	//simply an aesthetic alias.
	public function valid(array $rules = []) {
		return $this->validate($rules);
	}

	//get all errors of all values formatted by the ValidationErrors formats.
	public function get_errors() {
		$formatted_errors = [];

		foreach($this->errors as $name => $errors) {

			//format all the errors for this value.
			$value_errors = ValidationErrors::format_multi($name, $errors);

			$formatted_errors = array_merge($formatted_errors, $value_errors);
		}

		return $formatted_errors;
	}


}
