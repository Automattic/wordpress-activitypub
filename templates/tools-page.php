<?php 

$action_nonce = wp_create_nonce( 'activitypub_action' );
if ( isset( $_REQUEST['post_url'] ) && $_REQUEST['page'] == "activitypub_tools" ) {
	$post_url = $_REQUEST['post_url'];
	$user_id = get_current_user_id();
	if ( isset( $_REQUEST['submit_delete'] ) && wp_verify_nonce( $action_nonce, 'activitypub_action' ) ) {
		\Activitypub\Migrate\Posts::delete_url( rawurldecode( $_REQUEST['post_url'] ),  $user_id );
	}
}
?>
<div class="wrap">
	<h1><?php \esc_html_e( 'Migrate posts (Fediverse)', 'activitypub' ); ?></h1>

	<p><?php \printf(
		\__( 'You currently have %s posts to migrate.', 'activitypub' ),
		\esc_attr( \Activitypub\Tools\Posts::count_posts_to_migrate() )
	); ?></p>

	<p><?php \printf(
		\__( 'Posts with ActivityPub Comments, should be treated with care, existing interactions in the fediverse but not on site will be lost.', 'activitypub' ),
		\esc_attr( \Activitypub\Tools\Posts::count_posts_to_migrate() )
	); ?></p>

	<?php $token_table = new \Activitypub\Table\Migrate_List(); ?>

	<form method="POST" id="table">
		<?php
		$token_table->prepare_items();
		$token_table->display();
		?>
	</form>
	<hr class="separator">
	<form method="POST" id="delete">
		<div>
			<p><?php _e( 'Manually Delete a URL', 'activitypub' ); ?></p>
				<input type="hidden" name="page" value="activitypub_tools">
				<input type="hidden" name="_wpnonce" value="<?php echo $action_nonce ?>">
				<input type="text" class="post_url" id="url" name="post_url" size="40" value="">
				<input class="button button-primary" type="submit" value="<?php _e( 'Delete Federated URL', 'activitypub' ); ?>" name="submit_delete" id="submit_delete"><br></p>
		</div>
	</form>
</div>
