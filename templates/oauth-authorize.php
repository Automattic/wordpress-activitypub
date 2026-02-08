<?php
/**
 * OAuth Authorization Consent Form Template.
 *
 * @package Activitypub
 *
 * Variables available (passed via include from class-server.php):
 * @var WP_User $current_user          The current logged-in user.
 * @var array   $scopes                Array of requested scopes.
 * @var string  $client_id             The client ID.
 * @var string  $client_name           The client name.
 * @var string  $redirect_uri          The redirect URI.
 * @var string  $state                 The state parameter.
 * @var string  $code_challenge        The PKCE code challenge.
 * @var string  $code_challenge_method The PKCE method.
 * @var string  $form_url              The form action URL.
 * @var string  $scope                 The original scope string.
 */

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- Variables passed via include.

use Activitypub\OAuth\Scope;

// Use WordPress login page header.
$login_errors = new WP_Error();
login_header(
	/* translators: %s: Client name */
	sprintf( __( 'Authorize %s', 'activitypub' ), esc_html( $client_name ?: $client_id ) ),
	'',
	$login_errors
);
?>

<form method="post" action="<?php echo esc_url( $form_url ); ?>">
	<div class="activitypub-oauth-client">
		<p>
			<strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: Client name or ID */
					__( '%s wants to access your account.', 'activitypub' ),
					'<a href="' . esc_url( $client_id ) . '">' . esc_html( $client_name ?: $client_id ) . '</a>'
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
			</strong>
		</p>
	</div>

	<div class="activitypub-oauth-user" style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin: 20px 0;">
		<?php echo get_avatar( $current_user->ID, 48 ); ?>
		<p>
		<?php
		printf(
			/* translators: 1: User display name, 2: User login */
			esc_html__( 'Logged in as %1$s (%2$s). You can revoke access at any time.', 'activitypub' ),
			'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
			esc_html( $current_user->user_login )
		);
		?>
		</p>
	</div>

	<?php if ( ! empty( $scopes ) ) : ?>
	<div class="activitypub-oauth-scopes" style="margin: 20px 0;">
		<h3><?php esc_html_e( 'Permissions requested:', 'activitypub' ); ?></h3>
		<ul style="margin: 0; padding: 0 0 0 20px;">
			<?php foreach ( $scopes as $scope_name ) : ?>
				<li>
					<strong><?php echo esc_html( $scope_name ); ?></strong>
					<?php
					$description = Scope::get_description( $scope_name );
					if ( $description ) {
						echo ' &ndash; ' . esc_html( $description );
					}
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<div class="activitypub-oauth-redirect" style="background: #f0f6fc; padding: 10px 15px; border-radius: 4px; margin: 20px 0; font-size: 13px;">
		<?php
		printf(
			/* translators: %s: Redirect URI */
			esc_html__( 'You will be redirected to %s after authorization.', 'activitypub' ),
			'<code>' . esc_html( $redirect_uri ) . '</code>'
		);
		?>
	</div>

	<?php wp_nonce_field( 'activitypub_oauth_authorize' ); ?>
	<input type="hidden" name="client_id" value="<?php echo esc_attr( $client_id ); ?>" />
	<input type="hidden" name="redirect_uri" value="<?php echo esc_url( $redirect_uri ); ?>" />
	<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>" />
	<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>" />
	<input type="hidden" name="code_challenge" value="<?php echo esc_attr( $code_challenge ); ?>" />
	<input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( $code_challenge_method ); ?>" />

	<p class="submit" style="display: flex; gap: 10px;">
		<button type="submit" name="approve" value="1" class="button button-primary button-large">
			<?php esc_html_e( 'Authorize', 'activitypub' ); ?>
		</button>
		<button type="submit" name="deny" value="1" class="button button-large">
			<?php esc_html_e( 'Cancel', 'activitypub' ); ?>
		</button>
	</p>
</form>

<?php
login_footer();
