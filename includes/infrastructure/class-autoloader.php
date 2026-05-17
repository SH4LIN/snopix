<?php
/**
 * Plugin autoloader for WordPress-style class naming.
 *
 * Resolves PixelScout\* classes by converting the namespaced class name to a
 * filesystem path under includes/. No manual class map needed.
 *
 * Conventions:
 *   PixelScout\Indexing\Image_Indexer		   → includes/indexing/class-image-indexer.php
 *   PixelScout\Repository\Index_Repository_Interface → includes/repository/interface-index-repository.php
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Autoloader {

	private static string $base_dir = '';

	public static function init( string $base_directory ): void {
		self::$base_dir = rtrim( $base_directory, '/' );
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'PixelScout\\' ) ) {
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
	 * @return string|null Absolute path or null if not resolvable.
	 */
	private static function locate( string $class_name ): ?string {
		$relative = substr( $class_name, strlen( 'PixelScout\\' ) );
		$parts	= explode( '\\', $relative );
		$short	= array_pop( $parts );
		$dir	  = strtolower( implode( '/', $parts ) );

		if ( str_ends_with( $short, '_Interface' ) ) {
			$slug	 = strtolower( str_replace( '_', '-', substr( $short, 0, -strlen( '_Interface' ) ) ) );
			$filename = 'interface-' . $slug . '.php';
		} else {
			$slug	 = strtolower( str_replace( '_', '-', $short ) );
			$filename = 'class-' . $slug . '.php';
		}

		$path = self::$base_dir . '/' . $dir . '/' . $filename;

		return $path;
	}
}
