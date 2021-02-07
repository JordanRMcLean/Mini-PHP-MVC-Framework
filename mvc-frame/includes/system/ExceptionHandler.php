<?php

namespace system;

/* Treat all excpetions/errors as fatal to script execution.
	Will output a safe message when debug is turned off.
	Will attempt to use the templater system and use errorpage.html template.
	if that fails, will output a simple page.
 */


class ExceptionHandler {

	/*
	*  We define these here so the exception handler can be ported
	*  and these values be set directly in file.
	*  But ideally should be changed in config.php only.
	*/
	protected static $safe_message = \CONFIG::SAFE_ERROR_MESSAGE;
	protected static $error_file = \CONFIG::ERROR_LOG_FILE;
	protected static $max_logs = \CONFIG::ERROR_MAX_LOGS;

	//basic error page output template.
	//fallback for if templater has failed.
	protected static $error_template = "
	<!DOCTYPE HTML>
	<html>
		<head>
			<title>Application Error</title>
			<meta charset=\"utf-8\" />
		</head>
		<body>
		<h2>Fatal Application Error</h2>
		<h3>This error will need fixed internally.</h3>
		<p>{MESSAGE}</p>
		</body>
	</html>";

	public static function register() {
		set_exception_handler(['\system\ExceptionHandler', 'handler']);
		set_error_handler(['\system\ExceptionHandler', 'error_handler']);

		//check for and create an error log file.
		//we won't be using error_log function due to its many downfalls.
		$error_file = Loader::system(self::$error_file);

		if(!file_exists($error_file)) {
			file_put_contents($error_file, '');
		}
	}

	public static function unregister() {
		set_exception_handler(null);
	}

	//catch an error and pass it to our handler to treat as the same.
	public static function error_handler($errno, $msg) {
		if((error_reporting() & $errno) > 0) {
			return self::handler( new \ErrorException("[$errno] $msg") );
		}
	}

	public static function handler(\Throwable $e) {

		//because this function relies heavily on our classes
		//there is chance that there will be failure, so we will "try" everything
		//and if fails, provide somple output with basic native php.

		$html_message = $e->getMessage() . '<br>File: <strong>' . $e->getFile() . '</strong><br>Line: <strong>' . $e->getLine() . '</strong>';
		$log_message = $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine() . "\n";

		//first try and log the message.
		try {
			self::log($log_message);
			$error_logged = true;
		}
		catch(\Throwable $e){
			$error_logged = false;
		}

		//if debug is on... then we don't have to worry.
		//lets just output as much info as poss.
		if(\CONFIG::DEBUG) {
			$trace = str_replace('#', '<br>', $e->getTraceAsString());

			$html_message .= "<br>$trace";

			die( str_replace('{MESSAGE}', $html_message, self::$error_template) );
		}
		else {
			try {

				//now we'll try and output it to the template.
				$template = new \system\Template('error/errorpage.html');
				$template->set('ERROR_MESSAGE', self::$safe_message);

				$parser = new \system\TemplateParser();

				//try compiling and outputting.
				die($parser->parse($template)->compile($template));
			}
			catch(\Throwable $e) {

				//at this point we're assuming something is wrong that
				//is preventing us from using our classes.

				die( str_replace('{MESSAGE}', self::$safe_message, self::$error_template) );
			}
		}
	}

	protected static function log($msg) {

		//check we have a log file and append it.

		$error_file = Loader::system(self::$error_file);
		$date = (new \DateTime())->format('Y-m-d h:i:s');

		if(file_exists($error_file)) {

			//we dont want the log file getting too big so we set a maximum of logs.
			//500 by default which is reasonable. We could update this to do by filesize in future.
			$file_contents = file_get_contents($error_file);
			$logs = explode("\n\n", $file_contents);

			if( count($logs) >= self::$max_logs ) {

				//keep 10% of the logs.
				$keep_no = round( self::$max_logs * 0.1 );

				$keep_logs = array_slice($logs, -$keep_no );
				file_put_contents($error_file, implode("\n\n", $keep_logs));
			}

			file_put_contents($error_file, "\n" . "[$date] - $msg", FILE_APPEND);
		}
	}

}
