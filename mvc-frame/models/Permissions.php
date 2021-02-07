<?php

namespace models;

class Permissions extends Model {

	public $actions;

	function __construct() {
		parent::__construct(\CONFIG::PERMISSION_ROLES_TABLE);
		$this->actions = new Model(\CONFIG::PERMISSION_ACTIONS_TABLE);
	}

	public function get_action_keys($actions) {

		if(is_array($actions)) {
			$actions = implode(',', $actions);
		}

		$this->actions->set_custom_where('action_id IN (' . $actions . ')');
		$keys = $this->actions->get_results();
		$results = [];

		foreach($keys as $action_key) {
			$results[ $action_key['action_key'] ] = true;
		}

		return $results;
	}

	public function get_all_roles() {
		return $this->get_all();
	}

	public function get_all_actions() {
		return $this->actions->get_all();
	}
}
