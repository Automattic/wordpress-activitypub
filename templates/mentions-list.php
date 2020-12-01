<style type="text/css">
	.wp-list-table .column-actor { width: 25%; }
	.wp-list-table .column-comment { width: 65%; }
	.wp-list-table .column-date { width: 10%; }
</style>
<div class="wrap">
	<h1><?php \esc_html_e( 'Mentions (Fediverse)', 'activitypub' ); ?></h1>

	<p><?php \printf( \__( 'You currently have %s mentions.', 'activitypub' ), \esc_attr( \Activitypub\Peer\Mentions::count_mentions() ) ); ?></p>
  <?php
  //$comments = \Activitypub\Peer\mentions::get_views();
  //echo '<details><summary>DEBUG</summary><pre>'; print_r($comments); echo '</pre></details>';
  ?>
	<?php $mention_table = new \Activitypub\Table\Mentions_List(); ?>

	<form method="get">
		<input type="hidden" name="page" value="activitypub-mentions-list" />
		<?php
			$mention_table->prepare_items();
			$mention_table->display();
		?>
		</form>
</div>
