<?php
/**
 * ActivityPub Post Preview template.
 *
 * @package Activitypub
 */

$post        = \get_post(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$transformer = \Activitypub\Transformer\Factory::get_transformer( $post );

if ( \is_wp_error( $transformer ) ) {
	\wp_die(
		esc_html( $transformer->get_error_message() ),
		404
	);
}

\wp_register_style( 'activitypub-post-preview', ACTIVITYPUB_PLUGIN_URL . '/assets/css/activitypub-post-preview.css', array(), ACTIVITYPUB_PLUGIN_VERSION );

$object = $transformer->to_object();
$user   = $transformer->get_actor_object();

$has_images = false;
$video      = false;
$audio      = false;
$layout     = 'layout-1';

foreach ( $object->get_attachment() as $attachment ) {
	if ( isset( $attachment['mediaType'] ) ) {
		$media_type = strtok( $attachment['mediaType'], '/' );

		switch ( $media_type ) {
			case 'image':
				$has_images = true;
				$layout     = 'layout-' . count( wp_list_filter( $object->get_attachment(), array( 'type' => 'Image' ) ) );
				break 2;
			case 'video':
				$video = $attachment;
				break 2;
			case 'audio':
				$audio = $attachment;
				break 2;
		}
	}
}

?>
<DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( $object->get_name() ); ?></title>
		<?php wp_print_styles( 'activitypub-post-preview' ); ?>
	</head>
	<body>
		<div class="columns">
			<aside class="sidebar">
				<input type="search" disabled="disabled" placeholder="<?php esc_html_e( 'Search', 'activitypub' ); ?>" />
				<div>
					<div class="fake-image"></div>
					<div>
						<div class="name">
							████ ██████
						</div>
						<div class="webfinger">
							@█████@██████
						</div>
					</div>
				</div>
				<textarea rows="10" cols="50" disabled="disabled" placeholder="<?php esc_html_e( 'What\'s up', 'activitypub' ); ?>"></textarea>
			</aside>
			<main>
				<h1 class="column-header">
					Home
				</h1>
				<article>
					<address>
						<img src="<?php echo esc_url( $user->get_icon()['url'] ); ?>" alt="<?php echo esc_attr( $user->get_name() ); ?>" />
						<div>
							<div class="name">
								<?php echo esc_html( $user->get_name() ); ?>
							</div>
							<div class="webfinger">
								<?php echo esc_html( '@' . $user->get_webfinger() ); ?>
							</div>
						</div>
					</address>
					<div class="content">
						<?php if ( 'Article' === $object->get_type() && $object->get_name() ) : ?>
							<h2><?php echo esc_html( $object->get_name() ); ?></h2>
						<?php endif; ?>
						<?php
						$content_to_display = 'Article' === $object->get_type() ? $object->get_summary() : $object->get_content();

						// Avoid captions making it through wp_kses.
						$content_to_display = preg_replace( '/<figure.*?>.*?<\/figure>/s', '', $content_to_display );

						echo wp_kses( $content_to_display, ACTIVITYPUB_MASTODON_HTML_SANITIZER );
						?>
					</div>
					<?php if ( $object->get_attachment() ) : ?>
					<div class="attachments <?php echo \esc_attr( $layout ); ?>">
						<?php
						if ( $has_images ) :
							foreach ( $object->get_attachment() as $attachment ) :
								if ( 'Image' === $attachment['type'] ) :
									?>
									<img src="<?php echo esc_url( $attachment['url'] ); ?>" alt="<?php echo esc_attr( $attachment['name'] ?? '' ); ?>" />
									<?php
								endif;
							endforeach;
						elseif ( $video ) :
							?>
							<video controls src="<?php echo esc_url( $video['url'] ); ?>" title="<?php echo esc_attr( $video['name'] ?? '' ); ?>"></video>
							<?php
						elseif ( $audio ) :
							?>
							<audio controls src="<?php echo esc_url( $audio['url'] ); ?>" title="<?php echo esc_attr( $audio['name'] ?? '' ); ?>"></audio>
							<?php
						endif;
						?>
					</div>
					<?php endif; ?>
					<?php if ( $object->get_tag() ) : ?>
					<div class="tags">
						<?php foreach ( $object->get_tag() as $hashtag ) : ?>
							<?php if ( 'Hashtag' === $hashtag['type'] ) : ?>
								<a href="<?php echo esc_url( $hashtag['href'] ); ?>"><?php echo esc_html( $hashtag['name'] ); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</article>
			</main>
			<aside class="sidebar">
				<h1>⁂ Fediverse</h1>
				<ul>
					<li>████████</li>
					<li>███████████</li>
					<li>██████████</li>
					<li>█████████</li>
					<li>███████</li>
					<li>████████</li>
					<li>████████████</li>
					<li>████████████</li>
					<li>██████████</li>
					<li>████████████</li>
				</ul>
				<hr />
				<ul>
					<li>███████████</li>
					<li>██████████████</li>
					<li>█████████</li>
				</ul>
				<hr />
				<ul>
					<li>██████████</li>
				</ul>
			</aside>
		</div>
	</body>
</html>
