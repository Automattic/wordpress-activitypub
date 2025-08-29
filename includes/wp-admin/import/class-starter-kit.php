<?php
/**
 * Starter Kit importer file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

use function Activitypub\follow;
use function Activitypub\is_user_type_disabled;
use function Activitypub\object_to_uri;

/**
 * Starter Kit importer class.
 */
class Starter_Kit {
	/**
	 * Import file attachment ID.
	 *
	 * @var int
	 */
	private static $import_id;

	/**
	 * Author ID.
	 *
	 * @var int
	 */
	private static $author;

	/**
	 * Starter Kit file.
	 *
	 * @var string
	 */
	private static $file;

	/**
	 * Starter Kit JSON.
	 *
	 * @var object
	 */
	private static $starter_kit;

	/**
	 * Actors to follow.
	 *
	 * @var array
	 */
	private static $actor_list;

	/**
	 * Blog user filter callback.
	 *
	 * @var callable
	 */
	private static $blog_user_filter_callback;

	/**
	 * Blog user filter added.
	 *
	 * @var bool
	 */
	private static $blog_user_filter_added = false;

	/**
	 * Dispatch
	 */
	public static function dispatch() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = \absint( $_GET['step'] ?? 0 );

		self::header();

		switch ( $step ) {
			case 0:
				self::greet();
				break;

			case 1:
				\check_admin_referer( 'import-upload' );
				if ( self::handle_upload() ) {
					self::import_options();
				}
				break;

			case 2:
				\check_admin_referer( 'import-url' );
				if ( self::handle_url_import() ) {
					self::import_options();
				}
				break;

			case 3:
				\check_admin_referer( 'import-starter-kit' );
				self::$import_id  = \absint( $_POST['import_id'] ?? 0 );
				self::$author     = \absint( $_POST['author'] ?? \get_current_user_id() );
				self::$actor_list = \array_values(
					array_filter(
						array_map(
							function ( $actor ) {
								$actor = \sanitize_text_field( $actor );
								$actor = \wp_unslash( $actor );
								return self::is_valid_actor( $actor ) ? $actor : null;
							},
							// phpcs:ignore
							$_POST['actors'] ?? array()
						)
					)
				);

				\set_time_limit( 0 );
				self::import();
				break;
		}

