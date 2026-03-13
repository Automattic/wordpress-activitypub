<?php
/**
 * OAuth Authorization Consent Form Template.
 *
 * @package Activitypub
 *
 * Variables available (passed via include from class-server.php):
 * @var WP_User                  $current_user     The current logged-in user.
 * @var array                    $scopes           Array of requested scopes.
 * @var Activitypub\OAuth\Client $client           The client object.
 * @var array                    $authorize_params OAuth request parameters (client_id, redirect_uri, scope, state, code_challenge, code_challenge_method).
 * @var string                   $form_url         The form action URL.
 */

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- Variables passed via include.

use Activitypub\OAuth\Scope;

// Use WordPress login page header.
$login_errors = new WP_Error();

if ( empty( $authorize_params['code_challenge'] ) ) {
	$login_errors->add(
		'pkce_missing',
		__( '<strong>Warning:</strong> This client does not support PKCE. The connection may be less secure.', 'activitypub' ),
		'message'
	);
}

login_header(
	/* translators: %s: Client name */
	sprintf( __( 'Authorize %s', 'activitypub' ), esc_html( $client->get_display_name() ) ),
	'',
	$login_errors
);
?>

<form method="post" action="<?php echo esc_url( $form_url ); ?>">
	<div class="activitypub-oauth-client">
		<p>
			<strong>
			<?php
			$client_link_url = $client->get_link_url();
			$client_display  = esc_html( $client->get_display_name() );
			$client_label    = $client_link_url
				? sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $client_link_url ), $client_display )
				: $client_display;

			echo wp_kses(
				sprintf(
					/* translators: %s: Client name or ID */
					__( '%s wants to access your account.', 'activitypub' ),
					$client_label
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
			);
			?>
			</strong>
		</p>
	</div>

	<div class="activitypub-oauth-user" style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin: 20px 0;">
		<?php echo get_avatar( $current_user->ID, 48 ); ?>
		<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: User display name, 2: User login */
				__( 'Logged in as %1$s (%2$s). You can revoke access at any time.', 'activitypub' ),
				'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
				esc_html( $current_user->user_login )
			),
			array( 'strong' => array() )
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
		echo wp_kses(
			sprintf(
				/* translators: %s: Redirect URI */
				__( 'You will be redirected to %s after authorization.', 'activitypub' ),
				'<code>' . esc_html( $authorize_params['redirect_uri'] ) . '</code>'
			),
			array( 'code' => array() )
		);
		?>
	</div>

	<?php wp_nonce_field( 'activitypub_oauth_authorize' ); ?>
	<?php foreach ( $authorize_params as $param_name => $param_value ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $param_name ); ?>" value="<?php echo esc_attr( $param_value ); ?>" />
	<?php endforeach; ?>

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
