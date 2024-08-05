<?php

namespace Activitypub\Integration;

class Seriously_Simple_Podcasting {
	public static function init() {
		add_filter( 'activitypub_attachments', array( self::class, 'add_attachments' ), 10, 2 );
	}

	public static function add_attachments( $attachments, $post ) {
		if ( ! \get_post_meta( $post->ID, 'audio_file', true ) ) {
			return $attachments;
		}

		$attachment = array(
			'type' => \esc_attr( \get_post_meta( $post->ID, 'episode_type', true ) ),
			'url'  => \esc_url( \get_post_meta( $post->ID, 'audio_file', true ) ),
			'name' => \esc_attr( \get_the_title( $post->ID ) ),
			'icon' => \esc_url( \get_post_meta( $post->ID, 'cover_image', true ) ),
		);

		$attachment = array_filter( $attachment );
		array_unshift( $attachments, $attachment );

		return $attachments;
	}
}
