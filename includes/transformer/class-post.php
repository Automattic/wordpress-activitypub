<?php
namespace Activitypub\Transformer;

use WP_Post;
use Activitypub\Collection\Users;
use Activitypub\Model\Blog_User;
use Activitypub\Activity\Base_Object;
use Activitypub\Shortcodes;
use Activitypub\Transformer\Base;

use function Activitypub\esc_hashtag;
use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\site_supports_blocks;

/**
 * WordPress Post Transformer
 * The Post Transformer is responsible for transforming a WP_Post object into different othe
 * Object-Types.
 *
 * Currently supported are:
 * - Activitypub\Activity\Base_Object
 */
class Post extends Base {
	/**
	 * Getter function for the name of the transformer.
	 *
	 * @return string name
	 */
	public function get_name() {
		return 'activitypub/default';
	}

	/**
	 * Getter function for the display name (label/title) of the transformer.
	 *
	 * @return string name
	 */
	public function get_label() {
		return 'Built-In';
	}

	/**
	 * Returns the ActivityStreams 2.0 Object-Type for a Post based on the
	 * settings and the Post-Type.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#activity-types
	 *
	 * @return string The Object-Type.
	 */
	protected function get_object_type() {
		if ( 'wordpress-post-format' !== \get_option( 'activitypub_object_type', 'note' ) ) {
			return \ucfirst( \get_option( 'activitypub_object_type', 'note' ) );
		}

		// Default to Article.
		$object_type = 'Article';
		$post_type   = \get_post_type( $this->wp_post );
		switch ( $post_type ) {
			case 'post':
				$post_format = \get_post_format( $this->wp_post );
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
				$mime_type  = \get_post_mime_type();
				$media_type = \preg_replace( '/(\/[a-zA-Z]+)/i', '', $mime_type );
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
