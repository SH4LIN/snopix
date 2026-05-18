<?php
/**
 * Download 100 fixture images from Picsum Photos for integration tests.
 *
 * Usage: composer fixtures
 *        php tests/bin/download-fixtures.php
 *
 * Images are saved to tests/fixtures/images/{001..100}.jpg
 * Already-downloaded images are skipped.
 *
 * @package Pixel_Scout
 */

$dest_dir = dirname( __DIR__ ) . '/fixtures/images';

if ( ! is_dir( $dest_dir ) ) {
	mkdir( $dest_dir, 0755, true );
}

$downloaded = 0;
$skipped    = 0;
$failed     = 0;

for ( $i = 1; $i <= 100; $i++ ) {
	$filename = sprintf( '%s/%03d.jpg', $dest_dir, $i );

	if ( file_exists( $filename ) ) {
		++$skipped;
		continue;
	}

	$url  = sprintf( 'https://picsum.photos/id/%d/400/300', $i );
	$data = @file_get_contents( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if ( false === $data || strlen( $data ) < 1024 ) {
		echo "FAIL  #{$i} — skipping\n";
		++$failed;
		continue;
	}

	file_put_contents( $filename, $data );
	echo "OK    #{$i} → " . basename( $filename ) . "\n";
	++$downloaded;

	// Be polite to the API.
	usleep( 50000 );
}

echo "\nDone. Downloaded: {$downloaded}  Skipped: {$skipped}  Failed: {$failed}\n";
echo "Images saved to: {$dest_dir}\n";
