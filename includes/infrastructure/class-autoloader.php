<?php
/**
 * Plugin autoloader for WordPress-style class naming.
 *
 * Handles dynamic loading of classes following the pattern:
 * Pixel_Scout_Module_Class → includes/module/class-module-class.php
 *
 * @package Pixel_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader for Pixel Scout classes.
 */
class Pixel_Scout_Autoloader {
	/**
	 * Base directory for class files.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Class map for fast lookups.
	 *
	 * @var array<string, string>
	 */
	private static array $class_map = [];

	/**
	 * Initialize autoloader.
	 *
	 * @param string $base_directory Base directory for class files.
	 *
	 * @return void
	 */
	public static function init( string $base_directory ): void {
		self::$base_dir = rtrim( $base_directory, '/' );
		self::load_class_map();
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Load class by name.
	 *
	 * @param string $class_name Full class name.
	 *
	 * @return void
	 */
	public static function load( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'Pixel_Scout_' ) ) {
			return;
		}

		// Check class map first for fast lookups.
		if ( isset( self::$class_map[ $class_name ] ) ) {
			$file = self::$class_map[ $class_name ];
			if ( file_exists( $file ) ) {
				require_once $file;
			}
			return;
		}

		// Build file path from class name.
		$file = self::resolve_class_file( $class_name );

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolve file path from class name.
	 *
	 * Example: Pixel_Scout_Index_Repository → includes/repository/class-index-repository.php
	 *
	 * @param string $class_name Class name.
	 *
	 * @return string
	 */
	private static function resolve_class_file( string $class_name ): string {
		// Strip prefix.
		$relative = substr( $class_name, strlen( 'Pixel_Scout_' ) );

		// Convert underscores to hyphens and lowercase.
		$relative = strtolower( str_replace( '_', '-', $relative ) );

		// Split into parts.
		$parts = explode( '-', $relative );

		// Last part is the class name, preceding parts form directory structure.
		$class_part = array_pop( $parts );
		$dir_part   = implode( '/', $parts );

		// Build file path.
		if ( ! empty( $dir_part ) ) {
			$file = self::$base_dir . '/' . $dir_part . '/class-' . $class_part . '.php';
		} else {
			$file = self::$base_dir . '/class-' . $class_part . '.php';
		}

		return $file;
	}

	/**
	 * Load predefined class map for critical classes.
	 * Useful for bootstrap and schema classes that are needed early.
	 *
	 * @return void
	 */
	private static function load_class_map(): void {
		self::$class_map = [
			'Pixel_Scout_Plugin'                      => self::$base_dir . '/infrastructure/class-plugin.php',
			'Pixel_Scout_Query'                       => self::$base_dir . '/infrastructure/class-query.php',
			'Pixel_Scout_Schema'                      => self::$base_dir . '/repository/class-schema.php',
			'Pixel_Scout_Index_Repository_Interface' => self::$base_dir . '/repository/interface-repository.php',
			'Pixel_Scout_Index_Repository'            => self::$base_dir . '/repository/class-index-repository.php',
		];
	}
}

