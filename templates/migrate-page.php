<?php 

$delete_nonce = wp_create_nonce( 'activitypub_manual_delete_url' );
$announce_nonce = wp_create_nonce( 'activitypub_manual_announce_url' );

if ( isset( $_REQUEST['post_url'] ) && $_REQUEST['page'] == "activitypub_tools" ) {
	$post_url = $_REQUEST['post_url'];
	$post_author = get_current_user_id();
	if ( isset( $_REQUEST['submit_delete'] ) && wp_verify_nonce( $delete_nonce, 'activitypub_manual_delete_url' ) ) {
		\Activitypub\Migrate\Posts::delete_url( rawurldecode( $_REQUEST['post_url'] ),  $post_author );
	}
	if ( isset( $_REQUEST['submit_announce'] ) && wp_verify_nonce( $announce_nonce, 'activitypub_manual_announce_url' ) ) {
		\Activitypub\Migrate\Posts::announce_url( rawurldecode( $_REQUEST['post_url'] ) );
	}
}

?>
<div class="wrap">
	<h1><?php \esc_html_e( 'Migrate posts (Fediverse)', 'activitypub' ); ?></h1>

	<p><?php \printf( \__( 'You currently have %s posts to migrate.', 'activitypub' ), \esc_attr( \Activitypub\Migrate\Posts::count_posts() ) ); ?></p>

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
				<input type="hidden" name="_wpnonce" value="<?php echo $delete_nonce ?>">
				<input type="text" class="post_url" id="url" name="post_url" size="40" value="">
				<input class="button button-primary" type="submit" value="<?php _e( 'Delete Federated URL', 'activitypub' ); ?>" name="submit_delete" id="submit_delete"><br></p>
		</div>
	</form>
	<hr class="separator">
	<form method="POST" id="announce">
		<div>
			<p><?php _e( 'Manually Announce a Post', 'activitypub' ); ?></p>
				<input type="hidden" name="page" value="activitypub_tools">
				<input type="hidden" name="_wpnonce" value="<?php echo $announce_nonce ?>">
				<input type="text" class="post_url" id="url" name="post_url" size="40" value="">
				<input class="button button-primary" type="submit" value="<?php _e( 'Announce a Post URL', 'activitypub' ); ?>" name="submit_announce" id="submit_announce"><br></p>
		</div>
	</form>
</div>
