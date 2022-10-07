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
		\add_action( 'admin_init', array( '\Activitypub\Admin', 'version_check' ), 1 );
		\add_action( 'show_user_profile', array( '\Activitypub\Admin', 'add_fediverse_profile' ) );
		\add_action( 'admin_enqueue_scripts', array( '\Activitypub\Admin', 'enqueue_script_actions' ), 10, 2 );
		\add_action( 'wp_ajax_migrate_post', array( '\Activitypub\Admin', 'migrate_post_action' ) );
		\add_filter( 'comment_row_actions', array( '\Activitypub\Admin', 'reply_comments_actions' ), 10, 2 );

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

		\add_management_page( \__( 'ActivityPub Management', 'activitypub' ), \__( 'ActivityPub Tools', 'activitypub' ), 'manage_options', 'activitypub_tools', array( '\Activitypub\Admin', 'migrate_tools_page' ) );
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
	 * Load ActivityPub Tools page
	 */
	public static function migrate_tools_page() {
		\load_template( \dirname( __FILE__ ) . '/../templates/tools-page.php' );
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

	/**
	 * Update ActivityPub plugin
	 */
	public static function version_check() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_data = \get_plugin_data( ACTIVITYPUB_PLUGIN );
		$activitypub_db_version = \get_option( 'activitypub_version' );

		if ( empty( $activitypub_db_version ) || \version_compare( $plugin_data['Version'], $activitypub_db_version, '>' ) ) {
			// Check for specific migrations

			if ( version_compare( '0.13.4', $activitypub_db_version, '>' ) ) {
				\Activitypub\Tools\Posts::mark_posts_to_migrate();
			}
		}
		\update_option( 'activitypub_version', $plugin_data['Version'] );
		//\delete_option( 'activitypub_version' );
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

	public static function enqueue_script_actions( $hook ) {
		if ( 'edit-comments.php' === $hook || 'tools_page_activitypub_tools' === $hook ) {
			\wp_enqueue_script(
				'activitypub_actions',
				\plugin_dir_url( __FILE__ ) . '/activitypub.js',
				array( 'jquery' ),
				\filemtime( \plugin_dir_path( __FILE__ ) . '/activitypub.js' ),
				true
			);
		}
	}

	/**
	 * Migrate post (Ajax)
	 */
	public static function migrate_post_action() {
		if ( wp_verify_nonce( $_POST['nonce'], 'activitypub_migrate_actions' ) ) {
			\Activitypub\Tools\Posts::migrate_post( rawurldecode( $_POST['post_url'] ), absint( $_POST['post_author'] ) );
			\delete_post_meta( \url_to_postid( $_POST['post_url'] ), '_activitypub_permalink_compat' );	
		} else {
			$error = new WP_Error( 'nonce_failure', __( 'Unauthorized', 'activitypub' ) );
			wp_send_json_error( $error );
		}
		wp_die();
	}

	public static function reply_comments_actions( $actions, $comment ) {
		$recipients = \Activitypub\get_recipients( $comment->comment_ID );
		$summary = \Activitypub\get_summary( $comment->comment_ID );
		$reply_button = '<button type="button" data-comment-id="%d" data-post-id="%d" data-action="%s" class="%s button-link" aria-expanded="false" aria-label="%s" data-recipients="%s" data-summary="%s">%s</button>';
		$actions['reply'] = \sprintf(
			$reply_button,
			$comment->comment_ID,
			$comment->comment_post_ID,
			'replyto',
			'vim-r comment-inline',
			\esc_attr__( 'Reply to this comment', 'activitypub' ),
			$recipients,
			$summary,
			\__( 'Reply', 'activitypub' )
		);
		return $actions;
	}
}
