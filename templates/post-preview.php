<?php
/**
 * ActivityPub Post JSON template.
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

$object = $transformer->to_object();
$user   = $transformer->get_actor_object();

?>
<DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( $object->get_name() ); ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
				font-size: 1em;
				line-height: 1.5;
				margin: 0;
				padding: 0;
			}
			.columns {
				display: flex;
				flex-direction: row;
				justify-content: space-between;
				margin: 0 auto;
				max-width: 1200px;
			}
			.sidebar {
				flex: 1;
				padding: 1em;
				max-width: 285px;
			}
			.sidebar input[type="search"],
			.sidebar textarea {
				background-color: #f6f6f6;
				border: 1px solid #ccc;
				border-radius: 4px;
				box-sizing: border-box;
				color: #333;
				display: block;
				font-size: 1em;
				margin-bottom: 1em;
				padding: 0.5em;
				width: 100%;
			}
			.sidebar > div,
			main address {
				align-items: center;
				display: flex;
				margin-bottom: 1em;
				font-style: normal;
			}
			main address .name,
			main address .webfinger {
				color: #000;
			}
			.name {
				color: #ccc;
				font-weight: bold;
				display: block;
			}
			.webfinger {
				color: #ccc;
				font-size: 0.8em;
				font-weight: bold;
				display: block;
				margin-top: 0.5em;
			}
			address img, .sidebar .fake-image {
				border-radius: 8px;
				margin-right: 1em;
				width: 48px;
				height: 48px;
				background-color: #333;
			}
			main {
				flex: 1;
				border: 1px solid #ccc;
				border-radius: 4px;
				background-color: #fff;
				margin: 1em;
				max-width: 600px;
			}
			main p {
				margin-bottom: 1em;
			}
			.sidebar h1 {
				font-size: 1.5em;
				margin-bottom: 1em;
				margin-top: 0;
				padding: 5px 10px;
				border-radius: 4px;
				background-color: #6364ff;
				color: #fff;
				display: inline-block;
			}
			hr {
				background: transparent;
				border: 0;
				border-top: 1px solid #ccc;
				flex: 0 0 auto;
				margin: 10px 0;
			}
			.sidebar ul {
				list-style-type: none;
				padding: 0;
			}
			.sidebar ul li {
				padding: 5px;
				color: #ccc;
			}
			main article {
				padding: 1em;
			}
			main .content {
				margin: 1em 0;
				font-size: 1.2em;
			}
			main .content h2 {
				font-size: 1.2em;
			}
			main .attachments {
				border-radius: 8px;
				box-sizing: border-box;
				display: grid;
				gap: 2px;
				grid-template-columns: 1fr 1fr;
				grid-template-rows: auto;
				margin: 20px 0;
				min-height: 64px;
				overflow: hidden;
				position: relative;
				width: 100%;
			}
			main .attachments img {
				max-width: 100%;
				height: 100%;
				margin: 1em 0;
				display: inline-block;
				object-fit: cover;
				overflow: hidden;
			}
			main .tags a {
				background-color: #f6f6f6;
				border-radius: 4px;
				color: #333;
				display: inline-block;
				margin-right: 0.5em;
				padding: 0.5em;
				text-decoration: none;
			}
			main .tags a:hover {
				background-color: #e6e6e6;
				text-decoration: underline;
			}
			main .column-header {
				font-size: 1.5em;
				margin: 0;
				padding: 5px 10px;
				border-bottom: 1px solid #ccc;
				vertical-align: middle;
			}
		</style>
	</head>
	<body>
		<div class="columns">
			<aside class="sidebar">
				<input type="search" disabled="disabled" placeholder="<?php echo esc_html_e( 'Search', 'activitypub' ); ?>" disabled="disabled" />
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
						<?php echo wp_kses( 'Article' === $object->get_type() ? $object->get_summary() : $object->get_content(), ACTIVITYPUB_MASTODON_HTML_SANITIZER ); ?>
					</div>
					<div class="attachments">
						<?php foreach ( $object->get_attachment() as $attachment ) : ?>
							<?php if ( 'Image' === $attachment['type'] ) : ?>
								<img src="<?php echo esc_url( $attachment['url'] ); ?>" alt="<?php echo esc_attr( $attachment['name'] ); ?>" />
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<div class="tags">
						<?php foreach ( $object->get_tag() as $hashtag ) : ?>
							<?php if ( 'Hashtag' === $hashtag['type'] ) : ?>
								<a href="<?php echo esc_url( $hashtag['href'] ); ?>"><?php echo esc_html( $hashtag['name'] ); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
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
