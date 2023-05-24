<div class="activitypub-settings-header">
	<div class="activitypub-settings-title-section">
		<h1><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h1>
	</div>

	<nav class="activitypub-settings-tabs-wrapper hide-if-no-js" aria-label="<?php \esc_attr_e( 'Secondary menu', 'activitypub' ); ?>">
		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=activitypub' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['welcome'] ); ?>">
			<?php \esc_html_e( 'Welcome', 'activitypub' ); ?>
		</a>

		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=activitypub&tab=settings' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['settings'] ); ?>">
			<?php \esc_html_e( 'Settings', 'activitypub' ); ?>
		</a>

		<a href="<?php echo \esc_url_raw( admin_url( 'options-general.php?page=activitypub&tab=followers' ) ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args['followers'] ); ?>">
			<?php \esc_html_e( 'Followers', 'activitypub' ); ?>
		</a>
	</nav>
</div>
<hr class="wp-header-end">
