<?php
/**
 * App admin page file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

/**
 * ActivityPub App Admin Page Class.
 */
class App {

	const MOUNT_ID            = 'activitypub-app-root';
	const LOADER_MODULE       = '@activitypub/app';
	const FEED_CONTENT_MODULE = '@activitypub/app/routes/feed/content';
	const FEED_ROUTE_MODULE   = '@activitypub/app/routes/feed/route';

	/**
	 * Whether the WordPress admin app boot stack is available.
	 *
	 * This is the WordPress 7.0+ gate for the Social Web app. Instead of a
	 * `version_compare()` against `7.0`, it detects the boot stack by capability:
	 * the Script Modules API plus core's `@wordpress/boot` module asset, which
	 * ships in 7.0. Capability detection is deliberate, because a `version_compare`
	 * would lock out pre-release builds — `7.0-alpha`/`7.0-RC1` fail
	 * `version_compare( …, '7.0', '>=' )` yet already ship the boot module — so
	 * early-adopter installs would wrongly fall back to no app.
	 *
	 * @return bool True when the Social Web app can be booted (WordPress 7.0+).
	 */
	public static function is_supported() {
		return \function_exists( 'wp_register_script_module' ) && \is_array( self::get_boot_asset() );
	}

	/**
	 * Initialize the App page.
	 *
	 * Must run early (on admin_init) before the admin bar is initialized.
	 */
	public static function init() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'activitypub-social-web' === $_GET['page'] ) {
			\add_filter( 'wp_admin_bar_class', '__return_false' );
		}
	}

	/**
	 * Remove admin notices from the App page.
	 */
	public static function remove_admin_notices() {
		\remove_all_actions( 'admin_notices' );
		\remove_all_actions( 'all_admin_notices' );

		// Add fullscreen mode body class.
		\add_filter(
			'admin_body_class',
			static function ( $classes ) {
				return "$classes is-fullscreen-mode";
			}
		);
	}

	/**
	 * Enqueue scripts and styles for the App page.
	 */
	public static function enqueue_scripts() {
		\wp_dequeue_style( 'colors' );
		\wp_dequeue_script( 'common' );
		\wp_dequeue_script( 'svg-painter' );

		if ( ! self::is_supported() ) {
			return;
		}

		$boot_asset = self::get_boot_asset();

		if ( ! \is_array( $boot_asset ) ) {
			return;
		}

		self::preload_rest_data();

		$routes = self::get_routes();
		self::add_loader_data( $routes );

		\wp_register_script(
			'activitypub-app-prerequisites',
			false,
			self::get_script_dependencies( $boot_asset ),
			$boot_asset['version'],
			true
		);

		$style_dependencies = \array_filter(
			$boot_asset['dependencies'],
			static function ( $handle ) {
				return \wp_style_is( $handle, 'registered' );
			}
		);

		\wp_register_style(
			'activitypub-app-prerequisites',
			false,
			$style_dependencies,
			$boot_asset['version']
		);

		self::register_app_modules( $routes );
		self::enqueue_app_styles();

		\wp_enqueue_script( 'activitypub-app-prerequisites' );
		\wp_enqueue_script_module( self::LOADER_MODULE );
		\wp_enqueue_style( 'activitypub-app-prerequisites' );
	}

	/**
	 * Get classic script dependencies required before app script modules run.
	 *
	 * @param array $boot_asset Core boot module asset metadata.
	 * @return array Script handles.
	 */
	private static function get_script_dependencies( $boot_asset ) {
		$dependencies = \array_merge(
			$boot_asset['dependencies'] ?? array(),
			array(
				'lodash',
				'moment',
				'react',
				'react-dom',
				'react-jsx-runtime',
				'wp-api-fetch',
				'wp-commands',
				'wp-components',
				'wp-compose',
				'wp-core-data',
				'wp-data',
				'wp-data-controls',
				'wp-date',
				'wp-dom',
				'wp-element',
				'wp-html-entities',
				'wp-hooks',
				'wp-i18n',
				'wp-keyboard-shortcuts',
				'wp-keycodes',
				'wp-notices',
				'wp-preferences',
				'wp-primitives',
				'wp-private-apis',
				'wp-url',
				'wp-viewport',
			)
		);

		/**
		 * Filters the ActivityPub app prerequisite script dependencies.
		 *
		 * @param array $dependencies Script handles.
		 * @param array $boot_asset   Core boot module asset metadata.
		 */
		$dependencies = (array) \apply_filters(
			'activitypub_app_prerequisite_dependencies',
			\array_values( \array_unique( $dependencies ) ),
			$boot_asset
		);

		return \array_values(
			\array_filter(
				\array_unique( $dependencies ),
				static function ( $handle ) {
					return \wp_script_is( $handle, 'registered' );
				}
			)
		);
	}

	/**
	 * Add boot configuration for the app loader module.
	 *
	 * @param array $routes Route definitions.
	 */
	private static function add_loader_data( $routes ) {
		$mount_id = self::MOUNT_ID;

		\add_filter(
			'script_module_data_' . self::LOADER_MODULE,
			static function ( $data ) use ( $mount_id, $routes ) {
				$data['mountId'] = $mount_id;
				$data['routes']  = $routes;

				return $data;
			}
		);
	}

	/**
	 * Preload REST data used by the first app render.
	 */
	private static function preload_rest_data() {
		// Define paths to preload - must match exact fields from entities.js.
		$preload_paths = array(
			'/?_fields=description,gmt_offset,home,name,site_icon,site_icon_url,site_logo,timezone_string,url,page_for_posts,page_on_front,show_on_front',
			array( '/wp/v2/settings', 'OPTIONS' ),
		);

		$preload_data = \array_reduce(
			$preload_paths,
			'rest_preload_api_request',
			array()
		);

		\wp_add_inline_script(
			'wp-api-fetch',
			\sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', \wp_json_encode( $preload_data ) ),
			'after'
		);
	}

	/**
	 * Get the app route registry.
	 *
	 * @return array Route definitions.
	 */
	private static function get_routes() {
		$routes = array(
			array(
				'path'           => '/',
				'content_module' => self::FEED_CONTENT_MODULE,
				'route_module'   => self::FEED_ROUTE_MODULE,
			),
		);

		/**
		 * Filters the ActivityPub app route registry.
		 *
		 * @param array $routes Route definitions.
		 */
		return \apply_filters( 'activitypub_app_routes', $routes );
	}

	/**
	 * Register app script modules.
	 *
	 * @param array $routes Route definitions.
	 */
	private static function register_app_modules( $routes ) {
		$module_assets = self::get_app_module_assets();
		$module_ids    = array();

		foreach ( $routes as $route ) {
			foreach ( array( 'content_module', 'route_module' ) as $module_key ) {
				if ( ! empty( $route[ $module_key ] ) ) {
					$module_ids[] = $route[ $module_key ];
				}
			}
		}

		foreach ( \array_unique( $module_ids ) as $module_id ) {
			if ( ! isset( $module_assets[ $module_id ] ) ) {
				continue;
			}

			self::register_script_module(
				$module_id,
				$module_assets[ $module_id ]['script'],
				$module_assets[ $module_id ]['asset']
			);
		}

		$loader_asset = self::get_asset( 'build/app/loader.asset.php' );

		\wp_register_script_module(
			self::LOADER_MODULE,
			\plugins_url( 'build/app/loader.js', ACTIVITYPUB_PLUGIN_FILE ),
			self::get_boot_dependencies( $routes ),
			\is_array( $loader_asset ) && isset( $loader_asset['version'] ) ? $loader_asset['version'] : ACTIVITYPUB_PLUGIN_VERSION
		);
	}

	/**
	 * Get built app module asset paths keyed by script module ID.
	 *
	 * @return array Module asset definitions.
	 */
	private static function get_app_module_assets() {
		return array(
			self::FEED_CONTENT_MODULE => array(
				'script' => 'build/app/routes/feed/content.js',
				'asset'  => 'build/app/routes/feed/content.asset.php',
			),
			self::FEED_ROUTE_MODULE   => array(
				'script' => 'build/app/routes/feed/route.js',
				'asset'  => 'build/app/routes/feed/route.asset.php',
			),
		);
	}

	/**
	 * Register a built script module.
	 *
	 * @param string $module_id  Script module ID.
	 * @param string $script     Script path relative to the plugin root.
	 * @param string $asset_file Asset metadata path relative to the plugin root.
	 */
	private static function register_script_module( $module_id, $script, $asset_file ) {
		$asset = self::get_asset( $asset_file );

		if ( ! $asset ) {
			return;
		}

		\wp_register_script_module(
			$module_id,
			\plugins_url( $script, ACTIVITYPUB_PLUGIN_FILE ),
			isset( $asset['dependencies'] ) && \is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : ACTIVITYPUB_PLUGIN_VERSION
		);
	}

	/**
	 * Build dependencies for the app loader module.
	 *
	 * @param array $routes Route definitions.
	 * @return array Script module dependencies.
	 */
	private static function get_boot_dependencies( $routes ) {
		$dependencies = array(
			array(
				'id'     => '@wordpress/boot',
				'import' => 'static',
			),
		);

		foreach ( $routes as $route ) {
			if ( ! empty( $route['route_module'] ) ) {
				$dependencies[] = array(
					'id'     => $route['route_module'],
					'import' => 'static',
				);
			}

			if ( ! empty( $route['content_module'] ) ) {
				$dependencies[] = array(
					'id'     => $route['content_module'],
					'import' => 'dynamic',
				);
			}
		}

		/**
		 * Filters the ActivityPub app loader module dependencies.
		 *
		 * @param array $dependencies Script module dependencies.
		 * @param array $routes       Route definitions.
		 */
		return \apply_filters( 'activitypub_app_boot_dependencies', $dependencies, $routes );
	}

	/**
	 * Enqueue styles emitted by the module build.
	 */
	private static function enqueue_app_styles() {
		$style_path = 'build/app/routes/feed/style-content.css';
		$asset      = self::get_asset( 'build/app/routes/feed/content.asset.php' );

		if ( ! \file_exists( \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . $style_path ) ) {
			return;
		}

		$style_dependencies = \array_filter(
			array( 'wp-components', 'wp-edit-site' ),
			static function ( $handle ) {
				return \wp_style_is( $handle, 'registered' );
			}
		);

		\wp_enqueue_style(
			'activitypub-app',
			\plugins_url( $style_path, ACTIVITYPUB_PLUGIN_FILE ),
			$style_dependencies,
			\is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : ACTIVITYPUB_PLUGIN_VERSION
		);
	}

	/**
	 * Get the core boot asset file path.
	 *
	 * @return string Boot asset file path.
	 */
	private static function get_boot_asset_file() {
		return ABSPATH . WPINC . '/js/dist/script-modules/boot/index.min.asset.php';
	}

	/**
	 * Get the core boot asset metadata.
	 *
	 * @return array|null Boot asset metadata, or null when unavailable.
	 */
	private static function get_boot_asset() {
		static $cached = false;
		static $asset  = null;

		if ( $cached ) {
			return $asset;
		}

		$cached = true;

		$boot_asset_file = self::get_boot_asset_file();

		if ( ! \file_exists( $boot_asset_file ) ) {
			return $asset;
		}

		$boot_asset = include $boot_asset_file;
		$asset      = \is_array( $boot_asset ) ? $boot_asset : null;

		return $asset;
	}

	/**
	 * Get built asset metadata.
	 *
	 * @param string $asset_file Asset metadata path relative to the plugin root.
	 * @return array|null Asset metadata, or null when unavailable.
	 */
	private static function get_asset( $asset_file ) {
		$path = \plugin_dir_path( ACTIVITYPUB_PLUGIN_FILE ) . $asset_file;

		if ( ! \file_exists( $path ) ) {
			return null;
		}

		return include $path;
	}

	/**
	 * Render the App admin page.
	 */
	public static function render_page() {
		if ( ! self::is_supported() ) {
			?>
			<div class="notice notice-error">
				<p><?php \esc_html_e( 'The Social Web screen requires a newer WordPress admin app runtime. Please update WordPress to use this screen.', 'activitypub' ); ?></p>
			</div>
			<?php
			return;
		}
		?>
		<div id="<?php echo \esc_attr( self::MOUNT_ID ); ?>" class="boot-layout-container activitypub-app-layout"></div>
		<?php
	}
}
