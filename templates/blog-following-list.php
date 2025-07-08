<?php
/**
 * ActivityPub Blog Following List template.
 *
 * @package Activitypub
 */

$table           = new \Activitypub\Table\Following();
$following_count = $table->get_user_count();
// translators: The following count.
$following_template = _n( 'You currently follow %s person.', 'You currently follow %s people.', $following_count, 'activitypub' );
?>
<div class="wrap activitypub-followers-page">
	<p><?php \printf( \esc_html( $following_template ), \esc_attr( $following_count ) ); ?></p>

	<form method="get">
		<input type="hidden" name="page" value="activitypub" />
		<input type="hidden" name="tab" value="following" />
		<?php
		$table->prepare_items();
		$table->search_box( __( 'Search', 'activitypub' ), 'search' );
		$table->display();
		?>
		</form>
</div>
