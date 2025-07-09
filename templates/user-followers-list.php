<?php
/**
 * ActivityPub User Followers List template.
 *
 * @package Activitypub
 */

$table          = new \Activitypub\Table\Followers();
$follower_count = \Activitypub\Collection\Followers::count_followers( \get_current_user_id() );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Author Followers', 'activitypub' ); ?></h1>
	<form method="get">
		<input type="hidden" name="page" value="activitypub-followers-list" />
		<?php
		$table->prepare_items();
		$table->search_box( esc_html__( 'Search Followers', 'activitypub' ), 'search' );
		$table->display();
		?>
		</form>
</div>
