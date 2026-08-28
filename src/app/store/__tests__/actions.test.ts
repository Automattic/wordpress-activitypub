const mockSet = jest.fn();
const mockDispatch = jest.fn( ( _store: unknown ) => ( { set: mockSet } ) );

jest.mock( '@wordpress/data', () => ( {
	dispatch: ( store: unknown ) => mockDispatch( store ),
} ) );

jest.mock( '@wordpress/preferences', () => ( {
	store: 'core/preferences',
} ) );

import { actions } from '../actions';
import { SET_ACTIVE_ACTOR } from '../types';

describe( 'store actions', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'setActiveActor', () => {
		it( 'should return a SET_ACTIVE_ACTOR action with the actor id', () => {
			const action = actions.setActiveActor( 5 );
			expect( action ).toEqual( { type: SET_ACTIVE_ACTOR, actorId: 5 } );
		} );

		it( 'should persist the actor id to preferences', () => {
			actions.setActiveActor( 12 );
			expect( mockDispatch ).toHaveBeenCalledWith( 'core/preferences' );
			expect( mockSet ).toHaveBeenCalledWith( 'activitypub/app', 'activeActorId', 12 );
		} );
	} );
} );
