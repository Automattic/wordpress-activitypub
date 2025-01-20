#!/usr/bin/env node

const { execSync } = require('child_process');
const readline = require('readline');
const fs = require('fs');

const rl = readline.createInterface({
	input: process.stdin,
	output: process.stdout
});

const question = (query) => new Promise((resolve) => rl.question(query, resolve));

const exec = (command) => {
	try {
		return execSync(command, { stdio: 'inherit' });
	} catch (error) {
		console.error(`Error executing command: ${command}`);
		process.exit(1);
	}
};

const execWithOutput = (command) => {
	try {
		return execSync(command, { stdio: 'pipe' }).toString().trim();
	} catch (error) {
		console.error(`Error executing command: ${command}`);
		process.exit(1);
	}
};

const updateVersionInFile = (filePath, version, patterns) => {
	let content = fs.readFileSync(filePath, 'utf8');

	patterns.forEach(({ search, replace }) => {
		content = content.replace(
			search,
			typeof replace === 'function' ? replace(version) : replace
		);
	});

	fs.writeFileSync(filePath, content);
};

const updateChangelog = (version) => {
	const date = new Date().toISOString().split('T')[0];
	const content = fs.readFileSync('CHANGELOG.md', 'utf8');

	// Update the Unreleased section
	let updated = content.replace(
		/## \[Unreleased\]/,
		`## [${version}] - ${date}`
	);

	// Update the comparison links at the bottom
	const prevVersion = content.match(/compare\/(\d+\.\d+\.\d+)\.\.\.trunk/)[1];
	updated = updated.replace(
		/\[Unreleased\]: .*\n/,
		`[Unreleased]: https://github.com/Automattic/wordpress-activitypub/compare/${version}...trunk\n`
	);

	// Add the new version comparison link
	const newVersionLink = `[${version}]: https://github.com/Automattic/wordpress-activitypub/compare/${prevVersion}...${version}\n`;
	updated = updated.replace(
		/<!-- Add new release below and update "Unreleased" link -->\n/,
		`<!-- Add new release below and update "Unreleased" link -->\n${newVersionLink}`
	);

	fs.writeFileSync('CHANGELOG.md', updated);
};

async function createRelease(version) {
	// Create and checkout release branch
	const branchName = `release/${version}`;
	exec(`git checkout -b ${branchName}`);

	// Update version numbers in files
	updateVersionInFile('activitypub.php', version, [
		{
			search: /Version: \d+\.\d+\.\d+/,
			replace: `Version: ${version}`
		},
		{
			search: /ACTIVITYPUB_PLUGIN_VERSION', '\d+\.\d+\.\d+/,
			replace: `ACTIVITYPUB_PLUGIN_VERSION', '${version}`
		}
	]);

	updateVersionInFile('readme.txt', version, [
		{
			search: /Stable tag: \d+\.\d+\.\d+/,
			replace: `Stable tag: ${version}`
		},
		{
			search: /= Unreleased =/,
			replace: `= ${version} =`
		}
	]);

	updateVersionInFile('includes/class-migration.php', version, [
		{
			search: /version_compare\([^,]+,\s*['"]unreleased['"]/gi,
			replace: (match) => match.replace(/unreleased/i, version)
		}
	]);

	// Update CHANGELOG.md
	updateChangelog(version);

	// Stage and commit changes
	exec('git add .');
	exec(`git commit -m "Release ${version}"`);

	// Push to remote
	exec(`git push -u origin ${branchName}`);

	// Get current user's GitHub username
	const currentUser = execWithOutput('gh api user --jq .login');

	// Create PR using GitHub CLI and capture the URL
	console.log('\nCreating draft PR...');
	const prUrl = execWithOutput(`gh pr create --title "Release ${version}" --body "Release version ${version}" --base trunk --head ${branchName} --draft --reviewer "Automattic/fediverse" --assignee "${currentUser}" --json url --jq .url`);

	// Open PR in browser
	exec(`open ${prUrl}`);
}

async function release() {
	try {
		// Check if gh CLI is installed
		try {
			execSync('gh --version', { stdio: 'ignore' });
		} catch (error) {
			console.error('GitHub CLI (gh) is not installed. Please install it first:');
			console.error('https://cli.github.com/');
			process.exit(1);
		}

		// Store current branch
		const currentBranch = execWithOutput('git rev-parse --abbrev-ref HEAD');

		while (true) {
			// Get new version
			const version = await question('\nWhat version would you like to release? (x.x.x): ');
			if (!/^\d+\.\d+\.\d+$/.test(version)) {
				console.error('Invalid version format. Please use x.x.x');
				continue;
			}

			// Check if release branch already exists
			const branchExists = execWithOutput(`git branch --list release/${version}`);
			if (branchExists) {
				console.error(`\nError: Branch release/${version} already exists.`);
				// Return to original branch if we're not already there
				if (currentBranch !== execWithOutput('git rev-parse --abbrev-ref HEAD')) {
					exec(`git checkout ${currentBranch}`);
				}
				continue;
			}

			// Ensure we're on trunk branch and up to date
			exec('git checkout trunk');
			exec('git pull origin trunk');

			await createRelease(version);
			break;
		}

	} catch (error) {
		console.error('An error occurred:', error);
		process.exit(1);
	} finally {
		rl.close();
	}
}

release();
