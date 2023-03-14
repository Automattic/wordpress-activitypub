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
		\add_action( 'personal_options_update', array( '\Activitypub\Admin', 'save_user_description' ) );
		\add_action( 'admin_enqueue_scripts', array( '\Activitypub\Admin', 'enqueue_scripts' ) );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['tab'] ) ) {
			$tab = 'welcome';
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = sanitize_key( $_GET['tab'] );
		}

		switch ( $tab ) {
			case 'settings':
				\Activitypub\Model\Post::upgrade_post_content_template();

				\load_template( \dirname( __FILE__ ) . '/../templates/settings.php' );
				break;
			case 'welcome':
			default:
				wp_enqueue_script( 'plugin-install' );
				add_thickbox();
				wp_enqueue_script( 'updates' );

				\load_template( \dirname( __FILE__ ) . '/../templates/welcome.php' );
				break;
		}
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
		$ap_description = get_user_meta( $user->ID, ACTIVITYPUB_USER_DESCRIPTION_KEY, true );
		?>
		<h2 id="activitypub"><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h2>
		<?php
		\Activitypub\get_identifier_settings( $user->ID );
		?>
		<table class="form-table" role="presentation">
			<tr class="activitypub-user-description-wrap">
				<th><label for="activitypub-user-description"><?php _e( 'Fediverse Biography', 'activitypub' ); ?></label></th>
				<td><textarea name="activitypub-user-description" id="activitypub-user-description" rows="5" cols="30"><?php echo \esc_html( $ap_description ) ?></textarea>
				<p><?php _e( 'If you wish to use different biographical info for the fediverse, enter your alternate bio here.', 'activitypub' ); ?></p></td>
			</tr>
		</table>
		<?php
	}

	public static function save_user_description( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}
		update_user_meta( $user_id, ACTIVITYPUB_USER_DESCRIPTION_KEY, sanitize_text_field( $_POST['activitypub-user-description'] ) );
	}

	public static function enqueue_scripts( $hook_suffix ) {
		if ( false !== strpos( $hook_suffix, 'activitypub' ) ) {
			wp_enqueue_style( 'activitypub-admin-styles', plugins_url( 'assets/css/activitypub-admin.css', ACTIVITYPUB_PLUGIN_FILE ), array(), '1.0.0' );
			wp_enqueue_script( 'activitypub-admin-styles', plugins_url( 'assets/js/activitypub-admin.js', ACTIVITYPUB_PLUGIN_FILE ), array( 'jquery' ), '1.0.0', false );
		}
	}
}
