<?php

/*
* Include any code you want executed on every page here.
* We use this file to keep the index.php file untouched.
*/

session_start();

include \system\Loader::includes('date_time_functions.php');

//we initiate the current user as a global.
$SESSIONS = new \models\Sessions();
$USER = new User( $SESSIONS->current_user_id() );
