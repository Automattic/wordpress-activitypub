<?php
/**
 * Load the ActivityPub integrations.
 */

namespace Activitypub\Integration;

function plugin_init() {
	/**
	 * Adds WebFinger (plugin) support.
	 *
	 * This class handles the compatibility with the WebFinger plugin
	 * and coordinates the internal WebFinger implementation.
	 *
	 * @see https://wordpress.org/plugins/webfinger/
	 */
	require_once __DIR__ . '/class-webfinger.php';
	Webfinger::init();

	/**
	 * Adds NodeInfo (plugin) support.
	 *
	 * This class handles the compatibility with the NodeInfo plugin
	 * and coordinates the internal NodeInfo implementation.
	 *
	 * @see https://wordpress.org/plugins/nodeinfo/
	 */
	require_once __DIR__ . '/class-nodeinfo.php';
	Nodeinfo::init();

	/**
	 * Adds Enable Mastodon Apps support.
	 *
	 * This class handles the compatibility with the Enable Mastodon Apps plugin.
	 *
	 * @see https://wordpress.org/plugins/enable-mastodon-apps/
	 */
	if ( \defined( 'ENABLE_MASTODON_APPS_VERSION' ) ) {
		require_once __DIR__ . '/class-enable-mastodon-apps.php';
		Enable_Mastodon_Apps::init();
	}

	/**
	 * Adds OpenGraph support.
	 *
	 * This class handles the compatibility with the OpenGraph plugin.
	 *
	 * @see https://wordpress.org/plugins/opengraph/
	 */
	if ( '1' === \get_option( 'activitypub_use_opengraph', '1' ) ) {
		require_once __DIR__ . '/class-opengraph.php';
		Opengraph::init();
	}

	/**
	 * Adds Jetpack support.
	 *
	 * This class handles the compatibility with Jetpack.
	 *
	 * @see https://jetpack.com/
	 */
	if ( \defined( 'JETPACK__VERSION' ) && ! \defined( 'IS_WPCOM' ) ) {
		require_once __DIR__ . '/class-jetpack.php';
		Jetpack::init();
	}

	/**
	 * Adds Seriously Simple Podcasting support.
	 *
	 * This class handles the compatibility with Seriously Simple Podcasting.
	 *
	 * @see https://wordpress.org/plugins/seriously-simple-podcasting/
	 */
	if ( \defined( 'SSP_VERSION' ) ) {
		add_filter(
			'activitypub_transformer',
			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
			function( $transformer, $object, $object_class ) {
				if (
					'WP_Post' === $object_class &&
					\get_post_meta( $object->ID, 'audio_file', true )
				) {
					require_once __DIR__ . '/class-seriously-simple-podcasting.php';
					return new Seriously_Simple_Podcasting( $object );
				}
				return $transformer;
			},
			10,
			3
		);
	}
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_init' );

/**
 * Register the Stream Connector for ActivityPub.
 *
 * @param array $classes The Stream connectors.
 *
 * @return array The Stream connectors with the ActivityPub connector.
 */
function register_stream_connector( $classes ) {
	require plugin_dir_path( __FILE__ ) . '/class-stream-connector.php';

	$class_name = '\Activitypub\Integration\Stream_Connector';

	if ( ! class_exists( $class_name ) ) {
		return;
	}

	wp_stream_get_instance();
	$class = new $class_name();

	if ( ! method_exists( $class, 'is_dependency_satisfied' ) ) {
		return;
	}

	if ( $class->is_dependency_satisfied() ) {
		$classes[] = $class;
	}

	return $classes;
}
add_filter( 'wp_stream_connectors', __NAMESPACE__ . '\register_stream_connector' );

// Excluded ActivityPub post types from the Stream.
add_filter(
	'wp_stream_posts_exclude_post_types',
	function( $post_types ) {
		$post_types[] = 'ap_follower';
		$post_types[] = 'ap_extrafield';
		$post_types[] = 'ap_extrafield_blog';
		return $post_types;
	}
);

/**
 * Load the BuddyPress integration.
 *
 * Only load code that needs BuddyPress to run once BP is loaded and initialized.
 *
 * @see https://buddypress.org/
 */
add_action(
	'bp_include',
	function () {
		require_once __DIR__ . '/class-buddypress.php';
		Buddypress::init();
	},
	0
);
