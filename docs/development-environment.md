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

### Docker

If you prefer to use Docker, you can use the `docker-compose.yml` file in the root of the ActivityPub plugin directory.

To start the environment, run:

```bash
docker-compose up -d
```

This will start a local WordPress environment with the ActivityPub plugin installed and activated.

You can open the WordPress site in your browser by visiting `http://localhost:8076`.
