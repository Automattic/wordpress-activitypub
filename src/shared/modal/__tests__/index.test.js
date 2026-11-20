/**
 * @jest-environment jsdom
 */

import { readdirSync, readFileSync, existsSync } from 'fs';
import { join } from 'path';

let registered;

jest.mock( '@wordpress/interactivity', () => ( {
	store: ( _name, definition ) => {
		registered = definition;
		return definition;
	},
	getContext: () => global.__context,
	getConfig: () => ( { i18n: { copy: 'Copy', copied: 'Copied!' } } ),
	getElement: () => ( { ref: global.__button } ),
} ) );

jest.mock( '../../with-sync-event', () => ( {
	withSyncEvent: ( fn ) => fn,
} ) );

import { createModalStore } from '../index';

/**
 * Set a Clipboard API mock, or remove the API when no mock is given.
 *
 * @param {Object} [clipboard] The mock for `navigator.clipboard`.
 */
function setClipboard( clipboard ) {
	Object.defineProperty( window.navigator, 'clipboard', { value: clipboard, configurable: true } );
}

describe( 'createModalStore copyToClipboard', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		document.body.innerHTML =
			'<div class="activitypub-dialog__button-group"><input value="@jane@site.example" readonly /><button type="button"></button></div>';

		global.__button = document.querySelector( 'button' );
		global.__context = { copyButtonText: 'Copy', copyStatus: '' };

		createModalStore( 'activitypub/test' );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'copies the value of the field next to the button and announces it', async () => {
		const writeText = jest.fn().mockResolvedValue();
		setClipboard( { writeText } );

		registered.actions.copyToClipboard();
		await Promise.resolve();

		expect( writeText ).toHaveBeenCalledWith( '@jane@site.example' );
		expect( global.__context ).toEqual( { copyButtonText: 'Copied!', copyStatus: 'Copied!' } );

		jest.advanceTimersByTime( 1000 );

		expect( global.__context ).toEqual( { copyButtonText: 'Copy', copyStatus: '' } );
	} );

	test( 'a second copy changes the status again and restarts the reset', async () => {
		setClipboard( { writeText: jest.fn().mockResolvedValue() } );

		registered.actions.copyToClipboard();
		await Promise.resolve();
		jest.advanceTimersByTime( 900 );

		registered.actions.copyToClipboard();

		// The status is emptied first, so setting it again is a change a screen reader announces.
		expect( global.__context.copyStatus ).toBe( '' );

		await Promise.resolve();
		expect( global.__context.copyStatus ).toBe( 'Copied!' );

		// The reset of the first copy would have run by now.
		jest.advanceTimersByTime( 900 );
		expect( global.__context ).toEqual( { copyButtonText: 'Copied!', copyStatus: 'Copied!' } );

		jest.advanceTimersByTime( 100 );
		expect( global.__context ).toEqual( { copyButtonText: 'Copy', copyStatus: '' } );
	} );
} );

describe( 'blocks with a copy button', () => {
	const src = join( __dirname, '..', '..', '..' );
	const blocks = readdirSync( src ).filter( ( dir ) => existsSync( join( src, dir, 'view.js' ) ) );
	const withCopy = blocks.filter( ( dir ) =>
		readFileSync( join( src, dir, 'render.php' ), 'utf8' ).includes( 'actions.copyToClipboard' )
	);

	test( 'the three dialogs use the shared action', () => {
		expect( withCopy ).toEqual( expect.arrayContaining( [ 'follow-me', 'reactions', 'remote-reply' ] ) );
	} );

	test.each( blocks )( '%s does not copy on its own', ( dir ) => {
		expect( readFileSync( join( src, dir, 'view.js' ), 'utf8' ) ).not.toContain( 'navigator.clipboard' );
	} );

	test.each( withCopy )( '%s has the status region', ( dir ) => {
		const render = readFileSync( join( src, dir, 'render.php' ), 'utf8' );

		// Booleans, so a failure does not print the whole file.
		expect(
			render.includes( '<span class="screen-reader-text" data-wp-text="context.copyStatus" role="status">' )
		).toBe( true );
		expect( /'copyStatus'\]?\s*=>?\s*''/.test( render ) ).toBe( true );
	} );
} );
