/**
 * Script to sort PHP namespace imports
 *
 * This script is meant to be run before Prettier to sort PHP imports
 * It preserves blank lines between different types of imports (class vs function)
 */

const fs = require( 'fs' );
const path = require( 'path' );

// Sort imports in a PHP file
const sortImports = ( filePath ) => {
	try {
		const content = fs.readFileSync( filePath, 'utf8' );

		// Find blocks of use statements
		const useStatementRegex = /^use\s+([^;]+);/gm;
		let match;
		let blocks = [];

		// Find all blocks of consecutive use statements
		while ( ( match = useStatementRegex.exec( content ) ) !== null ) {
			const currentIndex = match.index;
			const fullMatch = match[ 0 ];

			// Check if this is part of an existing block or a new block
			if ( blocks.length > 0 ) {
				const lastBlock = blocks[ blocks.length - 1 ];

				// If this statement is close to the previous one, add it to the same block
				// We check for newlines between statements to determine if they're in the same block
				const textBetween = content.substring( lastBlock.end, currentIndex );
				const newlineCount = ( textBetween.match( /\n/g ) || [] ).length;

				if ( newlineCount <= 2 ) {
					lastBlock.statements.push( fullMatch );
					lastBlock.end = currentIndex + fullMatch.length;
					continue;
				}
			}

			// Start a new block
			blocks.push( {
				start: currentIndex,
				end: currentIndex + fullMatch.length,
				statements: [ fullMatch ],
			} );
		}

		// Sort each block of use statements, preserving separation between class and function imports
		let result = content;
		let offset = 0;
		let changed = false;

		blocks.forEach( ( block ) => {
			// Separate class and function imports
			const classImports = block.statements.filter( ( stmt ) => ! stmt.includes( 'use function' ) );
			const functionImports = block.statements.filter( ( stmt ) => stmt.includes( 'use function' ) );

			// Sort each group separately
			const sortedClassImports = [ ...classImports ].sort();
			const sortedFunctionImports = [ ...functionImports ].sort();

			// Combine with a blank line between if both types exist
			let sortedBlock;
			if ( sortedClassImports.length > 0 && sortedFunctionImports.length > 0 ) {
				sortedBlock = sortedClassImports.join( '\n' ) + '\n\n' + sortedFunctionImports.join( '\n' );
			} else {
				sortedBlock = [ ...sortedClassImports, ...sortedFunctionImports ].join( '\n' );
			}

			// Replace the original block with sorted statements
			const originalBlock = result.substring( block.start + offset, block.end + offset );

			if ( originalBlock !== sortedBlock ) {
				changed = true;
				result =
					result.substring( 0, block.start + offset ) + sortedBlock + result.substring( block.end + offset );

				// Update offset for subsequent replacements
				offset += sortedBlock.length - originalBlock.length;
			}
		} );

		// Only write the file if changes were made
		if ( changed ) {
			fs.writeFileSync( filePath, result, 'utf8' );
			return { changed: true };
		}

		return { changed: false };
	} catch ( error ) {
		console.error( `Error processing ${ filePath }:`, error );
		return { changed: false };
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
	sortImports( filePath );
}

// Export for use in other scripts
module.exports = { sortImports };
