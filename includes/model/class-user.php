<?php
namespace Activitypub\Model;

use WP_Query;
use WP_Error;
use Activitypub\Signature;
use Activitypub\Model\User;
use Activitypub\User_Factory;

use function Activitypub\get_rest_url_by_path;

class User {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	private $type = 'Person';

	/**
	 * The User constructor.
	 *
	 * @param numeric $user_id The User-ID.
	 */
	public function __construct( $user_id ) {
		$this->user_id = $user_id;

		add_filter( 'activitypub_json_author_array', array( $this, 'add_api_endpoints' ), 10, 2 );
		add_filter( 'activitypub_json_author_array', array( $this, 'add_attachments' ), 10, 2 );
	}

	/**
	 * Magic function to implement getter and setter
	 *
	 * @param string $method
	 * @param string $params
	 *
	 * @return void
	 */
	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'has', 3 ) === 0 ) {
			return (bool) call_user_func( 'get_' . $var, $this );
		}
	}

	/**
	 * Get the User-ID.
	 *
	 * @return string The User-ID.
	 */
	public function get_id() {
		return $this->get_url();
	}

	/**
	 * Get the User-Name.
	 *
	 * @return string The User-Name.
	 */
	public function get_name() {
		return \esc_attr( \get_the_author_meta( 'display_name', $this->user_id ) );
	}

	/**
	 * Get the User-Description.
	 *
	 * @return string The User-Description.
	 */
	public function get_summary() {
		$description = get_user_meta( $this->user_id, 'activitypub_user_description', true );
		if ( empty( $description ) ) {
			$description = get_user_meta( $this->user_id, 'description', true );
		}
		return \wpautop( \wp_kses( $description, 'default' ) );
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return \esc_url( \get_author_posts_url( $this->user_id ) );
	}

	public function get_canonical_url() {
		return $this->get_url();
	}

	public function get_username() {
		return \esc_attr( \get_the_author_meta( 'login', $this->user_id ) );
	}

	public function get_avatar() {
		return \esc_url(
			\get_avatar_url(
				$this->user_id,
				array( 'size' => 120 )
			)
		);
	}

	public function get_header_image() {
		if ( \has_header_image() ) {
			return \esc_url( \get_header_image() );
		}

		return null;
	}

	public function get_published() {
		return \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( \get_the_author_meta( 'registered', $this->user_id ) ) );
	}

	public function get_public_key() {
		return Signature::get_public_key( $this->user_id );
	}

	/**
	 * Array representation of the User.
	 *
	 * @param bool $context Whether to include the @context.
	 *
	 * @return array The array representation of the User.
	 */
	public function to_array( $context = true ) {
		$output = array();

		if ( $context ) {
			$output['@context'] = Activity::CONTEXT;
		}

		$output['id'] = $this->get_url();
		$output['type'] = $this->get_type();
		$output['name'] = $this->get_name();
		$output['summary'] = \html_entity_decode(
			$this->get_summary(),
			\ENT_QUOTES,
			'UTF-8'
		);
		$output['preferredUsername'] = $this->get_username(); // phpcs:ignore
		$output['url'] = $this->get_url();
		$output['icon'] = array(
			'type' => 'Image',
			'url'  => $this->get_avatar(),
		);

		if ( $this->has_header_image() ) {
			$output['image'] = array(
				'type' => 'Image',
				'url'  => $this->get_header_image(),
			);
		}

		$output['published'] = $this->get_published();

		$output['publicKey'] = array(
			'id' => $this->get_url() . '#main-key',
			'owner' => $this->get_url(),
			'publicKeyPem' => \trim( $this->get_public_key() ),
		);

		$output['manuallyApprovesFollowers'] = \apply_filters( 'activitypub_json_manually_approves_followers', \__return_false() ); // phpcs:ignore

		// filter output
		$output = \apply_filters( 'activitypub_json_author_array', $output, $this->user_id, $this );

		return $output;
	}

	/**
	 * Extend the User-Output with API-Endpoints.
	 *
	 * @param array   $array   The User-Output.
	 * @param numeric $user_id The User-ID.
	 *
	 * @return array The extended User-Output.
	 */
	public function add_api_endpoints( $array, $user_id ) {
		$array['inbox']     = get_rest_url_by_path( sprintf( 'users/%d/inbox', $user_id ) );
		$array['outbox']    = get_rest_url_by_path( sprintf( 'users/%d/outbox', $user_id ) );
		$array['followers'] = get_rest_url_by_path( sprintf( 'users/%d/followers', $user_id ) );
		$array['following'] = get_rest_url_by_path( sprintf( 'users/%d/following', $user_id ) );

		return $array;
	}

	/**
	 * Extend the User-Output with Attachments.
	 *
	 * @param array   $array   The User-Output.
	 * @param numeric $user_id The User-ID.
	 *
	 * @return array The extended User-Output.
	 */
	public function add_attachments( $array, $user_id ) {
		$array['attachment'] = array();

		$array['attachment']['blog_url'] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Blog', 'activitypub' ),
			'value' => \html_entity_decode(
				'<a rel="me" title="' . \esc_attr( \home_url( '/' ) ) . '" target="_blank" href="' . \home_url( '/' ) . '">' . \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		$array['attachment']['profile_url'] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Profile', 'activitypub' ),
			'value' => \html_entity_decode(
				'<a rel="me" title="' . \esc_attr( \get_author_posts_url( $user_id ) ) . '" target="_blank" href="' . \get_author_posts_url( $user_id ) . '">' . \wp_parse_url( \get_author_posts_url( $user_id ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		if ( \get_the_author_meta( 'user_url', $user_id ) ) {
			$array['attachment']['user_url'] = array(
				'type' => 'PropertyValue',
				'name' => \__( 'Website', 'activitypub' ),
				'value' => \html_entity_decode(
					'<a rel="me" title="' . \esc_attr( \get_the_author_meta( 'user_url', $user_id ) ) . '" target="_blank" href="' . \get_the_author_meta( 'user_url', $user_id ) . '">' . \wp_parse_url( \get_the_author_meta( 'user_url', $user_id ), \PHP_URL_HOST ) . '</a>',
					\ENT_QUOTES,
					'UTF-8'
				),
			);
		}

		return $array;
	}
}
