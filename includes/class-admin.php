<?php
namespace Activitypub;

use WP_User_Query;
use Activitypub\Model\Blog_User;

use function Activitypub\is_user_disabled;
use function Activitypub\was_comment_received;
use function Activitypub\is_comment_federatable;

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
		\add_action( 'load-comment.php', array( self::class, 'edit_comment' ) );
		\add_action( 'personal_options_update', array( self::class, 'save_user_description' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		\add_action( 'admin_notices', array( self::class, 'admin_notices' ) );

		\add_filter( 'comment_row_actions', array( self::class, 'comment_row_actions' ), 10, 2 );
		\add_filter( 'manage_edit-comments_columns', array( static::class, 'manage_comment_columns' ) );
		\add_filter( 'manage_comments_custom_column', array( static::class, 'manage_comments_custom_column' ), 9, 2 );

		\add_filter( 'manage_users_columns', array( self::class, 'manage_users_columns' ), 10, 1 );
		\add_filter( 'manage_users_custom_column', array( self::class, 'manage_users_custom_column' ), 10, 3 );
		\add_filter( 'bulk_actions-users', array( self::class, 'user_bulk_options' ) );
		\add_filter( 'handle_bulk_actions-users', array( self::class, 'handle_bulk_request' ), 10, 3 );

		if ( ! is_user_disabled( get_current_user_id() ) ) {
			\add_action( 'show_user_profile', array( self::class, 'add_profile' ) );
		}
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
	 * Display admin menu notices about configuration problems or conflicts.
	 *
	 * @return void
	 */
	public static function admin_notices() {
		$permalink_structure = \get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			$admin_notice = \__( 'You are using the ActivityPub plugin with a permalink structure of "plain". This will prevent ActivityPub from working.  Please go to "Settings" / "Permalinks" and choose a permalink structure other than "plain".', 'activitypub' );
			self::show_admin_notice( $admin_notice, 'error' );
		}
	}

	/**
	 * Display one admin menu notice about configuration problems or conflicts.
	 *
	 * @param string $admin_notice The notice to display.
	 * @param string $level The level of the notice (error, warning, success, info).
	 *
	 * @return void
	 */
	private static function show_admin_notice( $admin_notice, $level ) {
		?>

		<div class="notice notice-<?php echo esc_attr( $level ); ?>">
			<p><?php echo wp_kses( $admin_notice, 'data' ); ?></p>
		</div>

		<?php
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
		// user has to be able to publish posts
		if ( ! is_user_disabled( get_current_user_id() ) ) {
			\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/user-followers-list.php' );
		}
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
				'default' => '0',
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
			'activitypub_blog_user_identifier',
			array(
				'type'              => 'string',
				'description'       => \esc_html__( 'The Identifier of the Blog-User', 'activitypub' ),
				'show_in_rest'      => true,
				'default'           => Blog_User::get_default_username(),
				'sanitize_callback' => function ( $value ) {
					// hack to allow dots in the username
					$parts     = explode( '.', $value );
					$sanitized = array();

					foreach ( $parts as $part ) {
						$sanitized[] = \sanitize_title( $part );
					}

					$sanitized = implode( '.', $sanitized );

					// check for login or nicename.
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
							'activitypub_blog_user_identifier',
							'activitypub_blog_user_identifier',
							\esc_html__( 'You cannot use an existing author\'s name for the blog profile ID.', 'activitypub' ),
							'error'
						);

						return Blog_User::get_default_username();
					}

					return $sanitized;
				},
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_enable_users',
			array(
				'type' => 'boolean',
				'description' => \__( 'Every Author on this Blog (with the publish_posts capability) gets his own ActivityPub enabled Profile.', 'activitypub' ),
				'default' => '1',
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_enable_blog_user',
			array(
				'type' => 'boolean',
				'description' => \__( 'Your Blog becomes an ActivityPub compatible Profile.', 'activitypub' ),
				'default' => '0',
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
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_apnonce'] ) );
		if (
			! wp_verify_nonce( $nonce, 'activitypub-user-description' ) ||
			! current_user_can( 'edit_user', $user_id )
		) {
			return false;
		}
		$description = ! empty( $_POST['activitypub-user-description'] ) ? sanitize_text_field( wp_unslash( $_POST['activitypub-user-description'] ) ) : false;
		if ( $description ) {
			update_user_meta( $user_id, 'activitypub_user_description', $description );
		}
	}

	public static function enqueue_scripts( $hook_suffix ) {
		if ( false !== strpos( $hook_suffix, 'activitypub' ) ) {
			wp_enqueue_style( 'activitypub-admin-styles', plugins_url( 'assets/css/activitypub-admin.css', ACTIVITYPUB_PLUGIN_FILE ), array(), '1.0.0' );
			wp_enqueue_script( 'activitypub-admin-styles', plugins_url( 'assets/js/activitypub-admin.js', ACTIVITYPUB_PLUGIN_FILE ), array( 'jquery' ), '1.0.0', false );
		}
	}

	/**
	 * Hook into the edit_comment functionality
	 *
	 * * Disable the edit_comment capability for federated comments.
	 *
	 * @return void
	 */
	public static function edit_comment() {
		// Disable the edit_comment capability for federated comments.
		\add_filter(
			'user_has_cap',
			function ( $allcaps, $caps, $arg ) {
				if ( 'edit_comment' !== $arg[0] ) {
					return $allcaps;
				}

				if ( was_comment_received( $arg[2] ) ) {
					return false;
				}

				return $allcaps;
			},
			1,
			3
		);
	}

	public static function comment_row_actions( $actions, $comment ) {
		if ( was_comment_received( $comment ) ) {
			unset( $actions['edit'] );
			unset( $actions['quickedit'] );
		}

		return $actions;
	}

	/**
	 * Add a column "activitypub"
	 *
	 * This column shows if the user has the capability to use ActivityPub.
	 *
	 * @param array $columns The columns.
	 *
	 * @return array The columns extended by the activitypub.
	 */
	public static function manage_users_columns( $columns ) {
		$columns['activitypub'] = __( 'ActivityPub', 'activitypub' );
		return $columns;
	}

	/**
	 * Add "comment-type" and "protocol" as column in WP-Admin
	 *
	 * @param array $columns the list of column names
	 */
	public static function manage_comment_columns( $columns ) {
		$columns['comment_type'] = esc_attr__( 'Comment-Type', 'activitypub' );
		$columns['comment_protocol'] = esc_attr__( 'Protocol', 'activitypub' );

		return $columns;
	}

	/**
	 * Add "comment-type" and "protocol" as column in WP-Admin
	 *
	 * @param array $column     The column to implement
	 * @param int   $comment_id The comment id
	 */
	public static function manage_comments_custom_column( $column, $comment_id ) {
		if ( 'comment_type' === $column && ! defined( 'WEBMENTION_PLUGIN_DIR' ) ) {
			echo esc_attr( ucfirst( get_comment_type( $comment_id ) ) );
		} elseif ( 'comment_protocol' === $column ) {
			$protocol = get_comment_meta( $comment_id, 'protocol', true );

			if ( $protocol ) {
				echo esc_attr( ucfirst( str_replace( 'activitypub', 'ActivityPub', $protocol ) ) );
			} else {
				esc_attr_e( 'Local', 'activitypub' );
			}
		}
	}

	/**
	 * Return the results for the activitypub column.
	 *
	 * @param string $output      Custom column output. Default empty.
	 * @param string $column_name Column name.
	 * @param int    $user_id     ID of the currently-listed user.
	 *
	 * @return string The column contents.
	 */
	public static function manage_users_custom_column( $output, $column_name, $user_id ) {
		if ( 'activitypub' !== $column_name ) {
			return $output;
		}

		if ( \user_can( $user_id, 'activitypub' ) ) {
			return '&#x2713;';
		} else {
			return '&#x2717;';
		}
	}

	/**
	 * Add options to the Bulk dropdown on the users page
	 *
	 * @param array $actions The existing bulk options.
	 *
	 * @return array The extended bulk options.
	 */
	public static function user_bulk_options( $actions ) {
		$actions['add_activitypub_cap'] = __( 'Enable for ActivityPub', 'activitypub' );
		$actions['remove_activitypub_cap'] = __( 'Disable for ActivityPub', 'activitypub' );

		return $actions;
	}

	/**
	 * Handle bulk activitypub requests
	 *
	 * * `add_activitypub_cap` - Add the activitypub capability to the selected users.
	 * * `remove_activitypub_cap` - Remove the activitypub capability from the selected users.
	 *
	 * @param string $sendback The URL to send the user back to.
	 * @param string $action   The requested action.
	 * @param array  $users    The selected users.
	 *
	 * @return string The URL to send the user back to.
	 */
	public static function handle_bulk_request( $sendback, $action, $users ) {
		if (
			'remove_activitypub_cap' !== $action &&
			'add_activitypub_cap' !== $action
		) {
			return $sendback;
		}

		foreach ( $users as $user_id ) {
			$user = new \WP_User( $user_id );
			if (
				'add_activitypub_cap' === $action &&
				user_can( $user_id, 'publish_posts' )
			) {
				$user->add_cap( 'activitypub' );
			} elseif ( 'remove_activitypub_cap' === $action ) {
				$user->remove_cap( 'activitypub' );
			}
		}

		return $sendback;
	}
}
