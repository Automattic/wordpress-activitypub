# Life Cycle of a Pull Request

When you’re first starting out, your natural instinct when creating a new feature will be to create a local feature branch, and start building away. If you start doing this, *stop*, take your hands off the keyboard, grab a coffee and read on. :)

**It’s important to break your feature down into small pieces first**, each piece should become its own pull request.

## Creating a pull request

Once you know what the first small piece of your feature will be, follow this general process while working:

### Create a new branch

#### Branch Naming Scheme

All changes should be developed in a new branch created from the `trunk` branch.

Branches use the following naming conventions:

* `add/{something}` -- When you are adding a completely new feature
* `update/{something}` -- When you are iterating on an existing feature
* `fix/{something}` -- When you are fixing something broken in a feature
* `try/{something}` -- When you are trying out an idea and want feedback

For example, you can run: `git checkout trunk` and then `git checkout -b fix/whatsits` to create a new `fix/whatsits` branch off of `origin/trunk`.

The ActivityPub repo uses the following "reserved" branch name conventions:

* `release/{X.Y.Z}` -- Used for the release process.

### Develop, commit

1. Start developing and pushing out commits to your new branch.
    - Push your changes out frequently and try to avoid getting stuck in a long-running branch or a merge nightmare. Smaller changes are much easier to review and to deal with potential conflicts.
    - Don’t be afraid to change, [squash](http://gitready.com/advanced/2009/02/10/squashing-commits-with-rebase.html), and rearrange commits or to force push - `git push --force-with-lease origin fix/something-broken`. Keep in mind, however, that if other people are committing on the same branch then you can mess up their history. You are perfectly safe if you are the only one pushing commits to that branch.
    - Squash minor commits such as typo fixes or [fixes to previous commits](http://fle.github.io/git-tip-keep-your-branch-clean-with-fixup-and-autosquash.html) in the pull request.
1. If you have [Composer installed](https://getcomposer.org/), you can run `composer install` and `composer lint [directory or files updated]` to check your changes against WordPress coding standards. Please ensure your changes respect current coding standards.
1. If you end up needing more than a few commits, consider splitting the pull request into separate components. Discuss in the new pull request and in the comments why the branch was broken apart and any changes that may have taken place that necessitated the split. Our goal is to catch early in the review process those pull requests that attempt to do too much.

### Create a changelog entry

Before you push your changes, make sure you create a changelog entry. Those entries provide useful information to end-users and other developers about the changes you've made, and how they can impact their WordPress site.

#### How do I create a changelog entry?

You can use the `composer changelog:add` command to create a changelog entry, and then follow the prompt and commit the changelog file that was created for you.

### Create your Pull Request

When you feel that you are ready for a formal review or for merging into `trunk`, push your branch to GitHub and open a Pull Request.

As you open your Pull Request, make sure you check the following:
    - Make sure your Pull Request includes a changelog entry, or add the "Skip Changelog" label to the PR.
    - Make sure all required checks listed at the bottom of the Pull Request are passing.
    - Make sure your branch merges cleanly and consider rebasing against `trunk` to keep the branch history short and clean.
    - If there are visual changes, add before and after screenshots in the pull request comments.
    - If possible add unit tests,
    - Provide helpful instructions for the reviewer so they can test your changes. This will help speed up the review process.

