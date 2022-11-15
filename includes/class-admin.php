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
		\add_action( 'admin_menu', array( '\Activitypub\Admin', 'admin_menu' ) );
		\add_action( 'admin_init', array( '\Activitypub\Admin', 'register_settings' ) );
		\add_action( 'show_user_profile', array( '\Activitypub\Admin', 'add_fediverse_profile' ) );
		\add_action( 'admin_enqueue_scripts', array( '\Activitypub\Admin', 'admin_style' ) );
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$settings_page = \add_submenu_page(
			null,
			'ActivityPub Settings',
			'ActivityPub',
			'manage_options',
			'activitypub-settings',
			array( '\Activitypub\Admin', 'settings_page' )
		);

		$welcome_page = \add_options_page(
			'Welcome',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( '\Activitypub\Admin', 'welcome_page' )
		);

		\add_action( 'load-' . $settings_page, array( '\Activitypub\Admin', 'add_settings_help_tab' ) );
		\add_action( 'load-' . $welcome_page, array( '\Activitypub\Admin', 'add_settings_help_tab' ) );

		$followers_list_page = \add_users_page( \__( 'Followers', 'activitypub' ), \__( 'Followers (Fediverse)', 'activitypub' ), 'read', 'activitypub-followers-list', array( '\Activitypub\Admin', 'followers_list_page' ) );

		\add_action( 'load-' . $followers_list_page, array( '\Activitypub\Admin', 'add_followers_list_help_tab' ) );
	}

	/**
	 * Load settings page
	 */
	public static function settings_page() {
		\load_template( \dirname( __FILE__ ) . '/../templates/settings.php' );
	}

	/**
	 * Load welcome page
	 */
	public static function welcome_page() {
		\load_template( \dirname( __FILE__ ) . '/../templates/welcome.php' );
	}

	/**
	 * Load user settings page
	 */
	public static function followers_list_page() {
		\load_template( \dirname( __FILE__ ) . '/../templates/followers-list.php' );
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
						'enum' => array( 'title', 'excerpt', 'content' ),
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
			'activitypub_object_type',
			array(
				'type' => 'string',
				'description' => \__( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'article', 'wordpress-post-format' ),
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
			'activitypub_allowed_html',
			array(
				'type' => 'string',
				'description' => \__( 'List of HTML elements that are allowed in activities.', 'activitypub' ),
				'default' => ACTIVITYPUB_ALLOWED_HTML,
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
	}

	public static function add_settings_help_tab() {
		require_once \dirname( __FILE__ ) . '/help.php';
	}

	public static function add_followers_list_help_tab() {
		// todo
	}

	public static function add_fediverse_profile( $user ) {
		?>
		<h2 id="fediverse"><?php \esc_html_e( 'Fediverse', 'activitypub' ); ?></h2>
		<?php
		\Activitypub\get_identifier_settings( $user->ID );
	}

	public static function admin_style() {
		wp_enqueue_style( 'admin-styles', plugin_dir_url( __FILE__ ) . '../assets/css/admin.css' );
	}
}
