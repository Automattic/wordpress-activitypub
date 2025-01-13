#!/usr/bin/env node

const { execSync } = require('child_process');
const readline = require('readline');
const { URL } = require('url');
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

const getRepoInfo = () => {
  const remoteUrl = execSync('git remote get-url origin').toString().trim();
  const match = remoteUrl.match(/github\.com[:/](.+?)(?:\.git)?$/);
  return match ? match[1] : null;
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

async function release() {
  try {
    // Get new version first
    const version = await question('\nWhat version would you like to release? (x.x.x): ');
    if (!/^\d+\.\d+\.\d+$/.test(version)) {
      console.error('Invalid version format. Please use x.x.x');
      process.exit(1);
    }

    // Ensure we're on trunk branch and up to date
    exec('git checkout trunk');
    exec('git pull origin trunk');

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

    // Update CHANGELOG.md
    updateChangelog(version);

    // Stage and commit changes
    exec('git add .');
    exec(`git commit -m "Release ${version}"`);

    // Run tests after version update
    console.log('\nRunning tests...');
    exec('npm run env-test');

    // Push to remote
    exec(`git push -u origin ${branchName}`);

    // Generate PR URL with clickable link
    const repoPath = getRepoInfo();
    if (!repoPath) {
      console.error('Could not determine repository URL');
      process.exit(1);
    }

    const prUrl = `https://github.com/${repoPath}/compare/trunk...${branchName}?expand=1&title=Release%20${version}&labels=release`;
    console.log('\nOpening draft PR in your browser...');
    exec(`open ${prUrl}`);
    console.log('\nPR URL for reference:');
    console.log('----------------------------------------');
    console.log(prUrl);
    console.log('----------------------------------------');

  } catch (error) {
    console.error('An error occurred:', error);
    process.exit(1);
  } finally {
    rl.close();
  }
}

release();
