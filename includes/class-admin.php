<?php
/**
 * Admin Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_User_Query;
use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;

/**
 * ActivityPub Admin Class.
 *
 * @author Matthias Pfefferle
 */
class Admin {
	/**
	 * Initialize the class, registering WordPress hooks,
	 */
	public static function init() {
		\add_action( 'admin_menu', array( self::class, 'admin_menu' ) );
		\add_action( 'admin_init', array( self::class, 'register_settings' ) );
		\add_action( 'load-comment.php', array( self::class, 'edit_comment' ) );
		\add_action( 'load-post.php', array( self::class, 'edit_post' ) );
		\add_action( 'load-edit.php', array( self::class, 'list_posts' ) );
		\add_filter( 'page_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		\add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		\add_action( 'personal_options_update', array( self::class, 'save_user_settings' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		\add_action( 'admin_notices', array( self::class, 'admin_notices' ) );

		\add_filter( 'comment_row_actions', array( self::class, 'comment_row_actions' ), 10, 2 );
		\add_filter( 'manage_edit-comments_columns', array( static::class, 'manage_comment_columns' ) );
		\add_action( 'manage_comments_custom_column', array( static::class, 'manage_comments_custom_column' ), 9, 2 );
		\add_action( 'admin_comment_types_dropdown', array( static::class, 'comment_types_dropdown' ) );

		\add_filter( 'manage_posts_columns', array( static::class, 'manage_post_columns' ), 10, 2 );
		\add_action( 'manage_posts_custom_column', array( self::class, 'manage_posts_custom_column' ), 10, 2 );

		\add_filter( 'manage_users_columns', array( self::class, 'manage_users_columns' ) );
		\add_action( 'manage_users_custom_column', array( self::class, 'manage_users_custom_column' ), 10, 3 );
		\add_filter( 'bulk_actions-users', array( self::class, 'user_bulk_options' ) );
		\add_filter( 'handle_bulk_actions-users', array( self::class, 'handle_bulk_request' ), 10, 3 );

		if ( ! is_user_disabled( get_current_user_id() ) ) {
			\add_action( 'show_user_profile', array( self::class, 'add_profile' ) );
		}

		\add_filter( 'dashboard_glance_items', array( self::class, 'dashboard_glance_items' ) );
		\add_filter( 'plugin_action_links_' . ACTIVITYPUB_PLUGIN_BASENAME, array( self::class, 'add_plugin_settings_link' ) );
	}

	/**
	 * Add admin menu entry.
	 */
	public static function admin_menu() {
		$settings_page = \add_options_page(
			'Welcome',
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( self::class, 'settings_page' )
		);

		\add_action(
			'load-' . $settings_page,
			array( self::class, 'add_settings_help_tab' )
		);

		// User has to be able to publish posts.
		if ( ! is_user_disabled( get_current_user_id() ) ) {
			$followers_list_page = \add_users_page(
				\__( '⁂ Followers', 'activitypub' ),
				\__( '⁂ Followers', 'activitypub' ),
				'activitypub',
				'activitypub-followers-list',
				array(
					self::class,
					'followers_list_page',
				)
			);

			\add_action(
				'load-' . $followers_list_page,
				array( self::class, 'add_followers_list_help_tab' )
			);

			\add_users_page(
				\__( '⁂ Extra Fields', 'activitypub' ),
				\__( '⁂ Extra Fields', 'activitypub' ),
				'activitypub',
				\esc_url( \admin_url( '/edit.php?post_type=ap_extrafield' ) )
			);
		}
	}

	/**
	 * Display admin menu notices about configuration problems or conflicts.
	 */
	public static function admin_notices() {
		$permalink_structure = \get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			$admin_notice = sprintf(
				/* translators: %s: Permalink settings URL. */
				\__( 'ActivityPub needs SEO-friendly URLs to work properly. Please <a href="%s">update your permalink structure</a> to an option other than Plain.', 'activitypub' ),
				esc_url( admin_url( 'options-permalink.php' ) )
			);
			self::show_admin_notice( $admin_notice, 'error' );
		}

		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return;
		}
		if ( 'edit' === $current_screen->base && Extra_Fields::is_extra_fields_post_type( $current_screen->post_type ) ) {
			?>
			<div class="notice" style="margin: 0; background: none; border: none; box-shadow: none; padding: 15px 0 0 0; font-size: 14px;">
				<?php
					esc_html_e( 'These are extra fields that are used for your ActivityPub profile. You can use your homepage, social profiles, pronouns, age, anything you want.', 'activitypub' );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Display one admin menu notice about configuration problems or conflicts.
	 *
	 * @param string $admin_notice The notice to display.
	 * @param string $level        The level of the notice (error, warning, success, info).
	 */
	private static function show_admin_notice( $admin_notice, $level ) {
		?>

		<div class="notice notice-<?php echo esc_attr( $level ); ?>">
			<p><?php echo wp_kses( $admin_notice, 'data' ); ?></p>
		</div>

		<?php
	}

	/**
	 * Load settings page.
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
			case 'blog-profile':
				wp_enqueue_media();
				wp_enqueue_script( 'activitypub-header-image' );

				\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-settings.php' );
				break;
			case 'followers':
				\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-followers-list.php' );
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
		// User has to be able to publish posts.
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
				'type'         => 'string',
				'description'  => \__( 'Use title and link, summary, full or custom content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array(
							'title',
							'excerpt',
							'content',
						),
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
				'description'  => \__( 'Define your own custom post template', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => ACTIVITYPUB_CUSTOM_POST_CONTENT,
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_max_image_attachments',
			array(
				'type'        => 'integer',
				'description' => \__( 'Number of images to attach to posts.', 'activitypub' ),
				'default'     => ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS,
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_object_type',
			array(
				'type'         => 'string',
				'description'  => \__( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array(
							'note',
							'wordpress-post-format',
						),
					),
				),
				'default'      => ACTIVITYPUB_DEFAULT_OBJECT_TYPE,
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_use_hashtags',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Add hashtags in the content as native tags and replace the #tag with the tag-link', 'activitypub' ),
				'default'     => '0',
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_use_opengraph',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Automatically add "fediverse:creator" OpenGraph tags for Authors and the Blog-User.', 'activitypub' ),
				'default'     => '1',
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
			'activitypub_actor_mode',
			array(
				'type'        => 'integer',
				'description' => \__( 'Choose your preferred Actor-Mode.', 'activitypub' ),
				'default'     => ACTIVITYPUB_ACTOR_MODE,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_attribution_domains',
			array(
				'type'              => 'string',
				'description'       => \__( 'Websites allowed to credit you.', 'activitypub' ),
				'default'           => home_host(),
				'sanitize_callback' => function ( $value ) {
					$value = explode( PHP_EOL, $value );
					$value = array_filter( array_map( 'trim', $value ) );
					$value = array_filter( array_map( 'esc_attr', $value ) );
					$value = implode( PHP_EOL, $value );

					return $value;
				},
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_authorized_fetch',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Require HTTP signature authentication.', 'activitypub' ),
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_mailer_new_follower',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Send notifications via e-mail when a new follower is added.', 'activitypub' ),
				'default'     => '0',
			)
		);
		\register_setting(
			'activitypub',
			'activitypub_mailer_new_dm',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Send notifications via e-mail when a direct message is received.', 'activitypub' ),
				'default'     => '0',
			)
		);

		// Blog-User Settings.
		\register_setting(
			'activitypub_blog',
			'activitypub_blog_description',
			array(
				'type'         => 'string',
				'description'  => \esc_html__( 'The Description of the Blog-User', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => '',
			)
		);
		\register_setting(
			'activitypub_blog',
			'activitypub_blog_identifier',
			array(
				'type'              => 'string',
				'description'       => \esc_html__( 'The Identifier of the Blog-User', 'activitypub' ),
				'show_in_rest'      => true,
				'default'           => Blog::get_default_username(),
				'sanitize_callback' => function ( $value ) {
					// Hack to allow dots in the username.
					$parts     = explode( '.', $value );
					$sanitized = array();

					foreach ( $parts as $part ) {
						$sanitized[] = \sanitize_title( $part );
					}

					$sanitized = implode( '.', $sanitized );

					// Check for login or nicename.
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
							'activitypub_blog_identifier',
							'activitypub_blog_identifier',
							\esc_html__( 'You cannot use an existing author\'s name for the blog profile ID.', 'activitypub' ),
							'error'
						);

						return Blog::get_default_username();
					}

					return $sanitized;
				},
			)
		);
		\register_setting(
			'activitypub_blog',
			'activitypub_header_image',
			array(
				'type'        => 'integer',
				'description' => \__( 'The Attachment-ID of the Sites Header-Image', 'activitypub' ),
				'default'     => null,
			)
		);
	}

	/**
	 * Adds the ActivityPub settings to the Help tab.
	 */
	public static function add_settings_help_tab() {
		require_once ACTIVITYPUB_PLUGIN_DIR . 'includes/help.php';
	}

	/**
	 * Adds the follower list to the Help tab.
	 */
	public static function add_followers_list_help_tab() {
		// todo.
	}

	/**
	 * Add the profile.
	 *
	 * @param \WP_User $user The user object.
	 */
	public static function add_profile( $user ) {
		$description = \get_user_option( 'activitypub_description', $user->ID );

		wp_enqueue_media();
		wp_enqueue_script( 'activitypub-header-image' );

		\load_template(
			ACTIVITYPUB_PLUGIN_DIR . 'templates/user-settings.php',
			true,
			array(
				'description' => $description,
			)
		);
	}

	/**
	 * Save the user settings.
	 *
	 * Handles the saving of the ActivityPub settings.
	 *
	 * @param int $user_id The user ID.
	 */
	public static function save_user_settings( $user_id ) {
		if ( ! isset( $_REQUEST['_apnonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_apnonce'] ) );
		if (
			! wp_verify_nonce( $nonce, 'activitypub-user-settings' ) ||
			! current_user_can( 'edit_user', $user_id )
		) {
			return;
		}

		$description = ! empty( $_POST['activitypub_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['activitypub_description'] ) ) : false;
		if ( $description ) {
			\update_user_option( $user_id, 'activitypub_description', $description );
		} else {
			\delete_user_option( $user_id, 'activitypub_description' );
		}

		$header_image = ! empty( $_POST['activitypub_header_image'] ) ? sanitize_text_field( wp_unslash( $_POST['activitypub_header_image'] ) ) : false;
		if ( $header_image && \wp_attachment_is_image( $header_image ) ) {
			\update_user_option( $user_id, 'activitypub_header_image', $header_image );
		} else {
			\delete_user_option( $user_id, 'activitypub_header_image' );
		}
	}

	/**
	 * Enqueue the admin scripts and styles.
	 *
	 * @param string $hook_suffix The current page.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		wp_register_script(
			'activitypub-header-image',
			plugins_url(
				'assets/js/activitypub-header-image.js',
				ACTIVITYPUB_PLUGIN_FILE
			),
			array( 'jquery' ),
			ACTIVITYPUB_PLUGIN_VERSION,
			false
		);

		if ( false !== strpos( $hook_suffix, 'activitypub' ) ) {
			wp_enqueue_style(
				'activitypub-admin-styles',
				plugins_url(
					'assets/css/activitypub-admin.css',
					ACTIVITYPUB_PLUGIN_FILE
				),
				array(),
				ACTIVITYPUB_PLUGIN_VERSION
			);
			wp_enqueue_script(
				'activitypub-admin-script',
				plugins_url(
					'assets/js/activitypub-admin.js',
					ACTIVITYPUB_PLUGIN_FILE
				),
				array( 'jquery' ),
				ACTIVITYPUB_PLUGIN_VERSION,
				false
			);
		}

		if ( 'index.php' === $hook_suffix ) {
			wp_enqueue_style(
				'activitypub-admin-styles',
				plugins_url(
					'assets/css/activitypub-admin.css',
					ACTIVITYPUB_PLUGIN_FILE
				),
				array(),
				ACTIVITYPUB_PLUGIN_VERSION
			);
		}
	}

	/**
	 * Hook into the edit_comment functionality.
	 *
	 * Disables the edit_comment capability for federated comments.
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

	/**
	 * Hook into the edit_post functionality.
	 *
	 * Disables the edit_post capability for federated posts.
	 */
	public static function edit_post() {
		// Disable the edit_post capability for federated posts.
		\add_filter(
			'user_has_cap',
			function ( $allcaps, $caps, $arg ) {
				if ( 'edit_post' !== $arg[0] ) {
					return $allcaps;
				}

				$post = get_post( $arg[2] );

				if ( ! Extra_Fields::is_extra_field_post_type( $post->post_type ) ) {
					return $allcaps;
				}

				if ( (int) get_current_user_id() !== (int) $post->post_author ) {
					return false;
				}

				return $allcaps;
			},
			1,
			3
		);
	}

	/**
	 * Add ActivityPub specific actions/filters to the post list view.
	 */
	public static function list_posts() {
		// Show only the user's extra fields.
		\add_action(
			'pre_get_posts',
			function ( $query ) {
				if ( $query->get( 'post_type' ) === 'ap_extrafield' ) {
					$query->set( 'author', get_current_user_id() );
				}
			}
		);

		// Remove all views for the extra fields.
		$screen_id = get_current_screen()->id;

		add_filter(
			"views_{$screen_id}",
			function ( $views ) {
				if ( Extra_Fields::is_extra_fields_post_type( get_current_screen()->post_type ) ) {
					return array();
				}

				return $views;
			}
		);
	}

	/**
	 * Comment row actions.
	 *
	 * @param array           $actions The existing actions.
	 * @param int|\WP_Comment $comment The comment object or ID.
	 *
	 * @return array The modified actions.
	 */
	public static function comment_row_actions( $actions, $comment ) {
		if ( was_comment_received( $comment ) ) {
			unset( $actions['edit'] );
			unset( $actions['quickedit'] );
		}

		if ( in_array( get_comment_type( $comment ), Comment::get_comment_type_slugs(), true ) ) {
			unset( $actions['reply'] );
		}

		return $actions;
	}

	/**
	 * Add a column "activitypub".
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
	 * Add "comment-type" and "protocol" as column in WP-Admin.
	 *
	 * @param array $columns The list of column names.
	 *
	 * @return array The extended list of column names.
	 */
	public static function manage_comment_columns( $columns ) {
		$columns['comment_type']     = esc_attr__( 'Comment-Type', 'activitypub' );
		$columns['comment_protocol'] = esc_attr__( 'Protocol', 'activitypub' );

		return $columns;
	}

	/**
	 * Add "post_content" as column for Extra-Fields in WP-Admin.
	 *
	 * @param array  $columns   The list of column names.
	 * @param string $post_type The post type.
	 *
	 * @return array The extended list of column names.
	 */
	public static function manage_post_columns( $columns, $post_type ) {
		if ( Extra_Fields::is_extra_fields_post_type( $post_type ) ) {
			$after_key = 'title';
			$index     = array_search( $after_key, array_keys( $columns ), true );
			$columns   = array_slice( $columns, 0, $index + 1 ) + array( 'extra_field_content' => esc_attr__( 'Content', 'activitypub' ) ) + $columns;
		}

		return $columns;
	}

	/**
	 * Add "comment-type" and "protocol" as column in WP-Admin.
	 *
	 * @param array $column     The column to implement.
	 * @param int   $comment_id The comment id.
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
	 * Add the new ActivityPub comment types to the comment types dropdown.
	 *
	 * @param array $types The existing comment types.
	 *
	 * @return array The extended comment types.
	 */
	public static function comment_types_dropdown( $types ) {
		foreach ( Comment::get_comment_types() as $comment_type ) {
			$types[ $comment_type['type'] ] = esc_html( $comment_type['label'] );
		}

		return $types;
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
			return '<span aria-hidden="true">&#x2713;</span><span class="screen-reader-text">' . esc_html__( 'ActivityPub enabled for this author', 'activitypub' ) . '</span>';
		} else {
			return '<span aria-hidden="true">&#x2717;</span><span class="screen-reader-text">' . esc_html__( 'ActivityPub disabled for this author', 'activitypub' ) . '</span>';
		}
	}

	/**
	 * Add a column "extra_field_content" to the post list view.
	 *
	 * @param string $column_name The column name.
	 * @param int    $post_id     The post ID.
	 *
	 * @return void
	 */
	public static function manage_posts_custom_column( $column_name, $post_id ) {
		if ( 'extra_field_content' === $column_name ) {
			$post = get_post( $post_id );
			if ( Extra_Fields::is_extra_fields_post_type( $post->post_type ) ) {
				echo esc_attr( wp_strip_all_tags( $post->post_content ) );
			}
		}
	}

	/**
	 * Add options to the Bulk dropdown on the users page.
	 *
	 * @param array $actions The existing bulk options.
	 *
	 * @return array The extended bulk options.
	 */
	public static function user_bulk_options( $actions ) {
		$actions['add_activitypub_cap']    = __( 'Enable for ActivityPub', 'activitypub' );
		$actions['remove_activitypub_cap'] = __( 'Disable for ActivityPub', 'activitypub' );

		return $actions;
	}

	/**
	 * Handle bulk activitypub requests.
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

	/**
	 * Add ActivityPub infos to the dashboard glance items.
	 *
	 * @param array $items The existing glance items.
	 *
	 * @return array The extended glance items.
	 */
	public static function dashboard_glance_items( $items ) {
		\add_filter( 'number_format_i18n', '\Activitypub\custom_large_numbers', 10, 3 );

		if ( ! is_user_disabled( get_current_user_id() ) ) {
			$follower_count = sprintf(
				// translators: %s: number of followers.
				_n(
					'%s Follower',
					'%s Followers',
					count_followers( \get_current_user_id() ),
					'activitypub'
				),
				\number_format_i18n( count_followers( \get_current_user_id() ) )
			);
			$items['activitypub-followers-user'] = sprintf(
				'<a class="activitypub-followers" href="%1$s" title="%2$s">%3$s</a>',
				\esc_url( \admin_url( 'users.php?page=activitypub-followers-list' ) ),
				\esc_attr__( 'Your followers', 'activitypub' ),
				\esc_html( $follower_count )
			);
		}

		if ( ! is_user_type_disabled( 'blog' ) && current_user_can( 'manage_options' ) ) {
			$follower_count = sprintf(
				// translators: %s: number of followers.
				_n(
					'%s Follower (Blog)',
					'%s Followers (Blog)',
					count_followers( Actors::BLOG_USER_ID ),
					'activitypub'
				),
				\number_format_i18n( count_followers( Actors::BLOG_USER_ID ) )
			);
			$items['activitypub-followers-blog'] = sprintf(
				'<a class="activitypub-followers" href="%1$s" title="%2$s">%3$s</a>',
				\esc_url( \admin_url( 'options-general.php?page=activitypub&tab=followers' ) ),
				\esc_attr__( 'The Blog\'s followers', 'activitypub' ),
				\esc_html( $follower_count )
			);
		}

		\remove_filter( 'number_format_i18n', '\Activitypub\custom_large_numbers' );

		return $items;
	}

	/**
	 * Add a "⁂ Preview" link to the row actions.
	 *
	 * @param array    $actions The existing actions.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return array The modified actions.
	 */
	public static function row_actions( $actions, $post ) {
		// check if the post is enabled for ActivityPub.
		if (
			! \post_type_supports( \get_post_type( $post ), 'activitypub' ) ||
			! in_array( $post->post_status, array( 'pending', 'draft', 'future', 'publish' ), true ) ||
			! \current_user_can( 'edit_post', $post->ID ) ||
			ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL === get_content_visibility( $post ) ||
			site_supports_blocks()
		) {
			return $actions;
		}

		$preview_url = add_query_arg( 'activitypub', 'true', \get_preview_post_link( $post ) );

		$actions['activitypub'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			\esc_url( $preview_url ),
			\esc_html__( '⁂ Fediverse Preview', 'activitypub' )
		);

		return $actions;
	}

	/**
	 * Add plugin settings link.
	 *
	 * @param array $actions The current actions.
	 */
	public static function add_plugin_settings_link( $actions ) {
		$actions[] = \sprintf(
			'<a href="%1s">%2s</a>',
			\menu_page_url( 'activitypub', false ),
			\__( 'Settings', 'activitypub' )
		);

		return $actions;
	}
}
