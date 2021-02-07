<?php

namespace models;

class Users extends Model {

	public function __construct() {
		parent::__construct(\CONFIG::USERS_TABLE);
	}

	public function get_by_id($user_id) {
		$result = $this->set_where('user_id', $user_id)->get_first();
		return $result ?: null;
	}

	public function get_by_email($email) {
		$result = $this->set_where('user_email', $email)->get_first();
		return $result ?: null;
	}

	public function add_permissions() {
		$this->set_join_table(\CONFIG::PERMISSION_ROLES_TABLE, ['role_actions', 'role_name']);
		$this->set_join_condition(\CONFIG::PERMISSION_ROLES_TABLE, 'user_permission_role', 'role_id');
	}

	//check if a user already exists with one of matching fields.
	public function exists($field, $value) {
		$existing_user = $this->clear()->set_where($field, $value)->get_first();
		return ($existing_user && is_array($existing_user)) ? true : false;
	}

	//validation should already be complete.
	public function create_new($email, $password) {

		//this is all we need to insert as others have defaults set in the DB.
		$id = $this->insert([
			'user_email'			=> $email,
			'user_password'			=> password_hash($password, PASSWORD_DEFAULT)
		]);

		return $id;
	}
}
