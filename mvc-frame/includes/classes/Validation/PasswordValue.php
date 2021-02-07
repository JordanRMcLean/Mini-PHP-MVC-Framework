<?php

/* Extension of ValidationValue class but specifically for passwords.
*  With rules already set.
* Further rules can be added dynamically such as doesnt contain old password.
*/

namespace Validation;


class PasswordValue extends ValidationValue {
	protected $rules = [
		'contains_int'				=> [],
		'contains_upper'			=> [],
		'contains_alpha'			=> [],
		'contains_special_char'		=> [],
		'in_range'					=> [5, 40]
	];

	public function not_past_password($past_password) {
		return $this->value !== $past_password;
	}

	//for passwords entered twice, use this to check they match.
	public function must_match_password($verify_password) {
		return $this->value === trim($verify_password);
	}
}
