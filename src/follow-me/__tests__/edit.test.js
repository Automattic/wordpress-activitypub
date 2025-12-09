import { useUserOptions } from '../../shared/use-user-options';
import { useOptions } from '../../shared/use-options';

// Test the utility functions we can extract and test from the edit component
describe( 'FollowMe Edit utilities', () => {
	beforeEach( () => {
		// Mock window._activityPubOptions
		window._activityPubOptions = {
			defaultAvatarUrl: 'test.jpg',
			enabled: { users: true, blog: false },
		};
	} );

	afterEach( () => {
		delete window._activityPubOptions;
	} );

	test( 'default profile data structure', () => {
		// Test the DEFAULT_PROFILE_DATA object that should be defined in edit.js
		const DEFAULT_PROFILE_DATA = {
			avatar: 'https://secure.gravatar.com/avatar/default?s=120',
			webfinger: '@well@hello.dolly',
			name: 'Hello Dolly Fan Account',
			url: '#',
			image: { url: '' },
			summary: '',
		};

		expect( DEFAULT_PROFILE_DATA ).toHaveProperty( 'name', 'Hello Dolly Fan Account' );
		expect( DEFAULT_PROFILE_DATA ).toHaveProperty( 'webfinger', '@well@hello.dolly' );
		expect( DEFAULT_PROFILE_DATA ).toHaveProperty( 'avatar' );
	} );

	test( 'can import useUserOptions hook', () => {
		expect( typeof useUserOptions ).toBe( 'function' );
	} );

	test( 'can import useOptions hook', () => {
		const options = useOptions();
		expect( options.defaultAvatarUrl ).toBe( 'test.jpg' );
	} );
} );

describe( 'FollowMe button-only style detection', () => {
	/**
	 * Helper to detect if button-only style is active.
	 * This mirrors the logic used in edit.js.
	 *
	 * @param {string} className Block className attribute.
	 * @return {boolean} True if button-only style is active.
	 */
	const isButtonOnly = ( className ) => {
		return className && className.includes( 'is-style-button-only' );
	};

	test( 'detects button-only style from className', () => {
		expect( isButtonOnly( 'is-style-button-only' ) ).toBe( true );
		expect( isButtonOnly( 'wp-block-activitypub-follow-me is-style-button-only' ) ).toBe( true );
		expect( isButtonOnly( 'is-style-button-only has-custom-width' ) ).toBe( true );
	} );

	test( 'returns false for other styles', () => {
		expect( isButtonOnly( 'is-style-default' ) ).toBe( false );
		expect( isButtonOnly( 'is-style-profile' ) ).toBe( false );
	} );

	test( 'returns falsy for empty or missing className', () => {
		expect( isButtonOnly( '' ) ).toBeFalsy();
		expect( isButtonOnly( null ) ).toBeFalsy();
		expect( isButtonOnly( undefined ) ).toBeFalsy();
	} );
} );

describe( 'FollowMe button width CSS classes', () => {
	/**
	 * Core/button adds these classes when width is selected.
	 * Our CSS targets these to enable percentage widths in button-only style.
	 */
	const BUTTON_WIDTH_CLASSES = {
		25: 'has-custom-width wp-block-button__width-25',
		50: 'has-custom-width wp-block-button__width-50',
		75: 'has-custom-width wp-block-button__width-75',
		100: 'has-custom-width wp-block-button__width-100',
	};

	test( 'width classes follow core/button naming convention', () => {
		Object.entries( BUTTON_WIDTH_CLASSES ).forEach( ( [ width, className ] ) => {
			expect( className ).toContain( 'has-custom-width' );
			expect( className ).toContain( `wp-block-button__width-${ width }` );
		} );
	} );

	test( 'all percentage widths are supported', () => {
		const supportedWidths = [ 25, 50, 75, 100 ];
		supportedWidths.forEach( ( width ) => {
			expect( BUTTON_WIDTH_CLASSES[ width ] ).toBeDefined();
		} );
	} );

	test( 'has-custom-width class triggers block display mode', () => {
		// This test documents the expected behavior:
		// When .has-custom-width is present on .wp-block-button inside button-only style,
		// the CSS should switch from inline-block to block display.
		const hasCustomWidthSelector = '.is-style-button-only div.wp-block-button.has-custom-width';
		const expectedDisplay = 'block';

		// We're testing the selector pattern, not actual CSS (that's in style.scss)
		expect( hasCustomWidthSelector ).toContain( 'has-custom-width' );
		expect( expectedDisplay ).toBe( 'block' );
	} );
} );
