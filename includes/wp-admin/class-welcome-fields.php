<?php
/**
 * ActivityPub Welcome Fields Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;

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
		\add_action( 'load-settings_page_activitypub', array( self::class, 'add_admin_notices' ) );
	}

	/**
	 * Register welcome fields.
	 */
	public static function register_welcome_fields() {
		// Add settings sections.
		\add_settings_section(
			'activitypub_welcome_close',
			'',
			array( self::class, 'render_welcome_close_section' ),
			'activitypub_welcome'
		);

		// Add settings sections.
		\add_settings_section(
			'activitypub_intro',
			\__( 'Welcome', 'activitypub' ),
			array( self::class, 'render_welcome_intro_section' ),
			'activitypub_welcome'
		);

		if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
			\add_settings_section(
				'activitypub_blog_profile',
				\__( 'Blog profile', 'activitypub' ),
				array( self::class, 'render_blog_profile_section' ),
				'activitypub_welcome'
			);
		}

		if ( user_can_activitypub( \get_current_user_id() ) ) {
			\add_settings_section(
				'activitypub_author_profile',
				\__( 'Author profile', 'activitypub' ),
				array( self::class, 'render_author_profile_section' ),
				'activitypub_welcome'
			);
		}
	}

	/**
	 * Add Health Check errors as admin notices.
	 */
	public static function add_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) && 'welcome' !== $_GET['tab'] ) {
			return;
		}

		if ( ! \get_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', true ) ) {
			return;
		}

		if ( Health_Check::count_results( 'critical' ) ) {
			\add_action( 'admin_notices', array( self::class, 'admin_notices' ) );
		}
	}

	/**
	 * Render welcome intro section.
	 */
	public static function render_welcome_close_section() {
		?>
		<a class="welcome-tab-close" href="<?php echo \esc_url( \admin_url( 'options-general.php?page=activitypub&welcome=0' ) ); ?>" aria-label="<?php \esc_attr_e( 'Dismiss the welcome page', 'activitypub' ); ?>"><?php \esc_html_e( 'Dismiss Welcome Page', 'activitypub' ); ?></a>
		<?php
	}

	/**
	 * Render welcome intro section.
	 */
	public static function render_welcome_intro_section() {
		?>
		<p><?php echo wp_kses( \__( 'Enter the fediverse with <strong>ActivityPub</strong>, broadcasting your blog to a wider audience. Attract followers, deliver updates, and receive comments from a diverse user base on <strong>Mastodon</strong>, <strong>Friendica</strong>, <strong>Pleroma</strong>, <strong>Pixelfed</strong>, and all <strong>ActivityPub</strong>-compliant platforms.', 'activitypub' ), array( 'strong' => array() ) ); ?></p>
		<?php
	}

	/**
	 * Render blog profile section.
	 */
	public static function render_blog_profile_section() {
		$blog_user = new Blog();

		?>
		<p>
			<?php \esc_html_e( 'People can follow your blog by using:', 'activitypub' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Username', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-blog-identifier" value="<?php echo \esc_attr( $blog_user->get_webfinger() ); ?>" readonly />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-blog-url" value="<?php echo \esc_attr( $blog_user->get_url() ); ?>" readonly />
					</td>
				</tr>
			</tbody>
		</table>
		<p>
			<?php \esc_html_e( 'This blog profile will federate all posts written on your blog, regardless of the author who posted it.', 'activitypub' ); ?>
			<a href="<?php echo \esc_url( \admin_url( '/options-general.php?page=activitypub&tab=blog-profile' ) ); ?>">
				<?php \esc_html_e( 'Customize the blog profile.', 'activitypub' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render author profile section.
	 */
	public static function render_author_profile_section() {
		$user = Actors::get_by_id( \get_current_user_id() );
		?>
		<p>
			<?php \esc_html_e( 'People can follow you by using your author name:', 'activitypub' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Username', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-user-identifier" value="<?php echo \esc_attr( $user->get_webfinger() ); ?>" readonly />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Profile URL', 'activitypub' ); ?></th>
					<td>
						<input type="text" class="large-text code" id="activitypub-user-url" value="<?php echo \esc_attr( $user->get_url() ); ?>" readonly />
					</td>
				</tr>
			</tbody>
		</table>
		<p>
			<?php \esc_html_e( 'Authors who can not access this settings page will find their username on the "Edit Profile" page.', 'activitypub' ); ?>
			<a href="<?php echo \esc_url( \admin_url( '/profile.php#activitypub' ) ); ?>">
			<?php \esc_html_e( 'Customize username on "Edit Profile" page.', 'activitypub' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render troubleshooting section.
	 */
	public static function admin_notices() {
		$results = Health_Check::count_results();
		?>
		<div class="activitypub-notice notice notice-warning">
			<p>
				<span class="dashicons dashicons-warning"></span>
				<?php
				echo wp_kses(
					\sprintf(
						/* translators: the placeholders are the number of critical and recommended issues on the site. */
						\__(
							'<strong>Important:</strong> There are <span class="count">%1$d</span> critical and <span class="count">%2$d</span> recommended issues affecting your site&#8217;s compatibility with the fediverse. Please check the <a href="%3$s">Site Health</a> page to resolve these issues.',
							'activitypub'
						),
						$results['critical'],
						$results['recommended'],
						\esc_url( \admin_url( 'site-health.php' ) )
					),
					array(
						'strong' => array(),
						'span'   => array(
							'class' => array(),
						),
						'a'      => array(
							'href' => array(),
						),
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
