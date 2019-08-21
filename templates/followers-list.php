<div class="wrap">
	<h1><?php esc_html_e( 'Followers (Fediverse)', 'activitypub' ); ?></h1>

	<p><?php printf( __( 'You currently have %s followers.', 'activitypub' ), esc_attr( \Activitypub\Db\Followers::count_followers( get_current_user_id() ) ) ); ?></p>

	<?php $token_table = new \Activitypub\Table\Followers_List(); ?>

	<form method="get">
		<input type="hidden" name="page" value="indieauth_user_token" />
		<?php
		$token_table->prepare_items();
		$token_table->display();
		?>
		</form>
</div>
