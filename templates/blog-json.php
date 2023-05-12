<?php
use function Activitypub\get_rest_url_by_path;

$json = new \stdClass();

$json->{'@context'} = \Activitypub\get_context();
$json->id = \get_home_url( '/' );
$json->type = 'Organization';
$json->name = \get_bloginfo( 'name' );
$json->summary = \html_entity_decode(
	\get_bloginfo( 'description' ),
	\ENT_QUOTES,
	'UTF-8'
);
$json->preferredUsername = \get_bloginfo( 'name' ); // phpcs:ignore
$json->url = \get_home_url( '/' );

if ( \has_site_icon() ) {
	$json->icon = array(
		'type' => 'Image',
		'url'  => \get_site_icon_url( 120 ),
	);
}

if ( \has_header_image() ) {
	$json->image = array(
		'type' => 'Image',
		'url'  => \get_header_image(),
	);
}

$json->inbox = get_rest_url_by_path( 'blog/inbox' );
$json->outbox = get_rest_url_by_path( 'blog/outbox' );
$json->followers = get_rest_url_by_path( 'blog/followers' );
$json->following = get_rest_url_by_path( 'blog/following' );

$json->manuallyApprovesFollowers = \apply_filters( 'activitypub_json_manually_approves_followers', \__return_false() ); // phpcs:ignore

// phpcs:ignore
$json->publicKey = array(
	'id' => \get_home_url( '/' ) . '#main-key',
	'owner' => \get_home_url( '/' ),
	'publicKeyPem' => '',
);

$json->tag = array();
$json->attachment = array();

$json->attachment[] = array(
	'type' => 'PropertyValue',
	'name' => \__( 'Blog', 'activitypub' ),
	'value' => \html_entity_decode(
		'<a rel="me" title="' . \esc_attr( \home_url( '/' ) ) . '" target="_blank" href="' . \home_url( '/' ) . '">' . \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) . '</a>',
		\ENT_QUOTES,
		'UTF-8'
	),
);

// filter output
$json = \apply_filters( 'activitypub_json_blog_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_blog_pre' );

$options = 0;
// JSON_PRETTY_PRINT added in PHP 5.4
if ( \get_query_var( 'pretty' ) ) {
	$options |= \JSON_PRETTY_PRINT; // phpcs:ignore
}

$options |= \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT;

/*
 * Options to be passed to json_encode()
 *
 * @param int $options The current options flags
 */
$options = \apply_filters( 'activitypub_json_blog_options', $options );

\header( 'Content-Type: application/activity+json' );
echo \wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_blog_post' );
