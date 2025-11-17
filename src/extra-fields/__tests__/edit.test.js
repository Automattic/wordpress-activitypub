/**
 * @jest-environment jsdom
 */

import { render, screen, waitFor } from '@testing-library/react';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import Edit from '../edit';

// Mock WordPress dependencies
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch' );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	sprintf: ( format, ...args ) => {
		let formatted = format;
		args.forEach( ( arg, index ) => {
			formatted = formatted.replace( /%s/, arg );
		} );
		return formatted;
	},
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: jest.fn( ( props ) => props ),
	InspectorControls: ( { children } ) => <div data-testid="inspector-controls">{ children }</div>,
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children, title } ) => (
		<div data-testid="panel-body" data-title={ title }>
			{ children }
		</div>
	),
	SelectControl: ( { label, value, options, onChange } ) => (
		<div data-testid="select-control">
			<label>{ label }</label>
			<select value={ value } onChange={ ( e ) => onChange( e.target.value ) }>
				{ options.map( ( opt ) => (
					<option key={ opt.value } value={ opt.value }>
						{ opt.label }
					</option>
				) ) }
			</select>
		</div>
	),
	RangeControl: ( { label, value, onChange, min, max, help } ) => (
		<div data-testid="range-control">
			<label>{ label }</label>
			<input
				type="range"
				value={ value }
				min={ min }
				max={ max }
				onChange={ ( e ) => onChange( parseInt( e.target.value, 10 ) ) }
			/>
			<span>{ help }</span>
		</div>
	),
	Placeholder: ( { label, children } ) => (
		<div data-testid="placeholder" aria-label={ label }>
			{ children }
		</div>
	),
	Spinner: () => <div data-testid="spinner">Loading...</div>,
	Button: ( { children, onClick, variant } ) => (
		<button onClick={ onClick } data-variant={ variant }>
			{ children }
		</button>
	),
} ) );

jest.mock( '../../shared/use-user-options', () => ( {
	useUserOptions: jest.fn( () => [
		{ value: 'blog', label: 'Blog' },
		{ value: 'inherit', label: 'Inherit from post author' },
		{ value: '1', label: 'Admin' },
	] ),
} ) );

jest.mock( '../../shared/use-options', () => ( {
	useOptions: jest.fn( () => ( {
		namespace: 'activitypub/1.0',
		profileUrls: {
			user: '/wp-admin/profile.php#activitypub',
			blog: '/wp-admin/options-general.php?page=activitypub&tab=blog-profile',
		},
	} ) ),
} ) );

// Suppress console warnings for testing
const originalError = console.error;

