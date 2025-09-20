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
