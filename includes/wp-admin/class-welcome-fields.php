<?php
/**
 * ActivityPub Welcome Fields Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use function Activitypub\user_can_activitypub;

/**
 * Class Welcome_Fields.
 */
class Welcome_Fields {
	/**
	 * Initialize the welcome fields.
	 */
	public static function init() {
		\add_action( 'load-settings_page_activitypub', array( self::class, 'register_welcome_fields' ) );

		\add_action( 'add_option_activitypub_checklist_health_check_issues', array( self::class, 'resolve_checklist' ) );
		\add_action( 'update_option_activitypub_checklist_health_check_issues', array( self::class, 'resolve_checklist' ) );
		\add_action( 'add_option_activitypub_checklist_fediverse_intro_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'update_option_activitypub_checklist_fediverse_intro_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'add_option_activitypub_checklist_settings_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'update_option_activitypub_checklist_settings_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'add_option_activitypub_checklist_profile_setup_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'update_option_activitypub_checklist_profile_setup_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'add_option_activitypub_checklist_blocks_visited', array( self::class, 'resolve_checklist' ) );
		\add_action( 'update_option_activitypub_checklist_blocks_visited', array( self::class, 'resolve_checklist' ) );
	}

	/**
	 * Register welcome fields.
	 */
	public static function register_welcome_fields() {
		\add_settings_section(
			'activitypub_welcome_header',
			'',
			array( self::class, 'render_welcome_header_section' ),
			'activitypub_welcome'
		);

		\add_settings_section(
			'activitypub_onboarding_steps',
			'',
			array( self::class, 'render_onboarding_steps_section' ),
			'activitypub_welcome'
		);

		\add_settings_section(
			'activitypub_welcome_footer',
			'',
			array( self::class, 'render_welcome_footer_section' ),
			'activitypub_welcome'
		);

		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_plugin_installed' ), 10 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_site_health' ), 20 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_fediverse_intro' ), 30 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_profile_mode' ), 40 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_profile_setup' ), 50 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_features' ), 60 );

		/*
		 * Keep track of health check issues. This is used to determine if the site health step is complete.
		 *
		 * This needs to happen after onboarding steps were registered, so that we can compare the number of completed
		 * steps with the number of registered steps.
		 */
		\update_option( 'activitypub_checklist_health_check_issues', (string) Health_Check::count_results( 'critical' ) );
	}

	/**
	 * Render welcome header section.
	 */
	public static function render_welcome_header_section() {
		$completed_steps     = self::get_completed_steps_count();
		$total_steps         = self::get_total_steps_count();
		$progress_percentage = \min( 100, \round( ( $completed_steps / $total_steps ) * 100 ) );
		?>
		<div id="activitypub-welcome-checklist" class="activitypub-welcome-container">
		<div class="activitypub-welcome-header">
			<div class="activitypub-progress-circle">
				<div class="activitypub-progress-circle-content">
					<span><?php echo \esc_html( $completed_steps ); ?>/<?php echo \esc_html( $total_steps ); ?></span>
				</div>
				<svg class="activitypub-progress-ring" width="120" height="120">
					<circle class="activitypub-progress-ring-bg" cx="60" cy="60" r="54" />
					<circle class="activitypub-progress-ring-circle" cx="60" cy="60" r="54"
							stroke-dasharray="339.292"
							stroke-dashoffset="<?php echo \esc_attr( 339.292 - ( 339.292 * $progress_percentage / 100 ) ); ?>" />
				</svg>
			</div>
			<h2 class="activitypub-welcome-title"><?php \esc_html_e( 'Welcome to the Fediverse!', 'activitypub' ); ?></h2>
			<p class="activitypub-welcome-subtitle"><?php \esc_html_e( 'Get connected in just a few steps.', 'activitypub' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Get the count of completed steps.
	 */
	private static function get_completed_steps_count() {
		$count = 1; // Plugin is already installed.

		// We're looking for 0 issues.
		if ( self::has_step( 'site_health' ) && '0' === \get_option( 'activitypub_checklist_health_check_issues', (string) Health_Check::count_results( 'critical' ) ) ) {
			++$count;
		}

		// Check other completed steps.
		if ( self::has_step( 'fediverse_intro' ) && '1' === \get_option( 'activitypub_checklist_fediverse_intro_visited' ) ) {
			++$count;
		}

		if ( self::has_step( 'profile_mode' ) && '1' === \get_option( 'activitypub_checklist_settings_visited' ) ) {
			++$count;
		}

		if ( self::has_step( 'profile_setup' ) && '1' === \get_option( 'activitypub_checklist_profile_setup_visited' ) ) {
			++$count;
		}

		if ( self::has_step( 'features' ) && '1' === \get_option( 'activitypub_checklist_blocks_visited' ) ) {
			++$count;
		}

		return $count;
	}

	/**
	 * Get the total number of steps.
	 */
	private static function get_total_steps_count() {
		global $wp_filter;

		$count = 0;

		if ( isset( $wp_filter['activitypub_onboarding_steps'] ) ) {
			$pattern = '/^' . \preg_quote( self::class, '/' ) . '/';
			foreach ( $wp_filter['activitypub_onboarding_steps']->callbacks as $callbacks ) {
				$matching_keys = \preg_grep( $pattern, \array_keys( $callbacks ) );
				$count        += \count( $matching_keys );
			}
		}

		return $count;
	}

	/**
	 * Get the next incomplete step.
	 */
	private static function get_next_incomplete_step() {
		if ( self::has_step( 'site_health' ) && '0' !== \get_option( 'activitypub_checklist_health_check_issues', (string) Health_Check::count_results( 'critical' ) ) ) {
			return 'site_health';
		}

		if ( self::has_step( 'fediverse_intro' ) && ! \get_option( 'activitypub_checklist_fediverse_intro_visited', false ) ) {
			return 'fediverse_intro';
		}

		if ( self::has_step( 'profile_mode' ) && ! \get_option( 'activitypub_checklist_settings_visited', false ) ) {
			return 'profile_mode';
		}

		if ( self::has_step( 'profile_setup' ) && ! \get_option( 'activitypub_checklist_profile_setup_visited', false ) ) {
			return 'profile_setup';
		}

		if ( self::has_step( 'features' ) && ! \get_option( 'activitypub_checklist_blocks_visited', false ) ) {
			return 'features';
		}

		return '';
	}

	/**
	 * Check if a step exists.
	 *
	 * @param string $step Step slug.
	 * @return bool
	 */
	private static function has_step( $step ) {
		return \has_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_' . $step ) );
	}

	/**
	 * Render onboarding steps section.
	 */
	public static function render_onboarding_steps_section() {
		?>
		<div class="activitypub-onboarding-steps">
			<?php
			\do_action( 'activitypub_onboarding_steps' );
			?>
		</div>
		<?php
	}

	/**
	 * Render plugin installed step.
	 */
	public static function render_step_plugin_installed() {
		?>
		<div class="activitypub-onboarding-step activitypub-step-completed">
			<div class="step-indicator">
				<span class="step-icon dashicons dashicons-yes"></span>
			</div>
			<div class="step-content">
				<div class="step-text">
					<h3><?php \esc_html_e( 'Plugin installed', 'activitypub' ); ?></h3>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render site health step.
	 */
	public static function render_step_site_health() {
		$health_issues = Health_Check::count_results();
		$total_issues  = $health_issues['critical'] + $health_issues['recommended'];
		$checked       = '0' === \get_option( 'activitypub_checklist_health_check_issues', (string) $health_issues['critical'] );
		$step_class    = $checked ? 'activitypub-step-completed' : '';
		$next_step     = self::get_next_incomplete_step();
		$button_class  = ( 'site_health' === $next_step ) ? 'button-primary' : 'button-secondary';
		?>
		<div class="activitypub-onboarding-step <?php echo \esc_attr( $step_class ); ?>">
			<div class="step-indicator">
				<?php if ( $checked ) : ?>
					<span class="step-icon dashicons dashicons-yes"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-warning"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<div class="step-text">
					<h3><?php \esc_html_e( 'Check your site&#8217;s health', 'activitypub' ); ?></h3>
					<p>
						<?php
						echo \esc_html(
							\sprintf(
								/* translators: %d: Number of issues. */
								\_n( '%d issue needs your attention.', '%d issues need your attention.', $total_issues, 'activitypub' ),
								$total_issues
							)
						);
						?>
					</p>
				</div>
				<div class="step-action">
					<a href="<?php echo \esc_url( \admin_url( 'site-health.php' ) ); ?>" class="button <?php echo \esc_attr( $button_class ); ?>">
						<?php \esc_html_e( 'Review issues', 'activitypub' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Fediverse-Intro step.
	 */
	public static function render_step_fediverse_intro() {
		$checked      = '1' === \get_option( 'activitypub_checklist_fediverse_intro_visited', false );
		$step_class   = $checked ? 'activitypub-step-completed' : '';
		$next_step    = self::get_next_incomplete_step();
		$button_class = ( 'fediverse_intro' === $next_step ) ? 'button-primary' : 'button-secondary';
		?>
		<div class="activitypub-onboarding-step <?php echo \esc_attr( $step_class ); ?>">
			<div class="step-indicator">
				<?php if ( $checked ) : ?>
					<span class="step-icon dashicons dashicons-yes"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-video-alt3"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<div class="step-text">
					<h3><?php \esc_html_e( 'New to the Fediverse? Start Here', 'activitypub' ); ?></h3>
					<p><?php \esc_html_e( 'Learn what the Fediverse is and why it matters.', 'activitypub' ); ?></p>
				</div>
				<div class="step-action">
					<a href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&help-tab=getting-started#tab-link-getting-started' ) ); ?>" class="button <?php echo \esc_attr( $button_class ); ?>">
						<?php \esc_html_e( 'View intro', 'activitypub' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Profile Mode step.
	 */
	public static function render_step_profile_mode() {
		$checked      = '1' === \get_option( 'activitypub_checklist_settings_visited', false );
		$step_class   = $checked ? 'activitypub-step-completed' : '';
		$next_step    = self::get_next_incomplete_step();
		$button_class = ( 'profile_mode' === $next_step ) ? 'button-primary' : 'button-secondary';
		?>
		<div class="activitypub-onboarding-step <?php echo \esc_attr( $step_class ); ?>">
			<div class="step-indicator">
				<?php if ( $checked ) : ?>
					<span class="step-icon dashicons dashicons-yes"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-groups"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<div class="step-text">
					<h3><?php \esc_html_e( 'Select how you want to share', 'activitypub' ); ?></h3>
					<p><?php \esc_html_e( 'Pick your preferred user mode for connecting with others.', 'activitypub' ); ?></p>
				</div>
				<div class="step-action">
					<a href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&tab=settings' ) ); ?>" class="button <?php echo \esc_attr( $button_class ); ?>">
						<?php \esc_html_e( 'Choose mode', 'activitypub' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Profile Setup step.
	 */
	public static function render_step_profile_setup() {
		$user_can_activitypub = user_can_activitypub( \get_current_user_id() );
		$checked              = '1' === \get_option( 'activitypub_checklist_profile_setup_visited', false );
		$step_class           = $checked ? 'activitypub-step-completed' : '';
		$next_step            = self::get_next_incomplete_step();
		$button_class         = ( 'profile_setup' === $next_step ) ? 'button-primary' : 'button-secondary';
		?>
		<div class="activitypub-onboarding-step <?php echo \esc_attr( $step_class ); ?>">
			<div class="step-indicator">
				<?php if ( $checked ) : ?>
					<span class="step-icon dashicons dashicons-yes"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-admin-users"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<div class="step-text">
					<h3><?php \esc_html_e( 'Set up your public profile', 'activitypub' ); ?></h3>
					<p><?php \esc_html_e( 'Configure your display name and how you appear to others.', 'activitypub' ); ?></p>
				</div>
				<div class="step-action">
					<?php if ( true === $user_can_activitypub ) : ?>
						<a href="<?php echo \esc_url( \admin_url( '/profile.php#activitypub' ) ); ?>" class="button <?php echo \esc_attr( $button_class ); ?>">
							<?php \esc_html_e( 'Edit profile', 'activitypub' ); ?>
						</a>
					<?php elseif ( ACTIVITYPUB_BLOG_MODE === \get_option( 'activitypub_actor_mode' ) ) : ?>
						<a href="<?php echo \esc_url( \admin_url( '/options-general.php?page=activitypub&tab=blog-profile' ) ); ?>" class="button <?php echo \esc_attr( $button_class ); ?>">
							<?php \esc_html_e( 'Edit profile', 'activitypub' ); ?>
						</a>
					<?php else : ?>
						<button class="button <?php echo \esc_attr( $button_class ); ?>" disabled>
							<?php \esc_html_e( 'Edit profile', 'activitypub' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Features step.
	 */
	public static function render_step_features() {
		$checked      = '1' === \get_option( 'activitypub_checklist_blocks_visited', false );
		$step_class   = $checked ? 'activitypub-step-completed' : '';
		$next_step    = self::get_next_incomplete_step();
		$button_class = ( 'features' === $next_step ) ? 'button-primary' : 'button-secondary';
		?>
		<div class="activitypub-onboarding-step <?php echo \esc_attr( $step_class ); ?>">
			<div class="step-indicator">
				<?php if ( $checked ) : ?>
					<span class="step-icon dashicons dashicons-yes"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-info"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<div class="step-text">
					<h3><?php \esc_html_e( 'Learn more about Fediverse features', 'activitypub' ); ?></h3>
					<p><?php \esc_html_e( 'Discover blocks, privacy, and more.', 'activitypub' ); ?></p>
				</div>
				<div class="step-action">
					<a href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&help-tab=editor-blocks#tab-link-editor-blocks' ) ); ?>" class="button <?php echo \esc_attr( $button_class ); ?>">
						<?php \esc_html_e( 'Explore features', 'activitypub' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render welcome footer section.
	 */
	public static function render_welcome_footer_section() {
		?>
		</div><!-- closing welcome-container div -->
		<div class="activitypub-welcome-footer">
			<a href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&welcome=0' ) ); ?>" class="skip-steps-link">
				<?php \esc_html_e( 'Skip these steps', 'activitypub' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Resolve the welcome checklist.
	 */
	public static function resolve_checklist() {
		if ( self::get_total_steps_count() === self::get_completed_steps_count() ) {
			\update_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', 0 );
		}
	}
}
