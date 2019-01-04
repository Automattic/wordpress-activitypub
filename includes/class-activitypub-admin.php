<?php
/**
 * ActivityPub Admin Class
 */
class Activitypub_Admin {
	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$settings_page = add_options_page(
			'ActivityPub',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( 'Activitypub_Admin', 'settings_page' )
		);

		add_action( 'load-' . $settings_page, array( 'Activitypub_Admin', 'add_help_tab' ) );
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
				'type'         => 'string',
				'description'  => __( 'Use summary or full content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'excerpt', 'content' )
					),
				),
				'default'      => 0,
			)
		);
		register_setting(
			'activitypub', 'activitypub_object_type', array(
				'type'         => 'string',
				'description'  => __( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'article', 'wordpress-post-format' )
					),
				),
				'default'      => 'note',
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
}
