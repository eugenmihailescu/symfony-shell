<?php

namespace SymfonyShell;

/**
 * PHP script utility that allows the run of Composer and Symfony's
 * bin/console remotelely (without SSH or terminal access).
 *
 * @author Eugen Mihailescu
 * @version 0.1
 * @since 2016-05-11
 * @license MIT
 *         
 */

/**
 * True on *nix platforms, false on Windows
 *
 * @var bool $_IS_NIX_
 */
$_NIX_OS_ = ! stripos ( PHP_OS, 'win' );

/**
 * Sets the verbosity level.
 * When true the set the verbosity on, otherwise off.
 *
 * @var bool $_VERBOSITY_
 */
$_VERBOSITY_ = true;

/**
 * The path to composer.phar script file
 *
 * @var string $_COMPOSER_BIN_
 */
$_COMPOSER_BIN_ = __DIR__ . '/composer';

is_file ( $_COMPOSER_BIN_ ) || $_COMPOSER_BIN_ .= '.phar';

is_file ( $_COMPOSER_BIN_ ) || $_NIX_OS_ && $_COMPOSER_BIN_ = exec ( 'which composer.phar' );

is_file ( $_COMPOSER_BIN_ ) || $_NIX_OS_ && $_COMPOSER_BIN_ = exec ( 'which composer' );

is_file ( $_COMPOSER_BIN_ ) || $_COMPOSER_BIN_ = install_composer ();

/**
 * The path to the Symfony bin/console application script
 *
 * @var string $_SYMFONY_CONSOLE_
 */
$_SYMFONY_CONSOLE_ = __DIR__ . '/bin/console';

/**
 * The HTML terminal max-width in characters (em)
 *
 * @var int $_TERMINAL_WIDTH_
 */
$_TERMINAL_WIDTH_ = 80; // chars

/**
 * The HTML terminal max-height in characters (em)
 *
 * @var int $_TERMINAL_HEIGHT_
 */
$_TERMINAL_HEIGHT_ = 50; // chars

/**
 * The functions registered to run
 *
 * @var array
 */
$_REGISTERED_FUNCTIONS_ = array ();

/**
 * Encodes a string to a HTML UTF-8
 *
 * @param string $string
 *        	The string to be converted
 * @return string Returns the encoded string
 */
function encode_utf8_html($string) {
	if (function_exists ( 'mb_convert_encoding' ))
		$string = mb_convert_encoding ( $string, 'UTF-8', 'UTF-8' );
	
	return htmlspecialchars ( $string, ENT_COMPAT, 'UTF-8' );
}
function getHtmlColor2TerminalEscapeCode($color) {
	$code = '';
	switch ($color) {
		case 'tomato' :
			$code = '91';
			break;
		case '#fff' :
			$code = '97';
			break;
	}
	
	return $code;
}
/**
 * Exec a PHP script via the PHP CLI environment
 *
 * @param string $cmd
 *        	The PHP script to execute
 * @param array $args
 *        	The PHP script arguments with the following specification:
 *        	- the `prefix` key specifies the argument prefix to be used (default to --)
 *        	- the `separator` key specifies the argument {key}SEPARATOR{value} separator (default to =)
 *        	- the `items` key specifies the actual arguments array
 * @param string $work_dir
 *        	The initial working directory for the command
 * @param array $env
 *        	An array of key=value with the environment variables for the command that will be run
 * @return $array Returns the array the lines of output for STDOUT, STDERR file descriptors:
 *         - the 0-key contains the shell command
 *         - the 1-key contains the STDOUT output lines
 *         - the 2-key contains the STDERR output lines.
 *         - the 3-key contains the total execution time in microseconds
 *         - the 4-key contains the command exit code
 */