		self::footer();
	}

	/**
	 * Handle upload.
	 */
	public static function handle_upload() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		\check_admin_referer( 'import-upload' );

		if ( ! isset( $_FILES['import']['name'] ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			\printf(
				/* translators: 1: php.ini, 2: post_max_size, 3: upload_max_filesize */
				\esc_html__( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.', 'activitypub' ),
				'php.ini',
				'post_max_size',
				'upload_max_filesize'
			);
			echo '</p>';
			return false;
		}

		$file_info = \wp_check_filetype( \sanitize_file_name( $_FILES['import']['name'] ), array( 'json' => 'application/json' ) );
		if ( 'application/json' !== $file_info['type'] ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'The uploaded file must be a JSON file. Please try again with the correct file format.', 'activitypub' ) );
			return false;
		}

		$overrides = array(
			'test_form' => false,
			'test_type' => false,
		);

		$upload = \wp_handle_upload( $_FILES['import'], $overrides );

		if ( isset( $upload['error'] ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html( $upload['error'] ) );
			return false;
		}

		// Construct the attachment array.
		$attachment = array(
			'post_title'     => \wp_basename( $upload['file'] ),
			'post_content'   => $upload['url'],
			'post_mime_type' => $upload['type'],
			'guid'           => $upload['url'],
			'context'        => 'import',
			'post_status'    => 'private',
		);

		// Save the data.
		self::$import_id = \wp_insert_attachment( $attachment, $upload['file'] );

		// Schedule a cleanup for one day from now in case of failed import or missing wp_import_cleanup() call.
		\wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( self::$import_id ) );

		return true;
	}

	/**
	 * Handle URL import.
	 */
	public static function handle_url_import() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$url = \sanitize_url( \wp_unslash( $_POST['import_url'] ?? '' ) );
		if ( empty( $url ) ) {
			echo '<p><strong>' . \esc_html( $error_message ) . '</strong><br />';
			echo \esc_html__( 'Please provide a valid URL.', 'activitypub' ) . '</p>';
			return false;
		}

		// Validate URL format.
		if ( ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'The provided URL is not valid.', 'activitypub' ) );
			return false;
		}

		// Fetch the URL content.
		$response = \wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => array(
					'Accept' => 'application/activity+json',
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html( $response->get_error_message() ) );
			return false;
		}

		$response_code = \wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			\printf(
				'<p><strong>%s</strong><br />%s</p>',
				\esc_html( $error_message ),
				/* translators: %d: HTTP response code */
				\esc_html( \sprintf( \__( 'Failed to fetch URL. HTTP response code: %d', 'activitypub' ), $response_code ) )
			);
			return false;
		}

		$body = \wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'The URL returned empty content.', 'activitypub' ) );
			return false;
		}

		// Validate JSON format.
		$json_data = \json_decode( $body, true );
		if ( null === $json_data ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'The URL does not contain valid JSON data.', 'activitypub' ) );
			return false;
		}

		// Create a temporary file to store the JSON content.
		$upload_dir      = \wp_upload_dir();
		$base_filename   = 'starter-kit.json';
		$unique_filename = \wp_unique_filename( $upload_dir['path'], $base_filename );
		$temp_file       = \trailingslashit( $upload_dir['path'] ) . $unique_filename;

		if ( ! \WP_Filesystem() ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Failed to initialize the WordPress filesystem.', 'activitypub' ) );
			return false;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem || ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Failed to initialize the WordPress filesystem.', 'activitypub' ) );
			return false;
		}
		if ( ! $wp_filesystem->put_contents( $temp_file, $body, FS_CHMOD_FILE ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Failed to save the downloaded content.', 'activitypub' ) );
			return false;
		}

		// Construct the attachment array.
		$attachment = array(
			// phpcs:ignore
			'post_title'     => \sanitize_file_name( \basename( \wp_parse_url( $url, PHP_URL_PATH ) ) ) ?: 'starter-kit.json',
			'post_content'   => $url,
			'post_mime_type' => 'application/json',
			'guid'           => $url,
			'context'        => 'import',
			'post_status'    => 'private',
		);

		// Save the data.
		self::$import_id = \wp_insert_attachment( $attachment, $temp_file );

		// Check if the attachment was inserted successfully.
		if ( \is_wp_error( self::$import_id ) || ! self::$import_id ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Failed to insert attachment.', 'activitypub' ) );
			return false;
		}
		// Schedule a cleanup for one day from now in case of failed import or missing wp_import_cleanup() call.
		\wp_schedule_single_event( \time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( self::$import_id ) );

		return true;
	}

	/**
	 * Import options.
	 */
	public static function import_options() {
		self::setup_blog_user_filter();

		$actors = self::get_actor_list();
		if ( \is_wp_error( $actors ) ) {
			self::render_error( $actors );
			return;
		}

		self::render_import_form( $actors );
		self::cleanup_blog_user_filter();
	}

	/**
	 * Setup blog user filter for dropdown.
	 */
	private static function setup_blog_user_filter() {
		if ( is_user_type_disabled( 'blog' ) ) {
			return;
		}

		self::$blog_user_filter_callback = function ( $users ) {
			return \preg_replace(
				'/<\/select>/',
				'<option value="0">' . \__( 'Blog User', 'activitypub' ) . '</option></select>',
				$users
			);
		};

		\add_filter( 'wp_dropdown_users', self::$blog_user_filter_callback );

		self::$blog_user_filter_added = true;
	}

	/**
	 * Cleanup blog user filter.
	 */
	private static function cleanup_blog_user_filter() {
		if ( self::$blog_user_filter_callback && self::$blog_user_filter_added ) {
			\remove_filter( 'wp_dropdown_users', self::$blog_user_filter_callback );
			self::$blog_user_filter_callback = null;
		}

		self::$blog_user_filter_added = false;
	}

	/**
	 * Render error message.
	 *
	 * @param \WP_Error $error The error to render.
	 */
	private static function render_error( $error ) {
		\printf(
			'<p><strong>%s</strong><br />%s</p>',
			\esc_html__( 'Sorry, there has been an error.', 'activitypub' ),
			\esc_html( $error->get_error_message() )
		);
	}

	/**
	 * Render the import form.
	 *
	 * @param array $actors The actors to render.
	 */
	private static function render_import_form( $actors ) {
		?>
		<form action="<?php echo \esc_url( \admin_url( 'admin.php?import=starter-kit&amp;step=3' ) ); ?>" method="post">
			<?php \wp_nonce_field( 'import-starter-kit' ); ?>
			<input type="hidden" name="import_id" value="<?php echo esc_attr( self::$import_id ); ?>" />

			<?php self::render_starter_kit_info(); ?>
			<?php self::render_author_selection(); ?>
			<?php self::render_actor_selection( $actors ); ?>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Import', 'activitypub' ); ?>" />
			</p>
		</form>
		<?php
	}

	/**
	 * Render starter kit information.
	 */
	private static function render_starter_kit_info() {
		$name = empty( self::$starter_kit['name'] )
			? \__( 'Starter Kit', 'activitypub' )
			: self::$starter_kit['name'];

		echo '<h3>' . \esc_html( $name ) . '</h3>';

		if ( ! empty( self::$starter_kit['image']['url'] ) ) {
			\printf(
				'<img src="%s" style="max-width: 500px;" alt="%s" />',
				\esc_url( self::$starter_kit['image']['url'] ),
				\esc_attr( self::$starter_kit['image']['summary'] ?? '' )
			);
		}

		if ( ! empty( self::$starter_kit['summary'] ) ) {
			echo '<p>' . \esc_html( self::$starter_kit['summary'] ) . '</p>';
		}

		if ( ! empty( self::$starter_kit['attributedTo'] ) ) {
			echo \wp_kses_post(
				\sprintf(
					'Created by <a href="%1$s" target="_blank">%1$s</a>',
					\esc_url( self::$starter_kit['attributedTo'] )
				)
			);
		}
	}

	/**
	 * Render author selection.
	 */
	private static function render_author_selection() {
		?>
		<h4><?php \esc_html_e( 'Select the author for the imported Starter Kit', 'activitypub' ); ?></h4>
		<p>
			<label for="author"><?php \esc_html_e( 'Author:', 'activitypub' ); ?></label>
			<?php
			\wp_dropdown_users(
				array(
					'name'       => 'author',
					'id'         => 'author',
					'show'       => 'display_name_with_login',
					'selected'   => \get_current_user_id(),
					'capability' => 'activitypub',
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render actor selection.
	 *
	 * @param array $actors The actors to render.
	 */
	private static function render_actor_selection( $actors ) {
		?>
		<h4><?php \esc_html_e( 'Select the accounts you want to follow', 'activitypub' ); ?></h4>
		<ul>
			<?php foreach ( $actors as $actor ) : ?>
				<?php
				$actor_uri = object_to_uri( $actor );
				$actor_uri = \ltrim( $actor_uri, '@' );

				if ( ! self::is_valid_actor( $actor_uri ) ) {
					continue;
				}
				?>
				<li>
					<label>
						<input type="checkbox" name="actors[]" value="<?php echo \esc_attr( $actor_uri ); ?>" checked />
						<?php echo \esc_html( $actor_uri ); ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Check if actor URI is valid.
	 *
	 * @param string $actor_uri The actor URI to validate.
	 *
	 * @return bool True if the actor URI is valid, false otherwise.
	 */
	private static function is_valid_actor( $actor_uri ) {
		return false !== \filter_var( $actor_uri, FILTER_VALIDATE_URL ) || false !== \filter_var( $actor_uri, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Import.
	 */
	public static function import() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		\wp_suspend_cache_invalidation();

		/**
		 * Fires when the Starter Kit import starts.
		 */
		\do_action( 'import_start' );

		$result = self::follow();

		\wp_suspend_cache_invalidation( false );

		\wp_import_cleanup( self::$import_id );

		if ( \is_wp_error( $result ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html( $result->get_error_message() ) );
		} else {
			\printf( '<p>%s</p>', \esc_html__( 'All done.', 'activitypub' ) );
		}

		/**
		 * Fires when the Starter Kit import ends.
		 */
		\do_action( 'import_end' );
	}

	/**
	 * Process posts.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function follow() {
		$skipped  = 0;
		$followed = 0;

		$items = self::$actor_list;

		foreach ( $items as $actor_id ) {
			$actor_id = object_to_uri( $actor_id );
			$actor_id = \ltrim( $actor_id, '@' );

			if ( ! filter_var( $actor_id, FILTER_VALIDATE_URL ) && ! filter_var( $actor_id, FILTER_VALIDATE_EMAIL ) ) {
				++$skipped;
				continue;
			}

			$result = follow( $actor_id, self::$author );

			if ( \is_wp_error( $result ) ) {
				/* translators: %s: Account ID */
				\printf( '<p>' . \esc_html__( '&#x2717; %s', 'activitypub' ) . '</p>', \esc_html( $actor_id ) );
				++$skipped;
			} else {
				/* translators: %s: Account ID */
				\printf( '<p>' . \esc_html__( '&#x2713; %s', 'activitypub' ) . '</p>', \esc_html( $actor_id ) );
				++$followed;
			}
		}

		echo '<hr />';

		/* translators: %d: Number of followed actors */
		\printf( '<p>%s</p>', \esc_html( \sprintf( \_n( 'Followed %s Actor.', 'Followed %s Actors.', $followed, 'activitypub' ), \number_format_i18n( $followed ) ) ) );
		/* translators: %d: Number of skipped items */
		\printf( '<p>%s</p>', \esc_html( \sprintf( \_n( 'Skipped %s Item.', 'Skipped %s Items.', $skipped, 'activitypub' ), \number_format_i18n( $skipped ) ) ) );

		return true;
	}

	/**
	 * Intro.
	 */
	public static function greet() {
		echo '<div class="narrow">';
		echo '<p>' . \esc_html__( 'Starter Kits use the ActivityPub protocol with custom extensions to automate tasks such as following accounts, blocking unwanted content, and applying default configurations. The importer will automatically follow every user listed in the kit, helping users connect right away. Support for additional actions and features will be added over time.', 'activitypub' ) . '</p>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$url = isset( $_GET['url'] ) ? \sanitize_text_field( \wp_unslash( $_GET['url'] ) ) : '';

		if ( empty( $url ) ) {
			// File upload option.
			\printf( '<h3>%s</h3>', \esc_html__( 'Option 1: Upload a File', 'activitypub' ) );
			\wp_import_upload_form( 'admin.php?import=starter-kit&amp;step=1' );

			// URL import option.
			\printf( '<h3>%s</h3>', \esc_html__( 'Option 2: Import from URL', 'activitypub' ) );
		} else {
			// URL import option.
			\printf( '<h3>%s</h3>', \esc_html__( 'Import from URL', 'activitypub' ) );
		}
		?>
		<form id="import-url-form" method="post" action="<?php echo \esc_url( \admin_url( 'admin.php?import=starter-kit&amp;step=2' ) ); ?>">
			<?php
			\wp_nonce_field( 'import-url' );
			?>
			<p>
				<label for="import_url"><?php \esc_html_e( 'Starter Kit URL:', 'activitypub' ); ?><br />
					<input type="url" id="import_url" name="import_url" size="50" class="code" placeholder="https://example.com/starter-kit.json" value="<?php echo \esc_attr( $url ); ?>" required />
				</label>
			</p>
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button" value="<?php \esc_attr_e( 'Import from URL', 'activitypub' ); ?>" />
			</p>
		</form>

		</div>
		<?php
	}

	/**
	 * Header.
	 */
	public static function header() {
		echo '<div class="wrap">';
		echo '<h2>' . \esc_html__( 'Import a Fediverse Starter Kit (Beta)', 'activitypub' ) . '</h2>';
	}

	/**
	 * Footer.
	 */
	public static function footer() {
		echo '</div>';
	}

	/**
	 * Get actor list.
	 */
	private static function get_actor_list() {
		$file = \get_attached_file( self::$import_id );

		\WP_Filesystem();

		global $wp_filesystem;

		$file_contents = $wp_filesystem->get_contents( $file );
		if ( false === $file_contents ) {
			return new \WP_Error( 'file_not_found', \esc_html__( 'Could not read the uploaded file.', 'activitypub' ) );
		}

		self::$starter_kit = \json_decode( $file_contents, true );
		if ( null === self::$starter_kit ) {
			return new \WP_Error( 'invalid_json', \esc_html__( 'Invalid JSON format in the uploaded file.', 'activitypub' ) );
		}

		$actors = self::$starter_kit['items'] ?? self::$starter_kit['orderedItems'] ?? array();

		// Limit list to 150 actors.
		// TODO: Make this configurable.
		$actors = \array_slice( $actors, 0, 150 );

		if ( ! $actors ) {
			return new \WP_Error( 'empty_actor_list', \esc_html__( 'The uploaded file does not contain any actors.', 'activitypub' ) );
		}

		return $actors;
	}
}
