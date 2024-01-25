<?php

use Activitypub\Collection\Users;

\load_template(
	__DIR__ . '/admin-header.php',
	true,
	array(
		'settings' => '',
		'welcome' => '',
		'followers' => 'active',
	)
);

// Draw the follow table for the blog user if it is activated.
if ( ! \Activitypub\is_user_disabled( \Activitypub\Collection\Users::BLOG_USER_ID ) ) :
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
	
<?php endif;

// Draw the the follow table for the application user with reject and accept options.
$table = new \Activitypub\Table\Follow_Requests( Users::APPLICATION_USER_ID );
$table->prepare_items();
$follow_requests_count = $table->follow_requests_count;
// translators: The follower count.
$followers_template = _n( 'Your WordPress site currently has %s follow request.', 'Your WordPress site currently has %s follow requests.', $follow_requests_count, 'activitypub' );
?>
<div class="wrap activitypub-followers-page">
	<p><?php \printf( \esc_html( $followers_template ), \esc_attr( $follow_requests_count ) ); ?></p>

	<form method="get">
		<input type="hidden" name="page" value="activitypub" />
		<input type="hidden" name="tab" value="followers" />
		<?php
		$table->search_box( 'Search', 'search' );
		$table->display();
		
		?>
		</form>
</div>
