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

		\add_filter( 'pre_option_activitypub_distribution_mode', array( self::class, 'pre_option_activitypub_distribution_mode' ) );
		\add_filter( 'activitypub_dispatcher_batch_size', array( self::class, 'filter_dispatcher_batch_size' ) );
		\add_filter( 'activitypub_scheduler_async_batch_pause', array( self::class, 'filter_scheduler_batch_pause' ), 10, 2 );

		\add_filter( 'pre_option_activitypub_allow_likes', array( self::class, 'maybe_disable_interactions' ) );
		\add_filter( 'pre_option_activitypub_allow_replies', array( self::class, 'maybe_disable_interactions' ) );

		\add_filter( 'default_option_activitypub_negotiate_content', array( self::class, 'default_option_activitypub_negotiate_content' ) );
		\add_filter( 'option_activitypub_max_image_attachments', array( self::class, 'default_max_image_attachments' ) );
		\add_filter( 'option_activitypub_support_post_types', array( self::class, 'support_post_types_ensure_array' ) );
		\add_filter( 'option_activitypub_object_type', array( self::class, 'default_object_type' ) );

		\add_filter( 'option_activitypub_outbox_purge_days', array( self::class, 'sanitize_purge_days' ) );
		\add_filter( 'option_activitypub_inbox_purge_days', array( self::class, 'sanitize_purge_days' ) );
		\add_filter( 'option_activitypub_ap_post_purge_days', array( self::class, 'sanitize_purge_days' ) );

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
			'activitypub_default_feature_policy',
			array(
				'type'              => 'string',
				'description'       => 'Default policy for who can include this site\'s actors in featured collections (FEP-7aa9).',
				'default'           => ACTIVITYPUB_INTERACTION_POLICY_ME,
				'sanitize_callback' => static function ( $value ) {
					$allowed = array(
						ACTIVITYPUB_INTERACTION_POLICY_ANYONE,
						ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS,
						ACTIVITYPUB_INTERACTION_POLICY_ME,
					);
					return \in_array( $value, $allowed, true ) ? $value : ACTIVITYPUB_INTERACTION_POLICY_ME;
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
				'type'              => 'integer',
				'description'       => 'Number of days to keep items in the Outbox.',
				'default'           => ACTIVITYPUB_OUTBOX_PURGE_DAYS,
				'sanitize_callback' => static function ( $value ) {
					return \max( 1, \absint( $value ) );
				},
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_inbox_purge_days',
			array(
				'type'              => 'integer',
				'description'       => 'Number of days to keep items in the Inbox.',
				'default'           => ACTIVITYPUB_INBOX_PURGE_DAYS,
				'sanitize_callback' => static function ( $value ) {
					return \max( 1, \absint( $value ) );
				},
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_ap_post_purge_days',
			array(
				'type'              => 'integer',
				'description'       => 'Number of days to keep remote posts.',
				'default'           => ACTIVITYPUB_AP_POST_PURGE_DAYS,
				'sanitize_callback' => static function ( $value ) {
					return \max( 1, \absint( $value ) );
				},
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
			'activitypub_api',
			array(
				'type'        => 'boolean',
				'description' => 'Enable the ActivityPub API to allow third-party clients.',
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

		$default_distribution = self::get_distribution_preset_values()['default'];

		\register_setting(
			'activitypub_advanced',
			'activitypub_distribution_mode',
			array(
				'type'              => 'string',
				'description'       => \__( 'Distribution mode for federation delivery.', 'activitypub' ),
				'default'           => 'default',
				'sanitize_callback' => array( self::class, 'sanitize_distribution_mode' ),
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_custom_batch_size',
			array(
				'type'              => 'integer',
				'description'       => \__( 'Custom batch size for federation delivery.', 'activitypub' ),
				'default'           => $default_distribution['batch_size'],
				'sanitize_callback' => static function ( $value ) {
					return \min( 500, \max( 1, \absint( $value ) ) );
				},
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_custom_batch_pause',
			array(
				'type'              => 'integer',
				'description'       => \__( 'Custom pause in seconds between batches.', 'activitypub' ),
				'default'           => $default_distribution['pause'],
				'sanitize_callback' => static function ( $value ) {
					return \min( 3600, \absint( $value ) );
				},
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
			'activitypub_mailer_annual_report',
			array(
				'type'        => 'integer',
				'description' => 'Send the annual Fediverse Year in Review email.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_mailer_monthly_report',
			array(
				'type'        => 'integer',
				'description' => 'Send a monthly Fediverse stats report email.',
				'default'     => 0,
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
	 * Pre-get option filter for the Distribution Mode.
	 *
	 * @since unreleased
	 *
	 * @param string|false $pre The pre-get option value.
	 *
	 * @return string|false The distribution mode or false if it should not be filtered.
	 */
	public static function pre_option_activitypub_distribution_mode( $pre ) {
		return self::resolve_distribution_mode( $pre, ACTIVITYPUB_DISTRIBUTION_MODE );
	}

	/**
	 * Whether the distribution mode is locked to a valid preset by the
	 * `ACTIVITYPUB_DISTRIBUTION_MODE` constant.
	 *
	 * Returns true only when the constant is set to a key recognized by
	 * `get_distribution_preset_values()`. Invalid constant values fall back
	 * to `'default'` at runtime (see `resolve_distribution_mode()`) but the
	 * UI stays visible so admins can spot the misconfiguration.
	 *
	 * @since unreleased
	 *
	 * @return bool True when the constant pins the mode to a valid preset.
	 */
	public static function is_distribution_mode_locked() {
		if ( false === ACTIVITYPUB_DISTRIBUTION_MODE ) {
			return false;
		}

		return \in_array( ACTIVITYPUB_DISTRIBUTION_MODE, \array_keys( self::get_distribution_preset_values() ), true );
	}

	/**
	 * Resolve the distribution mode against the wp-config constant.
	 *
	 * Extracted from `pre_option_activitypub_distribution_mode()` so the
	 * constant-lock path can be exercised from tests without redefining
	 * the real constant.
	 *
	 * Only preset modes are honored via the constant. The 'custom' mode
	 * is excluded because its batch size and pause values are still read
	 * from the database, which would defeat the purpose of locking the
	 * mode via wp-config.php.
	 *
	 * @since unreleased
	 *
	 * @param string|false $pre            The pre-get option value.
	 * @param mixed        $constant_value The value of `ACTIVITYPUB_DISTRIBUTION_MODE`.
	 *
	 * @return string|false Mode if locked, `$pre` otherwise.
	 */
	public static function resolve_distribution_mode( $pre, $constant_value ) {
		if ( false === $constant_value ) {
			return $pre;
		}

		$allowed = \array_keys( self::get_distribution_preset_values() );

		if ( \in_array( $constant_value, $allowed, true ) ) {
			return $constant_value;
		}

		\_doing_it_wrong(
			__METHOD__,
			\sprintf(
				/* translators: %s: invalid constant value */
				\esc_html__( 'ACTIVITYPUB_DISTRIBUTION_MODE value %s is not a valid preset; falling back to default.', 'activitypub' ),
				\esc_html( (string) $constant_value )
			),
			'unreleased'
		);

		return 'default';
	}

	/**
	 * Get the raw batch_size/pause values for each distribution preset.
	 *
	 * Single source of truth for the preset values, used in the hot path
	 * (get_distribution_params, sanitize_distribution_mode, resolve_distribution_mode)
	 * to avoid running translation calls just to check keys or numbers.
	 *
	 * @since unreleased
	 *
	 * @return array Associative array of mode => { batch_size, pause }.
	 */
	private static function get_distribution_preset_values() {
		return array(
			'default'  => array(
				'batch_size' => 100,
				'pause'      => 15,
			),
			'balanced' => array(
				'batch_size' => 50,
				'pause'      => 30,
			),
			'eco'      => array(
				'batch_size' => 20,
				'pause'      => 30,
			),
		);
	}

	/**
	 * Get the available distribution mode presets with UI labels.
	 *
	 * Decorates `get_distribution_preset_values()` with translated labels
	 * and descriptions for use in the admin settings page.
	 *
	 * @since unreleased
	 *
	 * @return array Associative array of mode => { batch_size, pause, label, description }.
	 */
	public static function get_distribution_modes() {
		$modes = self::get_distribution_preset_values();

		$modes['default']['label']       = \__( 'Default', 'activitypub' );
		$modes['default']['description'] = \sprintf(
			/* translators: 1: batch size, 2: pause in seconds */
			\__( 'Deliver activities as fast as possible (<code>%1$d</code> per batch, <code>%2$ds</code> pause).', 'activitypub' ),
			$modes['default']['batch_size'],
			$modes['default']['pause']
		);
		$modes['balanced']['label']       = \__( 'Balanced', 'activitypub' );
		$modes['balanced']['description'] = \sprintf(
			/* translators: 1: batch size, 2: pause in seconds */
			\__( 'Moderate pace with reasonable pauses between batches (<code>%1$d</code> per batch, <code>%2$ds</code> pause).', 'activitypub' ),
			$modes['balanced']['batch_size'],
			$modes['balanced']['pause']
		);
		$modes['eco']['label']       = \__( 'Eco Mode', 'activitypub' );
		$modes['eco']['description'] = \sprintf(
			/* translators: 1: batch size, 2: pause in seconds */
			\__( 'Gentle on server resources, ideal for shared hosting (<code>%1$d</code> per batch, <code>%2$ds</code> pause).', 'activitypub' ),
			$modes['eco']['batch_size'],
			$modes['eco']['pause']
		);

		return $modes;
	}

	/**
	 * Sanitize the distribution mode option.
	 *
	 * Restricts the stored value to a known preset (from
	 * `get_distribution_modes()`) or `'custom'`. Anything else
	 * falls back to `'default'`.
	 *
	 * @since unreleased
	 *
	 * @param string $value The submitted option value.
	 *
	 * @return string A valid distribution mode key.
	 */
	public static function sanitize_distribution_mode( $value ) {
		$allowed = \array_merge( \array_keys( self::get_distribution_preset_values() ), array( 'custom' ) );

		return \in_array( $value, $allowed, true ) ? $value : 'default';
	}

	/**
	 * Get distribution parameters for the current mode.
	 *
	 * @since unreleased
	 *
	 * @return array { mode: string, batch_size: int, pause: int }
	 */
	public static function get_distribution_params() {
		$mode  = \get_option( 'activitypub_distribution_mode', 'default' );
		$modes = self::get_distribution_preset_values();

		if ( isset( $modes[ $mode ] ) ) {
			return array(
				'mode'       => $mode,
				'batch_size' => $modes[ $mode ]['batch_size'],
				'pause'      => $modes[ $mode ]['pause'],
			);
		}

		// Custom mode reads its values from dedicated options; any other
		// unrecognized mode falls back to the default preset so callers
		// always receive a valid configuration.
		if ( 'custom' !== $mode ) {
			return array(
				'mode'       => 'default',
				'batch_size' => $modes['default']['batch_size'],
				'pause'      => $modes['default']['pause'],
			);
		}

		$default_params = $modes['default'];

		return array(
			'mode'       => 'custom',
			'batch_size' => \max( 1, \absint( \get_option( 'activitypub_custom_batch_size', $default_params['batch_size'] ) ) ),
			'pause'      => \absint( \get_option( 'activitypub_custom_batch_pause', $default_params['pause'] ) ),
		);
	}

	/**
	 * Filter the dispatcher batch size based on distribution mode.
	 *
	 * In `'default'` mode the upstream value is passed through so the
	 * `ACTIVITYPUB_OUTBOX_PROCESSING_BATCH_SIZE` constant and other filters
	 * still win; any explicit mode imposes its own batch size.
	 *
	 * @since unreleased
	 *
	 * @param int $batch_size The default batch size.
	 *
	 * @return int The batch size for the current distribution mode.
	 */
	public static function filter_dispatcher_batch_size( $batch_size ) {
		$params = self::get_distribution_params();

		return 'default' === $params['mode'] ? $batch_size : $params['batch_size'];
	}

	/**
	 * Filter the scheduler batch pause based on distribution mode.
	 *
	 * Only delivery batches (`activitypub_send_activity`) are affected. Every
	 * mode imposes its own delivery pause: `'default'` is the fast preset, which
	 * is intentionally shorter than the generic async-batch baseline, so it does
	 * not pass the upstream value through.
	 *
	 * @since unreleased
	 *
	 * @param int               $pause The default pause in seconds.
	 * @param string|false|null $hook The async batch hook being scheduled.
	 *
	 * @return int The pause for the current distribution mode.
	 */
	public static function filter_scheduler_batch_pause( $pause, $hook = null ) {
		if ( 'activitypub_send_activity' !== $hook ) {
			return $pause;
		}

		return self::get_distribution_params()['pause'];
	}

	/**
	 * Sanitize purge day values.
	 *
	 * Ensures the value is a non-negative integer. Returns the
	 * registered default when the stored value is empty or false
	 * (option not properly set), but allows 0 to disable purging.
	 *
	 * @since 8.1.0
	 *
	 * @param mixed $value The stored option value.
	 *
	 * @return int The sanitized value.
	 */
	public static function sanitize_purge_days( $value ) {
		if ( '' === $value || false === $value ) {
			$filter   = \current_filter();
			$defaults = array(
				'option_activitypub_outbox_purge_days'  => ACTIVITYPUB_OUTBOX_PURGE_DAYS,
				'option_activitypub_inbox_purge_days'   => ACTIVITYPUB_INBOX_PURGE_DAYS,
				'option_activitypub_ap_post_purge_days' => ACTIVITYPUB_AP_POST_PURGE_DAYS,
			);

			return $defaults[ $filter ] ?? ACTIVITYPUB_OUTBOX_PURGE_DAYS;
		}

		return \max( 1, \absint( $value ) );
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
