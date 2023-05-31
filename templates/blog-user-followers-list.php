<?php
\load_template(
	\dirname( __FILE__ ) . '/admin-header.php',
	true,
	array(
		'settings' => '',
		'welcome' => '',
		'followers' => 'active',
	)
);
?>

<div class="wrap">
	<h1><?php \esc_html_e( 'Followers', 'activitypub' ); ?></h1>

	<?php $followers_table = new \Activitypub\Table\Followers(); ?>

	<?php // translators: ?>
	<p><?php \printf( \esc_html__( 'You currently have %s followers.', 'activitypub' ), \esc_attr( $followers_table->get_user_count() ) ); ?></p>

	<form method="get">
		<input type="hidden" name="page" value="activitypub" />
		<input type="hidden" name="tab" value="followers" />
		<?php
		$followers_table->prepare_items();
		$followers_table->display();
		?>
		<?php wp_nonce_field( 'activitypub-followers-list', '_apnonce' ); ?>
		</form>
</div>
