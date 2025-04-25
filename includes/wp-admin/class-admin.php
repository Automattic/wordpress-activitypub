<?php
/**
 * Admin Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Comment;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use function Activitypub\count_followers;
use function Activitypub\get_content_visibility;
use function Activitypub\is_user_type_disabled;
use function Activitypub\site_supports_blocks;
use function Activitypub\user_can_activitypub;
use function Activitypub\was_comment_received;

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
		\add_filter( 'admin_comment_types_dropdown', array( static::class, 'comment_types_dropdown' ) );

		\add_filter( 'manage_posts_columns', array( static::class, 'manage_post_columns' ), 10, 2 );
		\add_action( 'manage_posts_custom_column', array( self::class, 'manage_posts_custom_column' ), 10, 2 );

		\add_filter( 'manage_users_columns', array( self::class, 'manage_users_columns' ) );
		\add_filter( 'manage_users_custom_column', array( self::class, 'manage_users_custom_column' ), 10, 3 );
		\add_filter( 'bulk_actions-users', array( self::class, 'user_bulk_options' ) );
		\add_filter( 'handle_bulk_actions-users', array( self::class, 'handle_bulk_request' ), 10, 3 );

		if ( user_can_activitypub( \get_current_user_id() ) ) {
			\add_action( 'show_user_profile', array( self::class, 'add_profile' ) );
		}

		\add_filter( 'dashboard_glance_items', array( self::class, 'dashboard_glance_items' ) );
		\add_filter( 'plugin_action_links_' . ACTIVITYPUB_PLUGIN_BASENAME, array( self::class, 'add_plugin_settings_link' ) );
		\add_action( 'in_plugin_update_message-' . ACTIVITYPUB_PLUGIN_BASENAME, array( self::class, 'plugin_update_message' ), 10, 2 );

		if ( site_supports_blocks() ) {
			\add_action( 'tool_box', array( self::class, 'tool_box' ) );
		}

		\add_action( 'admin_print_footer_scripts-settings_page_activitypub', array( self::class, 'open_help_tab' ) );
	}

	/**
	 * Display admin menu notices about configuration problems or conflicts.
	 */
	public static function admin_notices() {
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
	 * Load user settings page
	 */
	public static function followers_list_page() {
		// User has to be able to publish posts.
		if ( user_can_activitypub( \get_current_user_id() ) ) {
			\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/user-followers-list.php' );
		}
	}

	/**
	 * Adds the follower list to the Help tab.
	 */
	public static function add_followers_list_help_tab() {
		// todo.
	}

	/**
	 * Render user settings.
	 */
	public static function add_profile() {
		wp_enqueue_media();
		wp_enqueue_script( 'activitypub-header-image' );

		wp_nonce_field( 'activitypub-user-settings', '_apnonce' );
		do_settings_sections( 'activitypub_user_settings' );
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

		// User options that should be processed with `sanitize_textarea_field()`.
		$textarea_field_user_options = array(
			'activitypub_blog_user_also_known_as',
			'activitypub_description',
		);

		foreach ( $textarea_field_user_options as $option ) {
			if ( ! empty( $_POST[ $option ] ) ) {
				\update_user_option( $user_id, $option, sanitize_textarea_field( wp_unslash( $_POST[ $option ] ) ) );
			} else {
				\delete_user_option( $user_id, $option );
			}
		}

		// User options that should be processed with `sanitize_text_field()`.
		$text_field_user_options = array(
			'activitypub_header_image',
		);

		foreach ( $text_field_user_options as $option ) {
			if ( ! empty( $_POST[ $option ] ) ) {
				\update_user_option( $user_id, $option, sanitize_text_field( wp_unslash( $_POST[ $option ] ) ) );
			} else {
				\delete_user_option( $user_id, $option );
			}
		}

		// User options that have a default value and therefore can't be empty (Empty triggers the default value).
		$required_user_options = array(
			'activitypub_mailer_new_dm',
			'activitypub_mailer_new_follower',
			'activitypub_mailer_new_mention',
		);

		foreach ( $required_user_options as $option ) {
			\update_user_option( $user_id, $option, sanitize_text_field( wp_unslash( $_POST[ $option ] ?? 0 ) ) );
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
			if ( 'add_activitypub_cap' === $action ) {
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

		if ( user_can_activitypub( \get_current_user_id() ) ) {
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
	 * Add a "Fediverse Preview ⁂" link to the row actions.
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
			ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL === get_content_visibility( $post->ID ) ||
			( site_supports_blocks() && \use_block_editor_for_post_type( $post->post_type ) )
		) {
			return $actions;
		}

		$preview_url = add_query_arg( 'activitypub', 'true', \get_preview_post_link( $post ) );

		$actions['activitypub'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			\esc_url( $preview_url ),
			\esc_html__( 'Fediverse Preview ⁂', 'activitypub' )
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

	/**
	 * Display plugin upgrade notice to users.
	 *
	 * @param array  $data   The plugin data.
	 * @param object $update The plugin update data.
	 */
	public static function plugin_update_message( $data, $update ) {
		if ( ! isset( $update->upgrade_notice ) ) {
			return;
		}

		echo '<br>' . wp_strip_all_tags( $update->upgrade_notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Adds metabox on wp-admin/tools.php.
	 */
	public static function tool_box() {
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/toolbox.php' );
	}

	/**
	 * Open the help tab.
	 *
	 * This function is used to open the help tab,
	 * it is triggered by the hash in the URL.
	 */
	public static function open_help_tab() {
		// get all tabs registered for the ActivityPub settings page.
		$tabs = \get_current_screen()->get_help_tabs();
		$ids  = \array_values( \wp_list_pluck( $tabs, 'id' ) );
		$ids  = \array_map(
			function ( $id ) {
				return '#tab-link-' . $id;
			},
			$ids
		);
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			// add allowed ids to the hash.
			const hash = window.location.hash;
			const allowed_ids = <?php echo \wp_json_encode( $ids ); ?>;
			if (allowed_ids.includes(hash)) {
				// Small delay to ensure the help tab is loaded.
				setTimeout(function() {
					document.getElementById('contextual-help-link').click();
					document.querySelector(hash + ' > a[href^="#tab-panel-"]').click();
				}, 500);
			}
		});
		</script>
		<?php
	}
}