function exec_cmd($cmd, $args = array(), $work_dir = __DIR__, $env = array()) {
	$start = microtime ( true );
	
	// prepare the command arguments
	$arg_prefix = isset ( $args ['prefix'] ) ? $args ['prefix'] : '--';
	$arg_separator = isset ( $args ['separator'] ) ? $args ['separator'] : '=';
	
	$cmd_args = array ();
	if (isset ( $args ['items'] ))
		foreach ( $args ['items'] as $key => $value ) {
			$cmd_args [] = $arg_prefix . $key . (null !== $value ? $arg_separator . escapeshellarg ( $value ) : '');
		}
	
	$php_cmd = sprintf ( 'php %s %s', escapeshellcmd ( $cmd ), implode ( ' ', $cmd_args ) );
	
	$pipes = array ();
	$descriptorspec = array (
			// STDOUT
			1 => array (
					'pipe',
					'w' 
			),
			// STDERR
			2 => array (
					'pipe',
					'w' 
			) 
	);
	
	$output = array (
			0 => $php_cmd 
	);
	
	// execute the process
	$p = proc_open ( $php_cmd, $descriptorspec, $pipes, $work_dir, $env );
	
	foreach ( array_keys ( $descriptorspec ) as $index ) {
		while ( ! feof ( $pipes [$index] ) ) {
			isset ( $output [$index] ) || $output [$index] = array ();
			$output [$index] [] = encode_utf8_html ( fgets ( $pipes [$index] ) );
		}
		
		fclose ( $pipes [$index] );
	}
	
	$output [4] = proc_close ( $p );
	
	$output [3] = microtime ( true ) - $start;
	
	return $output;
}
/**
 * Returns the COMPOSER_HOME environment variable
 *
 * @return array
 */
function get_composer_home() {
	$home = getenv ( 'HOME' ) . '/.composer';
	
	is_dir ( $home ) || (isset ( $_REQUEST ['composer_home'] ) && $home = $_REQUEST ['composer_home']);
	
	return array (
			'COMPOSER_HOME' => is_dir ( $home ) ? $home : __DIR__ 
	);
}

/**
 * Install the Composer
 *
 * @see https://getcomposer.org/download
 *
 * @return On success returns the composer.phar path, otherwise false
 */
function install_composer() {
	$url = 'https://getcomposer.org/installer';
	$composer_dir = __DIR__ . '/bin/';
	$setup = $composer_dir . 'composer-setup.php';
	
	is_dir ( $composer_dir ) || mkdir ( $composer_dir );
	
	$output = array (
			"cp $url $setup" 
	);
	
	if (copy ( $url, $setup )) {
		$output = exec_cmd ( $setup, array (
				'install-dir' => $composer_dir 
		), $composer_dir, get_composer_home () );
		unlink ( $setup );
		
		if (! $output [4] && is_file ( $composer_dir . 'composer.phar' ))
			return $composer_dir . 'composer.phar';
	}
	
	$cmd_err = error_get_last ();
	echoTerminaCmd ( array (
			$output [0],
			array (),
			array (
					sprintf ( '%s (%s)', $cmd_err ['message'], $cmd_err ['type'] ) 
			),
			0,
			$cmd_err ['type'] 
	) );
	
	return false;
}

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
function run_composer($composer_cmd, $composer_args = array(), $return_output = false) {
	global $_COMPOSER_BIN_, $_VERBOSITY_;
	
	$_VERBOSITY_ && $composer_args ['verbose'] = null;
	
	$args = array (
			'items' => $composer_args 
	);
	
	/*
	 * - the 0-key contains the shell command
	 * - the 1-key contains the STDOUT output lines
	 * - the 2-key contains the STDERR output lines.
	 * - the 3-key contains the total execution time in microseconds
	 * - the 4-key contains the command exit code
	 */
	
	$cmd = sprintf ( '%s %s', escapeshellcmd ( $_COMPOSER_BIN_ ), escapeshellcmd ( $composer_cmd ) );
	
	if (! $_COMPOSER_BIN_)
		return array (
				$cmd,
				array (),
				array (
						sprintf ( 'Composer binary not found. Make sure it is installed!' ) 
				),
				0,
				2 
		);
	
	return exec_cmd ( $cmd, $args, __DIR__, get_composer_home (), $return_output );
}

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
function run_symfony_console($symfony_cmd, $symfony_args = array(), $return_output = false) {
	global $_SYMFONY_CONSOLE_, $_VERBOSITY_;
	
	$_VERBOSITY_ && $symfony_args ['verbose'] = null;
	
	$args = array (
			'items' => $symfony_args 
	);
	
	return exec_cmd ( sprintf ( '%s %s', escapeshellcmd ( $_SYMFONY_CONSOLE_ ), escapeshellcmd ( $symfony_cmd ) ), $args, __DIR__, null, $return_output );
}

