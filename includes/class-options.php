<?php
/**
 * Options file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Model\Blog;

/**
 * Options class.
 */
class Options {

	/**
	 * Initialize the options.
	 */
	public static function init() {
		\add_action( 'admin_init', array( self::class, 'register_settings' ) );
		\add_action( 'rest_api_init', array( self::class, 'register_settings' ) );

		\add_filter( 'pre_option_activitypub_actor_mode', array( self::class, 'pre_option_activitypub_actor_mode' ) );
		\add_filter( 'pre_option_activitypub_authorized_fetch', array( self::class, 'pre_option_activitypub_authorized_fetch' ) );
		\add_filter( 'pre_option_activitypub_vary_header', array( self::class, 'pre_option_activitypub_vary_header' ) );
		\add_filter( 'pre_option_activitypub_following_ui', array( self::class, 'pre_option_activitypub_following_ui' ) );
		\add_filter( 'pre_option_activitypub_create_posts', array( self::class, 'pre_option_activitypub_create_posts' ) );

		\add_filter( 'pre_option_activitypub_allow_likes', array( self::class, 'maybe_disable_interactions' ) );
		\add_filter( 'pre_option_activitypub_allow_replies', array( self::class, 'maybe_disable_interactions' ) );

		\add_filter( 'default_option_activitypub_negotiate_content', array( self::class, 'default_option_activitypub_negotiate_content' ) );
		\add_filter( 'option_activitypub_max_image_attachments', array( self::class, 'default_max_image_attachments' ) );
		\add_filter( 'option_activitypub_support_post_types', array( self::class, 'support_post_types_ensure_array' ) );
		\add_filter( 'option_activitypub_object_type', array( self::class, 'default_object_type' ) );

		\add_action( 'update_option_activitypub_relay_mode', array( self::class, 'relay_mode_changed' ), 10, 2 );
	}

