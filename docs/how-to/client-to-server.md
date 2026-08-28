# Connecting an ActivityPub client to your site

The plugin supports the ActivityPub Client-to-Server (C2S) protocol, allowing third-party apps to create, edit, and delete posts on your behalf.

## Enable C2S

Go to **Settings > ActivityPub > Advanced** and enable **Client-to-Server**.

## Connect a client

1. Open an ActivityPub C2S client (e.g. [box](https://github.com/go-ap/box)).
2. Point it at your site's actor URL (e.g. `https://example.com/author/yourname`).
3. The client will discover the OAuth endpoints from your actor profile and start the authorization flow.
4. Log in to WordPress when prompted and approve the request.
5. The client receives an access token and can now post to your outbox.

## Register a client manually

If your client does not support dynamic registration, you can register it from your WordPress profile:

1. Go to **Users > Profile > Connected Applications**.
2. Enter the application name and redirect URI, then click **Register Application**.
3. Copy the client ID shown in the notice and configure it in your client.

## Manage connected apps

The **Connected Applications** section on your profile page lists all active OAuth tokens. You can revoke individual tokens or all of them at once.

## Supported activities

Clients can POST these activities to your outbox:

- **Create** (Note or Article) — creates a WordPress post
- **Update** — updates an existing post
- **Delete** — trashes a post
- **Follow** / **Undo Follow** — manage who you follow
- **Like** — like a remote object
- **Announce** — boost/reblog a remote object

Notes are created with the "status" post format. Articles use the `name` field as the post title and `summary` as the excerpt. Hashtags in content are automatically saved as WordPress tags.
