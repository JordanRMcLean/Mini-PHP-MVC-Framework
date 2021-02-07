<?php

namespace controllers;

class IndexController extends Controller {

	public function  __construct($id, $module) {
		$this->set('WELCOME_MESSAGE', 'Welcome, installation complete... ');

/* --------------------------------------------------------------------------------
							REMOVE LINES 10 - 30 once installed
   -------------------------------------------------------------------------------- */

		try{
			//try and connect to database.
			$db = new \models\Model();

			if($db) {
				$this->set('DB_CONNECT_SUCCESS', true);
			}

		}
		catch(\Exception $e) {
			$this->set('DB_CONNECT_SUCCESS', false);
			$this->set('DB_CONNECT_ERROR', $e->getMessage());
		}


/* -------------------------------------------------------------------------------- */


		$this->set_template('index.html');
	}
}
