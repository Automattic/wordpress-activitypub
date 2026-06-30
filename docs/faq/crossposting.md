# Can the plugin crosspost to my existing Mastodon account?

Short answer: no, and that is a deliberate design choice rather than a missing feature.

This is one of the most common questions in the [support forum](https://wordpress.org/support/plugin/activitypub/), usually phrased as *"How do I connect the plugin to my Mastodon account?"* or *"Why doesn't my post show up on my Mastodon profile?"* The honest answer is that the plugin is not a crossposter and is not trying to be one. This page explains the difference, why crossposting will not be added, and what to do if mirroring to an existing account is genuinely what you want.

## Crossposter vs. native federation

These are two fundamentally different models, and the plugin sits firmly on the federation side.

| | Crossposter | *ActivityPub for WordPress* |
|---|---|---|
| **Where your identity lives** | On a third-party Mastodon server (`@you@mastodon.social`) | On your own site (`@you@example.com`) |
| **What gets sent** | A *copy* of each post, pushed to that account | The *original* post, fetched and followed directly |
| **Who your followers follow** | Your Mastodon account | Your website |
| **Where replies land** | On the remote account, separate from your site | Back on your site, as WordPress comments |
| **Accounts you need** | A separate Mastodon account, plus your site | Just your site |

A crossposter takes a copy of your WordPress post and publishes it to a *separate* account you already have somewhere else. Your identity, your followers, and every reply stay over there. WordPress is only a feed that pushes copies out.

*ActivityPub for WordPress* works the other way around. It turns your own site into a first-class member of the Fediverse, so `@you@example.com` **is** the account. People on Mastodon, Pixelfed, Threads, and other platforms follow your site directly, your posts are the originals instead of copies, and likes, boosts, and replies come back to your site as comments. You own both the identity and the conversation.

## Why the plugin will not crosspost

Crossposting and native federation pull in opposite directions, and bolting one onto the other reintroduces the very problems federation is meant to solve:

- **Split identity.** Your audience has to choose between following your site and following your Mastodon account, and neither one ever has the full picture.
- **Lost conversations.** Replies would land on the remote account, disconnected from the original post, so your site's comments section would never see them.
- **Duplicate content.** The same post would exist in two places, with two follower lists, two comment threads, and two link targets. That is confusing for readers and bad for search engines.

> [!NOTE]
> This is about keeping the plugin focused, not about a technical limitation. Crossposting to an account you control elsewhere is a real, useful workflow. It is simply a different job, and dedicated tools already do it well.

## "But I already have a big Mastodon account"

This is the most understandable reason people ask, and there are two legitimate paths depending on what you want your main presence to be.

**Option 1: Make your WordPress site your primary Fediverse identity.**
If you would rather consolidate around your own domain, you can bring your existing Mastodon followers with you. Mastodon's account-migration tool can move your followers to `@you@example.com`, after which new posts reach them natively, with replies arriving as comments. See the [Account Migration guide](../how-to/account-migration.md).

**Option 2: Keep your Mastodon account as your main presence and mirror articles to it.**
If your established Mastodon account is where you want your audience, that is a crossposting job. Use a [dedicated crossposter plugin](https://wordpress.org/plugins/search/mastodon/) built for exactly this. They work happily alongside this plugin or entirely on their own.

> [!TIP]
> You can run both plugins at once: *ActivityPub for WordPress* to give your site its own Fediverse presence, and a crossposter to also announce new posts from a separate Mastodon account. They do not conflict, because they operate on different accounts.

## Related

- [Account Migration](../how-to/account-migration.md) — move your Mastodon followers to your WordPress site.
- [What is "ActivityPub for WordPress"?](../../readme.txt) — the bigger picture of how the plugin federates your site.
