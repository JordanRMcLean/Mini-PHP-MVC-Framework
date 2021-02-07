<?php

//re-route pathnames to a specific controller/module
//THIS IS NOT URL REWRITING and does not allow regular expression matching.
//this allows 'url.com/something' to re-route to '/specific_controller/optional_module'

// examples:
// 'about'			=> 'pages/about'
// 'login'			=> 'auth/login'
// 'page'			=> 'pages/specificpage'
// 'u'				=> 'users'			- when re-routing controllers, modules still follow. u/mod will still go to users/mod
// 'profile/update'	=> 'users/update'
// 'users/oldmodule'=> 'users/newmodule'

//rerouting can be recurssive...so if
// 'update' re-routes to 'users/update' and
// 'users/update' re-routes to 'profile/update'
// then 'update' will cause 'profile/update' to run.

// page/pathname route				countroller to route to.
$CONTROLLER_ROUTES = array(
	't1'				=> 'test/test1',
	't2'				=> 'test/test2',
	't3'				=> 'test/test3',

	//auth pages
	'login'				=> 'auth/login',
	'register'			=> 'auth/register'
);

return $CONTROLLER_ROUTES;
