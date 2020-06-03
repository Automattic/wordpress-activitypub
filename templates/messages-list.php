<style type="text/css">
	.wp-list-table .column-actor { width: 25%; }
	.wp-list-table .column-comment { width: 65%; }
	.wp-list-table .column-date { width: 10%; }
</style>
<div class="wrap">
	<h1><?php \esc_html_e( 'Messages (Fediverse)', 'activitypub' ); ?></h1>

	<p><?php \printf( \__( 'You currently have %s messages.', 'activitypub' ), \esc_attr( \Activitypub\Peer\Messages::count_messages() ) ); ?></p>
  
	<?php $message_table = new \Activitypub\Table\Messages_List(); ?>

	<form method="get">
		<input type="hidden" name="page" value="activitypub-messages-list" />
		<?php
			$message_table->prepare_items();
			$message_table->display();
		?>
		</form>
</div>
