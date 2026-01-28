/**
 * Jest setup file for testing-library matchers
 */

// Add custom jest matchers from jest-dom
import '@testing-library/jest-dom';

// Set up window.wp for Interactivity API modules
window.wp = window.wp || {
	apiFetch: jest.fn(),
	url: {
		addQueryArgs: jest.fn( ( path ) => path ),
	},
};
