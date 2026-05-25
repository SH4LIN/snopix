/**
 * Playwright end-to-end test for the full indexing pipeline.
 *
 * Uploads the Picsum fixture set via the REST media endpoint, runs the bulk
 * indexer (via WP-CLI cron triggers), verifies the per-image phash payload
 * lands in the index table, and finally exercises `/wp-json/snopix/v1/search` to
 * confirm the round-trip indexer → search behaviour on a real database.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { FIXTURES, runCron, wpLogin, getRestNonce } from './helpers';

test.describe( 'Snopix — image indexing and search', () => {
	test.setTimeout( 300_000 );

	test( 'uploads fixture images, bulk-indexes, then finds similar images', async ( { page } ) => {
		// 1. Verify fixtures.
		const files = fs.readdirSync( FIXTURES )
			.filter( f => f.endsWith( '.jpg' ) )
			.sort();

		expect(
			files.length,
			`Need fixture images in ${ FIXTURES }. Run: php tests/bin/download-fixtures.php`
		).toBeGreaterThanOrEqual( 20 );

		// 2. Login + get REST nonce.
		await wpLogin( page );
		const nonce = await getRestNonce( page );
		expect( nonce, 'wpApiSettings.nonce missing from wp-admin page' ).toBeTruthy();

		// 3. Clear existing index so counts are predictable.
		await page.request.post( '/wp-json/snopix/v1/tools/clear-index', {
			headers: { 'X-WP-Nonce': nonce },
		} );

		// 4. Upload images to WP media library via REST API.
		const uploadedIds: number[] = [];
		for ( const filename of files ) {
			const buffer = fs.readFileSync( path.join( FIXTURES, filename ) );
			const res    = await page.request.post( '/wp-json/wp/v2/media', {
				headers: {
					'X-WP-Nonce':          nonce,
					'Content-Disposition': `attachment; filename="${ filename }"`,
					'Content-Type':        'image/jpeg',
				},
				data: buffer,
			} );
			if ( res.status() === 201 ) {
				uploadedIds.push( ( await res.json() ).id );
			}
		}

		console.log( `Uploaded ${ uploadedIds.length } / ${ files.length } images` );
		expect( uploadedIds.length ).toBeGreaterThanOrEqual( 20 );

		// 5. Schedule bulk reindex.
		const scheduleRes = await page.request.post( '/wp-json/snopix/v1/tools/reindex-all', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( scheduleRes.status() ).toBe( 200 );

		// 6. Force WP-Cron batches via WP-CLI (bypasses scheduled delay).
		const batches = Math.ceil( uploadedIds.length / 50 );
		for ( let i = 0; i < batches; i++ ) {
			runCron( 'cron', 'event', 'run', 'snopix_bulk_index_batch' );
			await page.waitForTimeout( 1_000 );
		}

		// 7. Poll /progress until all images indexed.
		await expect.poll(
			async () => {
				runCron( 'cron', 'event', 'run', '--due-now' );
				const res  = await page.request.get( '/wp-json/snopix/v1/progress', {
					headers: { 'X-WP-Nonce': nonce },
				} );
				const data = await res.json();
				console.log( `Indexing progress: ${ data.done } / ${ data.total }` );
				return ( data.done as number ) >= uploadedIds.length;
			},
			{ timeout: 120_000, intervals: [ 3_000 ] }
		).toBe( true );

		// 8. Confirm /status reflects indexed count.
		const statusRes = await page.request.get( '/wp-json/snopix/v1/status', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		const status = await statusRes.json();
		expect( status.indexed ).toBeGreaterThanOrEqual( uploadedIds.length );

		// 9. Search with the first fixture image.
		const queryBuffer = fs.readFileSync( path.join( FIXTURES, files[ 0 ] ) );
		const searchRes   = await page.request.post( '/wp-json/snopix/v1/search', {
			multipart: {
				file: {
					name:     'query.jpg',
					mimeType: 'image/jpeg',
					buffer:   queryBuffer,
				},
			},
		} );

		expect( searchRes.status() ).toBe( 200 );
		const results: any[] = await searchRes.json();

		expect( Array.isArray( results ) ).toBe( true );
		expect( results.length ).toBeGreaterThan( 0 );
		expect( results[ 0 ] ).toHaveProperty( 'id' );
		expect( results[ 0 ] ).toHaveProperty( 'score' );
		expect( results[ 0 ].score ).toBeGreaterThan( 0 );

		console.log( `Search: ${ results.length } results, top score: ${ results[ 0 ].score }` );
	} );
} );
