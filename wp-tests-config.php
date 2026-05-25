<?php
/**
 * WordPress test configuration.
 * Uses WORDPRESS_DB_* env vars when set (wp-env container), falls back to local defaults.
 *
 * @package Snopix
 */

define( 'ABSPATH', getenv( 'WORDPRESS_ABSPATH' ) ?: '/var/www/html/' );
define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'root' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Snopix Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
