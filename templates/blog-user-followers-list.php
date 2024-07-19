<?php
\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'settings'     => '',
		'welcome'      => '',
		'followers'    => 'active',
		'blog-profile' => '',
	)
);
$table = new \Activitypub\Table\Followers();
$follower_count = $table->get_user_count();
// translators: The follower count.
$followers_template = _n( 'Your blog profile currently has %s follower.', 'Your blog profile currently has %s followers.', $follower_count, 'activitypub' );
?>
<div class="wrap activitypub-followers-page">
	<p><?php \printf( \esc_html( $followers_template ), \esc_attr( $follower_count ) ); ?></p>

	<form method="get">
		<input type="hidden" name="page" value="activitypub" />
		<input type="hidden" name="tab" value="followers" />
		<?php
		$table->prepare_items();
		$table->search_box( 'Search', 'search' );
		$table->display();
		?>
		</form>
</div>
