<?php
/**
 * Extract the committed fixture archive (images.zip) into tests/fixtures/images.
 *
 * The archive is the committed source of truth; the extracted tree is
 * gitignored and rebuilt here on first test run (and whenever the sentinel
 * files are missing). Required by both the unit and integration bootstraps
 * before any test touches a fixture path.
 *
 * @package Snopix
 */

if ( ! file_exists( __DIR__ . '/images/001.jpg' ) || ! file_exists( __DIR__ . '/images/variations/001.png' ) ) {
	$snopix_fixture_zip = new ZipArchive();
	if ( true !== $snopix_fixture_zip->open( __DIR__ . '/images.zip' ) ) {
		fwrite( STDERR, 'Cannot open tests/fixtures/images.zip - fixture archive missing or corrupt.' . PHP_EOL );
		exit( 1 );
	}
	if ( ! $snopix_fixture_zip->extractTo( __DIR__ ) ) {
		fwrite( STDERR, 'Failed to extract tests/fixtures/images.zip.' . PHP_EOL );
		exit( 1 );
	}
	$snopix_fixture_zip->close();
	unset( $snopix_fixture_zip );
}
