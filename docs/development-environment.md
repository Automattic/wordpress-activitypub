# Setting up your environment

## Overview

In order to start developing the ActivityPub plugin you want to have access to a WordPress installation where you can install the plugin and work on it.

To do that you need to set up a WordPress site and give it the ability to run your local build of the ActivityPub plugin code repository.

There are several ways to achieve this, listed in the next section.

## Get started with development

### Clone the repository

Before you get started, we recommend that you set up a public SSH key setup with GitHub, which is more secure than saving your GitHub credentials in your keychain. There are more details about [setting up a public key on GitHub.com](https://help.github.com/en/articles/adding-a-new-ssh-key-to-your-github-account).

Fork this repository to your own GitHub account and clone it to your local machine.

### Local development

To run the ActivityPub plugin in a local WordPress environment, you can use `wp-env` or Docker.

### wp-env

`wp-env` lets you easily set up a local WordPress environment for building and testing plugins and themes. It’s simple to install and requires no configuration.

To get started, install `wp-env` by running the following command in the root of the ActivityPub plugin directory:

```bash
npm install
```

Then, start the local environment by running:

```bash
npm run env-start
```

This will start a local WordPress environment with the ActivityPub plugin installed and activated. You can open the WordPress site in your browser by visiting `http://localhost`.

To stop the environment, run:

```bash
npm run env-stop
```

### Composer

Composer is used to install development dependencies for the ActivityPub plugin, to run unit tests, and to manage changelog entries.

To install Composer, follow the instructions on the [Composer website](https://getcomposer.org/).

Once Composer is available on your machin, you can install dependencies for the project like so:

```bash
composer install
```

## Running Tests

You can now run the test suite using either npm or composer:

```bash
# Using npm
npm run env-start
npm run env-test

# Using composer
wp-env start
composer run test:wp-env
```

### PHPUnit Arguments

Both commands support additional PHPUnit arguments. Add them after `--`:

```bash
# Run a specific test
npm run env-test -- --filter=test_migrate_to_4_1_0

# Run tests in a specific file
npm run env-test -- phpunit/tests/includes/class-test-migration.php

# Run tests with a specific group
npm run env-test -- --group=migration

# Run tests with verbose output
npm run env-test -- --verbose

# The same works with composer
composer run test:wp-env -- --filter=test_migrate_to_4_1_0
```

Common PHPUnit arguments:
- `--filter`: Run tests matching a pattern
- `--group`: Run tests with a specific @group annotation
- `--exclude-group`: Exclude tests with a specific @group annotation
- `--verbose`: Output more verbose information
- `--debug`: Display debugging information

### Code Coverage Reports

The coverage configuration is already set up in `phpunit.xml.dist` to analyze the code in the `includes` directory. To generate code coverage reports, you'll need to start wp-env with Xdebug enabled for coverage:

```bash
# Start the environment with Xdebug enabled.
npm run env-start -- --xdebug=coverage
```
```bash
# Run tests with code coverage.
npm run env-test -- --coverage-text
```

The above will display a text-based coverage report in your terminal. For a more detailed HTML report:

```bash
# Generate HTML coverage report in Docker.
npm run env-test -- --coverage-html ./coverage
```
```bash
# Open the coverage report in your default browser (macOS).
open coverage/index.html
```

The HTML report will be generated directly in the `coverage` directory in your local filesystem. The `index.html` file can then be opened in a browser, showing a detailed analysis of which lines of code are covered by tests.
