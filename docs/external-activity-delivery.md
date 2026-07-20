# External Activity delivery

Companion plugins can reuse the ActivityPub plugin's HTTP-signing implementation for their own WordPress HTTP requests. This lets a companion own its Activity construction and outbound delivery flow without adopting the plugin's Outbox model.

Pass the sender's public key identifier and private key as the `key_id` and `private_key` request arguments. The plugin's `http_request_args` filter adds the appropriate signature headers before WordPress sends the request:

```php
$response = wp_safe_remote_post(
	$recipient_inbox,
	array(
		'body'        => wp_json_encode( $activity ),
		'headers'     => array( 'Content-Type' => 'application/activity+json' ),
		'data_format' => 'body',
		'key_id'      => $sender_key_id,
		'private_key' => $sender_private_key,
	)
);
```

The companion remains responsible for:

- validating recipient URLs;
- selecting recipients;
- owning its delivery queue and retry policy; and
- resolving private key material only while sending, without persisting it in transport rows or logs.
