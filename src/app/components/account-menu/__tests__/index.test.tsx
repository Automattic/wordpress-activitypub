/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { useNavigate, useParams } from '@wordpress/route';

/**
 * Internal dependencies
 */
import AccountMenu from '../index';

jest.mock( '@wordpress/data', () => ( { useDispatch: jest.fn(), useSelect: jest.fn() } ) );
jest.mock( '@wordpress/boot', () => ( { store: 'wordpress/boot' } ), { virtual: true } );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );
jest.mock( '@wordpress/route', () => ( { useNavigate: jest.fn(), useParams: jest.fn() } ) );
jest.mock( '../../../store', () => ( { STORE_NAME: 'activitypub/app' } ) );

describe( 'AccountMenu', () => {
	const registerMenuItem = jest.fn();
	const setActiveActor = jest.fn();
	const navigate = jest.fn();
	const selection = {
		currentUser: { id: 7, name: 'Alice' },
		activeActorId: 7,
		actorMode: 'actor_blog',
		hasUserCap: true,
		hasBlogCap: true,
	};

	beforeEach( () => {
		jest.clearAllMocks();
		jest.mocked( useDispatch ).mockReturnValue( { registerMenuItem, setActiveActor } );
		jest.mocked( useSelect ).mockReturnValue( selection );
		jest.mocked( useNavigate ).mockReturnValue( navigate );
		jest.mocked( useParams ).mockReturnValue( {} );
	} );

	it( 'registers one direct switch link naming the current account', () => {
		const { container } = render( <AccountMenu /> );
		expect( container ).toBeEmptyDOMElement();
		expect( registerMenuItem ).toHaveBeenCalledWith(
			'feed-account',
			expect.objectContaining( {
				parent: 'feed',
				to: '/account/0',
				label: 'Account: Alice',
			} )
		);
		expect( registerMenuItem ).toHaveBeenCalledTimes( 1 );
		expect( registerMenuItem.mock.calls[ 0 ][ 1 ] ).not.toHaveProperty( 'parent_type' );
		expect( setActiveActor ).not.toHaveBeenCalled();
		expect( navigate ).not.toHaveBeenCalled();
	} );

	it( 'reverses the link and current-account label after switching', () => {
		const { rerender } = render( <AccountMenu /> );
		jest.mocked( useSelect ).mockReturnValue( { ...selection, activeActorId: 0 } );
		rerender( <AccountMenu /> );
		expect( registerMenuItem ).toHaveBeenLastCalledWith(
			'feed-account',
			expect.objectContaining( { to: '/account/7', label: 'Account: Site' } )
		);
	} );

	it.each( [
		[ 'actor', true, true ],
		[ 'blog', true, true ],
		[ 'actor_blog', true, false ],
		[ 'actor_blog', false, true ],
		[ 'actor_blog', false, false ],
	] )( 'omits the switch link for mode %s and capabilities %s/%s', ( actorMode, hasUserCap, hasBlogCap ) => {
		jest.mocked( useSelect ).mockReturnValue( { ...selection, actorMode, hasUserCap, hasBlogCap } );
		render( <AccountMenu /> );
		expect( registerMenuItem ).not.toHaveBeenCalled();
	} );

	it.each( [
		[ '0', 0 ],
		[ '7', 7 ],
	] )( 'selects permitted account %s after navigation', ( actorId, expected ) => {
		jest.mocked( useSelect ).mockReturnValue( { ...selection, activeActorId: expected === 0 ? 7 : 0 } );
		jest.mocked( useParams ).mockReturnValue( { actorId } );
		render( <AccountMenu /> );
		expect( setActiveActor ).toHaveBeenCalledWith( expected );
		expect( navigate ).not.toHaveBeenCalled();
	} );

	it.each( [ '8', '-1', '00', 'NaN', '7.0' ] )( 'does not select an invalid or foreign account %s', ( actorId ) => {
		jest.mocked( useParams ).mockReturnValue( { actorId } );
		render( <AccountMenu /> );
		expect( setActiveActor ).not.toHaveBeenCalled();
		expect( navigate ).not.toHaveBeenCalled();
	} );

	it( 'rejects a direct site-account link without permission', () => {
		jest.mocked( useSelect ).mockReturnValue( { ...selection, hasBlogCap: false } );
		jest.mocked( useParams ).mockReturnValue( { actorId: '0' } );
		render( <AccountMenu /> );
		expect( setActiveActor ).not.toHaveBeenCalled();
	} );

	it( 'waits for permissions instead of rejecting a link while they load', () => {
		jest.mocked( useSelect ).mockReturnValue( { ...selection, hasBlogCap: undefined } );
		jest.mocked( useParams ).mockReturnValue( { actorId: '0' } );
		render( <AccountMenu /> );
		expect( registerMenuItem ).not.toHaveBeenCalled();
		expect( setActiveActor ).not.toHaveBeenCalled();
		expect( navigate ).not.toHaveBeenCalled();
	} );

	it( 'corrects a saved selection when only the site account is available', () => {
		jest.mocked( useSelect ).mockReturnValue( { ...selection, actorMode: 'blog' } );
		render( <AccountMenu /> );
		expect( setActiveActor ).toHaveBeenCalledWith( 0 );
	} );
} );
