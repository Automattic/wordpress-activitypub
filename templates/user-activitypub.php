<div class="wrap">
	<h1><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h1>

	<h2><?php \esc_html_e( 'Profile identifier', 'activitypub' ); ?></h2>

	<?php $user = wp_get_current_user(); ?>
	<p><code><?php echo \esc_html( \Activitypub\get_webfinger_resource( $user->ID ) ); ?></code> or <code><?php echo \esc_url( \get_author_posts_url( $user->ID ) ); ?></code></p>
	<?php // translators: the webfinger resource ?>
	<p class="description"><?php \printf( \esc_html__( 'Try to follow "@%s" by searching for it on Mastodon, Friendica & Co., etc.', 'activitypub' ), \esc_html( \Activitypub\get_webfinger_resource( $user->ID ) ) ); ?></p>

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
