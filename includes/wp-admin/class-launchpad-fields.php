<?php
/**
 * Launchpad Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

/**
 * ActivityPub Launchpad Class.
 *
 * @author Matthias Pfefferle
 */
class Launchpad_Fields {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'load-settings_page_activitypub', array( self::class, 'register_launchpad_fields' ) );
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
	}

	/**
	 * Add the launchpad page to the menu.
	 */
	public static function register_launchpad_fields() {
		// Add header section
		\add_settings_section(
			'activitypub_launchpad_header',
			'',
			array( self::class, 'render_header_section' ),
			'activitypub_launchpad'
		);

		// Step 1: Plugin installed
		\add_settings_section(
			'activitypub_plugin_installed',
			'',
			array( self::class, 'render_plugin_installed_section' ),
			'activitypub_launchpad'
		);

		// Step 2: Watch Fediverse video series
		\add_settings_section(
			'activitypub_fediverse_video',
			'',
			array( self::class, 'render_fediverse_video_section' ),
			'activitypub_launchpad'
		);

		// Step 3: Select your user mode
		\add_settings_section(
			'activitypub_user_mode',
			'',
			array( self::class, 'render_user_mode_section' ),
			'activitypub_launchpad'
		);

		// Step 4: Learn more about Fediverse blocks
		\add_settings_section(
			'activitypub_fediverse_blocks',
			'',
			array( self::class, 'render_fediverse_blocks_section' ),
			'activitypub_launchpad'
		);

		// Add footer section
		\add_settings_section(
			'activitypub_launchpad_footer',
			'',
			array( self::class, 'render_footer_section' ),
			'activitypub_launchpad'
		);
	}

	/**
	 * Render the header section.
	 */
	public static function render_header_section() {
		$current_step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 1;
		$total_steps = 4;
		?>
		<div class="activitypub-launchpad-page">
			<div class="launchpad-header">
				<h1><?php esc_html_e( 'Let\'s get started!', 'activitypub' ); ?></h1>
				<p><?php esc_html_e( 'It\'s time to finish setting up your plugin.', 'activitypub' ); ?></p>
			</div>

			<div class="launchpad-progress">
				<div class="launchpad-progress-text"><?php echo esc_html( $current_step . '/' . $total_steps ); ?></div>
				<svg class="launchpad-progress-circle" width="80" height="80" viewBox="0 0 80 80">
					<circle class="bg" cx="40" cy="40" r="38" />
					<circle class="progress" cx="40" cy="40" r="38"
						stroke-dasharray="<?php echo esc_attr( ( $current_step / $total_steps ) * 240 ); ?> 240"
						stroke-dashoffset="0" />
				</svg>
			</div>
		<?php
	}

	/**
	 * Render the plugin installed section.
	 */
	public static function render_plugin_installed_section() {
		?>
		<div class="launchpad-card done">
			<?php esc_html_e( 'Plugin installed', 'activitypub' ); ?>
		</div>
		<?php
	}

	/**
	 * Render the Fediverse video section.
	 */
	public static function render_fediverse_video_section() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub&step=2' ) ); ?>" class="launchpad-card">
			<strong><?php esc_html_e( 'Watch Fediverse video series', 'activitypub' ); ?></strong><br>
			<?php esc_html_e( 'Learn what the Fediverse is and why it matters.', 'activitypub' ); ?>
		</a>
		<?php
	}

	/**
	 * Render the user mode section.
	 */
	public static function render_user_mode_section() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub&step=3' ) ); ?>" class="launchpad-card">
			<strong><?php esc_html_e( 'Select your user mode', 'activitypub' ); ?></strong>
		</a>
		<?php
	}

	/**
	 * Render the Fediverse blocks section.
	 */
	public static function render_fediverse_blocks_section() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub&step=4' ) ); ?>" class="launchpad-card">
			<strong><?php esc_html_e( 'Learn more about Fediverse blocks', 'activitypub' ); ?></strong>
		</a>
		<?php
	}

	/**
	 * Render the footer section.
	 */
	public static function render_footer_section() {
		?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub' ) ); ?>" class="launchpad-skip">
				<?php esc_html_e( 'Skip these steps', 'activitypub' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'settings_page_activitypub' !== $hook ) {
			return;
		}

		// Enqueue WordPress core styles.
		wp_enqueue_style( 'dashicons' );

		// Enqueue our custom styles.
		wp_enqueue_style(
			'activitypub-launchpad',
			ACTIVITYPUB_PLUGIN_URL . 'assets/css/activitypub-launchpad.css',
			array( 'dashicons' ),
			ACTIVITYPUB_PLUGIN_VERSION
		);
	}
}
