<?php
/**
 * Plugin autoloader for WordPress-style class naming.
 *
 * Resolves Snopix\* classes by converting the namespaced class name to a
 * filesystem path under includes/. No manual class map needed.
 *
 * Conventions:
 *   Snopix\Indexing\Image_Indexer         → includes/indexing/class-image-indexer.php
 *   Snopix\Repository\Index_Repository_Interface → includes/repository/interface-index-repository.php
 *
 * @package Snopix
 */

namespace Snopix\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * PSR-4–style autoloader for Snopix namespaced classes.
 */
class Autoloader {

	/**
	 * Base directory for class files.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Register the autoloader with spl_autoload_register.
	 *
	 * @param string $base_directory Absolute path to the includes directory.
	 *
	 * @return void
	 */
	public static function init( string $base_directory ): void {
		self::$base_dir = rtrim( $base_directory, '/' );
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Attempt to load a class by its fully-qualified name.
	 *
	 * @param string $class_name Fully-qualified class name.
	 *
	 * @return void
	 */
	public static function load( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'Snopix\\' ) ) {
			return;
		}

		$file = self::locate( $class_name );

		if ( $file && file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Convert namespaced class name to filesystem path.
	 *
	 * @param string $class_name Full namespaced class name.
	 *
	 * @return string Absolute path.
	 */
	private static function locate( string $class_name ): string {
		$relative = substr( $class_name, strlen( 'Snopix\\' ) );
		$parts    = explode( '\\', $relative );
		$short    = array_pop( $parts );
		$dir      = strtolower( implode( '/', $parts ) );

		if ( str_ends_with( $short, '_Interface' ) ) {
			$slug     = strtolower( str_replace( '_', '-', substr( $short, 0, -strlen( '_Interface' ) ) ) );
			$filename = 'interface-' . $slug . '.php';
		} else {
			$slug     = strtolower( str_replace( '_', '-', $short ) );
			$filename = 'class-' . $slug . '.php';
		}

		$path = self::$base_dir . '/' . $dir . '/' . $filename;

		return $path;
	}
}
