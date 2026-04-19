<?php
/**
 * Serialized replacer unit tests.
 *
 * @package MigrationUrlFixer
 */

namespace MUF\Tests\Unit;

use MUF\Serialized;
use PHPUnit\Framework\TestCase;
use stdClass;

class SerializedTest extends TestCase {

	private string $from = 'https://old.com';
	private string $to   = 'https://new.com';

	public function test_plain_string_replace(): void {
		$this->assertSame(
			'link: https://new.com/a.jpg',
			Serialized::replace( 'link: https://old.com/a.jpg', $this->from, $this->to )
		);
	}

	public function test_serialized_array_is_length_safe(): void {
		$orig = array( 'url' => 'https://old.com/x', 'nested' => array( 'img' => 'https://old.com/y.png' ) );
		$ser  = serialize( $orig );
		$out  = Serialized::replace( $ser, $this->from, $this->to );
		$rt   = unserialize( $out );
		$this->assertSame( 'https://new.com/x', $rt['url'] );
		$this->assertSame( 'https://new.com/y.png', $rt['nested']['img'] );
	}

	public function test_json_string_is_decoded_replaced_and_reencoded(): void {
		$json = '{"href":"https://old.com/page","items":["https://old.com/a","https://old.com/b"]}';
		$out  = Serialized::replace( $json, $this->from, $this->to );
		$rt   = json_decode( $out, true );
		$this->assertSame( 'https://new.com/page', $rt['href'] );
		$this->assertSame( array( 'https://new.com/a', 'https://new.com/b' ), $rt['items'] );
	}

	public function test_url_encoded_variant_is_replaced(): void {
		$this->assertSame(
			'redirect=https%3A%2F%2Fnew.com%2Fthing',
			Serialized::replace( 'redirect=https%3A%2F%2Fold.com%2Fthing', $this->from, $this->to )
		);
	}

	public function test_slash_escaped_variant_is_replaced(): void {
		$this->assertSame(
			'https:\/\/new.com\/image.jpg',
			Serialized::replace( 'https:\/\/old.com\/image.jpg', $this->from, $this->to )
		);
	}

	public function test_case_insensitive_mode(): void {
		$this->assertSame(
			'hi https://new.com/Y',
			Serialized::replace( 'hi HTTPS://OLD.COM/Y', $this->from, $this->to, array( 'case_insensitive' => true ) )
		);
	}

	public function test_regex_mode_with_backreferences(): void {
		// Flip first-last via back-references.
		$this->assertSame(
			'doe-jane',
			Serialized::replace( 'jane-doe', '#(\w+)-(\w+)#', '$2-$1', array( 'regex' => true ) )
		);
		// Simple replacement via regex.
		$this->assertSame(
			'see https://new.com/anything',
			Serialized::replace( 'see https://old.com/anything', '#https://old\.com#', $this->to, array( 'regex' => true ) )
		);
	}

	public function test_invalid_regex_is_swallowed(): void {
		$this->assertSame(
			'hello',
			Serialized::replace( 'hello', '#[unclosed', 'x', array( 'regex' => true ) )
		);
	}

	public function test_object_properties_are_walked(): void {
		$obj      = new stdClass();
		$obj->url = 'https://old.com/q';
		$obj->arr = array( 'https://old.com/r' );

		$out = Serialized::replace( $obj, $this->from, $this->to );
		$this->assertSame( 'https://new.com/q', $out->url );
		$this->assertSame( 'https://new.com/r', $out->arr[0] );
	}

	public function test_count_plain_and_variants(): void {
		$txt = 'abc https://old.com/1 def https://old.com/2 ghi';
		$this->assertSame( 2, Serialized::count( $txt, $this->from ) );
		$this->assertSame(
			2,
			Serialized::count( 'https://OLD.com/a and https://old.COM/b', $this->from, array( 'case_insensitive' => true ) )
		);
	}

	public function test_count_regex(): void {
		$txt = 'abc https://old.com/1 def https://old.com/2 ghi';
		$this->assertSame( 2, Serialized::count( $txt, '#old\.com#', array( 'regex' => true ) ) );
	}

	public function test_empty_and_noop(): void {
		$this->assertSame( '', Serialized::replace( '', $this->from, $this->to ) );
		$this->assertSame( 'unchanged', Serialized::replace( 'unchanged', $this->from, $this->to ) );
	}

	public function test_malformed_serialized_does_not_crash(): void {
		$broken = 's:5:"abcd";'; // wrong declared length.
		$result = Serialized::replace( $broken, 'abcd', 'xxxx' );
		$this->assertIsString( $result );
	}
}
