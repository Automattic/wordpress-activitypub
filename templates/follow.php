<?php
/**
 * Admin header template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args( $args ?? array() );

$actor = $args['actor'];
?>

<div class="activitypub-settings activitypub-settings-page hide-if-no-js">
	<div class="activitypub-follow-me-block-wrapper is-style-profile wp-block-activitypub-follow-me">
		<div class="activitypub-profile p-author h-card">
			<div class="activitypub-profile__header" style="background-image: url('<?php echo esc_url( $actor->get_image()['url'] ?? '' ); ?>');">
				<div class="activitypub-profile__follow-indicator">
					<?php echo esc_html__( 'Follows you', 'activitypub' ); ?>
				</div>
			</div>

			<div class="activitypub-profile__body">
				<img
					class="activitypub-profile__avatar u-photo"
					src="<?php echo esc_url( $actor->get_icon()['url'] ?? \get_avatar_url( '' ) ); ?>"
					alt="<?php echo esc_attr( $actor->get_name() ?? $actor->get_preferred_username() ); ?>"
				/>

				<div class="activitypub-profile__content">
					<div class="activitypub-profile__info">
						<div class="activitypub-profile__name p-name"><?php echo esc_html( $actor->get_name() ?? $actor->get_preferred_username() ); ?></div>
						<?php /** Using `data-wp-text` to avoid @see enrich_content_data() turning it into a mention. */ ?>
						<div class="activitypub-profile__handle p-nickname p-x-webfinger" data-wp-text="context.webfinger"></div>
					</div>

					<div class="button">
						<a aria-label="Follow me on the Fediverse">Follow</a>
					</div>

					<?php if ( $actor->get_summary() ) : ?>
						<div class="activitypub-profile__bio p-note">
							<?php echo wp_kses_post( $actor->get_summary() ); ?>
						</div>
					<?php endif; ?>

					<?php
					$attachments = $actor->get_attachment();
					if ( ! empty( $attachments ) ) :
						// Filter for PropertyValue attachments (extra fields).
						$extra_fields = array_filter(
							$attachments,
							function ( $attachment ) {
								return isset( $attachment['type'] ) && 'PropertyValue' === $attachment['type'];
							}
						);
						if ( ! empty( $extra_fields ) ) :
							?>
						<div class="activitypub-profile__extra-fields">
							<h4><?php echo esc_html__( 'Additional Information', 'activitypub' ); ?></h4>
							<table class="activitypub-extra-fields-table">
								<tbody>
									<?php foreach ( $extra_fields as $field ) : ?>
										<tr>
											<td class="field-name">
												<strong><?php echo esc_html( $field['name'] ); ?></strong>
											</td>
											<td class="field-value">
												<?php echo wp_kses_post( $field['value'] ); ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
