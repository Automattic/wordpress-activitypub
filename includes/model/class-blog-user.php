<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\User_Factory;

class Blog_User extends User {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	public $user_id = User_Factory::BLOG_USER_ID;

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	private $type = 'Person';

	/**
	 * The User constructor.
	 *
	 * @param int $user_id The User-ID.
	 */
	public function __construct( $user_id ) {
		add_filter( 'activitypub_json_author_array', array( $this, 'add_api_endpoints' ), 10, 2 );
	}

	/**
	 * Get the User-Name.
	 *
	 * @return string The User-Name.
	 */
	public function get_name() {
		return \esc_html( \get_bloginfo( 'name' ) );
	}

	/**
	 * Get the User-Description.
	 *
	 * @return string The User-Description.
	 */
	public function get_summary() {
		return \wpautop( \wp_kses( \get_bloginfo( 'description' ), 'default' ) );
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_username() );
	}

	public function get_canonical_url() {
		return \get_home_url();
	}

	public function get_username() {
		return \esc_html( \get_option( 'activitypub_blog_user_identifier', 'feed' ) );
	}

	public function get_avatar() {
		return \esc_url( \get_site_icon_url( 120 ) );
	}

	public function get_header_image() {
		if ( \has_header_image() ) {
			return esc_url( \get_header_image() );
		}

		return null;
	}

	public function get_published() {
		$first_post = new WP_Query(
			array(
				'orderby' => 'date',
				'order'   => 'ASC',
				'number'  => 1,
			)
		);

		if ( ! empty( $first_post->posts[0] ) ) {
			$time = \strtotime( $first_post->posts[0]->post_date_gmt );
		} else {
			$time = \time();
		}

		return \gmdate( 'Y-m-d\TH:i:s\Z', $time );
	}

	public function get_public_key() {
		return '';
	}
}
