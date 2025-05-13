<?php
/**
 * Reply embed template.
 *
 * @package Activitypub
 */

/* @var array $args Template arguments. */
$args = wp_parse_args(
	$args,
	array(
		'audio'       => null,
		'author_name' => '',
		'author_url'  => '',
		'avatar_url'  => '',
		'boosts'      => null,
		'content'     => '',
		'favorites'   => null,
		'image'       => '',
		'published'   => '',
		'title'       => '',
		'url'         => '',
		'video'       => null,
		'webfinger'   => '',
	)
);

\wp_enqueue_style( 'activitypub-embed', ACTIVITYPUB_PLUGIN_URL . 'assets/css/activitypub-embed.css', array(), ACTIVITYPUB_PLUGIN_VERSION );
?>

<div class="activitypub-embed u-in-reply-to h-cite">
	<div class="activitypub-embed-header p-author h-card">
		<?php if ( $args['avatar_url'] ) : ?>
			<img class="u-photo" src="<?php echo \esc_url( $args['avatar_url'] ); ?>" alt="" />
		<?php endif; ?>
		<div class="activitypub-embed-header-text">
			<h2 class="p-name"><?php echo \esc_html( $args['author_name'] ); ?></h2>
			<?php if ( $args['author_url'] ) : ?>
				<a href="<?php echo \esc_url( $args['author_url'] ); ?>" class="ap-account u-url"><?php echo \esc_html( $args['webfinger'] ?? $args['author_url'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="activitypub-embed-content">
		<?php if ( $args['title'] ) : ?>
			<h3 class="ap-title p-name"><?php echo \esc_html( $args['title'] ); ?></h3>
		<?php endif; ?>

		<?php if ( $args['content'] ) : ?>
			<div class="ap-subtitle p-summary e-content"><?php echo \wp_kses_post( $args['content'] ); ?></div>
		<?php endif; ?>

		<?php if ( $args['images'] ) : ?>
			<div class="ap-preview <?php echo \esc_attr( 'layout-' . count( $args['images'] ) ); ?>">
				<?php foreach ( $args['images'] as $image ) : ?>
				<img class="u-photo u-featured" src="<?php echo \esc_url( $image['url'] ); ?>" alt="<?php echo \esc_attr( $image['name'] ?? '' ); ?>" />
				<?php endforeach; ?>
			</div>
		<?php elseif ( $args['video'] ) : ?>
			<div class="ap-preview layout-1">
				<video controls class="u-photo u-featured" src="<?php echo \esc_url( $args['video']['url'] ); ?>" title="<?php echo \esc_attr( $args['video']['name'] ?? '' ); ?>"></video>
			</div>
		<?php elseif ( $args['audio'] ) : ?>
			<div class="ap-preview layout-1">
				<audio controls class="u-photo u-featured" src="<?php echo \esc_url( $args['audio']['url'] ); ?>" title="<?php echo \esc_attr( $args['audio']['name'] ?? '' ); ?>"></audio>
			</div>
		<?php endif; ?>
	</div>

	<div class="activitypub-embed-meta">
		<?php if ( $args['published'] ) : ?>
			<a href="<?php echo \esc_url( $args['url'] ); ?>" class="ap-stat ap-date dt-published u-in-reply-to"><?php echo \esc_html( $args['published'] ); ?></a>
		<?php endif; ?>

		<?php if ( null !== $args['boosts'] ) : ?>
		<span class="ap-stat">
			<?php
			/* translators: %s: number of boosts */
			printf( \esc_html__( '%s boosts', 'activitypub' ), '<strong>' . \esc_html( \number_format_i18n( $args['boosts'] ) ) . '</strong>' );
			?>
		</span>
		<?php endif; ?>

		<?php if ( null !== $args['favorites'] ) : ?>
		<span class="ap-stat">
			<?php
			/* translators: %s: number of favorites */
			printf( \esc_html__( '%s favorites', 'activitypub' ), '<strong>' . \esc_html( \number_format_i18n( $args['favorites'] ) ) . '</strong>' );
			?>
		</span>
		<?php endif; ?>
	</div>
</div>
