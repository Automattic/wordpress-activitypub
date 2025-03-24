<?php
/**
 * Mastodon importer file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

use Activitypub\Collection\Interactions;

/**
 * Mastodon importer class.
 */
class Mastodon {

	/**
	 * Outbox file.
	 *
	 * @var object
	 */
	public static $outbox;

	/**
	 * Dispatch
	 */
	public static function dispatch() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = absint( $_GET['step'] ?? 0 );

		self::header();

		switch ( $step ) {
			case 0:
				self::greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );

				set_time_limit( 0 );
				self::import();
				break;
		}

		self::footer();
	}

	/**
	 * Import.
	 */
	public static function import() {
		$file          = \wp_import_handle_upload();
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html( $file['error'] ) . '</p>';
			return null;
		} elseif ( ! \file_exists( $file['file'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			/* translators: File path. */
			\printf( \wp_kses_post( \__( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permission problem.', 'activitypub' ) ), esc_html( $file['file'] ) );
			echo '</p>';
			return null;
		}

		\WP_Filesystem();

		global $wp_filesystem;
		$import_folder = $wp_filesystem->wp_content_dir() . 'import/';
		$working_dir   = $import_folder . \basename( \basename( $file['file'], '.txt' ), '.zip' );

		// Clean up working directory.
		if ( $wp_filesystem->is_dir( $working_dir ) ) {
			$wp_filesystem->delete( $working_dir, true );
		}

		// Unzip package to working directory.
		\unzip_file( $file['file'], $working_dir );
		$files = $wp_filesystem->dirlist( $working_dir );

		if ( ! isset( $files['outbox.json'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html__( 'The archive does not contain an Outbox file, please try again.', 'activitypub' ) . '</p>';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		self::$outbox = json_decode( file_get_contents( $working_dir . '/outbox.json' ) );

		$wp_filesystem->delete( $import_folder, true );

		\wp_suspend_cache_invalidation();
		\wp_defer_term_counting( true );
		\wp_defer_comment_counting( true );

		do_action( 'import_start' );

		$result = self::import_posts();

		\wp_suspend_cache_invalidation( false );
		\wp_defer_term_counting( false );
		\wp_defer_comment_counting( false );

		\wp_import_cleanup( $file['id'] );

		if ( is_wp_error( $result ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo esc_html( $result->get_error_message() ) . '</p>';
		} else {
			echo '<p>';
			/* translators: Home URL */
			\printf( \wp_kses_post( \__( 'All done. <a href="%s">Have fun!</a>', 'activitypub' ) ), esc_url( admin_url() ) );
			echo '</p>';
		}

		do_action( 'import_end' );
	}

	/**
	 * Process posts.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function import_posts() {
		$user_id = 0;

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		foreach ( self::$outbox->orderedItems as $post ) {
			// Skip boosts.
			if ( 'Announce' === $post->type ) {
				continue;
			}

			if ( ! $user_id ) {
				$users = get_users(
					array(
						'fields'     => 'ID',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'meta_query' => array(
							array(
								'key'     => $GLOBALS['wpdb']->get_blog_prefix() . 'activitypub_also_known_as',
								'value'   => $post->actor,
								'compare' => 'LIKE',
							),
						),
					)
				);

				$user_id = current( $users );

				if ( ! $user_id ) {
					return new \WP_Error( 'missing_author', \__( 'Missing author.', 'activitypub' ) );
				}
			}

			$post_data = array(
				'post_author'  => $user_id,
				'post_date'    => $post->published,
				'post_excerpt' => $post->object->summary ?? '',
				'post_content' => $post->object->content,
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'tags_input'   => \array_map(
					function ( $tag ) {
						if ( 'Hashtag' === $tag->type ) {
							return ltrim( $tag->name, '#' );
						}

						return '';
					},
					$post->object->tag
				),
			);

			$post_exists = post_exists( '', $post_data['post_content'], $post_data['post_date'], $post_data['post_type'] );

			/**
			 * Filter ID of the existing post corresponding to post currently importing.
			 *
			 * Return 0 to force the post to be imported. Filter the ID to be something else
			 * to override which existing post is mapped to the imported post.
			 *
			 * @see post_exists()
			 *
			 * @param int   $post_exists  Post ID, or 0 if post did not exist.
			 * @param array $post_data    The post array to be inserted.
			 */
			$post_exists = apply_filters( 'wp_import_existing_post', $post_exists, $post_data );

			if ( $post_exists ) {
				/* translators: 1: Post type name */
				\printf( \esc_html__( '%1$s already exists.', 'activitypub' ), esc_html( get_post_type_object( $post_data['post_type'] )->labels->singular_name ) );
				echo '<br />';
				continue;
			}

			$post_id = \wp_insert_post( $post_data, true );

			if ( \is_wp_error( $post_id ) ) {
				return $post_id;
			}

			set_post_format( $post_id, 'status' );

			if ( $post_id && $post->replies->first->items ) {
				foreach ( $post->replies->first->items as $reply ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$reply->inReplyTo = \get_permalink( $post_id );

					Interactions::add_comment( $reply );
				}
			}
		}

		return true;
	}

	/**
	 * Header.
	 */
	public static function header() {
		echo '<div class="wrap">';
		echo '<h2>' . \esc_html__( 'Import from Mastodon (Beta)', 'activitypub' ) . '</h2>';
	}

	/**
	 * Footer.
	 */
	public static function footer() {
		echo '</div>';
	}

	/**
	 * Intro.
	 */
	public static function greet() {
		echo '<div class="narrow">';
		echo '<p>' . \esc_html__( 'Howdy! This importer allows you to extract posts from Mastodon exports into your site. Pick a Mastodon archive to upload and click Import.', 'activitypub' ) . '</p>';
		\wp_import_upload_form( 'admin.php?import=mastodon&amp;step=1' );
		echo '</div>';
	}
}
