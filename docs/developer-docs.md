# ActivityPub Plugin Developer Documentation

## Table of Contents
- [Introduction](#introduction)
- [Extending the Settings Interface](#extending-the-settings-interface)
- [JavaScript and CSS Development](#javascript-and-css-development)
  - [Block Development](#block-development)
  - [Feature-Based Asset Organization](#feature-based-asset-organization)
- [Development Workflow](#development-workflow)
  - [Available Commands](#available-commands)
  - [Build Process](#build-process)

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

## JavaScript and CSS Development

### Block Development

The ActivityPub plugin uses the WordPress Block Editor (Gutenberg) architecture for developing custom blocks. All block-related code is located in the `/src/blocks` directory.

#### Block Structure

Each block typically follows this directory structure:

```
/src/blocks/block-name/
├── block.json       # Block configuration.
├── edit.js          # Edit component.
├── index.js         # Block registration.
├── style.scss       # Block styles.
└── view.js          # Frontend JavaScript (optional).
```

#### Best Practices for Block Development

1. **Follow WordPress Coding Standards**: Adhere to WordPress JavaScript and CSS coding standards.
2. **Use WordPress Components**: Leverage existing WordPress components from `@wordpress/components`.
3. **Internationalization**: Make all user-facing strings translatable using the `__()` function.
4. **Accessibility**: Ensure your blocks are accessible to all users.
5. **Indentation**: Use tabs for indentation in SCSS files, not spaces.

### Feature-Based Asset Organization

The ActivityPub plugin organizes scripts and styles by feature rather than by file type. Each feature has its own directory containing all related assets.

#### Structure

```
/src/
├── blocks/          # Block-specific code and styles
│   ├── reply/       # Reply block
│   └── ...          # Other blocks
├── wp-admin/        # Admin-related features
│   ├── admin.js     # Admin JavaScript
│   ├── admin.scss   # Admin styles
│   └── ...          # Other admin features
├── feature-name/    # Any other feature
│   ├── script.js    # Feature JavaScript
│   ├── style.scss   # Feature styles
│   └── ...          # Other feature files
└── ...              # Other feature directories

/build/              # Compiled assets, organized by feature
```

#### Adding New Feature Assets

1. Create a new directory for your feature in the `/src/` directory:
   ```
   /src/your-feature/
   ```

2. Add your JavaScript and/or SCSS files to this directory:
   ```
   /src/your-feature/script.js
   /src/your-feature/style.scss
   ```

3. When you run `npm run build`, WordPress Scripts will:
   - Compile your JavaScript file to `/build/your-feature/script.js`.
   - Compile and minify your SCSS to `/build/your-feature/style.css`.
   - Generate source maps.

4. Enqueue your script and/or stylesheet in PHP, using the generated asset file:

```php
/**
 * Enqueue admin scripts.
 */
function activitypub_enqueue_admin_scripts() {
    // Load the asset file to get dependencies and version.
    $asset_data = include ACTIVITYPUB_PLUGIN_DIR . 'build/your-feature/script.asset.php';
    
    wp_enqueue_script(
        'activitypub-your-feature',
        plugins_url(
            'build/your-feature/script.js',
            ACTIVITYPUB_PLUGIN_FILE
        ),
        $asset_data['dependencies'],
        $asset_data['version'],
        true
    );
}
add_action( 'admin_enqueue_scripts', 'activitypub_enqueue_admin_scripts' );

/**
 * Enqueue admin styles.
 */
function activitypub_enqueue_admin_styles() {
    wp_enqueue_style(
        'activitypub-your-feature',
        plugins_url(
            'build/your-feature/style.css',
            ACTIVITYPUB_PLUGIN_FILE
        ),
        array(),
        ACTIVITYPUB_PLUGIN_VERSION
    );
    
    // Add RTL support.
    wp_style_add_data( 'activitypub-your-feature', 'rtl', 'replace' );
}
add_action( 'admin_enqueue_scripts', 'activitypub_enqueue_admin_styles' );
```

## Development Workflow

The ActivityPub plugin uses WordPress Scripts (`@wordpress/scripts`) for development, building, and linting JavaScript and CSS files.

### Available Commands

The following npm scripts are available in `package.json`:

- `npm run dev`: Start the development server with hot reloading.
- `npm run build`: Format code and build production assets.
- `npm run format`: Format JavaScript files using Prettier. Also part of the build process.
- `npm run lint:css`: Lint CSS/SCSS files.
- `npm run lint:js`: Lint JavaScript files.
- `npm run env`: Run WordPress environment commands.
- `npm run env-start`: Start the WordPress development environment.
- `npm run env-stop`: Stop the WordPress development environment.
- `npm run env-test`: Run PHPUnit tests in the WordPress environment.
- `npm run release`: Create a new release.

### Build Process

#### Development

During development, use the following workflow:

1. Start the development server:
   ```
   npm run dev
   ```
   This will watch for changes in your JavaScript and SCSS files and automatically rebuild them.

2. Make your changes to the source files in `/src` or `/assets`.

3. Test your changes in the browser.

#### Production

Before committing and pushing your changes to the remote repository:

1. Build the production assets:
   ```
   npm run build
   ```
   This will:
   - Format your JavaScript files.
   - Compile and minify JavaScript.
   - Compile and minify SCSS to CSS.
   - Generate source maps.

2. Commit both your source files and the built assets.

3. Push your changes to the remote repository.

> **Important**: Always run `npm run build` before pushing your changes to ensure that the built assets are up-to-date with your source code.
