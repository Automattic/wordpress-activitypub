#!/usr/bin/env node

const { execSync } = require('child_process');
const readline = require('readline');
const fs = require('fs');

const rl = readline.createInterface({
	input: process.stdin,
	output: process.stdout
});

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

const generateChangelog = async () => {
	// Run the changelog generation command
	try {
		execSync('composer changelog:write', { stdio: 'ignore' });
	} catch (error) {
		console.error('Error generating changelog:');
		console.error(error);
		process.exit(1);
	}

	// Grab the version from the generated changelog
	const content = fs.readFileSync('CHANGELOG.md', 'utf8');
	const version = content.match(/## \[(\d+\.\d+\.\d+)\] - \d{4}-\d{2}-\d{2}/)[1];

	if (!version) {
		console.error('No version found in CHANGELOG.md');
		process.exit(1);
	}

	return version;
};

async function createRelease() {
	// Start by generating the changelog.
	// The changelog will automatically pick a version
	// based off each changelog entry's provided significance.
	const version = await generateChangelog();

	const currentBranch = execWithOutput('git rev-parse --abbrev-ref HEAD');

	// Check if release branch already exists
	const branchExists = execWithOutput(`git branch --list release/${version}`);
	if (branchExists) {
		console.error(`\nError: Branch release/${version} already exists.`);
		// Return to original branch if we're not already there
		if (currentBranch !== execWithOutput('git rev-parse --abbrev-ref HEAD')) {
			exec(`git checkout ${currentBranch}`);
		}
		process.exit(1);
	}

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
			search: /(?<!\*[\s\S]{0,50})(?<=version_compare\s*\(\s*\$version_from_db,\s*')unreleased(?=',\s*['<=>])/g,
			replace: (match) => match.replace(/unreleased/i, version)
		}
	]);

	const phpFiles = execWithOutput('find . -name "*.php"').split('\n');

	phpFiles.forEach((filePath) => {
		updateVersionInFile(filePath, version, [
			{
				search: /@since unreleased/gi,
				replace: `@since ${version}`
			},
			{
				search: /@deprecated unreleased/gi,
				replace: `@deprecated ${version}`
			},
			{
				search: /(?<=_deprecated_function\s*\(\s*__METHOD__,\s*')unreleased(?=',\s*['<=>])/gi,
				replace: (match) => match.replace(/unreleased/i, version)
			},
			{
				search: /(?<=\bapply_filters_deprecated\s*\(\s*'.*?'\s*,\s*array\s*\(.*?\)\s*,\s*')unreleased(?=',\s*['<=>])/gi,
				replace: (match) => match.replace(/unreleased/i, version)
			}
		]);
	});

	// Stage and commit changes
	exec('git add .');
	exec(`git commit -m "Release ${version}"`);

	// Push to remote
	exec(`git push -u origin ${branchName}`);

	// Get current user's GitHub username
	const currentUser = execWithOutput('gh api user --jq .login');

	// Create PR using GitHub CLI and capture the URL
	console.log('\nCreating PR...');
	const prUrl = execWithOutput(`gh pr create --title "Release ${version}" --body "Release version ${version}" --base trunk --head ${branchName} --reviewer "Automattic/fediverse" --assignee "${currentUser}" --label "Release"`);

	// Open PR in browser if a URL was returned
	if (prUrl && prUrl.includes('github.com')) {
		exec(`open ${prUrl}`);
	}
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

		// Ensure we're on trunk branch and up to date
		// exec('git checkout trunk');
		// exec('git pull origin trunk');

		await createRelease();

	} catch (error) {
		console.error('An error occurred:', error);
		process.exit(1);
	} finally {
		rl.close();
	}
}

release();
