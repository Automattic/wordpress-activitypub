<?php
/**
 * ActivityPub Welcome Fields Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;

use function Activitypub\get_reply_intent_js;
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
		\add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_styles' ) );
	}

	public static function enqueue_styles() {
		$current_screen = get_current_screen();
		if ( 'settings_page_activitypub' === $current_screen->id ) {
			wp_enqueue_style(
				'activitypub-welcome',
				plugins_url( 'assets/css/activitypub-welcome.css', ACTIVITYPUB_PLUGIN_FILE ),
				array(),
				ACTIVITYPUB_PLUGIN_VERSION
			);
		}
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

		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_plugin_installed' ), 10 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_site_health' ), 20 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_fediverse_intro' ), 30 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_profile_mode' ), 40 );
		\add_action( 'activitypub_onboarding_steps', array( self::class, 'render_step_blocks' ), 50 );

		if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
			\add_settings_section(
				'activitypub_profiles_overview',
				\__( 'Your Fediverse Profiles', 'activitypub' ),
				array( self::class, 'render_profiles_overview_section' ),
				'activitypub_welcome'
			);
		}

		\add_settings_section(
			'activitypub_welcome_footer',
			'',
			array( self::class, 'render_welcome_footer_section' ),
			'activitypub_welcome'
		);
	}

	/**
	 * Render welcome header section.
	 */
	public static function render_welcome_header_section() {
		$completed_steps = self::get_completed_steps_count();
		$total_steps = 5; // Total number of steps
		$progress_percentage = min(100, round(($completed_steps / $total_steps) * 100));
		?>
		<div class="activitypub-welcome-header">
			<div class="activitypub-welcome-progress">
				<div class="activitypub-progress-indicator">
					<div class="activitypub-progress-bar" style="width: <?php echo esc_attr( $progress_percentage ); ?>%"></div>
				</div>
				<div class="activitypub-progress-text"><?php echo esc_html( $completed_steps ); ?>/<?php echo esc_html( $total_steps ); ?></div>
			</div>
			<h2 class="activitypub-welcome-title"><?php esc_html_e( 'Let\'s get started!', 'activitypub' ); ?></h2>
			<p class="activitypub-welcome-subtitle"><?php esc_html_e( 'It\'s time to finish setting up your plugin.', 'activitypub' ); ?></p>
			<a class="welcome-tab-close" href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&welcome=0' ) ); ?>" aria-label="<?php \esc_attr_e( 'Dismiss the welcome page', 'activitypub' ); ?>"><?php \esc_html_e( 'Skip these steps', 'activitypub' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Get the count of completed steps.
	 */
	private static function get_completed_steps_count() {
		$count = 1; // Plugin is already installed

		// Check if site health has no critical issues
		if (Health_Check::count_results('critical') === 0) {
			$count++;
		}

		// Check other completed steps
		if (\get_option('activitypub_checklist_fediverse_intro_visited', false)) {
			$count++;
		}

		if (\get_option('activitypub_checklist_settings_visited', false)) {
			$count++;
		}

		if (\get_option('activitypub_checklist_blocks_visited', false)) {
			$count++;
		}

		return $count;
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
				<span class="step-icon dashicons dashicons-yes-alt"></span>
			</div>
			<div class="step-content">
				<h3><?php esc_html_e( 'Plugin installed', 'activitypub' ); ?></h3>
				<p><?php esc_html_e( 'ActivityPub is ready to help you connect to the Fediverse.', 'activitypub' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render site health step.
	 */
	public static function render_step_site_health() {
		$critical_issues = Health_Check::count_results('critical');
		$recommended_issues = Health_Check::count_results('recommended');
		$is_completed = ($critical_issues === 0);
		$step_class = $is_completed ? 'activitypub-step-completed' : 'activitypub-step-attention';
		?>
		<div class="activitypub-onboarding-step <?php echo esc_attr($step_class); ?>">
			<div class="step-indicator">
				<?php if ($is_completed) : ?>
					<span class="step-icon dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-warning"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<h3><?php esc_html_e( 'Verify Site Health', 'activitypub' ); ?></h3>
				<?php if ($critical_issues > 0 || $recommended_issues > 0) : ?>
					<p>
						<?php
						echo wp_kses(
							sprintf(
								__('Your site has <strong>%1$d critical</strong> and <strong>%2$d recommended</strong> issues that may affect compatibility with the Fediverse.', 'activitypub'),
								$critical_issues,
								$recommended_issues
							),
							array('strong' => array())
						);
						?>
					</p>
					<a href="<?php echo esc_url(admin_url('site-health.php')); ?>" class="button button-primary">
						<?php esc_html_e('Fix Site Health Issues', 'activitypub'); ?>
					</a>
				<?php else : ?>
					<p><?php esc_html_e('No critical issues found. Your site is ready for the Fediverse!', 'activitypub'); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Fediverse-Intro step.
	 */
	public static function render_step_fediverse_intro() {
		$checked = \get_option( 'activitypub_checklist_fediverse_intro_visited', false );
		$step_class = $checked ? 'activitypub-step-completed' : '';
		?>
		<div class="activitypub-onboarding-step <?php echo esc_attr($step_class); ?>">
			<div class="step-indicator">
				<?php if ($checked) : ?>
					<span class="step-icon dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-video-alt3"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<h3><?php esc_html_e('Learn about the Fediverse', 'activitypub'); ?></h3>
				<p><?php esc_html_e('Understand what the Fediverse is and why it matters.', 'activitypub'); ?></p>
				<a href="<?php echo \esc_url(\admin_url('options-general.php?page=activitypub#tab-link-getting-started')); ?>" class="button">
					<?php esc_html_e('Watch Fediverse video series', 'activitypub'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Profile Mode step.
	 */
	public static function render_step_profile_mode() {
		$checked = \get_option( 'activitypub_checklist_settings_visited', false );
		$step_class = $checked ? 'activitypub-step-completed' : '';
		?>
		<div class="activitypub-onboarding-step <?php echo esc_attr($step_class); ?>">
			<div class="step-indicator">
				<?php if ($checked) : ?>
					<span class="step-icon dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-admin-users"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<h3><?php esc_html_e('Select your user mode', 'activitypub'); ?></h3>
				<p><?php esc_html_e('Choose whether to use blog profile, author profiles, or both.', 'activitypub'); ?></p>
				<a href="<?php echo \esc_url(\admin_url('options-general.php?page=activitypub&tab=settings#tab-link-core-features')); ?>" class="button">
					<?php esc_html_e('Configure profile settings', 'activitypub'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Blocks step.
	 */
	public static function render_step_blocks() {
		$checked = \get_option( 'activitypub_checklist_blocks_visited', false );
		$step_class = $checked ? 'activitypub-step-completed' : '';
		?>
		<div class="activitypub-onboarding-step <?php echo esc_attr($step_class); ?>">
			<div class="step-indicator">
				<?php if ($checked) : ?>
					<span class="step-icon dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span class="step-icon dashicons dashicons-editor-code"></span>
				<?php endif; ?>
			</div>
			<div class="step-content">
				<h3><?php esc_html_e('Explore Fediverse blocks', 'activitypub'); ?></h3>
				<p><?php esc_html_e('Learn how to connect your blog to the Fediverse with special blocks.', 'activitypub'); ?></p>
				<a href="<?php echo \esc_url(\admin_url('options-general.php?page=activitypub#tab-link-editor-blocks')); ?>" class="button">
					<?php esc_html_e('Learn more about Fediverse blocks', 'activitypub'); ?>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render profiles overview section.
	 */
	public static function render_profiles_overview_section() {
		?>
		<p class="profiles-description">
			<?php esc_html_e('Once configured, these are the profiles people can follow in the Fediverse:', 'activitypub'); ?>
		</p>
		<div class="activitypub-profiles-container">
			<?php
			if (user_can_activitypub(Actors::BLOG_USER_ID)) {
				self::render_blog_profile_card();
			}

			if (user_can_activitypub(\get_current_user_id())) {
				self::render_author_profile_card();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render blog profile card.
	 */
	private static function render_blog_profile_card() {
		$blog_user = new Blog();
		?>
		<div class="activitypub-profile-card">
			<div class="profile-card-header">
				<div class="profile-icon">
					<span class="dashicons dashicons-admin-site"></span>
				</div>
				<h3><?php esc_html_e('Blog Profile', 'activitypub'); ?></h3>
			</div>
			<div class="profile-card-content">
				<div class="profile-field">
					<label><?php esc_html_e('Username', 'activitypub'); ?></label>
					<input type="text" class="code" value="<?php echo esc_attr($blog_user->get_webfinger()); ?>" readonly />
				</div>
				<div class="profile-field">
					<label><?php esc_html_e('Profile URL', 'activitypub'); ?></label>
					<input type="text" class="code" value="<?php echo esc_attr($blog_user->get_url()); ?>" readonly />
				</div>
				<p class="profile-description">
					<?php esc_html_e('This blog profile will federate all posts written on your blog, regardless of the author.', 'activitypub'); ?>
				</p>
				<a href="<?php echo esc_url(admin_url('/options-general.php?page=activitypub&tab=blog-profile')); ?>" class="button">
					<?php esc_html_e('Customize', 'activitypub'); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render author profile card.
	 */
	private static function render_author_profile_card() {
		$user = Actors::get_by_id(\get_current_user_id());
		?>
		<div class="activitypub-profile-card">
			<div class="profile-card-header">
				<div class="profile-icon">
					<span class="dashicons dashicons-admin-users"></span>
				</div>
				<h3><?php esc_html_e('Author Profile', 'activitypub'); ?></h3>
			</div>
			<div class="profile-card-content">
				<div class="profile-field">
					<label><?php esc_html_e('Username', 'activitypub'); ?></label>
					<input type="text" class="code" value="<?php echo esc_attr($user->get_webfinger()); ?>" readonly />
				</div>
				<div class="profile-field">
					<label><?php esc_html_e('Profile URL', 'activitypub'); ?></label>
					<input type="text" class="code" value="<?php echo esc_attr($user->get_url()); ?>" readonly />
				</div>
				<p class="profile-description">
					<?php esc_html_e('Your author profile will federate only posts you publish.', 'activitypub'); ?>
				</p>
				<a href="<?php echo esc_url(admin_url('/profile.php#activitypub')); ?>" class="button">
					<?php esc_html_e('Customize', 'activitypub'); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render welcome footer section.
	 */
	public static function render_welcome_footer_section() {
		?>
		<div class="activitypub-welcome-footer">
			<p><?php esc_html_e('Need help? Check out our documentation or ask in the WordPress support forums.', 'activitypub'); ?></p>
			<div class="activitypub-footer-actions">
				<a href="https://wordpress.org/plugins/activitypub/faq/" class="button" target="_blank"><?php esc_html_e('Documentation', 'activitypub'); ?></a>
				<a href="https://wordpress.org/support/plugin/activitypub/" class="button" target="_blank"><?php esc_html_e('Support', 'activitypub'); ?></a>
				<a href="<?php echo esc_url(admin_url('options-general.php?page=activitypub&welcome=0')); ?>" class="button button-primary"><?php esc_html_e('Go to Settings', 'activitypub'); ?></a>
			</div>
		</div>
		<?php
	}
}
