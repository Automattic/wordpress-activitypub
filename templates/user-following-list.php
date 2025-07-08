<?php
/**
 * ActivityPub User Following List template.
 *
 * @package Activitypub
 */

$following_count = \Activitypub\Collection\Following::count_following( \get_current_user_id() );
// translators: The following count.
$following_template = _n( 'You currently follow %s person.', 'You currently follow %s people.', $following_count, 'activitypub' );
?>
<div class="wrap">
	<h1><?php \esc_html_e( 'Following', 'activitypub' ); ?></h1>
	<p><?php \printf( \esc_html( $following_template ), \esc_attr( $following_count ) ); ?></p>

	<?php $table = new \Activitypub\Table\Following(); ?>

	<form method="post">
		<input type="hidden" name="page" value="activitypub-following-list" />
		<?php
		wp_nonce_field( 'bulk-' . $table->_args['plural'] );
		$table->prepare_items();
		$table->search_box( 'Search', 'search' );
		$table->display();
		?>
		</form>
</div>
