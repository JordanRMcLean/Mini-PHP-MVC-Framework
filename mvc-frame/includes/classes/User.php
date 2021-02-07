<?php

// Class for managing one user, globally the current user.

use \models\Permissions as Permissions;
use \models\Users as Users;

class User {

	private $logged_in = false;
	private $id = 0;
	private $email;
	private $data = [];

	//stores the actions allowed by the account based on role.
	private $permissions = [];

	function __construct(int $id = 0) {
		if($id > 0) {
			$this->load('user_id', $id);
		}
	}

	public function load($field, $value) {
		$users = new Users();

		$users->set_where($field, $value)->add_permissions();
		$user = $users->get_first();

		if($user && is_array($user)) {
			$this->data = $user;
			$this->id = intval($user['user_id']);
			$this->email = $user['user_email'];
			$this->logged_in = true;

			//if some permissions are specified load them.
			if(!empty($user['role_actions'])) {
				$permissions = new Permissions();
				$this->permissions = $permissions->get_action_keys($user['role_actions']);
			}
		}
		else {
			$this->logged_in = false;
		}
	}

	public function load_by_email($value) {
		return $this->load('user_email', $value);
	}

	public function verify_password($password) {
		if($this->logged_in) {
			return password_verify($password, $this->data['user_password']);
		}
		return false;
	}

	public function get_id() {
		return $this->id;
	}

	public function get($data) {
		switch($data) {
			case 'id' :
				return $this->id;

			case 'email' :
				return $this->email;

			default :
				return array_key_exists($data, $this->data) ? $this->data[$data] : null;
		}
	}

	public function has_permission($permission_key) {
		if($this->logged_in) {
			if(array_key_exists($permission_key, $this->permissions)) {
				return $this->permissions[ $permission_key ] ?: false;
			}
		}

		return false;
	}


	public function is_logged_in($include_verified = false) {
		return ($this->logged_in && $this->id > 0);
	}

	//set all account data to the template.
	public function set_to_template(&$template, $prefix = '') {
		$template->set([
			$prefix . 'LOGGED_IN'			=> $this->is_logged_in(),
			$prefix . 'USER_ID'				=> $this->id,
			$prefix . 'USER_EMAIL'			=> $this->email,
			$prefix . 'USER_CREATED'		=> format_relative_time($this->get('user_created')),
			$prefix . 'USER_LAST_UPDATED'	=> format_relative_time($this->get('user_last_updated')),
			$prefix . 'USER_IS_ADMIN'		=> $this->has_permission('admin_access'),
			$prefix . 'USER_ROLE'			=> $this->get('role_name')
		]);
	}

}