/**
 * Echos the output to the HTML terminal
 *
 * @param array $output
 *        	An array compatible with the return value of the `exec_cmd` function
 */
function echoTerminaCmd($output) {
	echo sprintf ( '<div><span style="color:tomato;font-weight: bold">%s ~ $ </span><span>%s</span></div>', get_current_user () . '@' . php_uname ( 'n' ), $output [0] ), PHP_EOL;
	
	echo sprintf ( '<div style="padding:1em;color:#fff">%s</div>', implode ( '<br>', $output [1] ) . implode ( '<br>', $output [2] ) ), PHP_EOL;
	
	echo sprintf ( '<div style="display:inline-block;border: 1px double white;padding: 5px;margin-bottom: 1em;"><span style="color:tomato;font-weight: bold">%s (exec time: </span><span>%s</span>)</div>', $output [4] ? 'ERROR' : 'SUCCESS', date ( 'H:i:s', $output [3] ) . '.' . ceil ( 1000 * ($output [3] - floor ( $output [3] )) ) ), PHP_EOL;
}

/**
 * Register a function to be executed on terminal
 *
 * @param callable $callback        	
 * @param mixed $arguments
 *        	A variable number of arguments that are dynamically detected
 * @see run()
 */
function register_hook($callback) {
	global $_REGISTERED_FUNCTIONS_;
	
	$_REGISTERED_FUNCTIONS_ [] = func_get_args ();
}

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
function run($ignore_errors = false) {
	global $_NIX_OS_, $_REGISTERED_FUNCTIONS_, $_TERMINAL_WIDTH_, $_TERMINAL_HEIGHT_;
	
	$result = true;
	
	$filter_output = function ($output) {
		global $_NIX_OS_;
		if (php_sapi_name () == "cli") {
			// on *nix terminal use color escape codes
			if ($_NIX_OS_) {
				$matches = null;
				if (preg_match_all ( '/(<(div|span).+color\s*:\s*([^;\s\'"]+)).*<\/\2>/', $output, $matches ))
					foreach ( $matches [0] as $index => $html ) {
						$output = str_replace ( $html, chr ( 27 ) . '[1m' . chr ( 27 ) . '[' . getHtmlColor2TerminalEscapeCode ( $matches [3] [$index] ) . 'm' . $html . chr ( 27 ) . '[0m', $output );
					}
			}
			
			echo htmlspecialchars_decode ( strip_tags ( $output ) );
		} else
			echo $output;
		
		flush ();
	};
	
	ob_start ();
	?>

<div style="overflow:auto;padding:0.5em;background-color: #000; color: #0f0;max-width:<?php echo $_TERMINAL_WIDTH_;?>em;max-height:<?php echo $_TERMINAL_HEIGHT_;?>em">
<?php
	
	$filter_output ( ob_get_clean () );
	
	foreach ( $_REGISTERED_FUNCTIONS_ as $fn ) {
		ob_start ();
		
		$result &= call_user_func_array ( $fn [0], array_slice ( $fn, 1 ) );
		
		$filter_output ( ob_get_clean () );
		
		// exit on error
		if (! ($ignore_errors || $result))
			break;
	}
	
	ob_start ();
	?>	
</div>

<?php
	
	$filter_output ( ob_get_clean () );
	
	return $result;
}
?>