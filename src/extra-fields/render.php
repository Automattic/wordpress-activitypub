<?php
/**
 * Server-side rendering for the Extra Fields block.
 *
 * @package Activitypub
 */

use Activitypub\Blocks;
use Activitypub\Collection\Extra_Fields;

use function Activitypub\is_activitypub_request;

if ( is_activitypub_request() || is_feed() ) {
	return '';
}

/**
 * Render callback for the Extra Fields block.
 *
 * @var array $attributes Block attributes.
 */
$attributes = wp_parse_args( $attributes );
$user_id    = Blocks::get_user_id( $attributes['selectedUser'] ?? 'blog' );

// If user ID couldn't be determined, return empty.
if ( null === $user_id ) {
	return '';
}

// Get extra fields for this user.
$fields = Extra_Fields::get_actor_fields( $user_id );

// Apply max fields limit if set.
$max_fields = $attributes['maxFields'] ?? 0;
if ( $max_fields > 0 && count( $fields ) > $max_fields ) {
	$fields = array_slice( $fields, 0, $max_fields );
}

// Return empty on frontend if no fields (hide block).
if ( empty( $fields ) ) {
	return '';
}

// Get block wrapper attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'activitypub-extra-fields-block-wrapper',
	)
);

// Extract background color for cards style.
$background_color = '';
$card_style       = '';

// Check if this is the cards style by looking at className attribute or wrapper classes.
$is_cards_style = ( isset( $attributes['className'] ) && str_contains( $attributes['className'], 'is-style-cards' ) )
	|| str_contains( $wrapper_attributes, 'is-style-cards' );

if ( $is_cards_style ) {
	// Check for background color in various formats.
	if ( isset( $attributes['backgroundColor'] ) ) {
		$background_color = sprintf( 'var(--wp--preset--color--%s)', $attributes['backgroundColor'] );
	} elseif ( isset( $attributes['style']['color']['background'] ) ) {
		$background_color = $attributes['style']['color']['background'];
	}

	if ( $background_color ) {
		$card_style = sprintf( ' style="background-color: %s;"', esc_attr( $background_color ) );
	}
}
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<dl class="activitypub-extra-fields">
		<?php foreach ( $fields as $field ) : ?>
			<div class="activitypub-extra-field"<?php echo $card_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<dt><?php echo esc_html( $field->post_title ); ?></dt>
				<dd><?php echo Extra_Fields::get_formatted_content( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>
