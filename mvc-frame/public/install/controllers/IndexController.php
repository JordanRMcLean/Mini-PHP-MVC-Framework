<?php

namespace controllers;
use \CONFIG;
use \system\Request;

class IndexController extends Controller {

	public static $modules = ['auth', 'perm'];

	public function  __construct($id, $module) {

		$db = null;

		try{
			//try and connect to database.
			$db = new \models\Model();

			if($db) {
				$this->set('DB_CONNECT', true);
			}

		}
		catch(\Exception $e) {
			$this->set('DB_CONNECT', false);
			$this->set('DB_CONNECT_ERROR', $e->getMessage());
		}

		if($db) {
			$this->set([
				'SESSIONS_TABLE_INSTALLED'	=> $db->set_table(CONFIG::SESSIONS_TABLE)->table_exists(),
				'USERS_TABLE_INSTALLED'		=> $db->set_table(CONFIG::USERS_TABLE)->table_exists(),
				'ROLES_TABLE_INSTALLED'		=> $db->set_table(CONFIG::PERMISSION_ROLES_TABLE)->table_exists(),
				'ACTIONS_TABLE_INSTALLED'	=> $db->set_table(CONFIG::PERMISSION_ACTIONS_TABLE)->table_exists(),
				'AUTH_INSTALL_URL'			=> INDEX_ROOT . 'index/auth',
				'PERM_INSTALL_URL'			=> INDEX_ROOT . 'index/perm'
			]);
		}

		if($module === 'auth') {

		}

		$this->set_template('admin/install.html');
		$this->page_title = 'Install';
	}



	public function auth() {
		$admin_email = Request::get_input('admin-email', '');
		$admin_pass = Request::get_input('admin-pass', '');

		//the model class needs a table defined before executing a query.
		$db = new \models\Model();

		$users_table_installed = $db->set_table(CONFIG::USERS_TABLE)->table_exists();
		$sessions_table_installed = $db->set_table(CONFIG::SESSIONS_TABLE)->table_exists();
		$error = false;

		if(!empty($admin_email) && !empty($admin_pass)) {
			include './install_sql.php';

			if(!$sessions_table_installed) {
				foreach(\InstallSQL::$sessions_table_install as $query) {
					$db->raw_query($query);
				}
			}
			else {
				$error = 'Sessions table table already installed.';
			}

			if(!$users_table_installed) {
				foreach(\InstallSQL::$users_table_install as $query) {
					$db->raw_query($query);
				}
				$db->set_table(CONFIG::USERS_TABLE)->insert([
					'user_email'			=> $admin_email,
					'user_password' 		=> password_hash($admin_pass, PASSWORD_DEFAULT),
					'user_permission_role'	=> 1  //admin permission role.
				]);
			}
			else {
				$error = 'Users table already installed.';
			}

			$this->set([
				'USERS_TABLE_INSTALLED'		=> $db->set_table(CONFIG::USERS_TABLE)->table_exists(),
				'SESSIONS_TABLE_INSTALLED'	=> $db->set_table(CONFIG::SESSIONS_TABLE)->table_exists()
			]);
		}
		else {
			$error = 'Please fill in both fields.';
		}

		$this->set('AUTH_ERROR_MESSAGE', $error);
	}




	public function perm() {
		include './install_sql.php';

		//the model class needs a table defined before executing a query.
		$db = new \models\Model();

		$roles_table_installed = $db->set_table(CONFIG::PERMISSION_ROLES_TABLE)->table_exists();
		$actions_table_installed = $db->set_table(CONFIG::PERMISSION_ACTIONS_TABLE)->table_exists();
		$error = false;

		if(!$roles_table_installed) {
			foreach(\InstallSQL::$roles_table_install as $query) {
				$db->raw_query($query);
			}

			$db->set_table(CONFIG::PERMISSION_ROLES_TABLE)->insert([
				'role_name'		=> 'Administrator',
				'role_desc'		=> 'Administrator of site',
				'role_actions'	=> '1'
			]);

			$db->insert([
				'role_name'		=> 'User',
				'role_desc'		=> 'Basic user of site'
			]);
		}
		else {
			$error = 'Permissions roles table already installed.';
		}

		if(!$actions_table_installed) {
			foreach(\InstallSQL::$actions_table_install as $query) {
				$db->raw_query($query);
			}

			$db->set_table(CONFIG::PERMISSION_ACTIONS_TABLE)->insert([
				'action_key'	=> 'admin_access',
				'action_desc'	=> 'Access to administration options'
			]);
		}
		else {
			$error = 'Permissions actions table already installed.';
		}

		$this->set([
			'ROLES_TABLE_INSTALLED'		=> $db->set_table(CONFIG::PERMISSION_ROLES_TABLE)->table_exists(),
			'ACTIONS_TABLE_INSTALLED'	=> $db->set_table(CONFIG::PERMISSION_ACTIONS_TABLE)->table_exists(),
			'AUTH_ERROR_MESSAGE'		=> $error
		]);

	}


}
