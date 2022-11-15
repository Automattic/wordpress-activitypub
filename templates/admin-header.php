<div class="activitypub-settings-header">
	<div class="activitypub-settings-title-section">
		<h1>ActivityPub</h1>
	</div>

	<nav class="activitypub-settings-tabs-wrapper hide-if-no-js" aria-label="<?php esc_attr_e( 'Secondary menu', 'activitypub' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub' ) ); ?>" class="activitypub-settings-tab <?php echo $args['welcome']; ?>">
			<?php _e( 'Welcome', 'activitypub' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=activitypub-settings' ) ); ?>" class="activitypub-settings-tab <?php echo $args['settings']; ?>">
			<?php _e( 'Settings', 'activitypub' ); ?>
		</a>
	</nav>
</div>
<hr class="wp-header-end">
