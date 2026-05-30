<?php
/**
 * Immutable value object describing a single in-app feature notification.
 *
 * @package Snopix
 */

namespace Snopix\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature notification payload.
 *
 * Carries everything the admin UI needs to render a dismissible product
 * announcement (title/body/CTA/icon/severity) along with the metadata the
 * registry needs to target it (since_version). Instances are
 * created by {@see Feature_Notification_Registry} and consumed by
 * {@see \Snopix\Api\Notifications_REST_Controller}.
 */
final class Feature_Notification {

	/**
	 * Allowed severity tokens. Anything else falls back to 'info'.
	 *
	 * @var array<int, string>
	 */
	private const SEVERITIES = array( 'info', 'success', 'warning' );

	/**
	 * Constructor.
	 *
	 * @param string             $id            Stable identifier — used as the dismissal key.
	 *                                          Must be ASCII slug-shaped (a-z, 0-9, dash).
	 * @param string             $title         Short headline rendered as the card title.
	 * @param string             $body          Plain-text body. Rendered as text, not HTML.
	 * @param string             $icon          Icon slug recognised by the React icon registry.
	 * @param string             $severity      One of: info|success|warning.
	 * @param string             $since_version Plugin version that introduced this notification.
	 *                                          Reserved for "what's new" analytics; not user-facing.
	 * @param string             $cta_label     Optional CTA label. Empty string disables CTA.
	 * @param string             $cta_route     Optional in-app route slug ('duplicates', 'settings', …).
	 * @param string             $cta_url       Optional absolute URL. Only honoured if route is empty.
	 */
	public function __construct(
		public string $id,
		public string $title,
		public string $body,
		public string $icon = 'info',
		public string $severity = 'info',
		public string $since_version = '',
		public string $cta_label = '',
		public string $cta_route = '',
		public string $cta_url = ''
	) {}

	/**
	 * Serialise to the wire shape consumed by the React `useNotices` hook.
	 *
	 * Severity is normalised against the allowlist so a typo in the registry
	 * never leaks an unstyled banner into the UI.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$severity = in_array( $this->severity, self::SEVERITIES, true )
			? $this->severity
			: 'info';

		return array(
			'id'            => $this->id,
			'title'         => $this->title,
			'body'          => $this->body,
			'icon'          => $this->icon,
			'severity'      => $severity,
			'since_version' => $this->since_version,
			'cta_label'     => $this->cta_label,
			'cta_route'     => $this->cta_route,
			'cta_url'       => '' === $this->cta_route ? esc_url_raw( $this->cta_url ) : '',
		);
	}
}
