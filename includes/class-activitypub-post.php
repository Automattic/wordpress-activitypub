<?php
/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub_Post {
	private $post;

	public function __construct( $post = null ) {
		$this->post = get_post( $post );
	}

	public function get_post() {
		return $this->post;
	}

	public function get_post_author() {
		return $this->post->post_author;
	}

	public function to_array() {
		$post = $this->post;

		setup_postdata( $post );

		$array = array(
			'id' => get_permalink( $post ),
			'type' => $this->get_object_type(),
			'published' => date( 'Y-m-d\TH:i:s\Z', strtotime( $post->post_date ) ),
			'attributedTo' => get_author_posts_url( $post->post_author ),
			'summary' => apply_filters( 'the_excerpt', get_post_field( 'post_excerpt', $post->ID ) ),
			'inReplyTo' => null,
			'content' => apply_filters( 'the_content', get_post_field( 'post_content', $post->ID ) ),
			'contentMap' => array(
				strstr( get_locale(), '_', true ) => apply_filters( 'the_content', get_post_field( 'post_content', $post->ID ) ),
			),
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'attachment' => $this->get_attachments(),
			'tag' => $this->get_tags(),
		);

		wp_reset_postdata();

		return apply_filters( 'activitypub_post', $array );
	}

	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}

	public function get_attachments() {
		$max_images = apply_filters( 'activitypub_max_images', 3 );

		$images = array();

		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			$max_images = 1;
		}

		$id = $this->post->ID;

		$image_ids = array();
		// list post thumbnail first if this post has one
		if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $id ) ) {
			$image_ids[] = get_post_thumbnail_id( $id );
			$max_images--;
		}
		// then list any image attachments
		$query = new WP_Query(
			array(
				'post_parent' => $id,
				'post_status' => 'inherit',
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'order' => 'ASC',
				'orderby' => 'menu_order ID',
				'posts_per_page' => $max_images,
			)
		);
		foreach ( $query->get_posts() as $attachment ) {
			if ( ! in_array( $attachment->ID, $image_ids ) ) {
				$image_ids[] = $attachment->ID;
			}
		}
		// get URLs for each image
		foreach ( $image_ids as $id ) {
			$thumbnail = wp_get_attachment_image_src( $id, 'full' );
			$mimetype = get_post_mime_type( $id );

			if ( $thumbnail ) {
				$images[] = array(
					'url' => $thumbnail[0],
					'type' => $mimetype
				);
			}
		}

		$attachments = array();

		// add attachments
		if ( $images ) {
			foreach ( $images as $image ) {
				$attachment = array(
					"type" => "Image",
					"url" => $image['url'],
					"mediaType" => $image['type'],
				);
				$attachments[] = $attachment;
			}
		}

		return $attachments;
	}

	public function get_tags() {
		$tags = array();

		$post_tags = get_the_tags( $this->post->ID );
		if ( $post_tags ) {
			foreach( $post_tags as $post_tag ) {
				$tag = array(
					"type" => "Hashtag",
					"href" => get_tag_link( $post_tag->term_id ),
					"name" => '#' . $post_tag->name,
				);
				$tags[] = $tag;
			}
		}

		return $tags;
	}

	/**
	 * Returns the as2 object-type for a given post
	 *
	 * @param string $type the object-type
	 * @param Object $post the post-object
	 *
	 * @return string the object-type
	 */
	public function get_object_type() {
		$post_type = get_post_type( $this->post );
		switch ( $post_type ) {
			case 'post':
				$post_format = get_post_format( $this->post );
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
}
