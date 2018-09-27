<?php
function activitypub_post_to_json( $post, $json = null ) {
	if ( ! $json ) {
		$json = new stdClass();
	}

	$json->published = date( 'Y-m-d\TH:i:s\Z', strtotime( $post->post_date ) );
	$json->id = $post->guid . '&activitypub';
	$json->type = 'Create';
	$json->actor = get_author_posts_url( $post->post_author );
	$json->to = array( 'https://www.w3.org/ns/activitystreams#Public' );
	$json->cc = array( 'https://www.w3.org/ns/activitystreams#Public' );

	$json->object = array(
		'id' => $post->guid,
		'type' => activitypub_object_type( $post ),
		'published' => date( 'Y-m-d\TH:i:s\Z', strtotime( $post->post_date ) ),
		'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'attributedTo' => get_author_posts_url( $post->post_author ),
		'summary' => null,
		'inReplyTo' => null,
		'content' => esc_html( $post->post_content ),
		'contentMap' => array(
			strstr( get_locale(), '_', true ) => esc_html( $post->post_content )	,
		),
		'attachment' => array(),
		'tag' => array(),
	);

	return $json;
}

/**
 * Returns the as2 object for a given post
 *
 * @param string $type the object type
 * @param Object $post the post object
 * @return string the object type
 */
function activitypub_object_type( $post ) {
	$post_type = get_post_type( $post );
	switch ( $post_type ) {
		case 'post':
			$post_format = get_post_format( $post );
			switch ( $post_format ) {
				case 'aside':
				case 'status':
				case 'quote':
				case 'note':
					$object_type = 'Note';
					break;
				case 'gallery':
				case 'image':
					$object_type = 'Image';
					break;
				case 'video':
					$object_type = 'Video';
					break;
				case 'audio':
					$object_type = 'Audio';
					break;
				default:
					$object_type = 'Article';
					break;
			}
			break;
		case 'page':
			$object_type = 'Page';
			break;
		case 'attachment':
			$mime_type = get_post_mime_type();
			$media_type = preg_replace( '/(\/[a-zA-Z]+)/i', '', $mime_type );
			switch ( $media_type ) {
				case 'audio':
					$object_type = 'Audio';
					break;
				case 'video':
					$object_type = 'Video';
					break;
				case 'image':
					$object_type = 'Image';
					break;
			}
			break;
		default:
			$object_type = 'Article';
			break;
	}
	return $object_type;
}
