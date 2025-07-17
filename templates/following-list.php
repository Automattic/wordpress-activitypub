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
					<form method="post">
						<?php wp_nonce_field( 'activitypub-follow-nonce' ); ?>
						<div class="form-field form-required">
							<label for="activitypub-profile"><?php echo esc_html__( 'Profile', 'activitypub' ); ?></label>
							<input type="hidden" name="action" value="follow" />
							<input name="activitypub-profile" id="activitypub-profile" type="text" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['resource'] ?? '' ) ) ); ?>" size="40" aria-required="true" aria-describedby="activitypub-profile-description" placeholder="@username@domain.tld or https://domain.tld/@username">
							<p id="activitypub-profile-description">
								<?php echo esc_html__( 'Paste the WebFinger address or profile URL into the field above and click "Follow".', 'activitypub' ); ?>
							</p>
						</div>
						<?php submit_button( esc_attr__( 'Follow', 'activitypub' ) ); ?>
					</form>
					<p><?php echo esc_html__( 'There are two common ways to look up someone&rsquo;s profile on the Fediverse (e.g., Mastodon, Pixelfed, PeerTube, etc.):', 'activitypub' ); ?></p>

					<ol>
						<li><?php echo esc_html__( 'WebFinger address (like an email):', 'activitypub' ); ?>
							<ul>
								<li><code>@username@domain.tld</code></li>
							</ul>
						</li>
						<li><?php echo esc_html__( 'Profile URL:', 'activitypub' ); ?>
							<ul>
								<li><code>https://domain.tld/@username</code></li>
							</ul>
						</li>
					</ol>
				</div>
			</div>
		</div>
		<div id="col-right">
			<div class="col-wrap">
				<?php $following_list_table->views(); ?>

				<form method="post">
					<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
					<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
					<?php wp_nonce_field( 'bulk-' . $following_list_table->_args['plural'] ); ?>
					<?php $following_list_table->display(); ?>
				</form>
			</div>
		</div>
	</div>
</div>
