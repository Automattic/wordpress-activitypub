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
		add_action( 'admin_menu', array( '\Activitypub\Admin', 'admin_menu' ) );
		add_action( 'admin_init', array( '\Activitypub\Admin', 'register_settings' ) );
		add_action( 'show_user_profile', array( '\Activitypub\Admin', 'add_fediverse_profile' ) );
	}
	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$settings_page = add_options_page(
			'ActivityPub',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( '\Activitypub\Admin', 'settings_page' )
		);

		add_action( 'load-' . $settings_page, array( '\Activitypub\Admin', 'add_help_tab' ) );
	}

	/**
	 * Load settings page
	 */
	public static function settings_page() {
		load_template( dirname( __FILE__ ) . '/../templates/settings-page.php' );
	}

	/**
	 * Register PubSubHubbub settings
	 */
	public static function register_settings() {
		register_setting(
			'activitypub', 'activitypub_post_content_type', array(
				'type' => 'string',
				'description' => __( 'Use summary or full content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'excerpt', 'content' ),
					),
				),
				'default' => 'content',
			)
		);
		register_setting(
			'activitypub', 'activitypub_object_type', array(
				'type' => 'string',
				'description' => __( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'article', 'wordpress-post-format' ),
					),
				),
				'default' => 'note',
			)
		);
		register_setting(
			'activitypub', 'activitypub_use_shortlink', array(
				'type' => 'boolean',
				'description' => __( 'Use the Shortlink instead of the permalink', 'activitypub' ),
				'default' => 0,
			)
		);
		register_setting(
			'activitypub', 'activitypub_use_hashtags', array(
				'type' => 'boolean',
				'description' => __( 'Use the Shortlink instead of the permalink', 'activitypub' ),
				'default' => 0,
			)
		);
	}

	public static function add_help_tab() {
		get_current_screen()->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'activitypub' ),
				'content' =>
					'<p>' . __( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ) . '</p>',
			)
		);

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'activitypub' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://activitypub.rocks/">Test Suite</a>', 'activitypub' ) . '</p>' .
			'<p>' . __( '<a href="https://www.w3.org/TR/activitypub/">W3C Spec</a>', 'activitypub' ) . '</p>' .
			'<p>' . __( '<a href="https://github.com/pfefferle/wordpress-activitypub/issues">Give us feedback</a>', 'activitypub' ) . '</p>' .
			'<hr />' .
			'<p>' . __( '<a href="https://notiz.blog/donate">Donate</a>', 'activitypub' ) . '</p>'
		);
	}

	public static function add_fediverse_profile( $user ) {
		?>
		<h2><?php esc_html_e( 'Fediverse', 'activitypub' ); ?></h2>
		<?php
		\Activitypub\get_identifier_settings( $user->ID );
	}
}
