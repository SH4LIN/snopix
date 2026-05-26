<?php
/**
 * Per-user dismissal state for feature notifications.
 *
 * @package Snopix
 */

namespace Snopix\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes per-user feature-notification dismissal state.
 *
 * Dismissal is stored in `user_meta` (key {@see self::META_KEY}) as a flat
 * array of notification IDs. Each user maintains their own list, which matches
 * the WordPress core convention for dashboard widget visibility and welcome
 * panels and ensures the row is removed automatically when a user is deleted.
 *
 * The store deliberately does not validate inbound IDs against the registry —
 * the REST layer is responsible for that — so this class stays trivially
 * cheap and testable without booting the full notification subsystem.
 */
final class Feature_Notification_Store {

	/**
	 * user_meta key holding the dismissal list for a given user.
	 */
	public const META_KEY = 'snopix_dismissed_notifications';

	/**
	 * Check whether the given notification has been dismissed by the given user.
	 *
	 * @param int    $user_id         Target user ID.
	 * @param string $notification_id Notification identifier.
	 *
	 * @return bool
	 */
	public function is_dismissed( int $user_id, string $notification_id ): bool {
		if ( $user_id <= 0 || '' === $notification_id ) {
			return false;
		}
		return in_array( $notification_id, $this->dismissed_ids( $user_id ), true );
	}

	/**
	 * Mark a notification as dismissed for the given user.
	 *
	 * Idempotent: dismissing the same ID twice does not duplicate the entry.
	 *
	 * @param int    $user_id         Target user ID.
	 * @param string $notification_id Notification identifier.
	 *
	 * @return void
	 */
	public function dismiss( int $user_id, string $notification_id ): void {
		if ( $user_id <= 0 || '' === $notification_id ) {
			return;
		}

		$ids = $this->dismissed_ids( $user_id );
		if ( in_array( $notification_id, $ids, true ) ) {
			return;
		}

		$ids[] = $notification_id;
		update_user_meta( $user_id, self::META_KEY, array_values( $ids ) );
	}

	/**
	 * Remove a notification from the dismissal list, restoring its visibility
	 * for the given user. Reserved for future "show again" UX; not wired today.
	 *
	 * @param int    $user_id         Target user ID.
	 * @param string $notification_id Notification identifier.
	 *
	 * @return void
	 */
	public function restore( int $user_id, string $notification_id ): void {
		if ( $user_id <= 0 || '' === $notification_id ) {
			return;
		}

		$ids   = $this->dismissed_ids( $user_id );
		$index = array_search( $notification_id, $ids, true );
		if ( false === $index ) {
			return;
		}

		unset( $ids[ $index ] );
		update_user_meta( $user_id, self::META_KEY, array_values( $ids ) );
	}

	/**
	 * Bulk-dismiss every currently registered notification for the given user.
	 *
	 * Merges with the existing dismissal list so historical IDs that have
	 * since been removed from the registry remain marked dismissed (avoids
	 * surprise re-appearance if a notification gets re-introduced).
	 *
	 * @param int $user_id Target user ID.
	 *
	 * @return int Count of newly-dismissed notification IDs.
	 */
	public function dismiss_all( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		$existing       = $this->dismissed_ids( $user_id );
		$registered_ids = array_keys( Feature_Notification_Registry::all() );

		$merged = array_values( array_unique( array_merge( $existing, $registered_ids ) ) );
		$added  = count( $merged ) - count( $existing );

		if ( $added > 0 ) {
			update_user_meta( $user_id, self::META_KEY, $merged );
		}

		return $added;
	}

	/**
	 * Return the list of registered notifications that the given user has NOT
	 * dismissed, in registry order.
	 *
	 * @param int $user_id Target user ID.
	 *
	 * @return array<int, Feature_Notification>
	 */
	public function active_for_user( int $user_id ): array {
		$registered = Feature_Notification_Registry::all();
		$dismissed  = $user_id > 0 ? $this->dismissed_ids( $user_id ) : array();

		$active = array();
		foreach ( $registered as $notification ) {
			if ( in_array( $notification->id, $dismissed, true ) ) {
				continue;
			}
			$active[] = $notification;
		}
		return $active;
	}

	/**
	 * Raw dismissed-ID list for the given user. Coerces malformed meta values
	 * (legacy data, manually-edited rows) to an empty array.
	 *
	 * @param int $user_id Target user ID.
	 *
	 * @return array<int, string>
	 */
	private function dismissed_ids( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_filter( $raw, 'is_string' ) );
	}
}
