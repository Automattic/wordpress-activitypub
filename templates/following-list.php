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
 * @global \Activitypub\WP_Admin\Table\Following $following_list_table
 */
global $following_list_table;

$_search   = \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ?? '' ) );
$_page     = \sanitize_text_field( \wp_unslash( $_REQUEST['page'] ?? '' ) );
$_tab      = \sanitize_text_field( \wp_unslash( $_REQUEST['tab'] ?? '' ) );
$_status   = \sanitize_text_field( \wp_unslash( $_REQUEST['status'] ?? '' ) );
$_resource = \sanitize_text_field( \wp_unslash( $_REQUEST['resource'] ?? '' ) );

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
					<form method="post" id="activitypub-follow-form">
						<?php wp_nonce_field( 'activitypub-follow-nonce' ); ?>
						<div class="form-field form-required">
							<label for="activitypub-profile" class="screen-reader-text"><?php echo esc_html__( 'Profile link', 'activitypub' ); ?></label>
							<input type="hidden" name="action" value="follow" />
							<input name="activitypub-profile" id="activitypub-profile" type="text" value="<?php echo esc_attr( $_resource ); ?>" size="40" aria-required="true" class="<?php echo $_resource ? 'highlight' : ''; ?>" />
						</div>
						<?php submit_button( esc_attr__( 'Follow', 'activitypub' ) ); ?>
					</form>

					<p><?php echo wp_kses_post( __( 'You can follow people from other Fediverse platforms like <strong>Mastodon</strong>, <strong>Friendica</strong>, or other <strong>WordPress</strong> sites. Try these formats:', 'activitypub' ) ); ?></p>
					<ul>
						<li><p><?php echo wp_kses_post( __( 'Username: <code>@username@example.com</code>', 'activitypub' ) ); ?></p></li>
						<li><p><?php echo wp_kses_post( __( 'Profile link: <code>https://example.com/@username</code>', 'activitypub' ) ); ?></p></li>
					</ul>

					<p><?php echo esc_html__( '(Make sure the user you&#8217;re following is part of the fediverse and supports ActivityPub)', 'activitypub' ); ?></p>
					<?php
					/**
					 * Action to add custom content after the follow form.
					 *
					 * @since 7.3.0
					 */
					do_action( 'activitypub_post_follow_form' );
					?>
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
						<p class="description"><?php esc_html_e( 'When you follow another author, a follow request is sent on your behalf. If you see &#8220;Pending&#8221;, it means your follow request hasn&#8217;t been accepted yet&#8212;so you aren&#8217;t following that author until they approve your request. This is a normal part of the ActivityPub protocol and helps ensure that authors have control over who follows them.', 'activitypub' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
