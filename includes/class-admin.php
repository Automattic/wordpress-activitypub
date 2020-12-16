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
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$settings_page = \add_options_page(
			'ActivityPub',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( '\Activitypub\Admin', 'settings_page' )
		);

		\add_action( 'load-' . $settings_page, array( '\Activitypub\Admin', 'add_settings_help_tab' ) );

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
			'activitypub', 'activitypub_post_content_type', array(
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
			'activitypub', 'activitypub_custom_post_content', array(
				'type' => 'string',
				'description' => \__( 'Define your own custom post template', 'activitypub' ),
				'show_in_rest' => true,
				'default' => ACTIVITYPUB_CUSTOM_POST_CONTENT,
			)
		);
		\register_setting(
			'activitypub', 'activitypub_object_type', array(
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
			'activitypub', 'activitypub_use_hashtags', array(
				'type' => 'boolean',
				'description' => \__( 'Add hashtags in the content as native tags and replace the #tag with the tag-link', 'activitypub' ),
				'default' => 0,
			)
		);
		\register_setting(
			'activitypub', 'activitypub_allowed_html', array(
				'type' => 'string',
				'description' => \__( 'List of HTML elements that are allowed in activities.', 'activitypub' ),
				'default' => ACTIVITYPUB_ALLOWED_HTML,
			)
		);
		\register_setting(
			'activitypub', 'activitypub_support_post_types', array(
				'type'         => 'string',
				'description'  => \esc_html__( 'Enable ActivityPub support for post types', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => array( 'post', 'pages' ),
			)
		);
	}

	public static function add_settings_help_tab() {
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => \__( 'Overview', 'activitypub' ),
				'content' =>
					'<p>' . \__( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ) . '</p>',
			)
		);

		\get_current_screen()->set_help_sidebar(
			'<p><strong>' . \__( 'For more information:', 'activitypub' ) . '</strong></p>' .
			'<p>' . \__( '<a href="https://activitypub.rocks/">Test Suite</a>', 'activitypub' ) . '</p>' .
			'<p>' . \__( '<a href="https://www.w3.org/TR/activitypub/">W3C Spec</a>', 'activitypub' ) . '</p>' .
			'<p>' . \__( '<a href="https://github.com/pfefferle/wordpress-activitypub/issues">Give us feedback</a>', 'activitypub' ) . '</p>' .
			'<hr />' .
			'<p>' . \__( '<a href="https://notiz.blog/donate">Donate</a>', 'activitypub' ) . '</p>'
		);
	}

	public static function add_followers_list_help_tab() {
		// todo
	}

	public static function add_fediverse_profile( $user ) {
		?>
		<h2><?php \esc_html_e( 'Fediverse', 'activitypub' ); ?></h2>
		<?php
		\Activitypub\get_identifier_settings( $user->ID );
	}
}
