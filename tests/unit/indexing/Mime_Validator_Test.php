<?php
/**
 * Unit tests for Snopix\Indexing\Mime_Validator.
 *
 * @package Snopix
 */

use Snopix\Indexing\Mime_Validator;

/**
 * @covers \Snopix\Indexing\Mime_Validator
 */
final class Mime_Validator_Test extends Snopix_Unit_TestCase {

	private Mime_Validator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new Mime_Validator();
	}

	public function test_is_allowed_true_for_every_allowed_type(): void {
		$this->assertTrue( $this->validator->is_allowed( 'image/jpeg' ) );
		$this->assertTrue( $this->validator->is_allowed( 'image/png' ) );
		$this->assertTrue( $this->validator->is_allowed( 'image/gif' ) );
		$this->assertTrue( $this->validator->is_allowed( 'image/webp' ) );
		$this->assertTrue( $this->validator->is_allowed( 'image/bmp' ) );
	}

	public function test_is_allowed_false_for_disallowed_types(): void {
		$this->assertFalse( $this->validator->is_allowed( 'image/tiff' ) );
		$this->assertFalse( $this->validator->is_allowed( 'image/svg+xml' ) );
		$this->assertFalse( $this->validator->is_allowed( 'application/pdf' ) );
		$this->assertFalse( $this->validator->is_allowed( 'application/octet-stream' ) );
		$this->assertFalse( $this->validator->is_allowed( 'text/plain' ) );
		$this->assertFalse( $this->validator->is_allowed( '' ) );
	}

	public function test_is_allowed_is_case_sensitive_and_strict(): void {
		// in_array strict comparison: casing must match exactly.
		$this->assertFalse( $this->validator->is_allowed( 'IMAGE/JPEG' ) );
		$this->assertFalse( $this->validator->is_allowed( 'image/jpg' ) );
	}

	public function test_get_allowed_returns_exact_allowed_set(): void {
		$expected = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/bmp',
		);
		$this->assertSame( $expected, $this->validator->get_allowed() );
	}

	public function test_get_allowed_matches_is_allowed(): void {
		foreach ( $this->validator->get_allowed() as $mime ) {
			$this->assertTrue( $this->validator->is_allowed( $mime ) );
		}
	}
}
