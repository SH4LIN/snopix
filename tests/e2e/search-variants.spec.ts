/**
 * Playwright end-to-end regression tests for the reverse-image-search algo.
 *
 * Uploads a single fixture image, runs bulk indexing, then submits *variants*
 * of that image (downscaled, upscaled, blurred, re-encoded) via the REST
 * `/search` endpoint. Each variant must still return the original as the top
 * match with a high score. Any change to the perceptual algo that breaks this
 * fails the suite at the HTTP boundary.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { FIXTURES, runCron, wpLogin, getRestNonce } from './helpers';

const FIXTURE_ID = 1;
const FIXTURE_FILE = `${ String( FIXTURE_ID ).padStart( 3, '0' ) }.jpg`;

test.describe( 'Snopix — reverse-search variant robustness', () => {
	test.setTimeout( 300_000 );

	test.beforeAll( async () => {
		expect(
			fs.existsSync( path.join( FIXTURES, FIXTURE_FILE ) ),
			`Fixture ${ FIXTURE_FILE } missing. Run: php tests/bin/download-fixtures.php`,
		).toBe( true );
	} );

	/**
	 * Helper: upload the canonical fixture, run bulk indexing, return its
	 * uploaded attachment ID.
	 */
	async function uploadAndIndexFixture( page ): Promise<number> {
		await wpLogin( page );
		const nonce = await getRestNonce( page );

		await page.request.post( '/wp-json/snopix/v1/tools/clear-index', {
			headers: { 'X-WP-Nonce': nonce },
		} );

		const buffer = fs.readFileSync( path.join( FIXTURES, FIXTURE_FILE ) );
		const res    = await page.request.post( '/wp-json/wp/v2/media', {
			headers: {
				'X-WP-Nonce':          nonce,
				'Content-Disposition': `attachment; filename="${ FIXTURE_FILE }"`,
				'Content-Type':        'image/jpeg',
			},
			data: buffer,
		} );
		expect( res.status() ).toBe( 201 );
		const attachmentId = ( await res.json() ).id as number;

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

		return attachmentId;
	}

	/**
	 * Submit `queryBuffer` to /snopix/v1/search and assert the top result is
	 * `expectedId` with score above `minScore`.
	 */
	async function expectTopMatch(
		page,
		queryBuffer: Buffer,
		queryName: string,
		expectedId: number,
		minScore: number,
	) {
		const res = await page.request.post( '/wp-json/snopix/v1/search', {
			multipart: {
				file: {
					name:     queryName,
					mimeType: queryName.endsWith( '.png' )
						? 'image/png'
						: queryName.endsWith( '.webp' )
						? 'image/webp'
						: 'image/jpeg',
					buffer:   queryBuffer,
				},
			},
		} );
		expect( res.status() ).toBe( 200 );

		const results = await res.json() as Array<{ id: number; score: number }>;
		expect( results.length ).toBeGreaterThan( 0 );
		expect( results[ 0 ].id ).toBe( expectedId );
		expect( results[ 0 ].score ).toBeGreaterThanOrEqual( minScore );
	}

	test( 'identical query matches indexed original with high score', async ( { page } ) => {
		const indexedId = await uploadAndIndexFixture( page );
		const buffer    = fs.readFileSync( path.join( FIXTURES, FIXTURE_FILE ) );

		await expectTopMatch( page, buffer, 'query.jpg', indexedId, 0.95 );
	} );

	test( 'downscaled query matches indexed original', async ( { page } ) => {
		const indexedId = await uploadAndIndexFixture( page );

		// Use ImageMagick if available, else fall back to GD via PHP -r.
		const orig    = path.join( FIXTURES, FIXTURE_FILE );
		const variant = path.join( FIXTURES, '_downscale.jpg' );
		const { execFileSync } = require( 'child_process' );
		try {
			execFileSync( 'convert', [ orig, '-resize', '50%', variant ] );
		} catch {
			test.skip( true, 'ImageMagick "convert" not available — skipping variant test' );
		}

		const buffer = fs.readFileSync( variant );
		await expectTopMatch( page, buffer, 'downscale.jpg', indexedId, 0.85 );
		fs.unlinkSync( variant );
	} );

	test( 'blurred query matches indexed original', async ( { page } ) => {
		const indexedId = await uploadAndIndexFixture( page );

		const orig    = path.join( FIXTURES, FIXTURE_FILE );
		const variant = path.join( FIXTURES, '_blur.jpg' );
		const { execFileSync } = require( 'child_process' );
		try {
			execFileSync( 'convert', [ orig, '-blur', '0x5', variant ] );
		} catch {
			test.skip( true, 'ImageMagick "convert" not available — skipping variant test' );
		}

		const buffer = fs.readFileSync( variant );
		await expectTopMatch( page, buffer, 'blur.jpg', indexedId, 0.80 );
		fs.unlinkSync( variant );
	} );

	test( 'png re-encode matches indexed jpeg original', async ( { page } ) => {
		const indexedId = await uploadAndIndexFixture( page );

		const orig    = path.join( FIXTURES, FIXTURE_FILE );
		const variant = path.join( FIXTURES, '_reencode.png' );
		const { execFileSync } = require( 'child_process' );
		try {
			execFileSync( 'convert', [ orig, variant ] );
		} catch {
			test.skip( true, 'ImageMagick "convert" not available — skipping variant test' );
		}

		const buffer = fs.readFileSync( variant );
		await expectTopMatch( page, buffer, 'reencode.png', indexedId, 0.85 );
		fs.unlinkSync( variant );
	} );
} );
