<?php
/*  THE ROOT OF IT ALL
* ---------------------------
*  All requests go through index.php
*  Like most frameworks, controllers are accessed through url pathname; controller/module/parameter
*
*  This file can be duplicated in different directories to create sub-parts of site.
*  The setup_environment call will need edited to specify where to find controllers/models/views/system/includes
*/


/*
* We need our config before anything else.
* This must be found or we're doomed from the start.
* Local environments don't contain trailing slash, server environments do.
*/
require preg_replace('#/$#', '', $_SERVER['DOCUMENT_ROOT']) . '/../config.php';


/*
* Allowing these to be changed means we can set up another index.php in subdirectories
* and still use the same models/includes etc.
* For example in an /admin/ subdirectory so we have all our admin controllers separated
* but re-use includes/models/views etc.
*/
CONFIG::setup_environment([
	'system'		=> '../../includes/system',
	'models'		=> '../../models',
	'controllers'	=> 'controllers',
	'views'			=> '../../views',
	'includes'		=> '../../includes'
]);



/*
*  Now we have that set up we can use the loader to include our common file.
*  Using the common.php just keeps the index.php file neat.
*/
include system\Loader::includes('common.php');


//use our request controller to check the url
//and load the controller/module.
$REQUEST = new system\Request();


if( $REQUEST->valid() ) {
	$REQUEST->run_controller();
}
else {

	//invalid request.
	header("HTTP/1.0 404 Not Found");

	$invalidRequest = new \controllers\Controller();
	$invalidRequest->set_template('error/404.html')->render('Page not found.');
}
