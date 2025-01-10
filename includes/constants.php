<?php
/**
 * Plugin constants.
 *
 * @package Activitypub
 */

// The following constants can be defined in your wp-config.php file to override the default values.

\defined( 'ACTIVITYPUB_REST_NAMESPACE' ) || \define( 'ACTIVITYPUB_REST_NAMESPACE', 'activitypub/1.0' );
\defined( 'ACTIVITYPUB_EXCERPT_LENGTH' ) || \define( 'ACTIVITYPUB_EXCERPT_LENGTH', 400 );
\defined( 'ACTIVITYPUB_NOTE_LENGTH' ) || \define( 'ACTIVITYPUB_NOTE_LENGTH', 400 );
\defined( 'ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS' ) || \define( 'ACTIVITYPUB_SHOW_PLUGIN_RECOMMENDATIONS', true );
\defined( 'ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS' ) || \define( 'ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS', 3 );
\defined( 'ACTIVITYPUB_HASHTAGS_REGEXP' ) || \define( 'ACTIVITYPUB_HASHTAGS_REGEXP', '(?:(?<=\s)|(?<=<p>)|(?<=<br>)|^)#([A-Za-z0-9_]+)(?:(?=\s|[[:punct:]]|$))' );
\defined( 'ACTIVITYPUB_USERNAME_REGEXP' ) || \define( 'ACTIVITYPUB_USERNAME_REGEXP', '(?:([A-Za-z0-9\._-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))' );
\defined( 'ACTIVITYPUB_URL_REGEXP' ) || \define( 'ACTIVITYPUB_URL_REGEXP', '(https?:|www\.)\S+[\w\/]' );
\defined( 'ACTIVITYPUB_CUSTOM_POST_CONTENT' ) || \define( 'ACTIVITYPUB_CUSTOM_POST_CONTENT', "[ap_title type=\"html\"]\n\n[ap_content]\n\n[ap_hashtags]" );
\defined( 'ACTIVITYPUB_DISABLE_REWRITES' ) || \define( 'ACTIVITYPUB_DISABLE_REWRITES', false );
\defined( 'ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS' ) || \define( 'ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS', false );
\defined( 'ACTIVITYPUB_DISABLE_OUTGOING_INTERACTIONS' ) || \define( 'ACTIVITYPUB_DISABLE_OUTGOING_INTERACTIONS', false );
\defined( 'ACTIVITYPUB_SHARED_INBOX_FEATURE' ) || \define( 'ACTIVITYPUB_SHARED_INBOX_FEATURE', false );
\defined( 'ACTIVITYPUB_SEND_VARY_HEADER' ) || \define( 'ACTIVITYPUB_SEND_VARY_HEADER', false );
\defined( 'ACTIVITYPUB_DEFAULT_OBJECT_TYPE' ) || \define( 'ACTIVITYPUB_DEFAULT_OBJECT_TYPE', 'wordpress-post-format' );

// The following constants are invariable and define values used throughout the plugin.

/*
 * Mastodon HTML sanitizer.
 *
 * @see https://docs.joinmastodon.org/spec/activitypub/#sanitization
 */
\define(
	'ACTIVITYPUB_MASTODON_HTML_SANITIZER',
	array(
		'p'          => array(),
		'span'       => array( 'class' => true ),
		'br'         => array(),
		'a'          => array(
			'href'  => true,
			'rel'   => true,
			'class' => true,
		),
		'del'        => array(),
		'pre'        => array(),
		'code'       => array(),
		'em'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'i'          => array(),
		'u'          => array(),
		'ul'         => array(),
		'ol'         => array(
			'start'    => true,
			'reversed' => true,
		),
		'li'         => array( 'value' => true ),
		'blockquote' => array(),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
	)
);

// Define Actor-Modes for the plugin.
\define( 'ACTIVITYPUB_ACTOR_MODE', 'actor' );
\define( 'ACTIVITYPUB_BLOG_MODE', 'blog' );
\define( 'ACTIVITYPUB_ACTOR_AND_BLOG_MODE', 'actor_blog' );

// Post visibility constants.
\define( 'ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC', '' );
\define( 'ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC', 'quiet_public' );
\define( 'ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL', 'local' );
