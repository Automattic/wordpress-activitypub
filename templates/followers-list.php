<div class="wrap">
	<h1><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h1>

	<?php $user = wp_get_current_user(); \Activitypub\get_identifier_settings( $user->ID ); ?>

	<h2><?php \esc_html_e( 'Followers', 'activitypub' ); ?></h2>

	<?php // translators: ?>
	<p><?php \printf( \esc_html__( 'You currently have %s followers.', 'activitypub' ), \esc_attr( \Activitypub\Peer\Followers::count_followers_extended( \get_current_user_id() ) ) ); ?></p>

	<?php $token_table = new \Activitypub\Table\Followers_List(); ?>

	<div class="activitypub-followers-table">
		<form method="get">
			<input type="hidden" name="page" value="indieauth_user_token" />
			<?php
			$token_table->prepare_items();
			$token_table->display();
			?>
		</form>
	</div>
</div>
