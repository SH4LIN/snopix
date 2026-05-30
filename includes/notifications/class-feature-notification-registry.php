<?php
/**
 * Static registry of feature-notification definitions shipped with the plugin.
 *
 * @package Snopix
 */

namespace Snopix\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Source of truth for in-app feature announcements.
 *
 * Notifications are declared as plain PHP — adding a new "what's new" card
 * means appending one constructor call to {@see self::seed()}. Third-party
 * code can extend or replace the list via the `snopix_feature_notifications`
 * filter so addons can ship their own announcements without modifying the
 * plugin.
 */
final class Feature_Notification_Registry {

	/**
	 * Memoised registry payload. Filter only runs once per request.
	 *
	 * @var array<string, Feature_Notification>|null
	 */
	private static ?array $cache = null;

	/**
	 * Return every notification currently registered, keyed by notification ID.
	 *
	 * Identifier collisions resolve to last-write-wins after the filter runs —
	 * addon authors who want to override a default notification simply
	 * re-register the same ID with new payload.
	 *
	 * @return array<string, Feature_Notification>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$notifications = self::seed();

		/**
		 * Filter the registered feature notifications.
		 *
		 * @param array<int, Feature_Notification> $notifications Default notification list.
		 */
		$filtered = apply_filters( 'snopix_feature_notifications', $notifications );

		$keyed = array();
		foreach ( $filtered as $notification ) {
			if ( $notification instanceof Feature_Notification ) {
				$keyed[ $notification->id ] = $notification;
			}
		}

		self::$cache = $keyed;
		return $keyed;
	}

	/**
	 * Look up a notification by ID, or null if it isn't registered.
	 *
	 * Used by the dismiss endpoint to validate that the inbound `{id}` maps
	 * to a real notification before writing to user_meta — prevents arbitrary
	 * keys polluting per-user dismissal storage.
	 *
	 * @param string $id Notification identifier.
	 *
	 * @return Feature_Notification|null
	 */
	public static function find( string $id ): ?Feature_Notification {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Reset the memoised cache.
	 *
	 * Primarily exists for tests + the rare case where a filter callback
	 * needs to be re-evaluated within the same request.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Built-in notifications shipped with the plugin.
	 *
	 * Keep this list short — only announce features that materially change
	 * what the user can do. Marketing copy belongs on the project site, not
	 * inside the admin app.
	 *
	 * @return array<int, Feature_Notification>
	 */
	private static function seed(): array {
		return array(
			new Feature_Notification(
				id: 'duplicates-launch',
				title: __( 'Find duplicate images in your media library', 'snopix' ),
				body: __(
					'Snopix can now scan your library for visually identical and near-duplicate images. Reclaim disk space and tidy up your media library in one click.',
					'snopix'
				),
				icon: 'layers',
				severity: 'info',
				since_version: '1.1.0',
				cta_label: __( 'Open Duplicates', 'snopix' ),
				cta_route: 'duplicates'
			),
		);
	}
}
