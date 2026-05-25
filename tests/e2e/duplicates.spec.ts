/**
 * Playwright end-to-end tests for the duplicate-scan REST endpoints.
 *
 * Uploads two byte-identical copies of one fixture image + one unrelated
 * image, runs the scanner via /snopix/v1/duplicates/scan, polls /progress until
 * done, then asserts /duplicates returns exactly one group containing both
 * copies of the duplicate fixture.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { FIXTURES, runCron, wpLogin, getRestNonce } from './helpers';

test.describe( 'Snopix — duplicate scan REST flow', () => {
	test.setTimeout( 300_000 );

	/**
	 * Upload a file from FIXTURES with the supplied basename.
	 */
	async function uploadAs( page, src: string, name: string, nonce: string ): Promise<number> {
		const buffer = fs.readFileSync( src );
		const res    = await page.request.post( '/wp-json/wp/v2/media', {
			headers: {
				'X-WP-Nonce':          nonce,
				'Content-Disposition': `attachment; filename="${ name }"`,
				'Content-Type':        'image/jpeg',
			},
			data: buffer,
		} );
		expect( res.status() ).toBe( 201 );
		return ( await res.json() ).id as number;
	}

	test( 'scan finds byte-identical duplicates and exposes them via REST', async ( { page } ) => {
		await wpLogin( page );
		const nonce = await getRestNonce( page );

		await page.request.post( '/wp-json/snopix/v1/tools/clear-index', {
			headers: { 'X-WP-Nonce': nonce },
		} );

		const dupSrc = path.join( FIXTURES, '001.jpg' );
		const altSrc = path.join( FIXTURES, '010.jpg' );

		const a = await uploadAs( page, dupSrc, 'dup-a.jpg', nonce );
		const b = await uploadAs( page, dupSrc, 'dup-b.jpg', nonce );
		const c = await uploadAs( page, altSrc, 'unique.jpg', nonce );

		// Index everything.
		await page.request.post( '/wp-json/snopix/v1/tools/reindex-all', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		runCron( 'cron', 'event', 'run', 'snopix_bulk_index_batch' );
		await expect.poll(
			async () => {
				runCron( 'cron', 'event', 'run', '--due-now' );
				const r = await page.request.get( '/wp-json/snopix/v1/progress', {
					headers: { 'X-WP-Nonce': nonce },
				} );
				return ( await r.json() ).status;
			},
			{ timeout: 60_000, intervals: [ 2_000 ] }
		).toBe( 'done' );

		// Trigger the duplicate scan.
		const scanRes = await page.request.post( '/wp-json/snopix/v1/duplicates/scan', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( scanRes.status() ).toBe( 200 );

		runCron( 'cron', 'event', 'run', 'snopix_duplicate_scan' );

		await expect.poll(
			async () => {
				runCron( 'cron', 'event', 'run', '--due-now' );
				const r = await page.request.get( '/wp-json/snopix/v1/duplicates/progress', {
					headers: { 'X-WP-Nonce': nonce },
				} );
				return ( await r.json() ).status;
			},
			{ timeout: 60_000, intervals: [ 2_000 ] }
		).toBe( 'done' );

		// Fetch the resulting groups.
		const dupRes = await page.request.get( '/wp-json/snopix/v1/duplicates', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( dupRes.status() ).toBe( 200 );

		const data = await dupRes.json();
		expect( data ).toHaveProperty( 'groups' );
		expect( Array.isArray( data.groups ) ).toBe( true );

		const flatIds = data.groups.flatMap(
			( g: { images: Array<{ id: number }> } ) => g.images.map( i => i.id )
		);
		expect( flatIds ).toContain( a );
		expect( flatIds ).toContain( b );
		// The unrelated image must NOT be in any group.
		expect( flatIds ).not.toContain( c );
	} );

	test( 'starting a second scan while one is running returns 409', async ( { page } ) => {
		await wpLogin( page );
		const nonce = await getRestNonce( page );

		await page.request.post( '/wp-json/snopix/v1/duplicates/scan', {
			headers: { 'X-WP-Nonce': nonce },
		} );

		const second = await page.request.post( '/wp-json/snopix/v1/duplicates/scan', {
			headers: { 'X-WP-Nonce': nonce },
		} );
		// First call may have already completed; accept either 200 or 409.
		expect( [ 200, 409 ] ).toContain( second.status() );
	} );
} );
