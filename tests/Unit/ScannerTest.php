<?php
/**
 * Scanner unit tests (pure SQL construction; no DB required).
 *
 * @package MigrationUrlFixer
 */

namespace MUF\Tests\Unit;

use MUF\Scanner;
use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase {

	public function test_area_map_contains_core_tables(): void {
		$map = Scanner::area_map();
		foreach ( array( 'posts', 'postmeta', 'options', 'usermeta', 'comments', 'commentmeta', 'termmeta' ) as $area ) {
			$this->assertArrayHasKey( $area, $map );
			$this->assertNotEmpty( $map[ $area ]['columns'] );
			$this->assertNotEmpty( $map[ $area ]['table'] );
		}
		$this->assertContains( 'post_content', $map['posts']['blocks'] );
	}

	public function test_glob_to_like_translates_wildcard_and_escapes(): void {
		$this->assertSame( '\_transient\_%', Scanner::glob_to_like( '_transient_*' ) );
		$this->assertSame( '%\_session\_%', Scanner::glob_to_like( '*_session_*' ) );
	}

	public function test_build_where_excludes_post_types_for_posts_area(): void {
		$clause = Scanner::build_where( 'posts', array( 'exclude_post_types' => array( 'revision', 'nav_menu_item' ) ) );
		$this->assertStringContainsString( "post_type NOT IN ('revision','nav_menu_item')", $clause );
	}

	public function test_build_where_excludes_critical_options_by_default(): void {
		$clause = Scanner::build_where( 'options', array() );
		$this->assertStringContainsString( "option_name NOT LIKE 'siteurl'", $clause );
		$this->assertStringContainsString( "option_name NOT LIKE 'home'", $clause );
	}

	public function test_build_where_allow_critical_removes_protections(): void {
		$clause = Scanner::build_where( 'options', array( 'allow_critical' => true ) );
		$this->assertStringNotContainsString( 'siteurl', $clause );
		$this->assertStringNotContainsString( 'home', $clause );
	}

	public function test_build_where_custom_option_glob(): void {
		$clause = Scanner::build_where(
			'options',
			array(
				'allow_critical'  => true,
				'exclude_options' => array( '_transient_*' ),
			)
		);
		// wpdb::prepare() doubles the backslashes when interpolating %s — MySQL
		// decodes them back to single backslashes at query time.
		$this->assertStringContainsString( "option_name NOT LIKE '\\\\_transient\\\\_%'", $clause );
	}

	public function test_build_where_returns_empty_when_no_constraints(): void {
		$clause = Scanner::build_where( 'posts', array() );
		$this->assertSame( '', $clause );
	}
}
