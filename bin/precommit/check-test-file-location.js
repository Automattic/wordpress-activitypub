/**
 * Script to check that a PHPUnit test file mirrors a source file
 *
 * Test files live at the same path as the file they test, below
 * tests/phpunit/tests/, with a `class-test-` prefix:
 * includes/rest/class-seek-controller.php is tested in
 * tests/phpunit/tests/includes/rest/class-test-seek-controller.php.
 * See tests/README.md.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const TESTS_ROOT = 'tests/phpunit/tests/';

// Source file prefixes a test file name can map to.
const SOURCE_PREFIXES = [ 'class-', 'trait-', 'interface-', 'functions-', '' ];

// Test files that predate the rule. Do not add to this list, put new tests in the mirrored file.
const EXISTING_EXCEPTIONS = [
	'tests/phpunit/tests/includes/cache/class-test-file-move-retry.php',
	'tests/phpunit/tests/includes/class-test-query-feature-stamp.php',
	'tests/phpunit/tests/includes/cli/class-test-command-error-propagation.php',
	'tests/phpunit/tests/includes/model/class-test-interaction-policy.php',
	'tests/phpunit/tests/includes/rest/class-test-reader-authorization.php',
	'tests/phpunit/tests/integration/class-test-stream-connector.php',
];

// Check where a test file lives. Returns an error message, or null if the location is fine.
const checkTestFileLocation = ( filePath ) => {
	const file = filePath.split( path.sep ).join( '/' );

	if ( ! file.startsWith( TESTS_ROOT ) || ! file.endsWith( '.php' ) || EXISTING_EXCEPTIONS.includes( file ) ) {
		return null;
	}

	const relative = file.slice( TESTS_ROOT.length );
	const base = path.posix.basename( relative );

	if ( ! base.startsWith( 'class-test-' ) ) {
		return `Test file names start with "class-test-", found "${ base }".`;
	}

	const dir = path.posix.dirname( relative );
	const name = base.slice( 'class-test-'.length );
	const candidates = SOURCE_PREFIXES.map( ( prefix ) => path.posix.join( dir, prefix + name ) );

	if ( candidates.some( ( candidate ) => fs.existsSync( candidate ) ) ) {
		return null;
	}

	return `No source file for this test. Expected one of:\n${ candidates
		.filter( ( candidate, index ) => candidates.indexOf( candidate ) === index )
		.map( ( candidate ) => `    ${ candidate }` )
		.join( '\n' ) }`;
};

// If this script is run directly
if ( require.main === module ) {
	const args = process.argv.slice( 2 );
	if ( args.length === 0 ) {
		console.error( 'Please provide a file path' );
		process.exit( 1 );
	}

	const filePath = args[ 0 ];
	const error = checkTestFileLocation( filePath );

	if ( error ) {
		console.error( `\x1b[31mERROR: Test file does not mirror a source file: \x1b[36m${ filePath }\x1b[0m` );
		console.error( `  ${ error }` );
		console.error( '  Put the test into the test file of the source file it covers. See tests/README.md.' );
		process.exit( 1 );
	}
}

// Export for use in other scripts
module.exports = { checkTestFileLocation };
