<?php
/**
 * ActivityPub Followers List template.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

$table   = new \Activitypub\Table\Followers();
$_search = \sanitize_text_field( \wp_unslash( $_REQUEST['s'] ?? '' ) );
$_page   = \sanitize_text_field( \wp_unslash( $_REQUEST['page'] ?? '' ) );
$_tab    = \sanitize_text_field( \wp_unslash( $_REQUEST['tab'] ?? '' ) );

$table->prepare_items();
?>
<div class="wrap">
	<?php if ( ! $_tab ) : ?>
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Followers', 'activitypub' ); ?></h1>
	<?php endif; ?>

	<?php
	if ( strlen( $_search ) ) :
		echo '<span class="subtitle">';
		/* translators: %s: Search query. */
		printf( esc_html__( 'Search results for: %s', 'activitypub' ), '<strong>' . esc_html( $_search ) . '</strong>' );
		echo '</span>';
	endif;
	?>

	<hr class="wp-header-end">

	<?php $table->views(); ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
		<?php $table->search_box( esc_html__( 'Search Followers', 'activitypub' ), 'search' ); ?>
	</form>

	<form method="post">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_page ); ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $_tab ); ?>" />
		<?php wp_nonce_field( 'bulk-' . $table->_args['plural'] ); ?>
		<?php $table->display(); ?>
	</form>
</div>
