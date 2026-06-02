<?php
/**
 * Bootstrap for pure unit tests — no WordPress, no database.
 *
 * Every Snopix source file guards with `if ( ! defined( 'ABSPATH' ) ) exit;`.
 * Define a dummy ABSPATH so those guards pass, then load the plugin's own
 * autoloader. The classes exercised by the unit suite (imaging math, search
 * scoring, MIME validation) call zero WordPress functions at runtime.
 *
 * @package Snopix
 */

define( 'ABSPATH', sys_get_temp_dir() . '/snopix-unit/' );

require_once dirname( __DIR__ ) . '/includes/infrastructure/class-autoloader.php';

\Snopix\Infrastructure\Autoloader::init( dirname( __DIR__ ) . '/includes' );

require_once __DIR__ . '/unit/class-unit-testcase.php';
