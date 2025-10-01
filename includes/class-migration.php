<?php
/**
 * Migration class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Transformer\Factory;

/**
 * ActivityPub Migration Class
 *
 * @author Matthias Pfefferle
 */
class Migration {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::maybe_migrate();

		Scheduler::register_async_batch_callback( 'activitypub_migrate_from_0_17', array( self::class, 'migrate_from_0_17' ) );
		Scheduler::register_async_batch_callback( 'activitypub_update_comment_counts', array( self::class, 'update_comment_counts' ) );
		Scheduler::register_async_batch_callback( 'activitypub_create_post_outbox_items', array( self::class, 'create_post_outbox_items' ) );
		Scheduler::register_async_batch_callback( 'activitypub_create_comment_outbox_items', array( self::class, 'create_comment_outbox_items' ) );
	}

	/**
	 * The current version of the database structure.
	 *
	 * @return string The current version.
	 */
	public static function get_version() {
		return get_option( 'activitypub_db_version', 0 );
	}

	/**
	 * Locks the database migration process to prevent simultaneous migrations.
	 *
	 * @return bool|int True if the lock was successful, timestamp of existing lock otherwise.
	 */
	public static function lock() {
		global $wpdb;

		// Try to lock.
		$lock_result = (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", 'activitypub_migration_lock', \time() ) ); // phpcs:ignore WordPress.DB

		if ( ! $lock_result ) {
			$lock_result = \get_option( 'activitypub_migration_lock' );
		}

		return $lock_result;
	}

	/**
	 * Unlocks the database migration process.
	 */
	public static function unlock() {
		\delete_option( 'activitypub_migration_lock' );
	}

	/**
	 * Whether the database migration process is locked.
	 *
	 * @return boolean
	 */
	public static function is_locked() {
		$lock = \get_option( 'activitypub_migration_lock' );

		if ( ! $lock ) {
			return false;
		}

		$lock = (int) $lock;

		if ( $lock < \time() - 1800 ) {
			self::unlock();
			return false;
		}

		return true;
	}

	/**
	 * Whether the database structure is up to date.
	 *
	 * @return bool True if the database structure is up to date, false otherwise.
	 */
	public static function is_latest_version() {
		return (bool) \version_compare(
			self::get_version(),
			ACTIVITYPUB_PLUGIN_VERSION,
			'=='
		);
	}

	/**
	 * Updates the database structure if necessary.
	 */
	public static function maybe_migrate() {
		if ( self::is_latest_version() ) {
			return;
		}

		if ( self::is_locked() ) {
			return;
		}

		self::lock();

		$version_from_db = self::get_version();

		// Check for initial migration.
		if ( ! $version_from_db ) {
			self::add_default_settings();
			$version_from_db = ACTIVITYPUB_PLUGIN_VERSION;
		}

		if ( \version_compare( $version_from_db, '0.17.0', '<' ) ) {
			self::migrate_from_0_16();
		}
		if ( \version_compare( $version_from_db, '1.0.0', '<' ) ) {
			\wp_schedule_single_event( \time(), 'activitypub_migrate_from_0_17' );
		}
		if ( \version_compare( $version_from_db, '1.3.0', '<' ) ) {
			self::migrate_from_1_2_0();
		}
		if ( \version_compare( $version_from_db, '2.1.0', '<' ) ) {
			self::migrate_from_2_0_0();
		}
		if ( \version_compare( $version_from_db, '2.3.0', '<' ) ) {
			self::migrate_from_2_2_0();
		}
		if ( \version_compare( $version_from_db, '3.0.0', '<' ) ) {
			self::migrate_from_2_6_0();
		}
		if ( \version_compare( $version_from_db, '4.0.0', '<' ) ) {
			self::migrate_to_4_0_0();
		}
		if ( \version_compare( $version_from_db, '4.1.0', '<' ) ) {
			self::migrate_to_4_1_0();
		}
		if ( \version_compare( $version_from_db, '4.5.0', '<' ) ) {
			\wp_schedule_single_event( \time() + MINUTE_IN_SECONDS, 'activitypub_update_comment_counts' );
		}
		if ( \version_compare( $version_from_db, '4.7.1', '<' ) ) {
			self::migrate_to_4_7_1();
		}
		if ( \version_compare( $version_from_db, '4.7.2', '<' ) ) {
			self::migrate_to_4_7_2();
		}
		if ( \version_compare( $version_from_db, '4.7.3', '<' ) ) {
			add_action( 'init', 'flush_rewrite_rules', 20 );
		}
		if ( \version_compare( $version_from_db, '5.0.0', '<' ) ) {
			Scheduler::register_schedules();
			\wp_schedule_single_event( \time(), 'activitypub_create_post_outbox_items' );
			\wp_schedule_single_event( \time() + 15, 'activitypub_create_comment_outbox_items' );
			add_action( 'init', 'flush_rewrite_rules', 20 );
		}
		if ( \version_compare( $version_from_db, '5.4.0', '<' ) ) {
			\wp_schedule_single_event( \time(), 'activitypub_upgrade', array( 'update_actor_json_slashing' ) );
			\wp_schedule_single_event( \time(), 'activitypub_upgrade', array( 'update_comment_author_emails' ) );
			\add_action( 'init', 'flush_rewrite_rules', 20 );
		}
		if ( \version_compare( $version_from_db, '5.7.0', '<' ) ) {
			self::delete_mastodon_api_orphaned_extra_fields();
		}
		if ( \version_compare( $version_from_db, '5.8.0', '<' ) ) {
			self::update_notification_options();
		}

		if ( \version_compare( $version_from_db, '6.0.0', '<' ) ) {
			self::migrate_followers_to_ap_actor_cpt();
			\wp_schedule_single_event( \time(), 'activitypub_upgrade', array( 'update_actor_json_storage' ) );
		}

		if ( \version_compare( $version_from_db, '6.0.1', '<' ) ) {
			self::migrate_followers_to_ap_actor_cpt();
			\wp_schedule_single_event( \time(), 'activitypub_upgrade', array( 'update_actor_json_storage' ) );
		}

		if ( \version_compare( $version_from_db, '7.0.0', '<' ) ) {
			wp_unschedule_hook( 'activitypub_update_followers' );
			wp_unschedule_hook( 'activitypub_cleanup_followers' );

			if ( ! \wp_next_scheduled( 'activitypub_update_remote_actors' ) ) {
				\wp_schedule_event( time(), 'hourly', 'activitypub_update_remote_actors' );
			}

			if ( ! \wp_next_scheduled( 'activitypub_cleanup_remote_actors' ) ) {
				\wp_schedule_event( time(), 'daily', 'activitypub_cleanup_remote_actors' );
			}
		}

		if ( \version_compare( $version_from_db, '7.3.0', '<' ) ) {
			self::remove_pending_application_user_follow_requests();
		}

		if ( \version_compare( $version_from_db, '7.5.0', '<' ) ) {
			self::sync_jetpack_following_meta();
		}

		// Ensure all required cron schedules are registered.
		Scheduler::register_schedules();

		/*
		 * Add new update routines above this comment. ^
		 *
		 * Use 'unreleased' as the version number for new migrations and add tests for the callback directly.
		 * The release script will automatically replace it with the actual version number.
		 * Example:
		 *
		 * if ( \version_compare( $version_from_db, 'unreleased', '<' ) ) {
		 *     // Update routine.
		 * }
		 */

		/**
		 * Fires when the system has to be migrated.
		 *
		 * @param string $version_from_db The version from which to migrate.
		 * @param string $target_version  The target version to migrate to.
		 */
		\do_action( 'activitypub_migrate', $version_from_db, ACTIVITYPUB_PLUGIN_VERSION );

		\update_option( 'activitypub_db_version', ACTIVITYPUB_PLUGIN_VERSION );

		self::unlock();
	}

	/**
	 * Updates the custom template to use shortcodes instead of the deprecated templates.
	 */
	private static function migrate_from_0_16() {
		// Get the custom template.
		$old_content = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );

		/*
		 * If the old content exists but is a blank string, we're going to need a flag to updated it even
		 * after setting it to the default contents.
		 */
		$need_update = false;

		// If the old contents is blank, use the defaults.
		if ( '' === $old_content ) {
			$old_content = ACTIVITYPUB_CUSTOM_POST_CONTENT;
			$need_update = true;
		}

		// Set the new content to be the old content.
		$content = $old_content;

		// Convert old templates to shortcodes.
		$content = \str_replace( '%title%', '[ap_title]', $content );
		$content = \str_replace( '%excerpt%', '[ap_excerpt]', $content );
		$content = \str_replace( '%content%', '[ap_content]', $content );
		$content = \str_replace( '%permalink%', '[ap_permalink type="html"]', $content );
		$content = \str_replace( '%shortlink%', '[ap_shortlink type="html"]', $content );
		$content = \str_replace( '%hashtags%', '[ap_hashtags]', $content );
		$content = \str_replace( '%tags%', '[ap_hashtags]', $content );

		// Store the new template if required.
		if ( $content !== $old_content || $need_update ) {
			\update_option( 'activitypub_custom_post_content', $content );
		}
	}

	/**
	 * Updates the DB-schema of the followers-list.
	 */
	public static function migrate_from_0_17() {
		// Migrate followers.
		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			$followers = get_user_meta( $user_id, 'activitypub_followers', true );

			if ( $followers ) {
				foreach ( $followers as $actor ) {
					Followers::add_follower( $user_id, $actor );
				}
			}
		}

		Activitypub::flush_rewrite_rules();
	}

	/**
	 * Clear the cache after updating to 1.3.0.
	 */
	private static function migrate_from_1_2_0() {
		$user_ids = \get_users(
			array(
				'fields'         => 'ID',
				'capability__in' => array( 'publish_posts' ),
			)
		);

		foreach ( $user_ids as $user_id ) {
			wp_cache_delete( sprintf( Followers::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );
		}
	}

	/**
	 * Unschedule Hooks after updating to 2.0.0.
	 */
	private static function migrate_from_2_0_0() {
		wp_clear_scheduled_hook( 'activitypub_send_post_activity' );
		wp_clear_scheduled_hook( 'activitypub_send_update_activity' );
		wp_clear_scheduled_hook( 'activitypub_send_delete_activity' );

		wp_unschedule_hook( 'activitypub_send_post_activity' );
		wp_unschedule_hook( 'activitypub_send_update_activity' );
		wp_unschedule_hook( 'activitypub_send_delete_activity' );

		$object_type = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );
		if ( 'article' === $object_type ) {
			\update_option( 'activitypub_object_type', 'wordpress-post-format' );
		}
	}

	/**
	 * Add the ActivityPub capability to all users that can publish posts
	 * Delete old meta to store followers.
	 */
	private static function migrate_from_2_2_0() {
		// Add the ActivityPub capability to all users that can publish posts.
		self::add_activitypub_capability();
	}

	/**
	 * Rename DB fields.
	 */
	private static function migrate_from_2_6_0() {
		wp_cache_flush();

		self::update_usermeta_key( 'activitypub_user_description', 'activitypub_description' );

		self::update_options_key( 'activitypub_blog_user_description', 'activitypub_blog_description' );
		self::update_options_key( 'activitypub_blog_user_identifier', 'activitypub_blog_identifier' );
	}

	/**
	 * * Update actor-mode settings.
	 * * Get the ID of the latest blog post and save it to the options table.
	 */
	private static function migrate_to_4_0_0() {
		$latest_post_id = 0;

		// Get the ID of the latest blog post and save it to the options table.
		$latest_post = get_posts(
			array(
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
				'post_type'   => 'any',
				'post_status' => 'publish',
			)
		);

		if ( $latest_post ) {
			$latest_post_id = $latest_post[0]->ID;
		}

		\update_option( 'activitypub_last_post_with_permalink_as_id', $latest_post_id );

		$users = \get_users(
			array(
				'capability__in' => array( 'activitypub' ),
			)
		);

		foreach ( $users as $user ) {
			$followers = Followers::get_followers( $user->ID );

			if ( $followers ) {
				\update_user_option( $user->ID, 'activitypub_use_permalink_as_id', '1' );
			}
		}

		$followers = Followers::get_followers( Actors::BLOG_USER_ID );

		if ( $followers ) {
			\update_option( 'activitypub_use_permalink_as_id_for_blog', '1' );
		}

		self::migrate_actor_mode();
	}

	/**
	 * Update to 4.1.0
	 *
	 * * Migrate the `activitypub_post_content_type` to only use `activitypub_custom_post_content`.
	 */
	public static function migrate_to_4_1_0() {
		$content_type = \get_option( 'activitypub_post_content_type' );

		switch ( $content_type ) {
			case 'excerpt':
				$template = "[ap_excerpt]\n\n[ap_permalink type=\"html\"]";
				break;
			case 'title':
				$template = "[ap_title type=\"html\"]\n\n[ap_permalink type=\"html\"]";
				break;
			case 'content':
				$template = "[ap_content]\n\n[ap_permalink type=\"html\"]\n\n[ap_hashtags]";
				break;
			case 'custom':
				$template = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
				break;
			default:
				$template = ACTIVITYPUB_CUSTOM_POST_CONTENT;
				break;
		}

		\update_option( 'activitypub_custom_post_content', $template );

		\delete_option( 'activitypub_post_content_type' );

		$object_type = \get_option( 'activitypub_object_type', false );
		if ( ! $object_type ) {
			\update_option( 'activitypub_object_type', 'note' );
		}

		// Clean up empty visibility meta.
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM $wpdb->postmeta
			WHERE meta_key = 'activitypub_content_visibility'
			AND (meta_value IS NULL OR meta_value = '')"
		);
	}

	/**
	 * Updates post meta keys to be prefixed with an underscore.
	 */
	public static function migrate_to_4_7_1() {
		global $wpdb;

		$meta_keys = array(
			'activitypub_actor_json',
			'activitypub_canonical_url',
			'activitypub_errors',
			'activitypub_inbox',
			'activitypub_user_id',
		);

		foreach ( $meta_keys as $meta_key ) {
			// phpcs:ignore WordPress.DB
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_' . $meta_key ), array( 'meta_key' => $meta_key ) );
		}
	}

	/**
	 * Clears the post cache for Followers, we should have done this in 4.7.1 when we renamed those keys.
	 */
	public static function migrate_to_4_7_2() {
		global $wpdb;
		// phpcs:ignore WordPress.DB
		$followers = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", Remote_Actors::POST_TYPE )
		);
		foreach ( $followers as $id ) {
			clean_post_cache( $id );
		}
	}

	/**
	 * Update comment counts for posts in batches.
	 *
	 * @see Comment::pre_wp_update_comment_count_now()
	 * @param int $batch_size Optional. Number of posts to process per batch. Default 100.
	 * @param int $offset     Optional. Number of posts to skip. Default 0.
	 *
	 * @return int[]|void Array with batch size and offset if there are more posts to process.
	 */
	public static function update_comment_counts( $batch_size = 100, $offset = 0 ) {
		global $wpdb;

		Comment::register_comment_types();
		$comment_types  = Comment::get_comment_type_slugs();
		$type_inclusion = "AND comment_type IN ('" . implode( "','", $comment_types ) . "')";

		// Get and process this batch.
		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT DISTINCT comment_post_ID FROM {$wpdb->comments} WHERE comment_approved = '1' {$type_inclusion} ORDER BY comment_post_ID LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			)
		);

		foreach ( $post_ids as $post_id ) {
			\wp_update_comment_count_now( $post_id );
		}

		if ( count( $post_ids ) === $batch_size ) {
			// Schedule next batch.
			return array( $batch_size, $offset + $batch_size );
		}
	}

	/**
	 * Create outbox items for posts in batches.
	 *
	 * @param int $batch_size Optional. Number of posts to process per batch. Default 50.
	 * @param int $offset     Optional. Number of posts to skip. Default 0.
	 * @return array|null Array with batch size and offset if there are more posts to process, null otherwise.
	 */
	public static function create_post_outbox_items( $batch_size = 50, $offset = 0 ) {
		$posts = \get_posts(
			array(
				// our own `ap_outbox` will be excluded from `any` by virtue of its `exclude_from_search` arg.
				'post_type'      => 'any',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => 'activitypub_status',
						'value' => 'federated',
					),
				),
			)
		);

		// Avoid multiple queries for post meta.
		\update_postmeta_cache( \wp_list_pluck( $posts, 'ID' ) );

		foreach ( $posts as $post ) {
			$visibility = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );

			self::add_to_outbox( $post, 'Create', $post->post_author, $visibility );

			// Add Update activity when the post has been modified.
			if ( $post->post_modified !== $post->post_date ) {
				self::add_to_outbox( $post, 'Update', $post->post_author, $visibility );
			}
		}

		if ( count( $posts ) === $batch_size ) {
			return array(
				'batch_size' => $batch_size,
				'offset'     => $offset + $batch_size,
			);
		}

		return null;
	}

	/**
	 * Create outbox items for comments in batches.
	 *
	 * @param int $batch_size Optional. Number of posts to process per batch. Default 50.
	 * @param int $offset     Optional. Number of posts to skip. Default 0.
	 * @return array|null Array with batch size and offset if there are more posts to process, null otherwise.
	 */
	public static function create_comment_outbox_items( $batch_size = 50, $offset = 0 ) {
		$comments = \get_comments(
			array(
				'author__not_in' => array( 0 ), // Limit to comments by registered users.
				'number'         => $batch_size,
				'offset'         => $offset,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => 'activitypub_status',
						'value' => 'federated',
					),
				),
			)
		);

		foreach ( $comments as $comment ) {
			self::add_to_outbox( $comment, 'Create', $comment->user_id );
		}

		if ( count( $comments ) === $batch_size ) {
			return array(
				'batch_size' => $batch_size,
				'offset'     => $offset + $batch_size,
			);
		}

		return null;
	}

	/**
	 * Update _activitypub_actor_json meta values to ensure they are properly slashed.
	 *
	 * @param int $batch_size Optional. Number of meta values to process per batch. Default 100.
	 * @param int $offset     Optional. Number of meta values to skip. Default 0.
	 * @return array|null Array with batch size and offset if there are more meta values to process, null otherwise.
	 */
	public static function update_actor_json_slashing( $batch_size = 100, $offset = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$meta_values = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_actor_json' LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			)
		);

		foreach ( $meta_values as $meta ) {
			$json = \json_decode( $meta->meta_value, true );

			// If json_decode fails, try adding slashes.
			if ( null === $json && \json_last_error() !== JSON_ERROR_NONE ) {
				$escaped_value = \preg_replace( '#\\\\(?!["\\\\/bfnrtu])#', '\\\\\\\\', $meta->meta_value );
				$json          = \json_decode( $escaped_value, true );

				// Update the meta if json_decode succeeds with slashes.
				if ( null !== $json && \json_last_error() === JSON_ERROR_NONE ) {
					\update_post_meta( $meta->post_id, '_activitypub_actor_json', \wp_slash( $escaped_value ) );
				}
			}
		}

		if ( \count( $meta_values ) === $batch_size ) {
			return array(
				'batch_size' => $batch_size,
				'offset'     => $offset + $batch_size,
			);
		}

		return null;
	}

	/**
	 * Update comment author emails with webfinger addresses for ActivityPub comments.
	 *
	 * @param int $batch_size Optional. Number of comments to process per batch. Default 50.
	 * @param int $offset     Optional. Number of comments to skip. Default 0.
	 * @return array|null Array with batch size and offset if there are more comments to process, null otherwise.
	 */
	public static function update_comment_author_emails( $batch_size = 50, $offset = 0 ) {
		$comments = \get_comments(
			array(
				'number'     => $batch_size,
				'offset'     => $offset,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'   => 'protocol',
						'value' => 'activitypub',
					),
				),
			)
		);

		foreach ( $comments as $comment ) {
			$comment_author_url = $comment->comment_author_url;
			if ( empty( $comment_author_url ) ) {
				continue;
			}

			$webfinger = Webfinger::uri_to_acct( $comment_author_url );
			if ( \is_wp_error( $webfinger ) ) {
				continue;
			}

			\wp_update_comment(
				array(
					'comment_ID'           => $comment->comment_ID,
					'comment_author_email' => \str_replace( 'acct:', '', $webfinger ),
				)
			);
		}

		if ( count( $comments ) === $batch_size ) {
			return array(
				'batch_size' => $batch_size,
				'offset'     => $offset + $batch_size,
			);
		}

		return null;
	}

	/**
	 * Set the defaults needed for the plugin to work.
	 *
	 * Add the ActivityPub capability to all users that can publish posts.
	 */
	public static function add_default_settings() {
		self::add_activitypub_capability();
		self::add_default_extra_field();
	}

	/**
	 * Add an activity to the outbox without federating it.
	 *
	 * @param \WP_Post|\WP_Comment $comment       The comment or post object.
	 * @param string               $activity_type The type of activity.
	 * @param int                  $user_id       The user ID.
	 * @param string               $visibility    Optional. The visibility of the content. Default 'public'.
	 */
	private static function add_to_outbox( $comment, $activity_type, $user_id, $visibility = ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ) {
		$transformer = Factory::get_transformer( $comment );
		if ( ! $transformer || \is_wp_error( $transformer ) ) {
			return;
		}

		$activity = $transformer->to_activity( $activity_type );
		if ( ! $activity || \is_wp_error( $activity ) ) {
			return;
		}

		// If the user is disabled, fall back to the blog user when available.
		if ( ! user_can_activitypub( $user_id ) ) {
			if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
				$user_id = Actors::BLOG_USER_ID;
			} else {
				return;
			}
		}

		$post_id = Outbox::add( $activity, $user_id, $visibility );

		// Immediately set to publish, no federation needed.
		\wp_publish_post( $post_id );
	}

	/**
	 * Add the ActivityPub capability to all users that can publish posts.
	 */
	private static function add_activitypub_capability() {
		// Get all WP_User objects that can publish posts.
		$users = \get_users(
			array(
				'capability__in' => array( 'publish_posts' ),
			)
		);

		// Add ActivityPub capability to all users that can publish posts.
		foreach ( $users as $user ) {
			$user->add_cap( 'activitypub' );
		}
	}

	/**
	 * Add a default extra field for the user.
	 */
	private static function add_default_extra_field() {
		$users = \get_users(
			array(
				'capability__in' => array( 'activitypub' ),
			)
		);

		$title   = \__( 'Powered by', 'activitypub' );
		$content = 'WordPress';

		// Add a default extra field for each user.
		foreach ( $users as $user ) {
			\wp_insert_post(
				array(
					'post_type'    => Extra_Fields::USER_POST_TYPE,
					'post_author'  => $user->ID,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $content,
				)
			);
		}

		\wp_insert_post(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_author'  => 0,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Rename user meta keys.
	 *
	 * @param string $old_key The old comment meta key.
	 * @param string $new_key The new comment meta key.
	 */
	private static function update_usermeta_key( $old_key, $new_key ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->usermeta,
			array( 'meta_key' => $new_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( 'meta_key' => $old_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Update post meta keys.
	 *
	 * @param string $old_key The old post meta key.
	 * @param string $new_key The new post meta key.
	 */
	private static function update_postmeta_key( $old_key, $new_key ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->postmeta,
			array( 'meta_key' => $new_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( 'meta_key' => $old_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Rename option keys.
	 *
	 * @param string $old_key The old option key.
	 * @param string $new_key The new option key.
	 */
	private static function update_options_key( $old_key, $new_key ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->options,
			array( 'option_name' => $new_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( 'option_name' => $old_key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Migrate the actor mode settings.
	 */
	public static function migrate_actor_mode() {
		$blog_profile    = \get_option( 'activitypub_enable_blog_user', '0' );
		$author_profiles = \get_option( 'activitypub_enable_users', '1' );

		if (
			'1' === $blog_profile &&
			'1' === $author_profiles
		) {
			\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		} elseif (
			'1' === $blog_profile &&
			'1' !== $author_profiles
		) {
			\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		} elseif (
			'1' !== $blog_profile &&
			'1' === $author_profiles
		) {
			\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		}
	}

	/**
	 * Deletes user extra fields where the author is the blog user.
	 *
	 * These extra fields were created when the Enable Mastodon Apps integration passed
	 * an author_url instead of a user_id to the mastodon_api_account filter. This caused
	 * Extra_Fields::default_actor_extra_fields() to run but fail to cache the fact it ran
	 * for non-existent users. The result is a number of user extra fields with no author.
	 *
	 * @ticket https://github.com/Automattic/wordpress-activitypub/pull/1554
	 */
	public static function delete_mastodon_api_orphaned_extra_fields() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$wpdb->posts,
			array(
				'post_type'   => Extra_Fields::USER_POST_TYPE,
				'post_author' => Actors::BLOG_USER_ID,
			)
		);
	}

	/**
	 * Update notification options.
	 */
	public static function update_notification_options() {
		$new_dm       = \get_option( 'activitypub_mailer_new_dm', '1' );
		$new_follower = \get_option( 'activitypub_mailer_new_follower', '1' );

		// Add the blog user notification options.
		\add_option( 'activitypub_blog_user_mailer_new_dm', $new_dm );
		\add_option( 'activitypub_blog_user_mailer_new_follower', $new_follower );
		\add_option( 'activitypub_blog_user_mailer_new_mention', '1' );

		$user_ids = \get_users(
			array(
				'capability__in' => array( 'activitypub' ),
				'fields'         => 'id',
			)
		);

		// Add the actor notification options.
		foreach ( $user_ids as $user_id ) {
			\update_user_option( $user_id, 'activitypub_mailer_new_dm', $new_dm );
			\update_user_option( $user_id, 'activitypub_mailer_new_follower', $new_follower );
			\update_user_option( $user_id, 'activitypub_mailer_new_mention', '1' );
		}

		// Delete the old notification options.
		\delete_option( 'activitypub_mailer_new_dm' );
		\delete_option( 'activitypub_mailer_new_follower' );
	}

	/**
	 * Migrate followers to the new CPT.
	 */
	public static function migrate_followers_to_ap_actor_cpt() {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->posts,
			array( 'post_type' => Remote_Actors::POST_TYPE ),
			array( 'post_type' => 'ap_follower' ),
			array( '%s' ),
			array( '%s' )
		);

		self::update_postmeta_key( '_activitypub_user_id', Followers::FOLLOWER_META_KEY );
	}

	/**
	 * Update _activitypub_actor_json meta values to ensure they are properly slashed.
	 *
	 * @param int $batch_size Optional. Number of meta values to process per batch. Default 100.
	 *
	 * @return array|void Array with batch size and offset if there are more meta values to process, void otherwise.
	 */
	public static function update_actor_json_storage( $batch_size = 100 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$meta_values = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_actor_json' LIMIT %d",
				$batch_size
			)
		);

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		foreach ( $meta_values as $meta ) {
			$post = \get_post( $meta->post_id );

			if ( ! $post ) {
				\delete_post_meta( $meta->post_id, '_activitypub_actor_json' );
				continue;
			}

			$post_content = \json_decode( $meta->meta_value, true );

			if ( \json_last_error() !== JSON_ERROR_NONE ) {
				$post_content = Http::get_remote_object( $post->guid );

				if ( \is_wp_error( $post_content ) ) {
					\delete_post_meta( $post->ID, '_activitypub_actor_json' );
					continue;
				}
			}

			\wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => \wp_slash( \wp_json_encode( $post_content ) ),
				)
			);

			\delete_post_meta( $post->ID, '_activitypub_actor_json' );
		}

		if ( $has_kses ) {
			// Restore KSES filters.
			\kses_init_filters();
		}

		if ( \count( $meta_values ) === $batch_size ) {
			return array(
				'batch_size' => $batch_size,
			);
		}
	}

	/**
	 * Removes pending follow requests for the application user.
	 */
	public static function remove_pending_application_user_follow_requests() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'meta_key'   => '_activitypub_following', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => Actors::APPLICATION_USER_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Sync Jetpack meta for all followings.
	 *
	 * Replays the added_post_meta sync action for Jetpack with the Following::FOLLOWING_META_KEY meta key.
	 */
	public static function sync_jetpack_following_meta() {
		if ( ! \class_exists( 'Jetpack' ) || ! \Jetpack::is_connection_ready() ) {
			return;
		}

		global $wpdb;

		// Get all posts that have the following meta key.
		$posts_with_following = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				Following::FOLLOWING_META_KEY
			),
			ARRAY_N
		);

		// Trigger the added_post_meta action for each following relationship.
		foreach ( $posts_with_following as $meta ) {
			/**
			 * Fires when post meta is added.
			 *
			 * @param int    $meta_id    ID of the metadata entry.
			 * @param int    $object_id  Post ID.
			 * @param string $meta_key   Metadata key.
			 * @param mixed  $meta_value Metadata value.
			 */
			\do_action( 'added_post_meta', ...$meta );
		}
	}
}
