# ActivityPub Plugin Developer Documentation

## Table of Contents
- [Introduction](#introduction)
- [Snippets](#snippets)
- [Extending the Settings Interface](#extending-the-settings-interface)
- [Signing Outbound Requests](#signing-outbound-requests)

## Introduction
This documentation provides information for developers who want to extend and build upon the ActivityPub plugin. Whether you're developing a complementary plugin or integrating ActivityPub features into your existing WordPress plugin, this guide will help you understand the available hooks and customization options.

## Snippets

The repository includes a collection of community-contributed [snippets](../snippets/) that extend or customize its behavior. Snippets are small, self-contained WordPress plugins that hook into the ActivityPub plugin to add or modify functionality.

This follows a concept similar to WordPress' [feature plugins](https://make.wordpress.org/core/handbook/about/release-cycle/features-as-plugins/) -- experimental ideas are developed as snippets, and mature ones may eventually be integrated into the main plugin.

### Using Snippets

1. Copy the desired snippet folder from `snippets/` into your `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin.

Alternatively, copy the snippet's main PHP file to `wp-content/mu-plugins/` for automatic activation.

### Contributing Snippets

See the [Snippets README](../snippets/README.md) for guidelines on contributing new snippets.

## Extending the Settings Interface

### Adding Custom Settings Tabs
The ActivityPub plugin provides a flexible settings interface that can be extended with custom tabs. This allows you to seamlessly integrate your plugin's settings within the ActivityPub settings page.

#### Using the `activitypub_admin_settings_tabs` Filter
The `activitypub_admin_settings_tabs` filter allows you to add new tabs to the settings interface. Each tab consists of a label and a template file path.

##### Example Usage:
```php
/**
 * Adds a custom tab to the ActivityPub settings.
 *
 * @param array $tabs The existing tabs array.
 * @return array The modified tabs array.
 */
function my_custom_settings_tab( $tabs ) {
    $tabs['my-custom-tab'] = array(
        'label'    => __( 'My Custom Tab', 'my-plugin-textdomain' ),
        'template' => MY_PLUGIN_DIR . 'templates/custom-settings.php',
    );

    return $tabs;
}
add_filter( 'activitypub_admin_settings_tabs', 'my_custom_settings_tab' );
```

##### Parameters:
The tab configuration array requires two keys:
- `label`: (string) The displayed name of the tab (should be translatable).
- `template`: (string) Absolute path to the template file that will be loaded when the tab is active.

#### Best Practices
1. **Namespace Your Tab Keys**: Use unique identifiers for your tab keys to avoid conflicts with other plugins.
2. **Template Location**: Store your template files in your plugin's directory structure.
3. **Security**: Always implement proper security checks in your template files.
4. **Internationalization**: Make your labels and template content translatable.
5. **Asset Loading**: If your tab requires specific CSS or JavaScript, enqueue them conditionally:
```php
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'settings_page_activitypub' !== $hook ) {
        return;
    }
    
    // Check if we're on your custom tab.
    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'welcome';
    if ( 'my-custom-tab' === $current_tab ) {
        wp_enqueue_script( 'my-custom-tab-script' );
        wp_enqueue_style( 'my-custom-tab-style' );
    }
} );
```

## Signing Outbound Requests

A companion plugin that constructs and delivers its own Activities can reuse the plugin's HTTP-signing implementation without adopting the Outbox model. Pass the sender's public key identifier and private key as the `key_id` and `private_key` request arguments, and the plugin's `http_request_args` filter adds signature headers before WordPress sends the request:

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

The plugin chooses the signature format based on the site's RFC 9421 setting and the recipient's known support. If an RFC 9421-signed request receives a 4xx response, the plugin re-signs it with the older Draft Cavage format and retries once. Callers do not choose the format and should not depend on the format a given request uses.

Both arguments remain in the request arguments after signing because that retry needs them once the response returns. They are therefore visible to other callbacks on `http_request_args` and `http_response`, and to anything that logs request arguments. Resolve key material only for the duration of the send, and treat the request arguments as readable by other plugins.

The companion remains responsible for:

- validating recipient URLs;
- selecting recipients;
- owning its delivery queue and retry policy; and
- resolving private key material only while sending, without persisting it in transport rows or logs.
