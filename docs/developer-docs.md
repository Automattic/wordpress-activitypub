# ActivityPub Plugin Developer Documentation

## Table of Contents
- [Introduction](#introduction)
- [Extending the Settings Interface](#extending-the-settings-interface)

## Introduction
This documentation provides information for developers who want to extend and build upon the ActivityPub plugin. Whether you're developing a complementary plugin or integrating ActivityPub features into your existing WordPress plugin, this guide will help you understand the available hooks and customization options.

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