describe( 'Extra Fields Edit Component', () => {
	let mockEditorStore;
	let mockCoreStore;

	beforeAll( () => {
		console.error = jest.fn();
	} );

	afterAll( () => {
		console.error = originalError;
	} );

	beforeEach( () => {
		// Reset mocks before each test.
		jest.clearAllMocks();

		mockEditorStore = {
			getCurrentPostAttribute: jest.fn().mockReturnValue( 1 ),
		};

		mockCoreStore = {
			getEditedEntityRecord: jest.fn().mockReturnValue( null ),
			getEntityRecord: jest.fn().mockReturnValue( null ),
		};

		useSelect.mockImplementation( ( callback ) =>
			callback( ( storeName ) => {
				if ( 'core/editor' === storeName ) {
					return mockEditorStore;
				}

				if ( 'core' === storeName ) {
					return mockCoreStore;
				}

				return null;
			} )
		);

		// Default mock for apiFetch
		apiFetch.mockResolvedValue( {
			attachment: [
				{
					type: 'PropertyValue',
					name: 'Website',
					value: '<a href="https://example.com">example.com</a>',
				},
				{
					type: 'PropertyValue',
					name: 'Location',
					value: 'San Francisco, CA',
				},
			],
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders inspector controls with user and max fields settings', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: 'blog',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( screen.getByTestId( 'inspector-controls' ) ).toBeTruthy();
		} );

		expect( screen.getByTestId( 'select-control' ) ).toBeTruthy();
		expect( screen.getByTestId( 'range-control' ) ).toBeTruthy();
	} );

	test( 'fetches and displays extra fields', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		// Should show loading initially
		expect( screen.getByTestId( 'spinner' ) ).toBeTruthy();

		// Wait for data to load
		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		expect( screen.getByText( 'Location' ) ).toBeTruthy();
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/activitypub/1.0/actors/1',
			headers: { Accept: 'application/activity+json' },
		} );
	} );

	test( 'shows placeholder when inherit mode but no author', () => {
		mockEditorStore.getCurrentPostAttribute.mockReturnValue( null );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: 'inherit',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		expect( screen.getByTestId( 'placeholder' ) ).toBeTruthy();
		expect( screen.getByTestId( 'inspector-controls' ) ).toBeTruthy();
		expect(
			screen.getByText( 'This block will display extra fields based on the post author when published.' )
		).toBeTruthy();
	} );

	test( 'shows error message when API fetch fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Network error' ) );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( screen.getByText( /Error loading extra fields/i ) ).toBeTruthy();
		} );

		expect( screen.getByText( /Network error/i ) ).toBeTruthy();
	} );

	test( 'shows empty state when no fields available', async () => {
		apiFetch.mockResolvedValue( {
			attachment: [],
		} );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( screen.getByTestId( 'placeholder' ) ).toBeTruthy();
		} );

		expect( screen.getByText( 'No extra fields found.' ) ).toBeTruthy();
		expect( screen.getByText( 'Add fields in your profile settings' ) ).toBeTruthy();
	} );

	test( 'limits displayed fields when maxFields is set', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 1,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		// Should only show first field
		expect( screen.queryByText( 'Location' ) ).toBeNull();
	} );

	test( 'fetches blog user fields when selectedUser is blog', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: 'blog',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/activitypub/1.0/actors/0',
				headers: { Accept: 'application/activity+json' },
			} );
		} );
	} );

	test( 'fetches author fields when selectedUser is inherit', async () => {
		mockEditorStore.getCurrentPostAttribute.mockReturnValue( 5 ); // authorId = 5

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: 'inherit',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/activitypub/1.0/actors/5',
				headers: { Accept: 'application/activity+json' },
			} );
		} );
	} );

	test( 'uses context author when inherit mode is inside Query Loop', async () => {
		mockEditorStore.getCurrentPostAttribute.mockReturnValue( null );
		mockCoreStore.getEditedEntityRecord.mockReturnValue( {
			author: 9,
		} );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: 'inherit',
			maxFields: 0,
		};

		render(
			<Edit
				attributes={ attributes }
				setAttributes={ setAttributes }
				context={ { postId: 123, postType: 'post' } }
			/>
		);

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/activitypub/1.0/actors/9',
				headers: { Accept: 'application/activity+json' },
			} );
		} );
	} );

	test( 'filters attachment array to only PropertyValue types', async () => {
		apiFetch.mockResolvedValue( {
			attachment: [
				{
					type: 'PropertyValue',
					name: 'Website',
					value: 'example.com',
				},
				{
					type: 'Link',
					name: 'Other Link',
					href: 'https://other.com',
				},
				{
					type: 'PropertyValue',
					name: 'Location',
					value: 'SF',
				},
			],
		} );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		// Should show PropertyValue items
		expect( screen.getByText( 'Location' ) ).toBeTruthy();

		// Should not show Link type
		expect( screen.queryByText( 'Other Link' ) ).toBeNull();
	} );

	test( 'applies card style with background color when className includes is-style-cards', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
			className: 'is-style-cards',
			backgroundColor: 'primary',
		};

		const { container } = render(
			<Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } />
		);

		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		// Check if card style is applied
		const fields = container.querySelectorAll( '.activitypub-extra-field' );
		expect( fields.length ).toBeGreaterThan( 0 );
	} );

	test( 'applies card style with custom background color', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
			className: 'is-style-cards',
			style: {
				color: {
					background: '#ff0000',
				},
			},
		};

		const { container } = render(
			<Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } />
		);

		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		const fields = container.querySelectorAll( '.activitypub-extra-field' );
		expect( fields.length ).toBeGreaterThan( 0 );
	} );

	test( 'does not apply background color when not cards style', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
			className: 'is-style-compact',
			backgroundColor: 'primary',
		};

		const { container } = render(
			<Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } />
		);

		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		const fields = container.querySelectorAll( '.activitypub-extra-field' );
		expect( fields.length ).toBeGreaterThan( 0 );
	} );

	test( 'renders HTML content safely using dangerouslySetInnerHTML', async () => {
		apiFetch.mockResolvedValue( {
			attachment: [
				{
					type: 'PropertyValue',
					name: 'Website',
					value: '<a href="https://example.com">Click here</a>',
				},
			],
		} );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		const { container } = render(
			<Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } />
		);

		await waitFor( () => {
			expect( screen.getByText( 'Website' ) ).toBeTruthy();
		} );

		// Check that HTML is rendered
		const link = container.querySelector( 'a[href="https://example.com"]' );
		expect( link ).toBeTruthy();
		expect( link.textContent ).toBe( 'Click here' );
	} );

	test( 'refetches fields when userId changes', async () => {
		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		const { rerender } = render(
			<Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } />
		);

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		} );

		// Change user
		rerender(
			<Edit attributes={ { ...attributes, selectedUser: '2' } } setAttributes={ setAttributes } context={ {} } />
		);

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		} );

		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: '/activitypub/1.0/actors/2',
			headers: { Accept: 'application/activity+json' },
		} );
	} );

	test( 'handles empty attachment array in actor response', async () => {
		apiFetch.mockResolvedValue( {
			// No attachment property
		} );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: '1',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		await waitFor( () => {
			expect( screen.getByTestId( 'placeholder' ) ).toBeTruthy();
		} );

		expect( screen.getByText( 'No extra fields found.' ) ).toBeTruthy();
		expect( screen.getByText( 'Add fields in your profile settings' ) ).toBeTruthy();
	} );

	test( 'does not fetch when userId is null', () => {
		mockEditorStore.getCurrentPostAttribute.mockReturnValue( null );

		const setAttributes = jest.fn();
		const attributes = {
			selectedUser: 'inherit',
			maxFields: 0,
		};

		render( <Edit attributes={ attributes } setAttributes={ setAttributes } context={ {} } /> );

		// Should not call apiFetch
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );
