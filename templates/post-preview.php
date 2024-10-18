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
?>
<DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title><?php echo esc_html( $object->get_name() ); ?></title>
		<style>

		</style>
	</head>
	<body>
		<div class="status__content"><?php echo $object->get_content(); ?></div>
	</body>
</html>
