<?php
/**
 * Admin header template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );
?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<?php if ( $args['id'] && is_wp_error( $args['actor'] ) ) : ?>
		<div class="notice notice-error"><p><strong><?php echo esc_html( $args['actor']->get_error_message() ); ?></strong></p></div>
	<?php endif; ?>
	<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">
		<input type="hidden" name="page" value="activitypub_follow" />
		<input type="text" name="id" value="<?php echo esc_attr( $args['id'] ?? '' ); ?>" />
		<input type="submit" value="<?php echo esc_attr__( 'Search', 'activitypub' ); ?>" />
	</form>
</div>
