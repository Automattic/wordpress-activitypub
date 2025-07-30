<?php
/**
 * Starter Kit importer file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

use function Activitypub\follow;
use function Activitypub\is_actor;
use function Activitypub\object_to_uri;
use function Activitypub\is_user_type_disabled;

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
				\check_admin_referer( 'import-starter-kit' );
				self::$import_id = \absint( $_POST['import_id'] ?? 0 );
				self::$author    = \absint( $_POST['author'] ?? \get_current_user_id() );

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
	 * Import options.
	 */
	public static function import_options() {
		$activitypub_users = function ( $users ) {
			// Add blog user to the html output if enabled.
			$users = \preg_replace( '/<\/select>/', '<option value="0">' . \__( 'Blog User', 'activitypub' ) . '</option></select>', $users );
			return $users;
		};

		if ( ! is_user_type_disabled( 'blog' ) ) {
			\add_filter(
				'wp_dropdown_users',
				$activitypub_users
			);
		}
		?>
		<form action="<?php echo \esc_url( \admin_url( 'admin.php?import=starter-kit&amp;step=2' ) ); ?>" method="post">
			<?php \wp_nonce_field( 'import-starter-kit' ); ?>
			<input type="hidden" name="import_id" value="<?php echo esc_attr( self::$import_id ); ?>" />
			<h3><?php \esc_html_e( 'Assign Author', 'activitypub' ); ?></h3>
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
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Import', 'activitypub' ); ?>" />
			</p>
		</form>
		<?php
		\remove_filter( 'wp_dropdown_users', $activitypub_users );
	}

	/**
	 * Import.
	 */
	public static function import() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );
		$file          = \get_attached_file( self::$import_id );

		\WP_Filesystem();

		global $wp_filesystem;

		$file_contents = $wp_filesystem->get_contents( $file );
		if ( false === $file_contents ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Could not read the uploaded file.', 'activitypub' ) );
			return;
		}

		self::$starter_kit = \json_decode( $file_contents, true );
		if ( null === self::$starter_kit ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Invalid JSON format in the uploaded file.', 'activitypub' ) );
			return;
		}

		\wp_suspend_cache_invalidation();
		\wp_defer_term_counting( true );
		\wp_defer_comment_counting( true );

		/**
		 * Fires when the Starter Kit import starts.
		 */
		\do_action( 'import_start' );

		$result = self::follow();

		\wp_suspend_cache_invalidation( false );
		\wp_defer_term_counting( false );
		\wp_defer_comment_counting( false );

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

		$items = self::$starter_kit['items'] ?? self::$starter_kit['orderedItems'] ?? array();

		foreach ( $items as $item ) {
			if ( ! is_actor( $item ) ) {
				++$skipped;
				continue;
			}

			$result = follow( object_to_uri( $item ), self::$author );

			if ( \is_wp_error( $result ) ) {
				++$skipped;
			} else {
				/* translators: %s: Account ID */
				\printf( '<p>' . \esc_html__( 'Followed %s', 'activitypub' ) . '</p>', \esc_html( $item['id'] ) );
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

		\wp_import_upload_form( 'admin.php?import=starter-kit&amp;step=1' );

		echo '</div>';
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
}
