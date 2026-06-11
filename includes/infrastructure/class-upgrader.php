<?php
/**
 * Plugin upgrade orchestration - schema migrations and index rebuilds.
 *
 * @package Snopix
 */

namespace Snopix\Infrastructure;

use Snopix\Repository\{Index_Repository, Schema};
use Snopix\Imaging\{GD_Loader, PHash_Processor, Color_Processor, Edge_Processor};
use Snopix\Search\Fingerprint_Factory;
use Snopix\Indexing\{Mime_Validator, Index_Progress, Image_Indexer, Bulk_Indexer};

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects DB version changes and runs the upgrade work that follows:
 * schema migration plus a full index rebuild when fingerprint formats
 * changed between versions.
 */
class Upgrader {

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Schema installer/migrator.
	 */
	public function __construct( private Schema $schema ) {}

	/**
	 * Per-request upgrade check (hooked on plugins_loaded).
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$installed_version = get_option( SNOPIX_OPTION_DB_VERSION, '' );
		$this->schema->maybe_upgrade();
		$this->maybe_reindex_after_upgrade( $installed_version );
	}

	/**
	 * Activation path: always (re)install the schema, then rebuild the index
	 * if activation followed a manual update (plugin replaced while
	 * deactivated, so the plugins_loaded check never saw the old version).
	 *
	 * @return void
	 */
	public function install(): void {
		$installed_version = get_option( SNOPIX_OPTION_DB_VERSION, '' );
		$this->schema->install();
		$this->maybe_reindex_after_upgrade( $installed_version );
	}

	/**
	 * Rebuild the index when upgrading from an earlier DB version.
	 *
	 * Fingerprint formats change between versions (0.2.0 moved to HSV colour
	 * histograms, orientation-histogram edge vectors, and an AC-block pHash);
	 * vectors written by an older version are incompatible with the new
	 * scorers, so the whole index is wiped and re-queued. Fresh installs
	 * (no stored version) have nothing to rebuild. If a bulk job is already
	 * running, schedule_all() declines; rerun Reindex All from Tools then.
	 *
	 * @param string $installed_version DB version stored before the upgrade ran.
	 *
	 * @return void
	 */
	private function maybe_reindex_after_upgrade( string $installed_version ): void {
		if ( '' === $installed_version || SNOPIX_DB_VERSION === $installed_version ) {
			return;
		}

		global $wpdb;
		$repository   = new Index_Repository( $wpdb );
		$factory      = new Fingerprint_Factory(
			new GD_Loader(),
			new PHash_Processor(),
			new Color_Processor(),
			new Edge_Processor()
		);
		$indexer      = new Image_Indexer( new Mime_Validator(), $factory, $repository );
		$bulk_indexer = new Bulk_Indexer( $repository, $indexer, new Index_Progress(), new Action_Scheduler() );
		$bulk_indexer->schedule_all();
	}
}
