<?php

namespace models;

class Sessions extends Model {

	public function __construct() {
		parent::__construct(\CONFIG::SESSIONS_TABLE);
	}

	//return the user id of the current logged in session. or 0 if not. 
	public function current_user_id() {
		if(session_status() === PHP_SESSION_ACTIVE) {

			$this->set_where('session_id', session_id());
			$this->set_custom_where('session_login_time >= (NOW() - INTERVAL ' . \CONFIG::MAX_SESSION_LENGTH . ')');
			$session = $this->get_first();

			//session exists.
			if($session && is_array($session)) {
				return intval($session['session_user_id']);
			}
		}

		return 0;
	}

	public function update_current($user_id) {
		if(session_status() === PHP_SESSION_ACTIVE) {
			$this->replace([
				'session_id'			=> session_id(),
				'session_user_id'		=> $user_id
			]);
		}
	}

	public function remove_current() {
		if (session_status() == PHP_SESSION_ACTIVE) {
			$this->clear()->set_where('session_id', session_id())->delete();
		}
	}
}
