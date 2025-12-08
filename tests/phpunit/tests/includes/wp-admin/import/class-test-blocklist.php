<?php
/**
 * Test Blocklist import class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Import;

use Activitypub\WP_Admin\Import\Blocklist;

/**
 * Test Blocklist import class.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Import\Blocklist
 */
class Test_Blocklist extends \WP_UnitTestCase {

	/**
	 * Temporary files created during tests.
	 *
	 * @var array
	 */
	private $temp_files = array();

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		// Clean up temporary files.
		foreach ( $this->temp_files as $file ) {
			if ( \file_exists( $file ) ) {
				\wp_delete_file( $file );
			}
		}
		$this->temp_files = array();

		parent::tear_down();
	}

	/**
	 * Create a temporary CSV file with given content.
	 *
	 * @param string $content The file content.
	 * @return string The path to the temporary file.
	 */
	private function create_temp_csv( $content ) {
		$file = \wp_tempnam( 'blocklist-test-' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		\file_put_contents( $file, $content );
		$this->temp_files[] = $file;

		return $file;
	}

	/**
	 * Test parsing Mastodon CSV format with #domain header.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_mastodon_format() {
		$csv_content  = "#domain,#severity,#public_comment,#private_comment\n";
		$csv_content .= "example.com,suspend,\"Spam\",\"\"\n";
		$csv_content .= "bad.org,silence,\"Abuse\",\"Internal note\"\n";
		$csv_content .= "spam.net,suspend,\"\",\"\"\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 3, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
		$this->assertContains( 'spam.net', $domains );
	}

	/**
	 * Test parsing Mastodon CSV format with domain column not in first position.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_mastodon_format_domain_not_first() {
		$csv_content  = "#severity,#domain,#public_comment\n";
		$csv_content .= "suspend,example.com,\"Spam\"\n";
		$csv_content .= "silence,bad.org,\"Abuse\"\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
	}

	/**
	 * Test parsing simple domain-per-line format.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_simple_format() {
		$csv_content  = "example.com\n";
		$csv_content .= "bad.org\n";
		$csv_content .= "spam.net\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 3, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
		$this->assertContains( 'spam.net', $domains );
	}

	/**
	 * Test parsing CSV with 'domain' header (without #).
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_domain_header_without_hash() {
		$csv_content  = "domain,comment\n";
		$csv_content .= "example.com,\"Test domain\"\n";
		$csv_content .= "bad.org,\"Another domain\"\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
	}

	/**
	 * Test that duplicate domains are removed.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_removes_duplicates() {
		$csv_content  = "example.com\n";
		$csv_content .= "bad.org\n";
		$csv_content .= "example.com\n";
		$csv_content .= "Example.Com\n";  // Should be treated as duplicate (case-insensitive).

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
	}

	/**
	 * Test that domains are normalized to lowercase.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_normalizes_lowercase() {
		$csv_content  = "Example.COM\n";
		$csv_content .= "BAD.org\n";
		$csv_content .= "Spam.NET\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 3, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
		$this->assertContains( 'spam.net', $domains );
		$this->assertNotContains( 'Example.COM', $domains );
	}

	/**
	 * Test that comment lines are skipped.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_skips_comments() {
		$csv_content  = "# This is a comment\n";
		$csv_content .= "example.com\n";
		$csv_content .= "# Another comment\n";
		$csv_content .= "bad.org\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
	}

	/**
	 * Test that empty lines are skipped.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_skips_empty_lines() {
		$csv_content  = "example.com\n";
		$csv_content .= "\n";
		$csv_content .= "   \n";
		$csv_content .= "bad.org\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
	}

	/**
	 * Test that invalid domains are skipped.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_skips_invalid_domains() {
		$csv_content  = "example.com\n";
		$csv_content .= "notadomain\n";         // No dot.
		$csv_content .= "invalid domain.com\n"; // Space.
		$csv_content .= "bad.org\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
		$this->assertNotContains( 'notadomain', $domains );
	}

	/**
	 * Test parsing empty file.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_empty_file() {
		$file    = $this->create_temp_csv( '' );
		$domains = Blocklist::parse_csv( $file );

		$this->assertEmpty( $domains );
	}

	/**
	 * Test parsing non-existent file.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_nonexistent_file() {
		$domains = Blocklist::parse_csv( '/nonexistent/path/to/file.csv' );

		$this->assertEmpty( $domains );
	}

	/**
	 * Test parsing file with only header.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_only_header() {
		$csv_content = "#domain,#severity,#public_comment\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertEmpty( $domains );
	}

	/**
	 * Test parsing file with whitespace around domains.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_trims_whitespace() {
		$csv_content  = "  example.com  \n";
		$csv_content .= "	bad.org	\n";
		$csv_content .= " spam.net\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 3, $domains );
		$this->assertContains( 'example.com', $domains );
		$this->assertContains( 'bad.org', $domains );
		$this->assertContains( 'spam.net', $domains );
	}

	/**
	 * Test parsing subdomain.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_with_subdomains() {
		$csv_content  = "sub.example.com\n";
		$csv_content .= "deep.sub.example.org\n";
		$csv_content .= "www.test.net\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 3, $domains );
		$this->assertContains( 'sub.example.com', $domains );
		$this->assertContains( 'deep.sub.example.org', $domains );
		$this->assertContains( 'www.test.net', $domains );
	}

	/**
	 * Test parsing large file with many domains.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_large_file() {
		$csv_content = "#domain\n";
		for ( $i = 0; $i < 1000; $i++ ) {
			$csv_content .= "domain{$i}.example.com\n";
		}

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 1000, $domains );
		$this->assertContains( 'domain0.example.com', $domains );
		$this->assertContains( 'domain999.example.com', $domains );
	}

	/**
	 * Test parsing domains with hyphens.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_domains_with_hyphens() {
		$csv_content  = "my-example.com\n";
		$csv_content .= "another-test-domain.org\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 2, $domains );
		$this->assertContains( 'my-example.com', $domains );
		$this->assertContains( 'another-test-domain.org', $domains );
	}

	/**
	 * Test that domain starting with hyphen is rejected.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_rejects_domain_starting_with_hyphen() {
		$csv_content  = "-invalid.com\n";
		$csv_content .= "valid.com\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertCount( 1, $domains );
		$this->assertContains( 'valid.com', $domains );
		$this->assertNotContains( '-invalid.com', $domains );
	}

	/**
	 * Test that email-like identifiers are skipped gracefully.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_skips_email_like_identifiers() {
		$csv_content  = "user@example.com\n";
		$csv_content .= "admin@bad.org\n";
		$csv_content .= "valid.org\n";
		$csv_content .= "@invalid.net\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertSame( array( 'valid.org' ), $domains );
	}

	/**
	 * Test that email-like identifiers in Mastodon CSV format are skipped gracefully.
	 *
	 * @covers ::parse_csv
	 */
	public function test_parse_csv_mastodon_format_skips_email_like_identifiers() {
		$csv_content  = "#domain,#severity,#public_comment\n";
		$csv_content .= "user@example.com,suspend,\"Test\"\n";
		$csv_content .= "valid.org,silence,\"Test\"\n";
		$csv_content .= "admin@bad.org,suspend,\"Test\"\n";

		$file    = $this->create_temp_csv( $csv_content );
		$domains = Blocklist::parse_csv( $file );

		$this->assertSame( array( 'valid.org' ), $domains );
	}
}
