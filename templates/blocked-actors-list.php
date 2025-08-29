<?php
/**
 * ActivityPub Blocked Actors List template.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

/**
 * Blocked actors list table.
 *
 * @global \Activitypub\WP_Admin\Table\Blocked_Actors $blocked_actors_list_table
 */
global $blocked_actors_list_table;

$_search   = \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ?? '' ) );
$_page     = \sanitize_text_field( \wp_unslash( $_REQUEST['page'] ?? '' ) );
$_tab      = \sanitize_text_field( \wp_unslash( $_REQUEST['tab'] ?? '' ) );
$_resource = \sanitize_text_field( \wp_unslash( $_REQUEST['resource'] ?? '' ) );

$blocked_actors_list_table->prepare_items();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Blocked Actors', 'activitypub' ); ?></h1>
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
		<?php $blocked_actors_list_table->search_box( esc_html__( 'Search Blocked Actors', 'activitypub' ), 'search' ); ?>
	</form>

	<hr class="wp-header-end">

	<div id="col-container" class="wp-clearfix">
		<div id="col-left">
			<div class="col-wrap">
				<h2><?php echo esc_html__( 'Block Actor', 'activitypub' ); ?></h2>
				<div class="form-wrap">
					<form method="post" id="activitypub-block-form">
						<?php wp_nonce_field( 'activitypub-block-nonce' ); ?>
						<div class="form-field form-required">
							<label for="activitypub-profile" class="screen-reader-text"><?php echo esc_html__( 'Profile link', 'activitypub' ); ?></label>
							<input type="hidden" name="action" value="block" />
							<input name="activitypub-profile" id="activitypub-profile" type="text" value="<?php echo esc_attr( $_resource ); ?>" size="40" aria-required="true" class="<?php echo $_resource ? 'highlight' : ''; ?>" />
						</div>
						<?php submit_button( esc_attr__( 'Block', 'activitypub' ) ); ?>
					</form>

					<p><?php esc_html_e( 'Try these formats:', 'activitypub' ); ?></p>
					<ul>
						<li><p><?php echo wp_kses_post( __( 'Username: <code>@username@example.com</code>', 'activitypub' ) ); ?></p></li>
						<li><p><?php echo wp_kses_post( __( 'Profile link: <code>https://example.com/@username</code>', 'activitypub' ) ); ?></p></li>
					</ul>
				</div>
			</div>
		</div>
		<div id="col-right">
			<div class="col-wrap">
				<div class="form-wrap">
					<form method="post">
						<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
						<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
						<?php $blocked_actors_list_table->display(); ?>
					</form>
					<div class="edit-term-notes">
						<strong><?php esc_html_e( 'About Blocked Actors', 'activitypub' ); ?></strong>
						<p class="description"><?php esc_html_e( 'When you block an actor, they will not be able to interact with your content through ActivityPub. This includes replies, likes, shares, and follows.', 'activitypub' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
