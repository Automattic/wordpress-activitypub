<?php
/**
 * Blocklist importer file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Import;

use Activitypub\Blocklist_Subscriptions;
use Activitypub\Moderation;

/**
 * Blocklist importer class.
 *
 * Imports domain blocklists in CSV format (Mastodon, IFTAS DNI, etc.)
 */
class Blocklist {

	/**
	 * Dispatch the importer based on current step.
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
				self::handle_upload();
				break;

			case 2:
				\check_admin_referer( 'import-blocklist-url' );
				self::handle_url_import();
				break;
		}

		self::footer();
	}

	/**
	 * Display the importer header.
	 */
	private static function header() {
		echo '<div class="wrap">';
		echo '<h2>' . \esc_html__( 'Import Domain Blocklist', 'activitypub' ) . '</h2>';
	}

	/**
	 * Display the importer footer.
	 */
	private static function footer() {
		echo '</div>';
	}

	/**
	 * Display the greeting/intro screen.
	 */
	private static function greet() {
		echo '<div class="narrow">';
		echo '<p>' . \esc_html__( 'Import a domain blocklist to block multiple ActivityPub instances at once. Supported formats:', 'activitypub' ) . '</p>';
		echo '<ul>';
		echo '<li>' . \esc_html__( 'Mastodon CSV export (with #domain header)', 'activitypub' ) . '</li>';
		echo '<li>' . \esc_html__( 'Simple text file with one domain per line', 'activitypub' ) . '</li>';
		echo '</ul>';

		// File upload option.
		\printf( '<h3>%s</h3>', \esc_html__( 'Option 1: Upload a File', 'activitypub' ) );
		\wp_import_upload_form( 'admin.php?import=blocklist&amp;step=1' );

		// URL import option.
		\printf( '<h3>%s</h3>', \esc_html__( 'Option 2: Import from URL', 'activitypub' ) );
		?>
		<form id="import-url-form" method="post" action="<?php echo \esc_url( \admin_url( 'admin.php?import=blocklist&amp;step=2' ) ); ?>">
			<?php \wp_nonce_field( 'import-blocklist-url' ); ?>
			<p>
				<label for="import_url"><?php \esc_html_e( 'Blocklist URL:', 'activitypub' ); ?><br />
					<input type="url" id="import_url" name="import_url" size="50" class="code" placeholder="https://example.com/blocklist.csv" required />
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="subscribe" value="1" />
					<?php \esc_html_e( 'Subscribe for automatic weekly updates', 'activitypub' ); ?>
				</label>
			</p>
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button" value="<?php \esc_attr_e( 'Import from URL', 'activitypub' ); ?>" />
			</p>
		</form>

		<h4><?php \esc_html_e( 'Quick Import', 'activitypub' ); ?></h4>
		<p><?php \esc_html_e( 'Import from a well-known blocklist:', 'activitypub' ); ?></p>
		<form method="post" action="<?php echo \esc_url( \admin_url( 'admin.php?import=blocklist&amp;step=2' ) ); ?>">
			<?php \wp_nonce_field( 'import-blocklist-url' ); ?>
			<input type="hidden" name="import_url" value="<?php echo \esc_attr( Blocklist_Subscriptions::IFTAS_DNI_URL ); ?>" />
			<p>
				<label>
					<input type="checkbox" name="subscribe" value="1" />
					<?php \esc_html_e( 'Subscribe for automatic weekly updates', 'activitypub' ); ?>
				</label>
			</p>
			<p>
				<button type="submit" class="button">
					<?php \esc_html_e( 'Import IFTAS DNI List', 'activitypub' ); ?>
				</button>
				<span class="description">
					<?php \esc_html_e( 'Curated list of high-risk domains.', 'activitypub' ); ?>
				</span>
			</p>
		</form>

		<?php
		echo '</div>';
	}

