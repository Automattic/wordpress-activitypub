<?php
/**
 * ActivityPub Following List template.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Following list table.
 *
 * @global Activitypub\Table\Following $following_list_table
 */
global $following_list_table;

$_search = \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ?? '' ) );
$_page   = \sanitize_text_field( \wp_unslash( $_REQUEST['page'] ?? '' ) );
$_tab    = \sanitize_text_field( \wp_unslash( $_REQUEST['tab'] ?? '' ) );
$_status = \sanitize_text_field( \wp_unslash( $_REQUEST['status'] ?? '' ) );

$following_list_table->prepare_items();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Followings', 'activitypub' ); ?></h1>
	<?php
	if ( strlen( $_search ) ) :
		echo '<span class="subtitle">';
		/* translators: %s: Search query. */
		printf( esc_html__( 'Search results for: %s', 'activitypub' ), '<strong>' . esc_html( $_search ) . '</strong>' );
		echo '</span>';
	endif;
	?>

	<form method="get" class="search-form wp-clearfix">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
		<input type="hidden" name="status" value="<?php echo esc_attr( $_status ); ?>" />
		<?php $following_list_table->search_box( esc_html__( 'Search Followings', 'activitypub' ), 'search' ); ?>
	</form>

	<hr class="wp-header-end">

	<div id="col-container" class="wp-clearfix">
		<div id="col-left">
			<div class="col-wrap">
				<h2><?php echo esc_html__( 'Follow', 'activitypub' ); ?></h2>
				<div class="form-wrap">
					<p><?php echo wp_kses_post( __( 'You can follow people from other federated platforms (like <strong>Mastodon</strong>, <strong>Friendica</strong>, or another <strong>WordPress</strong> blog) using their profile link or WebFinger address.', 'activitypub' ) ); ?></p>

					<p><?php echo esc_html__( 'Just paste one of the following into the field below:', 'activitypub' ); ?></p>

					<ul class="edit-term-notes">
						<li><?php echo esc_html__( 'A WebFinger address — e.g. @username@example.com', 'activitypub' ); ?></li>
						<li><?php echo esc_html__( 'A full profile URL — e.g. https://example.com/@username', 'activitypub' ); ?></li>
					</ul>

					<p><?php echo esc_html__( 'Click Follow, and if the user accepts (or their server allows automatic follows), their posts will start appearing in your followers list.', 'activitypub' ); ?></p>

					<p><?php echo esc_html__( 'Make sure the user you&rsquo;re following is part of the fediverse and supports ActivityPub.', 'activitypub' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'activitypub-follow-nonce' ); ?>
						<div class="form-field form-required">
							<label for="activitypub-profile"><?php echo esc_html__( 'Profile', 'activitypub' ); ?></label>
							<input type="hidden" name="action" value="follow" />
							<input name="activitypub-profile" id="activitypub-profile" type="text" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['resource'] ?? '' ) ) ); ?>" size="40" aria-required="true" aria-describedby="activitypub-profile-description" placeholder="@username@example.com or https://example.com/@username">
							<p id="activitypub-profile-description">
								<?php echo esc_html__( 'Paste the WebFinger address or profile URL into the field above and click "Follow".', 'activitypub' ); ?>
							</p>
						</div>
						<?php submit_button( esc_attr__( 'Follow', 'activitypub' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<div id="col-right">
			<div class="col-wrap">
				<?php $following_list_table->views(); ?>
				<div class="form-wrap">
					<form method="post">
						<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
						<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
						<?php $following_list_table->display(); ?>
					</form>
					<div class="edit-term-notes">
						<strong><?php esc_html_e( 'About Followings', 'activitypub' ); ?></strong>
						<p class="description"><?php esc_html_e( 'When you follow another author, a follow request is sent on your behalf. If you see &#8220;Pending,&#8221; it means your follow request hasn&#8217;t been accepted yet—so you aren&#8217;t following that author until they approve your request. This is a normal part of the ActivityPub protocol and helps ensure that authors have control over who follows them.', 'activitypub' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

