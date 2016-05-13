<?php
require_once 'symfony-shell.php';

/**
 * Recursive copy a file or directory
 *
 * @param string $src        	
 * @param string $dst        	
 * @return bool Returns true on success, false otherwise
 */
function _copy($src, $dst, $mode = 0770) {
	$success = true;
	
	if (is_dir ( $src )) {
		if ($success &= is_dir ( $dst ) || mkdir ( $dst, $mode, true )) {
			
			$files = scandir ( $src );
			
			foreach ( $files as $file ) {
				if ($file != "." && $file != "..")
					$success &= _copy ( "$src/$file", "$dst/$file" );
			}
		}
	} else if (file_exists ( $src ))
		$success &= copy ( $src, $dst );
	
	return $success;
}

/**
 * Installs the Composer required components
 *
 * @return bool Returns true on success, false otherwise
 */
function composer_install() {
	$output = SymfonyShell\run_composer ( 'install', array (
			'optimize-autoloader' => null,
			'no-interaction' => null 
	) );
	SymfonyShell\echoTerminaCmd ( $output );
	
	return ! $output [4]; // returns the cmd exec exit code
}

/**
 * Dumps the Symfony assets
 *
 * @param string $environment
 *        	The Symfony environments (eg. dev, prod, etc)
 * @return bool Returns true on success, false otherwise
 */
function symfony_dump_assets($environment = 'prod') {
	$output = SymfonyShell\run_symfony_console ( 'assetic:dump', array (
			'env' => $environment,
			'no-debug' => null 
	) );
	SymfonyShell\echoTerminaCmd ( $output );
	
	return ! $output [4]; // returns the cmd exec exit code
}

/**
 * Clears the Symfony cache directory
 *
 * @param string $environment
 *        	The Symfony environment (eg. prod,dev,tests)
 * @return bool Returns true on success, false otherwise
 */
function symfony_cache_clear($environment = 'prod') {
	$output = SymfonyShell\run_symfony_console ( 'cache:clear', array (
			'env' => $environment,
			'no-debug' => null 
	) );
	
	SymfonyShell\echoTerminaCmd ( $output );
	
	return ! $output [4]; // returns the cmd exec exit code
}
/**
 * Install the bundle assets to the public (eg.
 * web) directory
 *
 * @param string $environment
 *        	The Symfony environments (eg. dev, prod, etc)
 * @param string $symlink
 *        	When true symlinks the assets otherwise copy them
 * @param string $relative
 *        	When true make relative symlinks
 * @return bool Returns true on success, false otherwise
 */
function symfony_assets_install($environment = 'prod', $symlink = false, $relative = false) {
	$args = array (
			'env' => $environment,
			'no-debug' => null 
	);
	
	$symlink && $args ['symlink'] = null;
	$relative && $args ['relative'] = null;
	
	$output = SymfonyShell\run_symfony_console ( 'assets:install', $args );
	
	SymfonyShell\echoTerminaCmd ( $output );
	
	return ! $output [4]; // returns the cmd exec exit code
}

/**
 * This is a custom hook that has nothing to do with Composer/Symfony
 *
 * @return bool Returns true on success, false otherwise
 */
function copy_vendor_assets() {
	$dir = '/vendor/bower-asset/';
	$src = $dir;
	$dst = '/web/bundles' . $dir;
	
	$result = false;
	$start = microtime ( true );
	
	$echo = function ($string, $is_error = false) use (&$src, &$dst, &$start) {
		SymfonyShell\echoTerminaCmd ( array (
				sprintf ( 'cp %s %s', $src, $dst ),
				array (
						$is_error ? '' : $string 
				),
				array (
						$is_error ? $string : '' 
				),
				microtime ( true ) - $start,
				0 
		) );
	};
	
	if (is_dir ( __DIR__ . $src )) {
		$stat = stat ( __DIR__ . $src ); // get file info
		if ($result = _copy ( __DIR__ . $src, __DIR__ . $dst, $stat ? $stat [2] : 0770 ))
			$echo ( sprintf ( 'Folder %s copied successfully to %s', $src, $dst ) );
		else {
			$sys_err = error_get_last ();
			$echo ( sprintf ( '%s (%s)', $sys_err ['message'], $sys_err ['type'] ) );
		}
	} else
		$echo ( sprintf ( 'Directory %s does not exist', $src ) );
	
	return $result;
}

// register our custom hooks
SymfonyShell\register_hook ( 'composer_install' ); // install the required dependencies as per composer.json
SymfonyShell\register_hook ( 'copy_vendor_assets' ); // should be registered before `symfony_dump_assets` hook
SymfonyShell\register_hook ( 'symfony_assets_install', 'prod', true ); // install the bundle assets to the default dir (default "web")
SymfonyShell\register_hook ( 'symfony_dump_assets' ); // dump the assets to the public folder (eg. web)
SymfonyShell\register_hook ( 'symfony_cache_clear' ); // clear the default (production) cache
SymfonyShell\register_hook ( 'symfony_cache_clear', 'dev' ); // explicitely clear the development cache
                                                             
// run the registered hook functions
SymfonyShell\run ();

?>