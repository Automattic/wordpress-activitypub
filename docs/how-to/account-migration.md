# Account Migration

Account migration in the Fediverse allows you to move your identity from one platform to another while bringing your followers with you. When you migrate, your followers are automatically notified and redirected to follow your new account.

The WordPress ActivityPub plugin supports migrating **to** and **from** WordPress, as well as moving between WordPress sites.

## Moving from Mastodon to WordPress

This is the most common migration scenario: you have an existing Mastodon account and want to make your WordPress blog your primary Fediverse identity.

### Step 1: Add your Mastodon account as an alias in WordPress

1. Log in to your WordPress site.
2. Go to **Users > Profile** (or **Settings > ActivityPub** for the Blog profile).
3. Scroll down to the **Account Aliases** field.
4. Enter your Mastodon profile URL (e.g. `https://mastodon.social/@username` or `@username@mastodon.social`).
5. Save your profile.

This tells the Fediverse that your WordPress account is also known as your Mastodon account, which is required for the migration to be accepted.

### Step 2: Initiate the move on Mastodon

1. Log in to your Mastodon account.
2. Go to **Preferences > Account > Move to a different account**.
3. Enter your WordPress ActivityPub handle (e.g. `@username@yourblog.com`) in the "Handle of the new account" field.
4. Enter your Mastodon password to confirm.

Mastodon will send a `Move` activity to all your followers, telling them to follow your WordPress account instead. Compatible Fediverse servers will automatically transfer the follow.

## Moving from WordPress to Mastodon

If you want to move away from your WordPress blog to a Mastodon (or other Fediverse) account, the process works in reverse.

### Step 1: Add your WordPress account as an alias on Mastodon

1. Log in to your Mastodon account.
2. Go to **Preferences > Account > Moving from a different account**.
3. Click **Create an account alias**.
4. Enter your WordPress ActivityPub handle (e.g. `@username@yourblog.com`).
5. Save the alias.

### Step 2: Initiate the move from WordPress

There is currently no admin UI for this. You can trigger the move using WP-CLI:

```bash
wp activitypub move https://yourblog.com/author/username https://mastodon.social/users/username
```

The plugin will verify that the target account (Mastodon) has your WordPress account listed in its aliases, then send a `Move` activity to all your followers.

After the move, your WordPress ActivityPub profile will display a `movedTo` notice pointing followers to your new Mastodon account.

## Moving between two WordPress sites

If you are migrating from one WordPress site to another (e.g. changing domains or consolidating blogs), the process is the same as any other migration.

### Step 1: Add the old blog as an alias on the new blog

1. On the **new** WordPress site, go to **Users > Profile** (or **Settings > ActivityPub** for the Blog profile).
2. In the **Account Aliases** field, add the URL of your old blog's ActivityPub profile (e.g. `@you@oldblog.com`).
3. Save.

### Step 2: Initiate the move from the old blog

On the **old** WordPress site, run:

```bash
wp activitypub move https://oldblog.com/author/you https://newblog.com/author/you
```

Your followers will be notified and redirected to your new blog.

## How it works

Account migration uses the ActivityPub `Move` activity type ([FEP-7628](https://codeberg.org/fediverse/fep/src/branch/main/fep/7628/fep-7628.md)). The process relies on two actor properties:

- **`alsoKnownAs`** — A list of other account URLs that belong to the same person. The *target* account must list the *origin* account here before a move can happen. This is the "account aliases" field in the UI.
- **`movedTo`** — Set on the *origin* account after migration, pointing to the *target*. This tells the Fediverse where the account has moved.

When a Fediverse server receives a `Move` activity, it verifies that:

1. The target account lists the origin in its `alsoKnownAs`.
2. The origin account has `movedTo` set to the target.

Only if both checks pass will the server transfer followers from the old account to the new one.

## Important notes

- **Migration moves followers, not content.** Your posts, media, and other content stay on the original platform. Only the follower relationships are transferred.
- **Not all servers support Move.** Most Mastodon-compatible servers do, but some smaller Fediverse platforms may not process `Move` activities.
- **Migration is essentially one-way.** While you can technically migrate back, followers who already transferred may not automatically follow back to the original account.
- **Add aliases before initiating the move.** The target account must have the origin listed in its aliases, or the move will be rejected.
