<?php
/**
 * Admin header template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );
?>
<div class="activitypub-settings-header">
	<div class="activitypub-settings-title-section">
		<h1><?php \esc_html_e( 'ActivityPub', 'activitypub' ); ?></h1>
	</div>

	<div class="activitypub-settings-tabs-scroller">
		<nav class="activitypub-settings-tabs-wrapper" aria-label="<?php \esc_attr_e( 'Secondary menu', 'activitypub' ); ?>">
			<?php
			foreach ( $args['tabs'] as $slug => $label ) :
				$url = add_query_arg(
					array( 'tab' => 'welcome' !== $slug ? $slug : false ),
					\admin_url( 'options-general.php?page=activitypub' )
				);
				?>

				<a href="<?php echo \esc_url( $url ); ?>" class="activitypub-settings-tab <?php echo \esc_attr( $args[ $slug ] ); ?>">
					<?php echo \esc_html( $label ); ?>
				</a>

				<?php
			endforeach;
			?>
		</nav>
	</div>
</div>
