/**
 * @jest-environment jsdom
 */

import { safeUrl } from '../utils';

describe( 'safeUrl', () => {
	it( 'returns http(s) URLs unchanged', () => {
		expect( safeUrl( 'https://example.com/@alice' ) ).toBe( 'https://example.com/@alice' );
		expect( safeUrl( 'http://example.com/path?a=1&b=2' ) ).toBe( 'http://example.com/path?a=1&b=2' );
	} );

	it( 'allows relative and protocol-relative URLs', () => {
		expect( safeUrl( '/wp-admin/admin.php' ) ).toBe( '/wp-admin/admin.php' );
		expect( safeUrl( '//example.com/avatar.png' ) ).toBe( '//example.com/avatar.png' );
	} );

	it( 'rejects javascript: URLs and returns the fallback', () => {
		expect( safeUrl( 'javascript:alert(document.cookie)' ) ).toBe( '#' );
		// Whitespace/case tricks are normalized away by the URL parser.
		expect( safeUrl( '  JavaScript:alert(1)' ) ).toBe( '#' );
		expect( safeUrl( 'java\tscript:alert(1)' ) ).toBe( '#' );
	} );

	it( 'rejects other script-executing or data schemes', () => {
		expect( safeUrl( 'data:text/html,<script>alert(1)</script>' ) ).toBe( '#' );
		expect( safeUrl( 'vbscript:msgbox(1)' ) ).toBe( '#' );
	} );

	it( 'returns the fallback for empty input', () => {
		expect( safeUrl( '' ) ).toBe( '#' );
	} );

	it( 'honors a custom fallback', () => {
		expect( safeUrl( 'javascript:alert(1)', '' ) ).toBe( '' );
	} );
} );
