<?php

// Parent controller.
// defines basics for controllers like template, title, display.

namespace controllers;

use \system\Template as Template;
use \system\TemplateParser;

class Controller {

	protected $template;
	protected $page_title;
	protected $module;
	protected $id;

	public function set_module($module = null) {
		$this->module = $module;
	}

	public function set_id($id = null) {
		$this->id = $id;
	}

	/* TEMPLATE shortcuts
	*  set_template - set the template file for the controller.
	*  set - set a template variable
	*  set_loop - add a record to a template loop.
	*  info_page - ends script and displays an info page.
	*  success_page - ends script and displays a success page with message.
	*  error_page - ends script and displays an error page with message.
	*  error_display - ends script and displays the set template, setting the given message.
	*/
	public function set_template($file = null) {
		//if a template is already defined, we load a new one so existing vars remain.
		//we allow the setting of vars/loops on an empty template so that the file can be called later
		if($this->template && $file) {
			$this->template->load($file);
		}
		else {
			$this->template = new Template($file);
		}

		return $this;
	}

	public function set($name, $value = null) {
		if( !$this->template ) {
			$this->set_template();
		}

		$this->template->set($name, $value);
	}

	public function set_loop($name, $values) {
		if( !$this->template ) {
			$this->set_template();
		}

		$this->template->set_loop($name, $values);
	}

	//display a page with an info message. Used for ending script when no longer needed to run.
	//classname should be 'error', 'success', 'info' - to style the info box.
	protected function info_page($title, $message, $link = null, $classname = 'info') {
		$this->set_template('common/info_message.html');

		if($link && !is_array($link)) {
			$link = null;
		}

		$this->set([
			'INFO_BOX_CLASSNAME'		=> $classname,
			'INFO_BOX_MESSAGE'			=> $message,
			'INFO_BOX_LINK_URL'			=> $link ? $link['url'] : false,
			'INFO_BOX_LINK_TEXT'		=> $link ? $link['text'] : false
		]);

		$this->render($title);
	}

	protected function success_page($title, $message, $link = null) {
		$this->info_page($title, $message, $link, 'success');
	}

	protected function error_page($title, $message, $link = null) {
		$this->info_page($title, $message, $link, 'error');
	}

	protected function error_display($message) {
		if(is_array($message)) {
			$message = implode('<br>', $message);
		}

		$this->set('ERROR_MESSAGE', $message);
		$this->render();
	}

	//display a confirmation message for the given action.
	//"yes" will lead back to the action with confirmed in the request.
	//"no" will lead to return url provided.
	//use confirmed() to know if has been confirmed or not.
	protected function confirm_message($text, $confirm_url, $return_url) {
		$this->set_template('common/confirm_message.html');

		$confirm_url = url($confirm_url, ['confirm' => 1]);

		$this->set([
			'CONFIRM_BOX_TEXT'	=> $text,
			'NO_URL'			=> $return_url,
			'YES_URL'			=> $confirm_url
		]);

		$this->render('Confirm');
	}


	public function render($page_title = null) {
		global $USER, $REQUEST;

		if($page_title) {
			$this->page_title = $page_title;
		}

		$template = $this->template;
		$parser = new TemplateParser();

		if($template && ($template instanceof Template)) {
			$template->set([
				'PAGE_TITLE' 	=> $this->page_title ?: ucfirst($REQUEST->controller),
				'DEBUG_MODE'	=> \CONFIG::DEBUG,
				'SQL_QUERIES'	=> \models\Model::$sql_queries
			]);

			if($USER) {
				$USER->set_to_template($template);
			}

			//set controller & module to template incase needed.
			$template->set([
				'CONTROLLER'	=> $REQUEST->controller,
				'MODULE'		=> $REQUEST->module
			]);

			$page_content = $parser->parse($template)->compile($template);

			die($page_content);
		}
		else {
			throw new \Exception('No template file defined for controller.');
		}
	}

	protected function get_input(string $input_name, $default = null, $haystack = null) {
		return \system\Request::get_input($input_name, $default, $haystack);
	}

	protected function is_confirmed() {
		return \system\Request::confirmed();
	}

	protected function is_submitted() {
		return \system\Request::submitted();
	}

}
