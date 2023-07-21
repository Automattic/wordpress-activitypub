<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'settings' => '',
		'welcome' => '',
		'followers' => 'active',
	)
);
?>

<div class="wrap activitypub-followers-page">
	<h1><?php \esc_html_e( 'Followers', 'activitypub' ); ?></h1>

	<?php $table = new \Activitypub\Table\Followers(); ?>

	<?php // translators: The follower count. ?>
	<p><?php \printf( \esc_html__( 'You currently have %s followers.', 'activitypub' ), \esc_attr( $table->get_user_count() ) ); ?></p>

	<form method="get">
		<input type="hidden" name="page" value="activitypub" />
		<input type="hidden" name="tab" value="followers" />
		<?php
		$table->prepare_items();
		$table->display();
		?>
		<?php wp_nonce_field( 'activitypub-followers-list', '_apnonce' ); ?>
		</form>
</div>
