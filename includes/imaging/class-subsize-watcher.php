<?php
/**
 * Snapshot + diff of registered image subsizes.
 *
 * @package Pixel_Scout
 */

namespace PixelScout\Imaging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects changes in the WordPress image-subsize registration between snapshots.
 *
 * Snapshot is stored in the `ps_subsizes_snapshot` option (autoload=false) as
 * an associative array of size-name => [w,h,crop]. The first diff() call seeds
 * the option so fresh installs don't flag every size as "new".
 */
class Subsize_Watcher {

	/**
	 * Option key for the persisted snapshot.
	 */
	public const OPTION_KEY = 'ps_subsizes_snapshot';

	/**
	 * Return the current vs. snapshot diff. Seeds the snapshot on first call.
	 *
	 * @return array{new: array<string>, removed: array<string>, changed: array<int, array{name:string, old:array{w:int,h:int,crop:bool}, new:array{w:int,h:int,crop:bool}}>, has_changes: bool}
	 */
	public function diff(): array {
		$current  = $this->current_sizes();
		$snapshot = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $snapshot ) ) {
			update_option( self::OPTION_KEY, $current, false );
			return array(
				'new'         => array(),
				'removed'     => array(),
				'changed'     => array(),
				'has_changes' => false,
			);
		}

		$new     = array_values( array_diff( array_keys( $current ), array_keys( $snapshot ) ) );
		$removed = array_values( array_diff( array_keys( $snapshot ), array_keys( $current ) ) );
		$changed = array();
		$shared  = array_intersect( array_keys( $current ), array_keys( $snapshot ) );

		foreach ( $shared as $name ) {
			if ( $current[ $name ] !== $snapshot[ $name ] ) {
				$changed[] = array(
					'name' => $name,
					'old'  => $snapshot[ $name ],
					'new'  => $current[ $name ],
				);
			}
		}

		return array(
			'new'         => $new,
			'removed'     => $removed,
			'changed'     => $changed,
			'has_changes' => ! empty( $new ) || ! empty( $removed ) || ! empty( $changed ),
		);
	}

	/**
	 * Persist the current registered sizes as the acknowledged snapshot.
	 *
	 * @return bool True on success.
	 */
	public function acknowledge(): bool {
		return (bool) update_option( self::OPTION_KEY, $this->current_sizes(), false );
	}

	/**
	 * Fetch registered subsizes, filtered to non-zero dims and normalized to
	 * a stable key-sorted shape so equality checks are deterministic.
	 *
	 * @return array<string, array{w:int,h:int,crop:bool}>
	 */
	private function current_sizes(): array {
		$out = array();
		foreach ( wp_get_registered_image_subsizes() as $name => $cfg ) {
			$w    = isset( $cfg['width'] ) ? (int) $cfg['width'] : 0;
			$h    = isset( $cfg['height'] ) ? (int) $cfg['height'] : 0;
			$crop = ! empty( $cfg['crop'] );
			if ( $w <= 0 || $h <= 0 ) {
				continue;
			}
			$out[ (string) $name ] = array(
				'w'    => $w,
				'h'    => $h,
				'crop' => $crop,
			);
		}
		ksort( $out );
		return $out;
	}
}
