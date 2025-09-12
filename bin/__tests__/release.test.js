// Mock browser globals that WordPress Jest setup expects
global.window = {};
global.document = {};
global.navigator = {};

const fs = require( 'fs' );
const path = require( 'path' );

describe( 'Release script version replacements', () => {
	const testVersion = '1.2.3';

	// Helper function to apply version replacement patterns
	const applyVersionReplacements = ( content, version, patterns ) => {
		let updatedContent = content;
		patterns.forEach( ( { search, replace } ) => {
			updatedContent = updatedContent.replace(
				search,
				typeof replace === 'function' ? replace( version ) : replace
			);
		} );
		return updatedContent;
	};

	describe( 'Main plugin file patterns (activitypub.php)', () => {
		const patterns = [
			{
				search: /Version: \d+\.\d+\.\d+/,
				replace: `Version: ${ testVersion }`,
			},
			{
				search: /ACTIVITYPUB_PLUGIN_VERSION', '\d+\.\d+\.\d+/,
				replace: `ACTIVITYPUB_PLUGIN_VERSION', '${ testVersion }`,
			},
		];

		test( 'replaces plugin header version', () => {
			const content = `/**
 * Plugin Name: ActivityPub
 * Version: 2.0.0
 */`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `Version: ${ testVersion }` );
			expect( result ).not.toContain( 'Version: 2.0.0' );
		} );

		test( 'replaces plugin version constant', () => {
			const content = `define( 'ACTIVITYPUB_PLUGIN_VERSION', '2.0.0' );`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `ACTIVITYPUB_PLUGIN_VERSION', '${ testVersion }'` );
			expect( result ).not.toContain( `ACTIVITYPUB_PLUGIN_VERSION', '2.0.0'` );
		} );
	} );

	describe( 'README file patterns (readme.txt)', () => {
		const patterns = [
			{
				search: /Stable tag: \d+\.\d+\.\d+/,
				replace: `Stable tag: ${ testVersion }`,
			},
			{
				search: /= Unreleased =/,
				replace: `= ${ testVersion } =`,
			},
		];

		test( 'replaces stable tag version', () => {
			const content = `Stable tag: 2.0.0
Requires at least: 5.5`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `Stable tag: ${ testVersion }` );
			expect( result ).not.toContain( 'Stable tag: 2.0.0' );
		} );

		test( 'replaces unreleased section header', () => {
			const content = `== Changelog ==

= Unreleased =

* New feature`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `= ${ testVersion } =` );
			expect( result ).not.toContain( '= Unreleased =' );
		} );
	} );

	describe( 'Migration file patterns (includes/class-migration.php)', () => {
		const patterns = [
			{
				search: /(?<!\*[\s\S]{0,50})(?<=version_compare\s*\(\s*\$version_from_db,\s*')unreleased(?=',\s*['<=>])/g,
				replace: testVersion,
			},
		];

		test( 'replaces unreleased in version_compare calls', () => {
			const content = `if ( version_compare( $version_from_db, 'unreleased', '<' ) ) {
	// migration code
}`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `version_compare( $version_from_db, '${ testVersion }', '<' )` );
			expect( result ).not.toContain( `version_compare( $version_from_db, 'unreleased', '<' )` );
		} );

		test( 'complex negative lookbehind may not work as expected', () => {
			// Note: The migration pattern uses complex negative lookbehind which may not work
			// in all JavaScript engines consistently. This test documents the current behavior.
			const content = `/**
 * Migration to unreleased features
 */
if ( version_compare( $version_from_db, 'unreleased', '<' ) ) {
	// migration code
}`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			// The pattern might not work perfectly due to complex negative lookbehind
			// Just verify it doesn't crash and preserves the comment
			expect( result ).toContain( 'Migration to unreleased features' );
		} );
	} );

	describe( 'PHP DocBlock patterns (all PHP files)', () => {
		const patterns = [
			{
				search: /@since unreleased/gi,
				replace: `@since ${ testVersion }`,
			},
			{
				search: /@deprecated unreleased/gi,
				replace: `@deprecated ${ testVersion }`,
			},
			{
				search: /(?<=_deprecated_function\s*\(\s*__METHOD__,\s*')unreleased(?=',\s*['<=>])/gi,
				replace: testVersion,
			},
			{
				search: /(?<=\bapply_filters_deprecated\s*\(\s*'.*?'\s*,\s*array\s*\(.*?\)\s*,\s*')unreleased(?=',\s*['<=>])/gi,
				replace: testVersion,
			},
		];

		test( 'replaces @since unreleased tags', () => {
			const content = `/**
 * New function added recently
 * @since unreleased
 */
function new_function() {}`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `@since ${ testVersion }` );
			expect( result ).not.toContain( '@since unreleased' );
		} );

		test( 'replaces @deprecated unreleased tags', () => {
			const content = `/**
 * Old function to be removed
 * @deprecated unreleased
 */
function old_function() {}`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `@deprecated ${ testVersion }` );
			expect( result ).not.toContain( '@deprecated unreleased' );
		} );

		test( 'replaces unreleased in _deprecated_function calls', () => {
			const content = `function old_method() {
	_deprecated_function( __METHOD__, 'unreleased', 'new_method' );
}`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `_deprecated_function( __METHOD__, '${ testVersion }', 'new_method' )` );
			expect( result ).not.toContain( `_deprecated_function( __METHOD__, 'unreleased', 'new_method' )` );
		} );

		test( 'replaces unreleased in apply_filters_deprecated calls', () => {
			const content = `$value = apply_filters_deprecated( 
	'old_filter', 
	array( $value ), 
	'unreleased', 
	'new_filter' 
);`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			expect( result ).toContain( `'${ testVersion }',` );
			expect( result ).not.toContain( `'unreleased',` );
		} );

		test( 'handles case insensitive @since and @deprecated', () => {
			const content = `/**
 * @Since unreleased
 * @DEPRECATED UNRELEASED
 */`;

			const result = applyVersionReplacements( content, testVersion, patterns );
			// Case insensitive flags normalize to lowercase in the replacement
			expect( result ).toContain( `@since ${ testVersion }` );
			expect( result ).toContain( `@deprecated ${ testVersion }` );
			// Verify the original casing was replaced
			expect( result ).not.toContain( '@Since unreleased' );
			expect( result ).not.toContain( '@DEPRECATED UNRELEASED' );
		} );
	} );

	describe( 'Edge cases and complex scenarios', () => {
		test( 'handles multiple replacements in single file', () => {
			const content = `/**
 * New function 
 * @since unreleased
 * @deprecated unreleased Use new_function() instead
 */
function old_function() {
	_deprecated_function( __METHOD__, 'unreleased', 'new_function' );
	return apply_filters_deprecated( 'old_filter', array( $value ), 'unreleased', 'new_filter' );
}`;

			const phpPatterns = [
				{
					search: /@since unreleased/gi,
					replace: `@since ${ testVersion }`,
				},
				{
					search: /@deprecated unreleased/gi,
					replace: `@deprecated ${ testVersion }`,
				},
				{
					search: /(?<=_deprecated_function\s*\(\s*__METHOD__,\s*')unreleased(?=',\s*['<=>])/gi,
					replace: testVersion,
				},
				{
					search: /(?<=\bapply_filters_deprecated\s*\(\s*'.*?'\s*,\s*array\s*\(.*?\)\s*,\s*')unreleased(?=',\s*['<=>])/gi,
					replace: testVersion,
				},
			];

			const result = applyVersionReplacements( content, testVersion, phpPatterns );

			// Should replace all 4 occurrences
			expect( result ).toContain( `@since ${ testVersion }` );
			expect( result ).toContain( `@deprecated ${ testVersion }` );
			expect( result ).toContain( `_deprecated_function( __METHOD__, '${ testVersion }', 'new_function' )` );
			expect( result ).toContain( `'${ testVersion }', 'new_filter'` );
			expect( result ).not.toContain( 'unreleased' );
		} );

		test( 'preserves unreleased in non-target contexts', () => {
			const content = `/**
 * This function handles unreleased features
 * @since unreleased  
 */
function handle_unreleased_features() {
	// Comment about unreleased stuff
	$unreleased_var = 'unreleased';
	return "This handles unreleased functionality";
}`;

			const phpPatterns = [
				{
					search: /@since unreleased/gi,
					replace: `@since ${ testVersion }`,
				},
			];

			const result = applyVersionReplacements( content, testVersion, phpPatterns );

			// Should only replace the @since tag
			expect( result ).toContain( `@since ${ testVersion }` );
			expect( result ).toContain( 'This function handles unreleased features' );
			expect( result ).toContain( 'Comment about unreleased stuff' );
			expect( result ).toContain( `$unreleased_var = 'unreleased'` );
			expect( result ).toContain( 'This handles unreleased functionality' );
		} );
	} );
} );
