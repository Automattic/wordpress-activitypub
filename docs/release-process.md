# Release Process

This document outlines the process for creating new releases of the WordPress ActivityPub plugin. The process differs slightly between major/minor releases and patch releases.

## Major and Minor Releases

Major and minor releases follow the same release process. These releases are created from the `trunk` branch.

### Steps

1. **Generate Version Bump PR**
   - Use the release script to automatically generate a version bump pull request:
     ```bash
     # From the plugin root directory
     npm run release
     ```
   - The script will:
     - Determine the new version number based on the unreleased changelog entries.
     - Update version numbers in relevant files
     - Update `CHANGELOG.md` and `readme.txt` with the changelog entries
     - Create a new branch
     - Commit changes
     - Push to GitHub
     - Create a pull request

2. **Review and Merge**
   - Review the generated PR to ensure all version numbers and changelog entries are correct.
   - Once approved, merge the PR into `trunk`.

3. **Create Release**
   - On GitHub, navigate to the main page of the repository.
   - To the right of the list of files, click **Releases**.
   - At the top of the page, click **Draft a new release**. 
   - To choose a tag for the release, select the **Choose a tag** dropdown menu.
     - Type the version number for your release, then click **Create new tag**.
   - Select the **Target** dropdown menu, then click `trunk`.
   - Select the **Previous tag** dropdown menu, then click the tag that identifies the previous release.
   - Above the description field, click **Generate release notes**.
   - If you're ready to publicize your release, click **Publish release**.
   ![Major/minor release UI](images/release-ui-major.png)

## Patch Releases

Patch releases require a more manual process as they need to be created from the previous release branch.

### Steps

1. **Restore Release Branch**
   - Locate the most recent release branch (for `5.3.0` it was `release/5.3.0`, created via [#1371](https://github.com/Automattic/wordpress-activitypub/pull/1371)).
   - Click "Restore branch" to recreate it.
   - Locally, checkout that release branch you just restored: `git fetch origin release/5.3.0 && git checkout release/5.3.0`

2. **Cherry-pick Changes into the release branch**
   - Identify merge commits from `trunk` that need to be included. You can find them at the bottom of each PR:

<img width="904" alt="image" src="https://github.com/user-attachments/assets/4c49c5bd-928c-44d2-b64b-39454baa8d9d" />

   - Cherry-pick each merge commit into this branch:
     ```bash
     # Checkout the release branch.
     git checkout release/5.3.0

     # Cherry-pick a merge commit.
     git cherry-pick -m 1 <commit-hash>
     ```
     > Note: The `-m 1` flag is required when cherry-picking merge commits. Merge commits have two parent commits - the first parent (`-m 1`) is the target branch of the original merge (usually the main branch), and the second parent (`-m 2`) is the source branch that was being merged. We use `-m 1` to tell Git to use the changes as they appeared in the main branch.

   - Resolve merge conflicts that may come up as you cherry-pick commits.

3. **Update changelog and version numbers**
   - Run `composer changelog:write`. It will update `CHANGELOG.md` with the changelog entries you cherry-picked, and will give you a version number for that release.
   - Edit `readme.txt` to paste the changelog entries from `CHANGELOG.md` into the `== Changelog ==` section.
   - The release script doesn't support releasing patch versions, so you'll need to manually update version numbers in the different files (`activitypub.php`, `readme.txt`, and files that may have been changed to introduce an `unreleased` text).

4. **Review and push your changes**
   - Review your changes locally, and `git push` to push your changes to the remote.

5. **Create Release**
   - On GitHub, navigate to the main page of the repository.
   - To the right of the list of files, click **Releases**.
   - At the top of the page, click **Draft a new release**. 
   - To choose a tag for the release, select the **Choose a tag** dropdown menu.
     - Type the version number for your release, then click **Create new tag**.
   - Select the **Target** dropdown menu, then click the branch that contains the patches you want to release.
   - Select the **Previous tag** dropdown menu, then click the tag that identifies the previous release.
   - Above the description field, click **Generate release notes**.
   - If you're ready to publicize your release, click **Publish release**.
   ![Patch release UI](images/release-ui-patch.png)
