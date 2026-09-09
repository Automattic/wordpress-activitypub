/**
 * @jest-environment jsdom
 */

import { isSafeUrl } from '../safe-url';

describe( 'isSafeUrl', () => {
	test.each( [
		[ 'https URL', 'https://example.com/authorize_interaction?uri=acct:a@b.com' ],
		[ 'http URL', 'http://example.com/intent/follow' ],
		[ 'uppercase scheme', 'HTTPS://example.com/' ],
		[ 'unresolved placeholder', 'https://example.com/intent?uri={uri}' ],
	] )( 'accepts %s', ( _label, url ) => {
		expect( isSafeUrl( url ) ).toBe( true );
	} );

	test.each( [
		[ 'javascript scheme', 'javascript:alert(1)//acct:a@b.com' ],
		[ 'mixed-case javascript scheme', 'JaVaScRiPt:alert(1)' ],
		[ 'padded javascript scheme', '  \tjavascript:alert(1)' ],
		[ 'data scheme', 'data:text/html;base64,PHNjcmlwdD48L3NjcmlwdD4=' ],
		[ 'blob scheme', 'blob:https://example.com/1234' ],
		[ 'vbscript scheme', 'vbscript:msgbox(1)' ],
		[ 'file scheme', 'file:///etc/passwd' ],
		[ 'protocol-relative URL', '//example.com/intent' ],
		[ 'relative URL', '/wp-admin/post-new.php' ],
		[ 'empty string', '' ],
		[ 'whitespace only', '   ' ],
		[ 'undefined', undefined ],
		[ 'null', null ],
		[ 'object', { url: 'https://example.com' } ],
	] )( 'rejects %s', ( _label, url ) => {
		expect( isSafeUrl( url ) ).toBe( false );
	} );
} );
