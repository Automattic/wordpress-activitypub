<?php
/**
 * Follow class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Webfinger;

/**
 * ActivityPub Follow class.
 */
class Follow {
	/**
	 * Actor ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Actor.
	 *
	 * @var \Activitypub\Model\Actor
	 */
	public $actor;

	/**
	 * Initialize the settings fields.
	 */
	public function __construct() {
		if ( get_current_screen()->id === 'settings_page_activitypub' ) {
			$this->user_id = Actors::BLOG_USER_ID;
		} else {
			$this->user_id = \get_current_user_id();
		}

		\wp_enqueue_style( 'activitypub-follow-me', plugins_url( 'build/follow-me/style-index.css', ACTIVITYPUB_PLUGIN_FILE ), array(), ACTIVITYPUB_PLUGIN_VERSION );
	}

	/**
	 * Display the follow page.
	 */
	public function display() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->id = \sanitize_text_field( \wp_unslash( $_GET['id'] ?? '' ) );

		if ( empty( $this->id ) ) {
			$post = null;
		} elseif ( is_numeric( $this->id ) ) {
			$post = \get_post( $this->id );
		} else {
			$id = Webfinger::resolve( $this->id );
			if ( \is_wp_error( $id ) ) {
				$post = null;
			} else {
				$post = Actors::fetch_remote_by_uri( $id );
			}
		}

		$this->actor = Actors::get_actor( $post );

		if ( is_wp_error( $this->actor ) ) {
			$this->search();
		} else {
			$this->preview();
		}
	}

	/**
	 * Follow dialog.
	 */
	public function preview() {
		?>
		<div class="activitypub-follow-me-block-wrapper is-style-profile wp-block-activitypub-follow-me">
			<div class="activitypub-profile p-author h-card">
				<div class="activitypub-profile__header" style="background-image: url('<?php echo esc_url( $this->actor->get_image()['url'] ?? '' ); ?>');">
					<?php if ( $this->actor->get_follows() ) : ?>
						<div class="activitypub-profile__follow-indicator">
							<?php echo esc_html__( 'Follows you', 'activitypub' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="activitypub-profile__body">
					<img
						class="activitypub-profile__avatar u-photo"
						src="<?php echo esc_url( $this->actor->get_icon()['url'] ?? \get_avatar_url( '' ) ); ?>"
						alt="<?php echo esc_attr( $this->actor->get_name() ?? $this->actor->get_preferred_username() ); ?>"
					/>

					<div class="activitypub-profile__content">
						<div class="activitypub-profile__info">
							<div class="activitypub-profile__name p-name"><?php echo esc_html( $this->actor->get_name() ?? $this->actor->get_preferred_username() ); ?></div>
							<?php /** Using `data-wp-text` to avoid @see enrich_content_data() turning it into a mention. */ ?>
							<div class="activitypub-profile__handle p-nickname p-x-webfinger" data-wp-text="context.webfinger"></div>
						</div>

						<?php $this->follow_button(); ?>

						<?php if ( $this->actor->get_summary() ) : ?>
							<div class="activitypub-profile__bio p-note">
								<?php echo wp_kses_post( $this->actor->get_summary() ); ?>
							</div>
						<?php endif; ?>

						<?php
						$attachments = $this->actor->get_attachment();
						if ( ! empty( $attachments ) ) :
							// Filter for PropertyValue attachments (extra fields).
							$extra_fields = array_filter(
								$attachments,
								function ( $attachment ) {
									return isset( $attachment['type'] ) && 'PropertyValue' === $attachment['type'];
								}
							);
							if ( ! empty( $extra_fields ) ) :
								?>
							<div class="activitypub-profile__extra-fields">
								<h4><?php echo esc_html__( 'Additional Information', 'activitypub' ); ?></h4>
								<table class="activitypub-extra-fields-table">
									<tbody>
										<?php foreach ( $extra_fields as $field ) : ?>
											<tr>
												<td class="field-name">
													<strong><?php echo esc_html( $field['name'] ); ?></strong>
												</td>
												<td class="field-value">
													<?php echo wp_kses_post( $field['value'] ); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Search for an actor.
	 */
	public function search() {
		if ( get_current_screen()->id === 'settings_page_activitypub' ) {
			$url = \admin_url( 'options-general.php' );
		} else {
			$url = \admin_url( 'users.php' );
		}

		if ( $this->id && is_wp_error( $this->actor ) ) {
			?>
			<div class="notice notice-error"><p><strong><?php echo esc_html( $this->actor->get_error_message() ); ?></strong></p></div>
			<?php
		}
		?>
		<p><?php echo esc_html__( 'There are two common ways to look up someone&rsquo;s profile on the Fediverse (e.g., Mastodon, Pixelfed, PeerTube, etc.):', 'activitypub' ); ?></p>

		<ol>
			<li><?php echo esc_html__( 'WebFinger address (like an email):', 'activitypub' ); ?>
				<ul>
					<li><code>@username@domain.tld</code></li>
				</ul>
			</li>
			<li><?php echo esc_html__( 'Profile URL:', 'activitypub' ); ?>
				<ul>
					<li><code>https://domain.tld/@username</code></li>
				</ul>
			</li>
		</ol>

		<p><?php echo esc_html__( 'Paste either into the search bar above to find and follow the profile.', 'activitypub' ); ?></p>
		<form method="get" action="<?php echo esc_url( $url ); ?>">
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<input type="hidden" name="page" value="<?php echo \esc_attr( \wp_unslash( $_GET['page'] ?? '' ) ); ?>" />
			<input type="hidden" name="tab" value="follow" />
			<input type="text" class="regular-text ltr activitypub-profile-search" width="100%" name="id" value="<?php echo esc_attr( $this->id ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'username@domain.tld or https://domain.tld/@username', 'activitypub' ); ?>" />
			<?php submit_button( esc_attr__( 'Search', 'activitypub' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Handle the search query.
	 */
	public function follow() {
		// @todo Implement follow.
	}

	/**
	 * Follow button.
	 */
	public function follow_button() {
		$status = Following::check_status( $this->user_id, $this->id );

		switch ( $status ) {
			case 'accepted':
				?>
				<div class="button disabled" title="<?php echo esc_attr__( 'You are following this user', 'activitypub' ); ?>">
					<span aria-label="<?php echo esc_attr__( 'You are following this user', 'activitypub' ); ?>">Following</span>
				</div>
				<?php
				break;
			case 'pending':
				?>
				<div class="button disabled" title="<?php echo esc_attr__( 'You have sent a follow request', 'activitypub' ); ?>">
					<span aria-label="<?php echo esc_attr__( 'You have sent a follow request', 'activitypub' ); ?>">Pending</span>
				</div>
				<?php
				break;
			default:
				?>
				<div class="button">
					<a aria-label="Follow me on the Fediverse">Follow</a>
				</div>
				<?php
				break;
		}
	}
}
