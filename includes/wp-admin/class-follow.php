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
	 * URL.
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Redirect URL.
	 *
	 * @var string
	 */
	public $redirect;

	/**
	 * Error.
	 *
	 * @var \WP_Error
	 */
	public $error;

	/**
	 * Actor.
	 *
	 * @var \Activitypub\Model\Actor
	 */
	public $actor;

	/**
	 * Post.
	 *
	 * @var \WP_Post
	 */
	public $post;

	/**
	 * Query arguments.
	 *
	 * @var array
	 */
	public $query_args;

	/**
	 * Initialize the settings fields.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $url Base URL.
	 * @param string $redirect Redirect URL.
	 */
	public function __construct( $user_id, $url, $redirect ) {
		$this->user_id  = $user_id;
		$this->url      = $url;
		$this->redirect = $redirect;

		$query_string = \wp_parse_url( $url, PHP_URL_QUERY );
		\parse_str( $query_string, $this->query_args );

		\add_action( 'admin_init', array( $this, 'admin_head' ) );
	}

	/**
	 * Display the follow page.
	 */
	public function display() {
		if (
			isset( $_POST ) &&
			isset( $_POST['activitypub-follow-nonce'] ) &&
			isset( $_POST['id'] ) &&
			\wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['activitypub-follow-nonce'] ) ), 'activitypub-follow' )
		) {
			$id = \sanitize_text_field( \wp_unslash( $_POST['id'] ) );

			$result = Following::follow( $id, $this->user_id );
			if ( \is_wp_error( $result ) ) {
				$this->error = $result;
			} else {
				return $this->success();
			}
		}

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

		$this->post  = $post;
		$this->actor = Actors::get_actor( $post );

		if ( is_wp_error( $this->actor ) ) {
			if ( $this->id ) {
				$this->error = $this->actor;
			}

			return $this->search();
		} else {
			return $this->preview();
		}
	}

	/**
	 * Follow dialog.
	 */
	public function preview() {
		$actor = $this->actor;
		$post  = $this->post;
		?>
		<div class="activitypub-follow-wrapper">
			<div class="activitypub-profile p-author h-card">
				<div class="activitypub-profile__header" style="background-image: url('<?php echo esc_url( $actor->get_image()['url'] ?? '' ); ?>');">
					<?php if ( Followers::follows( $post->ID, $this->user_id ) ) : ?>
						<div class="activitypub-profile__follow-indicator">
							<?php echo esc_html__( 'Follows you', 'activitypub' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="activitypub-profile__body">
					<img
						class="activitypub-profile__avatar u-photo"
						src="<?php echo esc_url( $actor->get_icon()['url'] ?? \get_avatar_url( '' ) ); ?>"
						alt="<?php echo esc_attr( $actor->get_name() ?? $actor->get_preferred_username() ); ?>"
					/>

					<div class="activitypub-profile__content">
						<div class="activitypub-profile__info">
							<div class="activitypub-profile__name p-name"><?php echo esc_html( $actor->get_name() ?? $actor->get_preferred_username() ); ?></div>
							<?php /** Using `data-wp-text` to avoid @see enrich_content_data() turning it into a mention. */ ?>
							<div class="activitypub-profile__handle p-nickname p-x-webfinger" data-wp-text="context.webfinger"></div>
						</div>

						<?php $this->follow_button(); ?>

						<?php if ( $actor->get_summary() ) : ?>
							<div class="activitypub-profile__bio p-note">
								<?php echo wp_kses_post( $actor->get_summary() ); ?>
							</div>
						<?php endif; ?>

						<?php
						$attachments = $actor->get_attachment();
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
		if ( $this->error ) {
			?>
			<div class="notice notice-error"><p><strong><?php echo esc_html( $this->error->get_error_message() ); ?></strong></p></div>
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
		<form method="get" action="<?php echo esc_url( $this->url ); ?>">
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php foreach ( $this->query_args as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php endforeach; ?>
			<input type="text" class="regular-text ltr activitypub-profile-search" width="100%" name="id" value="<?php echo esc_attr( $this->id ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'username@domain.tld or https://domain.tld/@username', 'activitypub' ); ?>" />
			<?php \submit_button( \esc_attr__( 'Search', 'activitypub' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Handle the search query.
	 */
	public function success() {
		?>
		<p>
			<?php echo esc_html__( 'Follow request successfully sent.', 'activitypub' ); ?>
		</p>
		<?php
	}

	/**
	 * Follow button.
	 */
	public function follow_button() {
		$status = Following::check_status( $this->user_id, $this->post->ID );

		$url = esc_url(
			add_query_arg(
				array(
					'page' => \sanitize_text_field( \wp_unslash( $_GET['page'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'tab'  => 'follow',
				),
				$this->url
			)
		);

		switch ( $status ) {
			case 'accepted':
				?>
				<div class="button disabled" title="<?php echo esc_attr__( 'You are following this user', 'activitypub' ); ?>">
					<span aria-label="<?php echo esc_attr__( 'You are following this user', 'activitypub' ); ?>"><?php echo esc_html__( '&#x2713; Following', 'activitypub' ); ?></span>
				</div>
				<?php
				break;
			case 'pending':
				?>
				<div class="button disabled" title="<?php echo esc_attr__( 'You have sent a follow request', 'activitypub' ); ?>">
					<span aria-label="<?php echo esc_attr__( 'You have sent a follow request', 'activitypub' ); ?>"><?php echo esc_html__( '&#x2D35; Pending', 'activitypub' ); ?></span>
				</div>
				<?php
				break;
			default:
				?>
				<form method="post" action="<?php echo esc_url( $url ); ?>">
					<?php \wp_nonce_field( 'activitypub-follow', 'activitypub-follow-nonce' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( $this->post->ID ); ?>" />
					<?php \submit_button( \esc_attr__( 'Follow', 'activitypub' ) ); ?>
				</form>
				<?php
				break;
		}
	}
}
