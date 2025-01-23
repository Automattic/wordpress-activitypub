<?php
/**
 * Load the ActivityPub integrations.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

\Activitypub\Autoloader::register_path( __NAMESPACE__, __DIR__ );

/**
 * Initialize the ActivityPub integrations.
 */
function plugin_init() {
	/**
	 * Adds WebFinger (plugin) support.
	 *
	 * This class handles the compatibility with the WebFinger plugin
	 * and coordinates the internal WebFinger implementation.
	 *
	 * @see https://wordpress.org/plugins/webfinger/
	 */
	Webfinger::init();

	/**
	 * Adds NodeInfo (plugin) support.
	 *
	 * This class handles the compatibility with the NodeInfo plugin
	 * and coordinates the internal NodeInfo implementation.
	 *
	 * @see https://wordpress.org/plugins/nodeinfo/
	 */
	Nodeinfo::init();

	/**
	 * Adds Enable Mastodon Apps support.
	 *
	 * This class handles the compatibility with the Enable Mastodon Apps plugin.
	 *
	 * @see https://wordpress.org/plugins/enable-mastodon-apps/
	 */
	if ( \defined( 'ENABLE_MASTODON_APPS_VERSION' ) ) {
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
		Jetpack::init();
	}

	/**
	 * Adds Akismet support.
	 *
	 * This class handles the compatibility with the Akismet plugin.
	 *
	 * @see https://wordpress.org/plugins/akismet/
	 */
	if ( \defined( 'AKISMET_VERSION' ) ) {
		Akismet::init();
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
			function ( $transformer, $data, $object_class ) {
				if (
					'WP_Post' === $object_class &&
					\get_post_meta( $data->ID, 'audio_file', true )
				) {
					return new Seriously_Simple_Podcasting( $data );
				}
				return $transformer;
			},
			10,
			3
		);
	}

	/**
	 * Adds WPML Multilingual CMS (plugin) support.
	 *
	 * This class handles the compatibility with the WPML plugin.
	 *
	 * @see https://wpml.org/
	 */
	if ( \defined( 'ICL_SITEPRESS_VERSION' ) ) {
		WPML::init();
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
	$class = new Stream_Connector();

	if ( method_exists( $class, 'is_dependency_satisfied' ) && $class->is_dependency_satisfied() ) {
		$classes[] = $class;
	}

	return $classes;
}
add_filter( 'wp_stream_connectors', __NAMESPACE__ . '\register_stream_connector' );

// Excluded ActivityPub post types from the Stream.
add_filter(
	'wp_stream_posts_exclude_post_types',
	function ( $post_types ) {
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
add_action( 'bp_include', array( __NAMESPACE__ . '\Buddypress', 'init' ), 0 );
