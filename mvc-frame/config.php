<?php

/* -----------------------
*   CONFIG for whole app.
*  -----------------------
*  - database settings
*  - database table definitions
*  - template settings
*  - session settings
*/
class CONFIG {







	/* -----------------------
	*   DATABASE
	*  -----------------------
	*/
	const DATABASE_HOST = 'myserver.mysql.com';
	const DATABASE_NAME = 'mvc_framework';
	const DATABASE_USERNAME = 'my_db_user';
	const DATABASE_PASSWORD = 'myDBpassword99!';


	/*
	* Format of stored dates in the DB. For MySQL this is Y-m-d
	* This is for easy date conversion and formatting.
	*/
	const DATABASE_DATE_FORMAT = 'Y-m-d';









	/* -----------------------
	*   DATABASE TABLES
	*   Continue to define future tables here if you want to ensure easy editing and db portability.
	*  -----------------------
	*/
	const USERS_TABLE = 'users';
	const SESSIONS_TABLE = 'sessions';
	const PERMISSION_ROLES_TABLE = 'permission_roles';
	const PERMISSION_ACTIONS_TABLE = 'permission_actions';










	/* -----------------------
	*   Errors
	*  -----------------------
	*/

	/*
	* bog standard debug setting. Turn on to output more helpful errors.
	*/
	const DEBUG = false;

	/*
	* A message safe to output to general public for when there is an error and DEBUG is turned off.
	*/
	const SAFE_ERROR_MESSAGE = 'Apologies, there has been an application error that has been logged.';

	/*
	* Error log filename. Stored within /includes/system
	* This will be created if doesn't exist.
	*/
	const ERROR_LOG_FILE = 'error_log.txt';

	/*
	* Maxmimum logs before the file is cleared
	* 10% of the current logs are kept when cleared.
	*/
	const ERROR_MAX_LOGS = 500;









	/* -----------------------
	*   TEMPLATE SETTINGS
	*   Can be set directly in the templateParser file but would be a bit hidden.
	*  -----------------------
	*/

	/*
	* How long in seconds to cache compiled templates for before they are considered too old.
	* 600 = 10 mins, 300 = 5 mins
	* Set to 0 to disable caching. Automatically disabled if DEBUG is on.
	*/
	const TEMPLATE_CACHE_TIME = 300;










	/* -----------------------
	*   SESSION SETTINGS
	*   How long to keep users authenticated for. Ignore this if using own auth methods.
	*  -----------------------
	*/

	/*
	* How long before an SQL session record should be considered expired.
	* format [0-9] DAY or [0-9] HOUR     (DO NOT PLURALISE)
	*
	* may need to alter cookie settings to match, if so, include the following in common.php
	* ini_set('session.cookie_lifetime', 604800);
	*/
	const MAX_SESSION_LENGTH = '7 DAY';



















	/* -----------------------
	*   END OF CONFIG.
	*
	*   DO NOT EDIT BELOW.
	*  -----------------------
	*
	*
	*
	*
	*
	*
	*
	*   Function used in index.php that allow a dynamic set up in any directory.
	*
	*/

	/*
	* Detect where the app is being run from, and set it as the root.
	* Set up our Folders and Loader.
	*/
	public static function setup_environment($paths) {

		if(!defined('INDEX_ROOT')) {
			define('INDEX_ROOT', str_replace('index.php', '', $_SERVER['SCRIPT_NAME']));
		}

		try {
			//find the Loader in the system file.
			$system_folder = $paths['system'] ?: '../system';
			require $system_folder . '/Loader.php';
		}
		catch(Throwable $e) {

			//ugly, but got no choice as can't access anything...
			die('Could not find system folder. Please ensure it is within the includes directory.');
		}

		//defines all our folders so can be accessed by the Loader.
		system\Loader::setup($paths);

		//register the loader now that it is set up.
		system\Loader::register();

		//set up our error/exception handler.
		system\ExceptionHandler::register();

		//set our views to the template class.
		system\Template::set_directory(system\Loader::views());
	}

}
