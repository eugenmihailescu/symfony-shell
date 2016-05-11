<?php
require_once 'symfony-shell.php';

/**
 * Installs the Composer required components
 */
function composer_install() {
	SymfonyShell\echoTerminaCmd ( SymfonyShell\run_composer ( 'install', array (
			'no-dev' => null,
			'optimize-autoloader' => null 
	) ) );
}

/**
 * Dumps the Symfony assets
 *
 * @param string $environment
 *        	The Symfony environments (eg. dev, prod, etc)
 */
function symfony_dump_assets($environment = 'prod') {
	SymfonyShell\echoTerminaCmd ( SymfonyShell\run_symfony_console ( 'assetic:dump', array (
			'env' => $environment 
	) ) );
}
function symfony_cache_clear($environment = 'prod') {
	SymfonyShell\echoTerminaCmd ( SymfonyShell\run_symfony_console ( 'cache:clear', array (
			'env' => $environment 
	) ) );
}

SymfonyShell\register_function ( 'composer_install' );
SymfonyShell\register_function ( 'symfony_cache_clear' );
SymfonyShell\register_function ( 'symfony_dump_assets' );

SymfonyShell\run ();

?>