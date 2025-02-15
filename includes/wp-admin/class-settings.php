<?php
/**
 * Settings file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Model\Blog;
use function Activitypub\is_user_disabled;

/**
 * ActivityPub Settings Class.
 */
class Settings {
	/**
	 * Initialize the class, registering WordPress hooks,
	 */
	public static function init() {
		\add_action( 'admin_init', array( self::class, 'register_settings' ), 11 );
		\add_action( 'admin_menu', array( self::class, 'add_settings_page' ) );
	}

	/**
	 * Register ActivityPub settings
	 */
	public static function register_settings() {
		\register_setting(
			'activitypub',
			'activitypub_post_content_type',
			array(
				'type'         => 'string',
				'description'  => \__( 'Use title and link, summary, full or custom content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'title', 'excerpt', 'content' ),
					),
				),
				'default'      => 'content',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_custom_post_content',
			array(
				'type'         => 'string',
				'description'  => \__( 'Define your own custom post template', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => ACTIVITYPUB_CUSTOM_POST_CONTENT,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_max_image_attachments',
			array(
				'type'        => 'integer',
				'description' => \__( 'Number of images to attach to posts.', 'activitypub' ),
				'default'     => ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_object_type',
			array(
				'type'         => 'string',
				'description'  => \__( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'wordpress-post-format' ),
					),
				),
				'default'      => ACTIVITYPUB_DEFAULT_OBJECT_TYPE,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_use_hashtags',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Add hashtags in the content as native tags and replace the #tag with the tag-link', 'activitypub' ),
				'default'     => '0',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_use_opengraph',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Automatically add "fediverse:creator" OpenGraph tags for Authors and the Blog-User.', 'activitypub' ),
				'default'     => '1',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_support_post_types',
			array(
				'type'         => 'string',
				'description'  => \esc_html__( 'Enable ActivityPub support for post types', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => array( 'post' ),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_actor_mode',
			array(
				'type'        => 'integer',
				'description' => \__( 'Choose your preferred Actor-Mode.', 'activitypub' ),
				'default'     => ACTIVITYPUB_ACTOR_MODE,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_attribution_domains',
			array(
				'type'              => 'string',
				'description'       => \__( 'Websites allowed to credit you.', 'activitypub' ),
				'default'           => \Activitypub\home_host(),
				'sanitize_callback' => function ( $value ) {
					$value = explode( PHP_EOL, $value );
					$value = array_filter( array_map( 'trim', $value ) );
					$value = array_filter( array_map( 'esc_attr', $value ) );
					$value = implode( PHP_EOL, $value );

					return $value;
				},
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_authorized_fetch',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Require HTTP signature authentication.', 'activitypub' ),
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_mailer_new_follower',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Send notifications via e-mail when a new follower is added.', 'activitypub' ),
				'default'     => '0',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_mailer_new_dm',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Send notifications via e-mail when a direct message is received.', 'activitypub' ),
				'default'     => '0',
			)
		);

		// Blog-User Settings.
		\register_setting(
			'activitypub_blog',
			'activitypub_blog_description',
			array(
				'type'         => 'string',
				'description'  => \esc_html__( 'The Description of the Blog-User', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => '',
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_identifier',
			array(
				'type'              => 'string',
				'description'       => \esc_html__( 'The Identifier of the Blog-User', 'activitypub' ),
				'show_in_rest'      => true,
				'default'           => Blog::get_default_username(),
				'sanitize_callback' => function ( $value ) {
					// Hack to allow dots in the username.
					$parts     = explode( '.', $value );
					$sanitized = array();

					foreach ( $parts as $part ) {
						$sanitized[] = \sanitize_title( $part );
					}

					$sanitized = implode( '.', $sanitized );

					// Check for login or nicename.
					$user = new WP_User_Query(
						array(
							'search'         => $sanitized,
							'search_columns' => array( 'user_login', 'user_nicename' ),
							'number'         => 1,
							'hide_empty'     => true,
							'fields'         => 'ID',
						)
					);

					if ( $user->results ) {
						add_settings_error(
							'activitypub_blog_identifier',
							'activitypub_blog_identifier',
							\esc_html__( 'You cannot use an existing author\'s name for the blog profile ID.', 'activitypub' ),
							'error'
						);

						return Blog::get_default_username();
					}

					return $sanitized;
				},
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_header_image',
			array(
				'type'        => 'integer',
				'description' => \__( 'The Attachment-ID of the Sites Header-Image', 'activitypub' ),
				'default'     => null,
			)
		);
	}

	/**
	 * Load settings page.
	 */
	public static function settings_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'welcome';

		$settings_tabs = array(
			'welcome'  => array(
				'label'    => __( 'Welcome', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/welcome.php',
			),
			'settings' => array(
				'label'    => __( 'Settings', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/settings.php',
			),
		);
		if ( ! is_user_disabled( Actors::BLOG_USER_ID ) ) {
			$settings_tabs['blog-profile'] = array(
				'label'    => __( 'Blog Profile', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-settings.php',
			);
			$settings_tabs['followers']    = array(
				'label'    => __( 'Followers', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-followers-list.php',
			);
		}

		/**
		 * Filters the tabs displayed in the ActivityPub settings.
		 *
		 * @param array $settings_tabs The tabs to display.
		 */
		$custom_tabs   = \apply_filters( 'activitypub_admin_settings_tabs', array() );
		$settings_tabs = \array_merge( $settings_tabs, $custom_tabs );

		switch ( $tab ) {
			case 'blog-profile':
				wp_enqueue_media();
				wp_enqueue_script( 'activitypub-header-image' );
				break;
			case 'welcome':
				wp_enqueue_script( 'plugin-install' );
				add_thickbox();
				wp_enqueue_script( 'updates' );
				break;
		}

		$labels       = wp_list_pluck( $settings_tabs, 'label' );
		$args         = array_fill_keys( array_keys( $labels ), '' );
		$args[ $tab ] = 'active';
		$args['tabs'] = $labels;

		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/admin-header.php', true, $args );
		\load_template( $settings_tabs[ $tab ]['template'] );
	}

	/**
	 * Adds the ActivityPub settings to the Help tab.
	 */
	public static function add_settings_help_tab() {
		require_once ACTIVITYPUB_PLUGIN_DIR . 'includes/help.php';
	}
}
