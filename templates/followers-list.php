<?php
if ( get_current_screen()->id === 'settings_page_activitypub' ) {
	\load_template(
		\dirname( __FILE__ ) . '/admin-header.php',
		true,
		array(
			'settings' => '',
			'welcome' => '',
			'followers' => 'active',
		)
	);
}
?>

<div class="wrap">
	<h1><?php \esc_html_e( 'Followers', 'activitypub' ); ?></h1>
	<?php Activitypub\Migration::maybe_migrate(); ?>
	<?php // translators: ?>
	<p><?php \printf( \esc_html__( 'You currently have %s followers.', 'activitypub' ), \esc_attr( \Activitypub\Collection\Followers::count_followers( \get_current_user_id() ) ) ); ?></p>

	<?php $table = new \Activitypub\Table\Followers(); ?>

	<form method="get">
		<input type="hidden" name="page" value="activitypub-followers-list" />
		<?php
		$table->prepare_items();
		$table->display();
		?>
		<?php wp_nonce_field( 'activitypub-followers-list', '_apnonce' ); ?>
		</form>
</div>
