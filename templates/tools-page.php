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
<style>
	#the-list tr.warning {
		background-color: #fcf9e8;
 		box-shadow: inset 0 -1px 0 rgba(0,0,0,.1);
	}
	#the-list tr.warning th.check-column {
		border-left: 4px solid #d63638;
	}
</style>
<div class="wrap">
	<h1><?php \esc_html_e( 'Manage ActivityPub posts (Fediverse)', 'activitypub' ); ?></h1>

	<?php if ( \Activitypub\Tools\Posts::count_posts_to_migrate() > 0 ): ?>

		<div class="notice notice-warning">
			<p><?php \printf(
				\__( 'The following table lists ActivityPub posts which have been marked as backwards compatible, and ready for migration. <br>
				Migration here means updating the Activity ID by federating a Delete activity removing the original post from the fediverse, and then Sharing the new post ID with an Announce activity. <br>
				Posts with comments should be treated with care, existing interactions in the fediverse will be lost.', 'activitypub' ),
				\esc_attr( \Activitypub\Tools\Posts::count_posts_to_migrate() )
			); ?></p>
		</div>

		<p><?php \printf(
			\__( 'You currently have %s posts marked for review.', 'activitypub' ),
			\esc_attr( \Activitypub\Tools\Posts::count_posts_to_migrate() )
		); ?></p>

		<?php $token_table = new \Activitypub\Table\Migrate_List(); ?>
		<form method="POST" id="table">
			<?php
				$token_table->views();
				$token_table->prepare_items();
				$token_table->display();
			?>
		</form>
	<?php endif; ?>
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
