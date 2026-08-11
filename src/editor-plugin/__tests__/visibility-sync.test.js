/**
 * Regression tests for the visibility sync effect in the editor plugin.
 *
 * These render the real `EditorPlugin` so they exercise the selector the component
 * actually reads the post date from. The pure-function tests in `plugin.test.js`
 * cannot catch this: reading the *saved* date instead of the *edited* one produces
 * the correct visibility for the wrong post state, which only shows up as the
 * editor going dirty right after a save.
 *
 * @see https://github.com/Automattic/wordpress-activitypub/issues/3641
 *
 * @jest-environment jsdom
 */

import { render } from '@testing-library/react';

const DAY = 24 * 60 * 60 * 1000;

/**
 * Selector values for the mocked editor store. Reassigned per test.
 *
 * @member {Object} mockEditor
 */
let mockEditor = {};

/**
 * The meta object handed to the component, and the setter it calls to persist changes.
 */
let mockMeta = {};
const mockSetMeta = jest.fn();

/**
 * Captures the components registered via `registerPlugin` at module scope.
 *
 * @member {Object} mockRegistered
 */
const mockRegistered = {};

const mockNoticeActions = {
	createWarningNotice: jest.fn(),
	removeNotice: jest.fn(),
};

jest.mock( '@wordpress/plugins', () => ( {
	registerPlugin: ( name, settings ) => {
		mockRegistered[ name ] = settings.render;
	},
} ) );

jest.mock( '@wordpress/data', () => ( {
	// The component only ever reads the editor store, so hand every `select()` the
	// same fake selector bag.
	useSelect: ( mapSelect ) => mapSelect( () => mockEditor ),
	useDispatch: () => mockNoticeActions,
	select: () => mockEditor,
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	useEntityProp: () => [ mockMeta, mockSetMeta ],
} ) );

jest.mock( '@wordpress/editor', () => ( {
	store: 'core/editor',
	PluginDocumentSettingPanel: () => null,
	PluginPreviewMenuItem: () => null,
} ) );

jest.mock( '@wordpress/edit-post', () => ( {
	PluginDocumentSettingPanel: () => null,
} ) );

jest.mock( '@wordpress/notices', () => ( {
	store: 'core/notices',
} ) );

// Kept light on purpose: the settings panel is stubbed out above, so these are only
// ever referenced as element types and never actually rendered.
jest.mock( '@wordpress/components', () => ( {
	TextControl: () => null,
	RadioControl: () => null,
	RangeControl: () => null,
	SelectControl: () => null,
	Tooltip: () => null,
	__experimentalText: () => null,
} ) );

jest.mock( '@wordpress/icons', () => ( {
	Icon: () => null,
	globe: 'globe',
	people: 'people',
	external: 'external',
} ) );

jest.mock( '@wordpress/primitives', () => ( {
	SVG: () => null,
	Path: () => null,
} ) );

// Load the plugin once so it registers its components. It must not be re-required per
// test: a fresh module registry would hand the component a second copy of React and
// every hook call would fail.
require( '../plugin' );

const EditorPlugin = mockRegistered[ 'activitypub-editor-plugin' ];

/**
 * Renders the registered editor plugin with the given editor state.
 *
 * @param {Object} editorState Selector return values for the fake editor store.
 * @param {Object} meta        The post meta the component should see.
 */
const renderPlugin = ( editorState, meta = {} ) => {
	mockEditor = {
		getCurrentPostType: () => 'post',
		getCurrentPost: () => ( { date: editorState.savedDate, status: 'draft' } ),
		getEditedPostAttribute: ( attribute ) => {
			const attributes = {
				date: editorState.editedDate,
				status: editorState.status ?? 'draft',
				password: editorState.password ?? '',
			};

			return attributes[ attribute ];
		},
	};
	mockMeta = meta;

	render( <EditorPlugin /> );
};

describe( 'EditorPlugin visibility sync', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'does not persist visibility when only the saved date is old', () => {
		// The state right after saving a post whose date was moved back to today:
		// the saved date is still stale for a tick, but the edited date is current.
		// Reading the saved date here is what dirtied the editor immediately after a save.
		renderPlugin( {
			savedDate: new Date( Date.now() - 60 * DAY ).toISOString(),
			editedDate: new Date().toISOString(),
		} );

		expect( mockSetMeta ).not.toHaveBeenCalled();
	} );

	test( 'persists local visibility as soon as the post is backdated, before saving', () => {
		// The user has just set the date to two months ago but has not saved yet, so
		// the saved date is still today. The panel already shows "Do not federate",
		// and the meta has to agree with it.
		renderPlugin( {
			savedDate: new Date().toISOString(),
			editedDate: new Date( Date.now() - 60 * DAY ).toISOString(),
		} );

		expect( mockSetMeta ).toHaveBeenCalledWith( {
			activitypub_content_visibility: 'local',
		} );
	} );

	test( 'leaves an explicitly chosen visibility alone', () => {
		renderPlugin(
			{
				savedDate: new Date().toISOString(),
				editedDate: new Date( Date.now() - 60 * DAY ).toISOString(),
			},
			{ activitypub_content_visibility: 'quiet_public' }
		);

		expect( mockSetMeta ).not.toHaveBeenCalled();
	} );

	test( 'does not take an already federated post out of the Fediverse', () => {
		renderPlugin(
			{
				savedDate: new Date( Date.now() - 60 * DAY ).toISOString(),
				editedDate: new Date( Date.now() - 60 * DAY ).toISOString(),
			},
			{ activitypub_status: 'federated' }
		);

		expect( mockSetMeta ).not.toHaveBeenCalled();
	} );
} );