	/**
	 * Handle file upload and import.
	 */
	private static function handle_upload() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in dispatch().
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
			return;
		}

		// Allow CSV and TXT files.
		$allowed_types = array(
			'csv' => 'text/csv',
			'txt' => 'text/plain',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in dispatch().
		$file_info = \wp_check_filetype( \sanitize_file_name( $_FILES['import']['name'] ), $allowed_types );

		if ( ! $file_info['type'] ) {
			\printf(
				'<p><strong>%s</strong><br />%s</p>',
				\esc_html( $error_message ),
				\esc_html__( 'The uploaded file must be a CSV or TXT file. Please try again with the correct file format.', 'activitypub' )
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Nonce verified in dispatch(), tmp_name is a server path.
		$file_path = $_FILES['import']['tmp_name'] ?? '';

		if ( empty( $file_path ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Upload failed. Please try again.', 'activitypub' ) );
			return;
		}

		$domains = self::parse_csv( $file_path );

		if ( empty( $domains ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'No valid domains found in the file.', 'activitypub' ) );
			return;
		}

		self::import( $domains );
	}

	/**
	 * Handle URL import.
	 */
	private static function handle_url_import() {
		$error_message = \__( 'Sorry, there has been an error.', 'activitypub' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in dispatch().
		$url = \sanitize_url( \wp_unslash( $_POST['import_url'] ?? '' ) );

		if ( empty( $url ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Please provide a valid URL.', 'activitypub' ) );
			return;
		}

		if ( ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'The provided URL is not valid.', 'activitypub' ) );
			return;
		}

		$result = Blocklist_Subscriptions::sync( $url );

		if ( false === $result ) {
			\printf( '<p><strong>%s</strong><br />%s</p>', \esc_html( $error_message ), \esc_html__( 'Failed to fetch or parse the blocklist URL.', 'activitypub' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in dispatch().
		$subscribe = ! empty( $_POST['subscribe'] );

		// Add subscription if requested (no need to sync again, just did it).
		$subscribed = $subscribe && Blocklist_Subscriptions::add( $url );

		self::show_url_import_results( $result, $subscribed );
	}

	/**
	 * Execute the import for file uploads.
	 *
	 * @param array $domains Array of domains to import.
	 */
	private static function import( $domains ) {
		\set_time_limit( 0 );

		/**
		 * Fires when the blocklist import starts.
		 */
		\do_action( 'import_start' );

		$existing    = Moderation::get_site_blocks()[ Moderation::TYPE_DOMAIN ] ?? array();
		$new_domains = \array_diff( $domains, $existing );
		$imported    = \count( $new_domains );
		$skipped     = \count( $domains ) - $imported;

		Moderation::add_site_blocks( Moderation::TYPE_DOMAIN, $new_domains );

		/**
		 * Fires when the blocklist import ends.
		 */
		\do_action( 'import_end' );

		echo '<h3>' . \esc_html__( 'Import Complete', 'activitypub' ) . '</h3>';

		\printf(
			'<p>%s</p>',
			\esc_html(
				\sprintf(
					/* translators: %s: Number of domains */
					\_n( 'Imported %s domain.', 'Imported %s domains.', $imported, 'activitypub' ),
					\number_format_i18n( $imported )
				)
			)
		);

		if ( $skipped > 0 ) {
			\printf(
				'<p>%s</p>',
				\esc_html(
					\sprintf(
						/* translators: %s: Number of domains */
						\_n( 'Skipped %s domain (already blocked).', 'Skipped %s domains (already blocked).', $skipped, 'activitypub' ),
						\number_format_i18n( $skipped )
					)
				)
			);
		}

		\printf(
			'<p><a href="%s">%s</a></p>',
			\esc_url( \admin_url( 'options-general.php?page=activitypub&tab=settings' ) ),
			\esc_html__( 'View blocked domains in settings', 'activitypub' )
		);
	}

	/**
	 * Show results for URL import.
	 *
	 * @param int  $imported   Number of domains imported.
	 * @param bool $subscribed Whether the URL was subscribed to.
	 */
	private static function show_url_import_results( $imported, $subscribed ) {
		echo '<h3>' . \esc_html__( 'Import Complete', 'activitypub' ) . '</h3>';

		\printf(
			'<p>%s</p>',
			\esc_html(
				\sprintf(
					/* translators: %s: Number of domains */
					\_n( 'Imported %s new domain.', 'Imported %s new domains.', $imported, 'activitypub' ),
					\number_format_i18n( $imported )
				)
			)
		);

		if ( $subscribed ) {
			echo '<p>' . \esc_html__( 'Subscribed for automatic weekly updates.', 'activitypub' ) . '</p>';
		}

		\printf(
			'<p><a href="%s">%s</a></p>',
			\esc_url( \admin_url( 'options-general.php?page=activitypub&tab=settings' ) ),
			\esc_html__( 'View blocked domains in settings', 'activitypub' )
		);
	}

	/**
	 * Parse a CSV file and extract domain names.
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return array Array of unique, valid domain names.
	 */
	public static function parse_csv( $file_path ) {
		if ( ! \file_exists( $file_path ) || ! \is_readable( $file_path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file.
		$content = \file_get_contents( $file_path );
		if ( false === $content ) {
			return array();
		}

		return Blocklist_Subscriptions::parse_csv_string( $content );
	}
}
