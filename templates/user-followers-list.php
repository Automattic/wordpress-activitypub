<?php

$follower_count = \Activitypub\Collection\Followers::count_followers( \get_current_user_id() );
// translators: The follower count.
$followers_template = _n( 'Your author profile currently has %s follower.', 'Your author profile currently has %s followers.', $follower_count, 'activitypub' );
?>
<div class="wrap">
	<h1><?php \esc_html_e( 'Author Followers', 'activitypub' ); ?></h1>
	<p><?php \printf( \esc_html( $followers_template ), \esc_attr( $follower_count ) ); ?></p>

	<?php $table = new \Activitypub\Table\Followers(); ?>

	<form method="get">
		<input type="hidden" name="page" value="activitypub-followers-list" />
		<?php
		$table->prepare_items();
		$table->search_box( 'Search', 'search' );
		$table->display();
		?>
		</form>
</div>
