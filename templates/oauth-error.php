<?php
/**
 * OAuth Authorization Error Template.
 *
 * Renders a styled error page using WordPress login page chrome,
 * consistent with the consent form in oauth-authorize.php.
 *
 * @package Activitypub
 *
 * Variables available (passed via include from class-server.php):
 * @var string $error_message The error message to display.
 */

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- Variables passed via include.

$login_errors = new WP_Error(
	'oauth_error',
	esc_html( $error_message )
);

login_header(
	esc_html__( 'Authorization Error', 'activitypub' ),
	'',
	$login_errors
);
?>

<p style="margin-top: 20px; text-align: center;">
	<a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Go to homepage', 'activitypub' ); ?></a>
</p>

<?php
login_footer();
