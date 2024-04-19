<?php
namespace Activitypub\Model;

use Activitypub\Collection\Users;
use Activitypub\Transformer\Post as Post_Transformer;

/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 */
class Post {
	/**
	 * The \Activitypub\Activity\Base_Object object.
	 *
	 * @var \Activitypub\Activity\Base_Object
	 */
	protected $object;

	/**
	 * The WordPress Post Object.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Constructor
	 *
	 * @param WP_Post $post
	 * @param int     $post_author
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public function __construct( $post, $post_author = null ) {
		_deprecated_function( __CLASS__, '1.0.0', '\Activitypub\Transformer\Post' );

		$transformer = Post_Transformer::transform( $post );

		if ( ! \is_wp_error( $transformer ) ) {
			$this->post   = $post;
			$this->object = $transformer->to_object();
		}
	}

	/**
	 * Returns the User ID.
	 *
	 * @return int the User ID.
	 */
	public function get_user_id() {
		return apply_filters( 'activitypub_post_user_id', $this->post->post_author, $this->post );
	}

	/**
	 * Converts this Object into an Array.
	 *
	 * @return array the array representation of a Post.
	 */
	public function to_array() {
		return \apply_filters( 'activitypub_post', $this->object->to_array(), $this->post );
	}

	/**
	 * Returns the Actor of this Object.
	 *
	 * @return string The URL of the Actor.
	 */
	public function get_actor() {
		$user = Users::get_by_id( $this->get_user_id() );

		return $user->get_url();
	}

	/**
	 * Converts this Object into a JSON String
	 *
	 * @return string
	 */
	public function to_json() {
		return \wp_json_encode( $this->to_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}

	/**
	 * Returns the URL of an Activity Object
	 *
	 * @return string
	 */
	public function get_url() {
		return $this->object->get_url();
	}

	/**
	 * Returns the ID of an Activity Object
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->object->get_id();
	}

	/**
	 * Returns a list of Image Attachments
	 *
	 * @return array
	 */
	public function get_attachments() {
		return $this->object->get_attachment();
	}

	/**
	 * Returns a list of Tags, used in the Post
	 *
	 * @return array
	 */
	public function get_tags() {
		return $this->object->get_tag();
	}

	/**
	 * Returns the as2 object-type for a given post
	 *
	 * @return string the object-type
	 */
	public function get_object_type() {
		return $this->object->get_type();
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * @return string the content
	 */
	public function get_content() {
		return $this->object->get_content();
	}
}
