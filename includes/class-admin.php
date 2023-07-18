<?php
namespace Activitypub;

/**
 * ActivityPub Admin Class
 *
 * @author Matthias Pfefferle
 */
class Admin {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
		\add_action( 'admin_init', array( self::class, 'register_settings' ) );
		\add_action( 'show_user_profile', array( self::class, 'add_profile' ) );
		\add_action( 'personal_options_update', array( self::class, 'save_user_description' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$settings_page = \add_options_page(
			'Welcome',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( self::class, 'settings_page' )
		);

		\add_action( 'load-' . $settings_page, array( self::class, 'add_settings_help_tab' ) );

		// user has to be able to publish posts
		if ( ! is_user_disabled( get_current_user_id() ) ) {
			$followers_list_page = \add_users_page( \__( 'Followers', 'activitypub' ), \__( 'Followers', 'activitypub' ), 'read', 'activitypub-followers-list', array( self::class, 'followers_list_page' ) );

			\add_action( 'load-' . $followers_list_page, array( self::class, 'add_followers_list_help_tab' ) );
		}
	}

	/**
	 * Load settings page
	 */
	public static function settings_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['tab'] ) ) {
			$tab = 'welcome';
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = sanitize_key( $_GET['tab'] );
		}

		switch ( $tab ) {
			case 'settings':
				\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/settings.php' );
				break;
			case 'followers':
				\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-user-followers-list.php' );
				break;
			case 'welcome':
			default:
				wp_enqueue_script( 'plugin-install' );
				add_thickbox();
				wp_enqueue_script( 'updates' );

				\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/welcome.php' );
				break;
		}
	}

	/**
	 * Load user settings page
	 */
	public static function followers_list_page() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/user-followers-list.php' );
	}

	/**
	 * Register ActivityPub settings
	 */
	public static function register_settings() {
		\register_setting(
			'activitypub',
			'activitypub_post_content_type',
			array(
				'type' => 'string',
				'description' => \__( 'Use title and link, summary, full or custom content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array(
							'title',
							'excerpt',
							'content',
						),
					),
				),
				'default' => 'content',
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_custom_post_content',
			array(
				'type' => 'string',
				'description' => \__( 'Define your own custom post template', 'activitypub' ),
				'show_in_rest' => true,
				'default' => ACTIVITYPUB_CUSTOM_POST_CONTENT,
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_max_image_attachments',
			array(
				'type' => 'integer',
				'description' => \__( 'Number of images to attach to posts.', 'activitypub' ),
				'default' => ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS,
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_object_type',
			array(
				'type' => 'string',
				'description' => \__( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array(
							'note',
							'article',
							'wordpress-post-format',
						),
					),
				),
				'default' => 'note',
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_use_hashtags',
			array(
				'type' => 'boolean',
				'description' => \__( 'Add hashtags in the content as native tags and replace the #tag with the tag-link', 'activitypub' ),
				'default' => 0,
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_support_post_types',
			array(
				'type'         => 'string',
				'description'  => \esc_html__( 'Enable ActivityPub support for post types', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => array( 'post', 'pages' ),
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_blog_user_identifier',
			array(
				'type'              => 'string',
				'description'       => \esc_html__( 'The Identifier of th Blog-User', 'activitypub' ),
				'show_in_rest'      => true,
				'default'           => 'feed',
				'sanitize_callback' => function( $value ) {
					// hack to allow dots in the username
					$parts     = explode( '.', $value );
					$sanitized = array();

					foreach ( $parts as $part ) {
						$sanitized[] = \sanitize_title( $part );
					}

					return implode( '.', $sanitized );
				},
			)
		);
	}

	public static function add_settings_help_tab() {
		require_once ACTIVITYPUB_PLUGIN_DIR . 'includes/help.php';
	}

	public static function add_followers_list_help_tab() {
		// todo
	}

	public static function add_profile( $user ) {
		$description = get_user_meta( $user->ID, 'activitypub_user_description', true );

		\load_template(
			ACTIVITYPUB_PLUGIN_DIR . 'templates/user-settings.php',
			true,
			array(
				'description' => $description,
			)
		);
	}

	public static function save_user_description( $user_id ) {
		if ( ! isset( $_REQUEST['_apnonce'] ) ) {
			return false;
		}
		if (
			! wp_verify_nonce( $_REQUEST['_apnonce'], 'activitypub-user-description' ) ||
			! current_user_can( 'edit_user', $user_id )
		) {
			return false;
		}
		update_user_meta( $user_id, 'activitypub_user_description', sanitize_text_field( $_POST['activitypub-user-description'] ) );
	}

	public static function enqueue_scripts( $hook_suffix ) {
		if ( false !== strpos( $hook_suffix, 'activitypub' ) ) {
			wp_enqueue_style( 'activitypub-admin-styles', plugins_url( 'assets/css/activitypub-admin.css', ACTIVITYPUB_PLUGIN_FILE ), array(), '1.0.0' );
			wp_enqueue_script( 'activitypub-admin-styles', plugins_url( 'assets/js/activitypub-admin.js', ACTIVITYPUB_PLUGIN_FILE ), array( 'jquery' ), '1.0.0', false );
		}
	}
}
