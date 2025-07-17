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
$_status = \sanitize_text_field( \wp_unslash( $_REQUEST['status'] ?? 'accepted' ) );

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

	<hr class="wp-header-end">

	<?php $following_list_table->views(); ?>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
		<input type="hidden" name="status" value="<?php echo esc_attr( $_status ); ?>" />
		<?php $following_list_table->search_box( esc_html__( 'Search Followings', 'activitypub' ), 'search' ); ?>
	</form>

	<form method="post">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
		<?php $following_list_table->display(); ?>
	</form>
	<div class="form-wrap edit-term-notes">
		<strong><?php esc_html_e( 'About Followings', 'activitypub' ); ?></strong>
		<p class="description"><?php esc_html_e( 'When you follow another author, a follow request is sent on your behalf. If you see &#8220;Pending,&#8221; it means your follow request hasn&#8217;t been accepted yet—so you aren&#8217;t following that author until they approve your request. This is a normal part of the ActivityPub protocol and helps ensure that authors have control over who follows them.', 'activitypub' ); ?></p>
	</div>
</div>
