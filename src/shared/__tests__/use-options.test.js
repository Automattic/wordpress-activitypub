/**
 * @jest-environment jsdom
 */

import { renderHook } from '@testing-library/react';
import { useOptions } from '../use-options';

// Suppress console warnings for testing
const originalError = console.error;

describe( 'useOptions', () => {
	beforeAll( () => {
		console.error = jest.fn();
	} );

	afterAll( () => {
		console.error = originalError;
	} );

	afterEach( () => {
		delete window._activityPubOptions;
		jest.clearAllMocks();
	} );

	test( 'returns empty object when no options set', () => {
		const { result } = renderHook( () => useOptions() );
		expect( result.current ).toEqual( {} );
	} );

	test( 'returns options from window global', () => {
		window._activityPubOptions = {
			defaultAvatarUrl: 'https://example.com/avatar.jpg',
			enabled: { users: true, blog: false },
		};

		const { result } = renderHook( () => useOptions() );

		expect( result.current ).toEqual( {
			defaultAvatarUrl: 'https://example.com/avatar.jpg',
			enabled: { users: true, blog: false },
		} );
	} );

	test( 'handles missing window options gracefully', () => {
		window._activityPubOptions = undefined;

		const { result } = renderHook( () => useOptions() );

		expect( result.current ).toEqual( {} );
	} );
} );
