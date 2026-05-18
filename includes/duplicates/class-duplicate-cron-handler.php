<?php
/**
 * WordPress cron handler for duplicate scan.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Duplicates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WP action that fires the duplicate scan batch.
 */
class Duplicate_Cron_Handler {

	/**
	 * Constructor.
	 *
	 * @param Duplicate_Scanner $scanner Duplicate scanner.
	 */
	public function __construct( private Duplicate_Scanner $scanner ) {}

	/**
	 * Register cron hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Duplicate_Scanner::CRON_HOOK, array( $this, 'run' ) );
	}

	/**
	 * Execute the duplicate scan.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->scanner->run();
	}
}
