<?php
/**
 * Admin header template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args(
	$args,
	array(
		'welcome'      => '',
		'settings'     => '',
		'blog-profile' => '',
		'followers'    => '',
	)
);
?>
<div class="activitypub-settings-header">
	<div class="activitypub-settings-title-section">
		<h1><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h1>
	</div>

	<nav class="activitypub-settings-tabs-wrapper" aria-label="<?php \esc_attr_e( 'Secondary menu', 'activitypub' ); ?>">
		<a href="<?php echo \esc_url( admin_url( 'options-general.php?page=activitypub' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['welcome'] ); ?>">
			<?php \esc_html_e( 'Welcome', 'activitypub' ); ?>
		</a>

		<a href="<?php echo \esc_url( admin_url( 'options-general.php?page=activitypub&tab=settings' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['settings'] ); ?>">
			<?php \esc_html_e( 'Settings', 'activitypub' ); ?>
		</a>

		<?php if ( ! \Activitypub\is_user_disabled( \Activitypub\Collection\Actors::BLOG_USER_ID ) ) : ?>

		<a href="<?php echo \esc_url( admin_url( 'options-general.php?page=activitypub&tab=blog-profile' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['blog-profile'] ); ?>">
			<?php \esc_html_e( 'Blog-Profile', 'activitypub' ); ?>
		</a>

		<a href="<?php echo \esc_url( admin_url( 'options-general.php?page=activitypub&tab=followers' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['followers'] ); ?>">
			<?php \esc_html_e( 'Followers', 'activitypub' ); ?>
		</a>

		<?php endif; ?>
	</nav>
</div>
<hr class="wp-header-end">