	/**
	 * Register ActivityPub settings.
	 */
	public static function register_settings() {
		/*
		 * Options Group: activitypub
		 */
		\register_setting(
			'activitypub',
			'activitypub_post_content_type',
			array(
				'type'         => 'string',
				'description'  => 'Use title and link, summary, full or custom content',
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
				'description'  => 'Define your own custom post template',
				'show_in_rest' => true,
				'default'      => ACTIVITYPUB_CUSTOM_POST_CONTENT,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_max_image_attachments',
			array(
				'type'              => 'integer',
				'description'       => 'Number of images to attach to posts.',
				'default'           => ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS,
				'sanitize_callback' => static function ( $value ) {
					return \is_numeric( $value ) ? \absint( $value ) : ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS;
				},
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_use_hashtags',
			array(
				'type'        => 'boolean',
				'description' => 'Add hashtags in the content as native tags and replace the #tag with the tag-link',
				'default'     => '0',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_use_opengraph',
			array(
				'type'        => 'boolean',
				'description' => 'Automatically add "fediverse:creator" OpenGraph tags for Authors and the Blog-User.',
				'default'     => '1',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_support_post_types',
			array(
				'type'         => 'string',
				'description'  => 'Enable ActivityPub support for post types',
				'show_in_rest' => true,
				'default'      => array( 'post' ),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_actor_mode',
			array(
				'type'         => 'string',
				'description'  => 'Choose your preferred Actor-Mode.',
				'default'      => ACTIVITYPUB_ACTOR_MODE,
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array(
							ACTIVITYPUB_ACTOR_MODE,
							ACTIVITYPUB_BLOG_MODE,
							ACTIVITYPUB_ACTOR_AND_BLOG_MODE,
						),
					),
				),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_attribution_domains',
			array(
				'type'              => 'string',
				'description'       => 'Websites allowed to credit you.',
				'default'           => home_host(),
				'sanitize_callback' => array( Sanitize::class, 'host_list' ),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_allow_likes',
			array(
				'type'              => 'integer',
				'description'       => 'Allow likes.',
				'default'           => '1',
				'sanitize_callback' => 'absint',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_allow_reposts',
			array(
				'type'              => 'integer',
				'description'       => 'Allow reposts.',
				'default'           => '1',
				'sanitize_callback' => 'absint',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_auto_approve_reactions',
			array(
				'type'              => 'integer',
				'description'       => 'Auto-approve Reactions.',
				'default'           => '0',
				'sanitize_callback' => 'absint',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_default_quote_policy',
			array(
				'type'              => 'string',
				'description'       => 'Default quote policy for new posts.',
				'default'           => ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
				'sanitize_callback' => static function ( $value ) {
					$allowed = array(
						ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
						ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
						ACTIVITYPUB_INTERACTION_POLICY_ME,
					);
					return \in_array( $value, $allowed, true ) ? $value : ACTIVITYPUB_INTERACTION_POLICY_ANYONE;
				},
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_relays',
			array(
				'type'              => 'array',
				'description'       => 'Relays',
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'url_list' ),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_site_blocked_actors',
			array(
				'type'              => 'array',
				'description'       => 'Site-wide blocked ActivityPub actors.',
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'identifier_list' ),
			)
		);

		/*
		 * Options Group: activitypub_advanced
		 */
		\register_setting(
			'activitypub_advanced',
			'activitypub_outbox_purge_days',
			array(
				'type'        => 'integer',
				'description' => 'Number of days to keep items in the Outbox.',
				'default'     => 180,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_inbox_purge_days',
			array(
				'type'        => 'integer',
				'description' => 'Number of days to keep items in the Inbox.',
				'default'     => 180,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_ap_post_purge_days',
			array(
				'type'        => 'integer',
				'description' => 'Number of days to keep remote posts.',
				'default'     => 30,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_vary_header',
			array(
				'type'        => 'boolean',
				'description' => 'Add the Vary header to the ActivityPub response.',
				'default'     => true,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_content_negotiation',
			array(
				'type'        => 'boolean',
				'description' => 'Enable content negotiation.',
				'default'     => true,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_authorized_fetch',
			array(
				'type'        => 'boolean',
				'description' => 'Require HTTP signature authentication.',
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_rfc9421_signature',
			array(
				'type'        => 'boolean',
				'description' => 'Use RFC-9421 signature.',
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_following_ui',
			array(
				'type'        => 'boolean',
				'description' => 'Show Following UI in admin menus and settings.',
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_reader_ui',
			array(
				'type'        => 'boolean',
				'description' => 'Enable the Reader to view posts from accounts you follow.',
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_create_posts',
			array(
				'type'        => 'boolean',
				'description' => 'Allow creating posts via ActivityPub.',
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_object_type',
			array(
				'type'         => 'string',
				'description'  => 'The Activity-Object-Type',
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'wordpress-post-format' ),
					),
				),
				'default'      => ACTIVITYPUB_DEFAULT_OBJECT_TYPE,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_relay_mode',
			array(
				'type'              => 'integer',
				'description'       => 'Enable relay mode to forward public activities to all followers.',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);

		/*
		 * Options Group: activitypub_blog
		 */
		\register_setting(
			'activitypub_blog',
			'activitypub_blog_description',
			array(
				'type'         => 'string',
				'description'  => 'The Description of the Blog-User',
				'show_in_rest' => true,
				'default'      => '',
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_identifier',
			array(
				'type'              => 'string',
				'description'       => 'The Identifier of the Blog-User',
				'show_in_rest'      => true,
				'default'           => Blog::get_default_username(),
				'sanitize_callback' => array( Sanitize::class, 'blog_identifier' ),
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_header_image',
			array(
				'type'        => 'integer',
				'description' => 'The Attachment-ID of the Sites Header-Image',
				'default'     => null,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_mailer_new_dm',
			array(
				'type'        => 'integer',
				'description' => 'Send a notification when someone sends a user of the blog a direct message.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_mailer_new_follower',
			array(
				'type'        => 'integer',
				'description' => 'Send a notification when someone starts to follow a user of the blog.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_mailer_new_mention',
			array(
				'type'        => 'integer',
				'description' => 'Send a notification when someone mentions a user of the blog.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_also_known_as',
			array(
				'type'              => 'array',
				'description'       => 'An array of URLs that the blog user is known by.',
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'identifier_list' ),
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_hide_social_graph',
			array(
				'type'              => 'integer',
				'description'       => 'Hide Followers and Followings on Profile.',
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Delete all options.
	 */
	public static function delete() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'activitypub_%'" );
	}

	/**
	 * Pre-get option filter for the Actor-Mode.
	 *
	 * @param string|false $pre The pre-get option value.
	 *
	 * @return string|false The actor mode or false if it should not be filtered.
	 */
	public static function pre_option_activitypub_actor_mode( $pre ) {
		if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) && ACTIVITYPUB_SINGLE_USER_MODE ) {
			return ACTIVITYPUB_BLOG_MODE;
		}

		if ( \defined( 'ACTIVITYPUB_DISABLE_USER' ) && ACTIVITYPUB_DISABLE_USER ) {
			return ACTIVITYPUB_BLOG_MODE;
		}

		if ( \defined( 'ACTIVITYPUB_DISABLE_BLOG_USER' ) && ACTIVITYPUB_DISABLE_BLOG_USER ) {
			return ACTIVITYPUB_ACTOR_MODE;
		}

		return $pre;
	}

	/**
	 * Pre-get option filter for the Authorized Fetch.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_authorized_fetch( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_AUTHORIZED_FETCH' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_AUTHORIZED_FETCH ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Pre-get option filter for the Vary Header.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the constant is defined, return the value, otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_vary_header( $pre ) {
		if ( ! \defined( 'ACTIVITYPUB_SEND_VARY_HEADER' ) ) {
			return $pre;
		}

		if ( ACTIVITYPUB_SEND_VARY_HEADER ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Pre-get option filter for the Following UI.
	 *
	 * Forces the Following UI to be enabled when the Reader is enabled.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the Reader is enabled, return '1', otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_following_ui( $pre ) {
		/*
		 * Bypass the filter to get the actual stored value for activitypub_reader_ui.
		 * This avoids infinite loops if activitypub_reader_ui also had a pre_option filter.
		 */
		if ( \get_option( 'activitypub_reader_ui', '0' ) ) {
			return '1';
		}

		return $pre;
	}

	/**
	 * Pre-get option filter for the Create Posts setting.
	 *
	 * Forces the Create Posts setting to be enabled when the Reader is enabled.
	 *
	 * @param string $pre The pre-get option value.
	 *
	 * @return string If the Reader is enabled, return '1', otherwise return the pre-get option value.
	 */
	public static function pre_option_activitypub_create_posts( $pre ) {
		if ( \get_option( 'activitypub_reader_ui', '0' ) ) {
			return '1';
		}

		return $pre;
	}

	/**
	 * Disallow interactions if the constant is set.
	 *
	 * @param bool $pre The value of the option.
	 *
	 * @return bool|string The value of the option.
	 */
	public static function maybe_disable_interactions( $pre ) {
		if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			return '0';
		}

		return $pre;
	}

	/**
	 * Default option filter for the Content-Negotiation.
	 *
	 * @see https://github.com/Automattic/wordpress-activitypub/wiki/Caching
	 *
	 * @param string $default_value The default value of the option.
	 *
	 * @return string The default value of the option.
	 */
	public static function default_option_activitypub_negotiate_content( $default_value ) {
		$disable_for_plugins = array(
			'wp-optimize/wp-optimize.php',
			'wp-rocket/wp-rocket.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-fastest-cache/wp-fastest-cache.php',
			'sg-cachepress/sg-cachepress.php',
		);

		foreach ( $disable_for_plugins as $plugin ) {
			if ( \is_plugin_active( $plugin ) ) {
				return '0';
			}
		}

		return $default_value;
	}

	/**
	 * Default max image attachments.
	 *
	 * @param string $value The value of the option.
	 *
	 * @return string|int The value of the option.
	 */
	public static function default_max_image_attachments( $value ) {
		if ( ! \is_numeric( $value ) ) {
			$value = ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS;
		}

		return $value;
	}

	/**
	 * Ensure support post types is an array.
	 *
	 * @param string[] $value The value of the option.
	 *
	 * @return string[] The value of the option.
	 */
	public static function support_post_types_ensure_array( $value ) {
		return (array) $value;
	}

	/**
	 * Default object type.
	 *
	 * @param string $value The value of the option.
	 *
	 * @return string The value of the option.
	 */
	public static function default_object_type( $value ) {
		if ( ! $value ) {
			$value = ACTIVITYPUB_DEFAULT_OBJECT_TYPE;
		}

		return $value;
	}

	/**
	 * Handle relay mode option changes.
	 *
	 * When relay mode is enabled, switch to blog-only mode and set username to "relay".
	 * When disabled, restore previous settings.
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $new_value The new option value.
	 */
	public static function relay_mode_changed( $old_value, $new_value ) {
		if ( $new_value && ! $old_value ) {
			// Enabling relay mode.
			// Store previous username and actor mode for restoration.
			\update_option( 'activitypub_relay_previous_blog_identifier', \get_option( 'activitypub_blog_identifier' ) );
			\update_option( 'activitypub_relay_previous_actor_mode', \get_option( 'activitypub_actor_mode' ) );

			// Set blog username to "relay".
			\update_option( 'activitypub_blog_identifier', 'relay' );

			// Switch to blog-only mode.
			\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		} elseif ( ! $new_value && $old_value ) {
			// Disabling relay mode - restore previous settings.
			$previous_identifier = \get_option( 'activitypub_relay_previous_blog_identifier' );
			$previous_actor_mode = \get_option( 'activitypub_relay_previous_actor_mode' );

			if ( $previous_identifier ) {
				\update_option( 'activitypub_blog_identifier', $previous_identifier );
				\delete_option( 'activitypub_relay_previous_blog_identifier' );
			}

			if ( $previous_actor_mode ) {
				\update_option( 'activitypub_actor_mode', $previous_actor_mode );
				\delete_option( 'activitypub_relay_previous_actor_mode' );
			}
		}
	}
}
