# symfony-shell
Access composer and Symfony's bin/console tool remotely without terminal/SSH access.

#### Requirements
- PHP 5.3.2+
- [composer](https://en.wikipedia.org/wiki/Composer_%28software%29) or [Symfony](https://en.wikipedia.org/wiki/Symfony)

#### Installation
Just copy (or `git clone`) the [symfony-shell.php](https://github.com/eugenmihailescu/symfony-shell/blob/master/symfony-shell.php) to your remote web document location (eg. /public_html/).

#### Extension
In order to tell the Composer or Symfony what to do you have to write an extension. 

The extension is just a regular PHP script where you register one/more hook functions and finally you call the built-in `run` function which will run one-by-one each registered hook.

A hook function is a callable PHP function (anything that can be run via [call_user_func](http://php.net/manual/en/function.call-user-func.php) where you can do whatever you want but additionally it should call either the built-in `run_composer` or `run_symfony_console` functions which allows you to run a Composer or Symfony command by specifying the command arguments.

The output of each hook function (ie. of each hooked Composer/Symfony command) will be displayed on a built-in HTML terminal.

##### run_composer

This built-in function allows you to run a Composer command with a variable number of arguments.

Syntax:

```php
/**
 * Execute a Composer command
 *
 * @param string $composer_cmd
 *        	The Composer command to run (eg. install, update, etc)
 * @param array $composer_args
 *        	An argument=value array of arguments for the Composer command
 * @param string $return_output
 *        	When false the output is echoed, otherwise is not.
 * @return Returns the array the lines of output for STDOUT, STDERR file descriptors
 */
run_composer($composer_cmd, $composer_args = array(), $return_output = false)	
``` 
 Example:

```php
run_composer('install',array('no-dev'=>null,'working-dir'=>'/the/working/dir'));
```
 
##### run\_symfony\_console

This built-in function allows you to run a Symfony bin/console with a variable number of arguments.

Syntax:

```php
/**
 * Execute a Symfony console command
 *
 * @param string $symfony_cmd
 *        	The Symfony console command to run (eg. cache:clear, assetic:dump, etc)
 * @param array $symfony_args
 *        	An argument=value array of arguments for the Symfony console command
 * @param string $return_output
 *        	When false the output is echoed, otherwise is not.
 * @return Returns the array the lines of output for STDOUT, STDERR file descriptors
 */
run_symfony_console($symfony_cmd, $symfony_args = array(), $return_output = false)
``` 

Example:

```php
run_symfony_console('cache:clear',array('no-warmup'=>null,'env'=>'prod'));
```
##### register_hook

This built-in function allows you to register your custom hook function that normally should call one of the above `run_composer` or `run_symfony_console` function although your hook function may do whatever you want.

Syntax:

```php
/**
 * Register a function to be executed on terminal
 *
 * @param callable $callback        	
 * @param mixed $arguments
 *        	A variable number of arguments that are dynamically detected
 * @see run()
 */
function register_hook($callback, [$arguments])
```

Example:

```php
// composer_install is just a custom function (see the complete example below)
register_hook('composer_install');
```

##### run

This built-in funciton allow you to run the registered hook functions in their registration order.

Syntax:

```php
/**
 * Run the registered functions on terminal
 *
 * @see register_hook()
 *
 * @param bool $ignore_errors
 *        	When true continue the execution by ignoring the hook execution exit codes, otherwise return
 *        	
 * @return bool Returns true if ALL the hooks succeeded, false otherwise
 *        
 */
function run($ignore_errors = false) 
```

#### A complete extension example
```php
<?php
require_once 'symfony-shell.php';

// the hook function
function composer_install() {
	// the composer arguments for install command
    $args = array (
			'no-dev' => null,
			'optimize-autoloader' => null 
	);
	
	// run the composer install command 
	$output = SymfonyShell\run_composer ( 'install', $args );
	
	// echo the composer install command output to the built-in HTML terminal
	SymfonyShell\echoTerminaCmd ($output);
	
	// contains the command exec exit code
	return $output[4];
}

// register the hook function
SymfonyShell\register_hook ( 'composer_install' );

// run will exit on the first error
$ignore_errors = false;

// finally run the registered hook functions
SymfonyShell\run ($ignore_errors);

?>
```

See the [symfony-post-deploy.php](https://github.com/eugenmihailescu/symfony-shell/blob/master/symfony-post-deploy.php) sample hook extension.