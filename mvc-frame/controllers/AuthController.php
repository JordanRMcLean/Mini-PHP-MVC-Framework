<?php
/*
* Auth controller
* Login, register, password change
*/

namespace controllers;

use \User as User;
use \Validation\ValidationValue;
use \Validation\PasswordValue;

class AuthController extends Controller {

 	public static $modules = ['login', 'register', 'logout'];

	public function  __construct($id, $module) {
		global $USER;

		//modules accessible by logged in users.
		$logged_in_modules = ['logout'];

		//if the user is logged in and the module is not for logged in users (login, register etc)
		if( $USER->is_logged_in() && !in_array($module, $logged_in_modules)) {
			$this->error_page('Denied', 'Permission denied');
		}

		//if there is no module show login module.
		if( !$module ) {
			$this->login();
		}
	}


	//if form is submitted we log the user in
	//if not then dispay the log in form.
	public function login() {
		global $USER, $SESSIONS;

		//set our log in template and also URL for the form to submit to.
		$this->set_template('auth/login.html');
		$this->set('FORM_ACTION', INDEX_ROOT . 'login');

		//log in form has been submitted.
		if( $this->is_submitted() ) {

			//get our input values.
			$email = $this->get_input('email', '');
			$password = $this->get_input('password', '');

			//set the attempted email to template, so we can display it.
			$this->set('ATTEMPTED_EMAIL', $email);

			//validate the email.
			$login_form = new \Validation\FormValidator([
				'Email'		=> new ValidationValue($email, ['is_email', 'required'])
			]);

			//our email is a ValidationValue object, which we can use the validation methods.
			if( !$login_form->valid() ) {

				//this will display the current template with the error message set.
				//our error message has been taken from ValidationErrors class for consistency.
				$this->error_display( $login_form->get_errors() );

			}

			//Lets start a User object.
			$user = new User();
			$user->load_by_email($email);


			//check it has been loaded and verify password.
			if($user->is_logged_in() && $user->verify_password($password)) {

				//looks like we're okay to log the user in. Store the session.
				$SESSIONS->update_current($user->get_id());

				//override the global USER class with the one of the logged in user.
				$GLOBALS['USER'] = $user;

				$this->success_page('Success', 'You have now been logged in!', [
					'url'	=> INDEX_ROOT,
					'text'	=> 'Return to Index'
				]);
			}

			$this->error_display('The details you provided are incorrect.');

		}
	}

	//if form is submitted then we begin the registration process
	//if not then we dispaly the registration page.
	public function register() {
		global $USER;

		$this->set_template('auth/register.html');
		$this->page_title = 'Register';
		$this->set('FORM_ACTION', INDEX_ROOT . 'register');

		//check if registration form has been submitted.
		if( $this->is_submitted() ) {

			//get our input fields.
			$email = $this->get_input('email', '');
			$password = $this->get_input('password', '');
			$verify_password = $this->get_input('verify_password', '');

			//set the email to the template
			$this->set('ATTEMPTED_EMAIL', $email);

			//validate form.
			$validator = new \Validation\FormValidator([
				'Email'		=> new ValidationValue($email, ['required', 'is_email']),
				'Password'	=> new PasswordValue($password, [['must_match_password', $verify_password]])
			]);

			//if validation fails, display the errors.
			if( !$validator->validate() ) {
				$this->error_display( $validator->get_errors() );
			}

			//check if the email has been used already.
			$Users = new \models\Users();

			if( $Users->exists('user_email', $email) ) {
				$this->error_display('An account already exists with that email.');
			}

			//validation complete, create a new user.
			$Users->create_new($email, $password);

			$this->success_page('Success', 'Your account has been created you can now log in.', [
				'url'	=> INDEX_ROOT,
				'text'	=> 'Return to Index'
			]);
		}
	}

	//self explanatory..
	public function logout() {
		global $SESSIONS;

		$SESSIONS->remove_current();

		//reset the global user class.
		$GLOBALS['USER'] = new User();

		$this->success_page('Logged Out', 'You have been logged out successfully.', [
			'url'	=> INDEX_ROOT,
			'text'	=> 'Return to Index'
		]);
	}




	//forgotten password.
	public function forgot() {

	}

}
