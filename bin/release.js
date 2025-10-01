#!/usr/bin/env node

const { execSync } = require( 'child_process' );
const readline = require( 'readline' );
const fs = require( 'fs' );

const rl = readline.createInterface( {
	input: process.stdin,
	output: process.stdout,
} );

const exec = ( command ) => {
	try {
		return execSync( command, { stdio: 'inherit' } );
	} catch ( error ) {
		console.error( `Error executing command: ${ command }` );
		process.exit( 1 );
	}
};

const execWithOutput = ( command ) => {
	try {
		return execSync( command, { stdio: 'pipe' } ).toString().trim();
	} catch ( error ) {
		console.error( `Error executing command: ${ command }` );
		process.exit( 1 );
	}
};

const updateVersionInFile = ( filePath, version, patterns ) => {
	let content = fs.readFileSync( filePath, 'utf8' );

	patterns.forEach( ( { search, replace } ) => {
		content = content.replace( search, typeof replace === 'function' ? replace( version ) : replace );
	} );

	fs.writeFileSync( filePath, content );
};

const generateChangelog = async () => {
	// Run the changelog generation command
	try {
		execSync( 'composer changelog:write', { stdio: 'ignore' } );
	} catch ( error ) {
		console.error( 'Error generating changelog:' );
		console.error( error );
		process.exit( 1 );
	}

	// Grab the version from the generated changelog
	const content = fs.readFileSync( 'CHANGELOG.md', 'utf8' );
	const version = content.match( /## \[(\d+\.\d+\.\d+)\] - \d{4}-\d{2}-\d{2}/ )[ 1 ];

	if ( ! version ) {
		console.error( 'No version found in CHANGELOG.md' );
		process.exit( 1 );
	}

	return version;
};

const updateReadmeWithChangelog = ( version ) => {
	// Grab the contents of the changelog and readme files.
	const changelogContent = fs.readFileSync( 'CHANGELOG.md', 'utf8' );
	const readmeContent = fs.readFileSync( 'readme.txt', 'utf8' );

	// Ensure the latest release entry was found in the list of latest releases we grabbed.
	const latestReleaseRegex = new RegExp( `## \\[${ version }\\].*?(?=## \\[|$)`, 's' );
	const latestReleaseMatch = changelogContent.match( latestReleaseRegex );
	if ( ! latestReleaseMatch ) {
		console.error( `No changelog entry found for version ${ version }` );
		process.exit( 1 );
	}

	// Extract the changelog entries for the given version
	// as well as any other entries from other releases under the same major version
	// e.g. if the latest release is 5.4.1, then we want to include all entries from 5.0.0 to 5.4.1.
	const majorVersion = version.split( '.' )[ 0 ];

	// Find all releases with the same major version
	const releaseRegex = /## \[(\d+\.\d+\.\d+)\] - (\d{4}-\d{2}-\d{2})/g;
	const releases = [];
	let match;

	while ( ( match = releaseRegex.exec( changelogContent ) ) !== null ) {
		const [ , releaseVersion, releaseDate ] = match;
		if ( releaseVersion.startsWith( `${ majorVersion }.` ) ) {
			// Find the content for this release
			const releaseContentRegex = new RegExp( `## \\[${ releaseVersion }\\].*?(?=## \\[|$)`, 's' );
			const releaseContent = changelogContent.match( releaseContentRegex );

			if ( releaseContent ) {
				releases.push( {
					version: releaseVersion,
					date: releaseDate,
					content: releaseContent[ 0 ],
				} );
			}
		}
	}

	// Sort releases by version number (newest first)
	releases.sort( ( a, b ) => {
		const aParts = a.version.split( '.' ).map( Number );
		const bParts = b.version.split( '.' ).map( Number );

		for ( let i = 0; i < 3; i++ ) {
			if ( aParts[ i ] !== bParts[ i ] ) {
				return bParts[ i ] - aParts[ i ]; // Descending order
			}
		}

		return 0;
	} );

	// Format the changelog entries for readme.txt
	// 1. Increase the header level by one (add one more #)
	// 2. Remove the square brackets from the version numbers.
	// 3. Remove PR numbers like [#123] from the ends of lines.
	let formattedChangelog = releases
		.map( ( release ) => {
			return release.content
				.replace( /### /g, '#### ' )
				.replace(
					`## [${ release.version }] - ${ release.date }`,
					`### ${ release.version } - ${ release.date }`
				)
				.replace( /\s+\[#\d+\]$/gm, '' )
				.trim();
		} )
		.join( '\n\n' );

	// Find the changelog section in readme.txt
	const changelogSectionRegex = /== Changelog ==([\s\S]*?)(?=== |$)/;
	const changelogSection = readmeContent.match( changelogSectionRegex );

	if ( ! changelogSection ) {
		console.error( 'No changelog section found in readme.txt' );
		process.exit( 1 );
	}

	// At the bottom of the changelog section, add a link to the full changelog on GitHub.
	formattedChangelog +=
		'\n\nSee full Changelog on [GitHub](https://github.com/Automattic/wordpress-activitypub/blob/trunk/CHANGELOG.md).';

	// Update the readme.txt with the new changelog section
	const updatedReadmeContent = readmeContent.replace(
		changelogSectionRegex,
		`== Changelog ==\n\n${ formattedChangelog }\n\n`
	);

	fs.writeFileSync( 'readme.txt', updatedReadmeContent );
	console.log(
		`Updated readme.txt with changelog entries for version ${ version } and other entries from major version ${ majorVersion }`
	);
};

const updateReadmeWithUpgradeNotice = ( version ) => {
	return new Promise( ( resolve ) => {
		rl.question( '\nWould you like to add an upgrade notice for this version? (y/n): ', ( answer ) => {
			if ( answer.toLowerCase() === 'y' || answer.toLowerCase() === 'yes' ) {
				rl.question( 'Enter the upgrade notice (leave empty to skip): ', ( notice ) => {
					if ( notice.trim() ) {
						// Read the readme.txt file
						let readmeContent = fs.readFileSync( 'readme.txt', 'utf8' );

						// Check if Upgrade Notice section already exists
						const upgradeNoticeSectionRegex = /== Upgrade Notice ==([\s\S]*?)(?=== |$)/;
						const upgradeNoticeSection = readmeContent.match( upgradeNoticeSectionRegex );

						// Create the new upgrade notice section
						const newUpgradeNotice = `== Upgrade Notice ==\n\n= ${ version } =\n\n${ notice.trim() }\n\n`;

						if ( upgradeNoticeSection ) {
							// Replace the entire existing Upgrade Notice section
							readmeContent = readmeContent.replace( upgradeNoticeSectionRegex, newUpgradeNotice );
						} else {
							// Create a new Upgrade Notice section at the end of the file
							readmeContent += `\n\n${ newUpgradeNotice }`;
						}

						fs.writeFileSync( 'readme.txt', readmeContent );
						console.log( `Added upgrade notice for version ${ version } to readme.txt` );
					} else {
						console.log( 'No upgrade notice added.' );
					}
					resolve();
				} );
			} else {
				console.log( 'Skipping upgrade notice.' );
				resolve();
			}
		} );
	} );
};

async function createRelease() {
	// Start by generating the changelog.
	// The changelog will automatically pick a version
	// based off each changelog entry's provided significance.
	const version = await generateChangelog();

	const currentBranch = execWithOutput( 'git rev-parse --abbrev-ref HEAD' );

	// Check if release branch already exists
	const branchExists = execWithOutput( `git branch --list release/${ version }` );
	if ( branchExists ) {
		console.error( `\nError: Branch release/${ version } already exists.` );
		// Return to original branch if we're not already there
		if ( currentBranch !== execWithOutput( 'git rev-parse --abbrev-ref HEAD' ) ) {
			exec( `git checkout ${ currentBranch }` );
		}
		process.exit( 1 );
	}

	// Create and checkout release branch
	const branchName = `release/${ version }`;
	exec( `git checkout -b ${ branchName }` );

	// Update version numbers in files
	updateVersionInFile( 'activitypub.php', version, [
		{
			search: /Version: \d+\.\d+\.\d+/,
			replace: `Version: ${ version }`,
		},
		{
			search: /ACTIVITYPUB_PLUGIN_VERSION', '\d+\.\d+\.\d+/,
			replace: `ACTIVITYPUB_PLUGIN_VERSION', '${ version }`,
		},
	] );

	updateVersionInFile( 'readme.txt', version, [
		{
			search: /Stable tag: \d+\.\d+\.\d+/,
			replace: `Stable tag: ${ version }`,
		},
		{
			search: /= Unreleased =/,
			replace: `= ${ version } =`,
		},
	] );

	// Update the changelog section in readme.txt
	updateReadmeWithChangelog( version );

	// Prompt for and update the upgrade notice section in readme.txt
	await updateReadmeWithUpgradeNotice( version );

	updateVersionInFile( 'includes/class-migration.php', version, [
		{
			search: /(?<!\*[\s\S]{0,50})(?<=version_compare\s*\(\s*\$version_from_db,\s*')unreleased(?=',\s*['<=>])/g,
			replace: ( match ) => match.replace( /unreleased/i, version ),
		},
	] );

	const phpFiles = execWithOutput( 'find . -name "*.php"' ).split( '\n' );

	phpFiles.forEach( ( filePath ) => {
		updateVersionInFile( filePath, version, [
			{
				search: /@since unreleased/gi,
				replace: `@since ${ version }`,
			},
			{
				search: /@deprecated unreleased/gi,
				replace: `@deprecated ${ version }`,
			},
			{
				search: /(?<=_deprecated_(?:function|class|constructor|file|argument|hook)\s*\(\s*.*?,\s*')unreleased(?=')/gi,
				replace: ( match ) => match.replace( /unreleased/i, version ),
			},
			{
				search: /(?<=_doing_it_wrong\s*\(\s*.*?,\s*'.*?',\s*')unreleased(?=')/gi,
				replace: ( match ) => match.replace( /unreleased/i, version ),
			},
			{
				search: /(?<=\b(?:apply_filters_deprecated|do_action_deprecated)\s*\(\s*'.*?'\s*,\s*array\s*\(.*?\)\s*,\s*')unreleased(?=['"],\s*['"])/gi,
				replace: ( match ) => match.replace( /unreleased/i, version ),
			},
		] );
	} );

	// Stage and commit changes
	exec( 'git add .' );
	exec( `git commit -m "Release ${ version }"` );

	// Push to remote
	exec( `git push -u origin ${ branchName }` );

	// Get current user's GitHub username
	const currentUser = execWithOutput( 'gh api user --jq .login' );

	// Create PR using GitHub CLI and capture the URL
	console.log( '\nCreating PR...' );
	const prUrl = execWithOutput(
		`gh pr create --title "Release ${ version }" --body "Release version ${ version }" --base trunk --head ${ branchName } --reviewer "Automattic/fediverse" --assignee "${ currentUser }" --label "Release"`
	);

	// Open PR in browser if a URL was returned
	if ( prUrl && prUrl.includes( 'github.com' ) ) {
		exec( `open ${ prUrl }` );
	}
}

async function release() {
	try {
		// Check if gh CLI is installed
		try {
			execSync( 'gh --version', { stdio: 'ignore' } );
		} catch ( error ) {
			console.error( 'GitHub CLI (gh) is not installed. Please install it first:' );
			console.error( 'https://cli.github.com/' );
			process.exit( 1 );
		}

		// Ensure we're on trunk branch and up to date
		exec( 'git checkout trunk' );
		exec( 'git pull origin trunk' );

		await createRelease();
	} catch ( error ) {
		console.error( 'An error occurred:', error );
		process.exit( 1 );
	} finally {
		rl.close();
	}
}

release();
