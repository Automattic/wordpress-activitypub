/**
 * Script to check for unused PHP imports
 *
 * This script analyzes PHP files to detect unused imports
 * and reports them with clear error messages
 */

const fs = require( 'fs' );

// Check for unused imports in a PHP file
const checkUnusedImports = ( filePath ) => {
	try {
		const content = fs.readFileSync( filePath, 'utf8' );

		// Find all import statements
		const useStatementRegex = /^use\s+([^;]+);/gm;
		let match;
		let allImports = [];

		// Find all imports
		while ( ( match = useStatementRegex.exec( content ) ) !== null ) {
			const fullMatch = match[ 0 ];
			const importName = match[ 1 ].trim();

			// Store the import for later checking
			const isFunction = fullMatch.includes( 'use function' );

			// Handle renamed imports (using 'as' keyword)
			let shortName;
			if ( importName.includes( ' as ' ) ) {
				// For renamed imports, use the alias (after 'as')
				shortName = importName.split( ' as ' ).pop().trim();
			} else {
				// For regular imports, use the last part of the namespace
				shortName = importName.split( '\\' ).pop();
			}

			allImports.push( {
				fullMatch,
				importName,
				shortName,
				isFunction,
			} );
		}

		// Check for unused imports
		const unusedImports = [];

		allImports.forEach( ( importInfo ) => {
			const { shortName, isFunction, fullMatch } = importInfo;

			// Create regex patterns to find usages
			let patterns = [];

			if ( isFunction ) {
				// For functions, look for function calls: shortName(
				patterns.push( new RegExp( `\\b${ shortName }\\s*\\(` ) );
			} else {
				// For classes, look for various usages:
				// 1. new ClassName(
				patterns.push( new RegExp( `new\\s+${ shortName }\\b` ) );
				// 2. ClassName::
				patterns.push( new RegExp( `\\b${ shortName }::` ) );
				// 3. instanceof ClassName
				patterns.push( new RegExp( `instanceof\\s+${ shortName }\\b` ) );
				// 4. Type hints: function(ClassName $param)
				patterns.push( new RegExp( `[\\(,]\\s*${ shortName }\\s+\\$` ) );
				// 5. Return type hints: function(): ClassName
				patterns.push( new RegExp( `:\\s*${ shortName }\\b` ) );
				// 6. Used as a variable
				patterns.push( new RegExp( `\\b${ shortName }\\b` ) );
			}

			// Check if the import is used anywhere in the file
			const isUsed = patterns.some( ( pattern ) => {
				// Skip checking in the import section itself
				const contentWithoutImports = content.replace( /^use\s+[^;]+;/gm, '' );
				return pattern.test( contentWithoutImports );
			} );

			if ( ! isUsed ) {
				unusedImports.push( { fullMatch, shortName } );
			}
		} );

		return {
			unusedImports,
		};
	} catch ( error ) {
		console.error( `Error processing ${ filePath }:`, error );
		return {
			unusedImports: [],
		};
	}
};

// If this script is run directly
if ( require.main === module ) {
	const args = process.argv.slice( 2 );
	if ( args.length === 0 ) {
		console.error( 'Please provide a file path' );
		process.exit( 1 );
	}

	const filePath = args[ 0 ];
	const result = checkUnusedImports( filePath );

	if ( result.unusedImports.length > 0 ) {
		console.error( `\x1b[31mERROR: Unused imports found in \x1b[36m${ filePath }\x1b[31m:\x1b[0m` );
		result.unusedImports.forEach( ( { fullMatch, shortName } ) => {
			console.error( `  - \x1b[33m${ shortName }\x1b[0m (${ fullMatch.trim() })` );
		} );
		process.exit( 1 );
	}
}

// Export for use in other scripts
module.exports = { checkUnusedImports };
