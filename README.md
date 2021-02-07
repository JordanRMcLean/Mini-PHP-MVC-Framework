# Mini MVC Framework

Mini MVC framework I use for personal projects.

Designed similar to most with following features:
- URLs dictating the controller
- alternative controller routing
- autoloader with pathname methods.
- Built in validation.
- Easy templating: [PHP Templater](https://github.com/JordanRMcLean/Templater)
- Exception handler built in with error reporting to text file.
- Basic date/time functions for handling sql dates.
- Preset models for users, sessions and permissions
- Basic built in auth setup with permissions.
- Simple install page for installing DB tables.
- Config file for ease of options.
- User input handling through controllers.

More docs coming in future if needed.


## Model
Uses my own [MySQL Model](https://github.com/JordanRMcLean/PHP-MySQL-Model)

-------------------

## View
Uses my own [PHP Templater](https://github.com/JordanRMcLean/Templater)

------------------

## Controller
Controllers set to do most of the work. Extending of the main controller class gives access to these methods:

"$this" refers to the controller class as these would be executed within the controller.
- Set the template for the controller: `$this->set_template('header.html');`
- Set a template variable: `$this->set('TEMPLATE_VAR', 'value');`
- Set a template loop: `$this->set_loop('loop_name', loop_array)`
- Stop execution, display an info page: `$this->info_page('Page title', 'Info Message', ['url' => url, 'text' => url_text])`
- Stop execution, display an error page: `$this->error_page('Page title', 'Message', ['url' => url, 'text' => url_text])`
- Stop execution, display a success page: `$this->success_page('Page title', 'Message', ['url' => url, 'text' => url_text])`
- Stop execution and display current template with ERROR_MESSAGE template var. `$this->error_display($message)`
- Stop execution and display a confirm page: `$this->confirm_message($message, $confirmed_url, $return_url)`
- Render the template and display. This is called automatically if not manually: `$this->render()`
- Get user input and type-match against default: `$this->get_input('input name', $default_value)`
- Check if confirm page has been confirmed: `$this->is_confirmed()`
- Check if form has been submitted (with input name of 'submit'): `$this->is_submitted()`

## Installation
Set up config.php file with database credentials and options.

Visit the /install directory to install test DB connection and install default tables.

If moving any folders elsewhere you'll need to specify new pathnames in the setup_environment function in public/index.php.
This also allows for multiple installs on the same site, see /public/install/index.php how new pathnames are defined to access the same includes/views/models but specify new controllers.

## Basic Auth
There is a basic auth set up with register/login and logout scripts already existing.

## Validation
Uses ValidationValue objects, and uses FormValidator to validate multiple at a time.
Docs coming in separate repository.

## Includes and files.
The loader used by the framework includes static functions for always accessing the correct folders.
These functions return the path to the folder or a file if specified.

E.g: `include \system\Loader::includes('common.php');`
