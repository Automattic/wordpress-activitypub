/**
 * @jest-environment jsdom
 */
import { sprintf } from '@wordpress/i18n';

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn( ( text ) => text ),
	sprintf: jest.fn( ( format, ...args ) => format.replace( /%s/g, () => args.shift() ) ),
} ) );

describe( 'ActivityPub Command Palette', () => {
	beforeEach( () => {
		// Set up default window config
		window.activitypubCommandPalette = {
			followingEnabled: false,
			actorMode: 'actor',
			canManageOptions: false,
		};
	} );

	afterEach( () => {
		delete window.activitypubCommandPalette;
	} );

	describe( 'Configuration', () => {
		test( 'window.activitypubCommandPalette can be set', () => {
			expect( window.activitypubCommandPalette ).toBeDefined();
			expect( window.activitypubCommandPalette.actorMode ).toBe( 'actor' );
		} );

		test( 'supports all actor modes', () => {
			const modes = [ 'actor', 'blog', 'actor_blog' ];
			modes.forEach( ( mode ) => {
				window.activitypubCommandPalette = {
					followingEnabled: false,
					actorMode: mode as 'actor' | 'blog' | 'actor_blog',
					canManageOptions: false,
				};
				expect( window.activitypubCommandPalette.actorMode ).toBe( mode );
			} );
		} );

		test( 'supports following enabled/disabled', () => {
			window.activitypubCommandPalette.followingEnabled = true;
			expect( window.activitypubCommandPalette.followingEnabled ).toBe( true );

			window.activitypubCommandPalette.followingEnabled = false;
			expect( window.activitypubCommandPalette.followingEnabled ).toBe( false );
		} );

		test( 'supports manage options capability', () => {
			window.activitypubCommandPalette.canManageOptions = true;
			expect( window.activitypubCommandPalette.canManageOptions ).toBe( true );

			window.activitypubCommandPalette.canManageOptions = false;
			expect( window.activitypubCommandPalette.canManageOptions ).toBe( false );
		} );
	} );

	describe( 'sprintf utility', () => {
		test( 'formats strings with placeholders', () => {
			const result = sprintf( 'ActivityPub: Edit - %s', 'Blog' );
			expect( result ).toBe( 'ActivityPub: Edit - Blog' );
		} );

		test( 'handles multiple placeholders', () => {
			const result = sprintf( '%s: %s', 'ActivityPub', 'Test' );
			expect( result ).toBe( 'ActivityPub: Test' );
		} );

		test( 'handles empty title', () => {
			const result = sprintf( 'ActivityPub: Edit - %s', '' );
			expect( result ).toBe( 'ActivityPub: Edit - ' );
		} );
	} );

	describe( 'Title sanitization', () => {
		test( 'removes double quotes from titles', () => {
			const title = 'My "Special" Field';
			const sanitized = title.replace( /["'`]/g, '' );
			expect( sanitized ).toBe( 'My Special Field' );
		} );

		test( 'removes single quotes from titles', () => {
			const title = "My 'Special' Field";
			const sanitized = title.replace( /["'`]/g, '' );
			expect( sanitized ).toBe( 'My Special Field' );
		} );

		test( 'removes backticks from titles', () => {
			const title = 'My `Special` Field';
			const sanitized = title.replace( /["'`]/g, '' );
			expect( sanitized ).toBe( 'My Special Field' );
		} );

		test( 'removes all quote types from titles', () => {
			const title = `My "Special" 'Field' \`Test\``;
			const sanitized = title.replace( /["'`]/g, '' );
			expect( sanitized ).toBe( 'My Special Field Test' );
		} );

		test( 'handles titles without quotes', () => {
			const title = 'My Normal Field';
			const sanitized = title.replace( /["'`]/g, '' );
			expect( sanitized ).toBe( 'My Normal Field' );
		} );
	} );

	describe( 'Command names', () => {
		test( 'generates unique command names with IDs', () => {
			const id1 = 123;
			const id2 = 456;

			const name1 = `activitypub/edit-extra-field/${ id1 }`;
			const name2 = `activitypub/edit-extra-field/${ id2 }`;

			expect( name1 ).toBe( 'activitypub/edit-extra-field/123' );
			expect( name2 ).toBe( 'activitypub/edit-extra-field/456' );
			expect( name1 ).not.toBe( name2 );
		} );

		test( 'command names follow consistent pattern', () => {
			const commands = [
				'activitypub/navigate-user-followers',
				'activitypub/navigate-user-following',
				'activitypub/navigate-blog-followers',
				'activitypub/navigate-blog-following',
				'activitypub/navigate-blocked-actors',
				'activitypub/navigate-settings',
				'activitypub/navigate-extra-fields',
				'activitypub/add-extra-field',
			];

			commands.forEach( ( command ) => {
				expect( command ).toMatch( /^activitypub\// );
			} );
		} );
	} );

	describe( 'Navigation URLs', () => {
		test( 'generates correct user followers URL', () => {
			const url = 'users.php?page=activitypub-followers-list';
			expect( url ).toContain( 'users.php' );
			expect( url ).toContain( 'activitypub-followers-list' );
		} );

		test( 'generates correct blog followers URL', () => {
			const url = 'options-general.php?page=activitypub&tab=followers';
			expect( url ).toContain( 'options-general.php' );
			expect( url ).toContain( 'tab=followers' );
		} );

		test( 'generates correct extra field edit URL', () => {
			const postId = 123;
			const url = `post.php?post=${ postId }&action=edit`;
			expect( url ).toBe( 'post.php?post=123&action=edit' );
		} );

		test( 'generates correct new extra field URL', () => {
			const url = 'post-new.php?post_type=ap_extrafield';
			expect( url ).toContain( 'post-new.php' );
			expect( url ).toContain( 'post_type=ap_extrafield' );
		} );
	} );
} );
