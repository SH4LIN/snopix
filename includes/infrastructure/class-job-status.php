<?php
/**
 * Canonical status values for background jobs (indexing, duplicate scan).
 *
 * @package Snopix
 */

namespace Snopix\Infrastructure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Named constants for the progress envelope's `status` field.
 *
 * Plain `const class` (not a PHP 8.1 enum) so the codebase keeps its PHP 8.0
 * floor (see composer.json `"php": ">=8.0"`). The string values are part of
 * the REST contract — the admin app's TypeScript discriminated unions match
 * these exact tokens, and persisted transients contain them — so DO NOT
 * rename them without bumping the plugin DB version and migrating.
 */
final class Job_Status {

	/**
	 * No job running. Default when the progress transient is missing.
	 */
	public const IDLE = 'idle';

	/**
	 * A batch chain is in flight.
	 */
	public const RUNNING = 'running';

	/**
	 * The chain aborted because an entire batch failed or an unhandled
	 * exception broke the loop. Requires an explicit reset to recover.
	 */
	public const STALLED = 'stalled';

	/**
	 * All work completed normally.
	 */
	public const DONE = 'done';

	/**
	 * Whether the given status represents an in-flight or stuck job — i.e.
	 * something the user must explicitly reset before scheduling a new run.
	 *
	 * @param string $status Status string from a progress envelope.
	 *
	 * @return bool
	 */
	public static function is_active( string $status ): bool {
		return self::RUNNING === $status || self::STALLED === $status;
	}
}
