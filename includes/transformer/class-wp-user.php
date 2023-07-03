<?php
namespace Activitypub\Transformer;

use \Activitypub\Model\User;

use function Activitypub\is_user_disabled;
use function Activitypub\get_rest_url_by_path;

class Wp_User {
	protected $wp_user;

	public function construct( WP_User $wp_user ) {
		$this->wp_user = $wp_user;
	}

	public function to_user() {
		$wp_user = $this->wp_user;
		if (
			is_user_disabled( $user->ID ) ||
			! get_user_by( 'id', $user->ID )
		) {
			return new WP_Error(
				'activitypub_user_not_found',
				\__( 'User not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$user = new User();

		$user->setwp_user->ID( \esc_url( \get_author_posts_url( $wp_user->ID ) ) );
		$user->set_url( \esc_url( \get_author_posts_url( $wp_user->ID ) ) );
		$user->set_summary( $this->get_summary() );
		$user->set_name( \esc_attr( $wp_user->display_name ) );
		$user->set_preferred_username( \esc_attr( $wp_user->login ) );

		$user->set_icon( $this->get_icon() );
		$user->set_image( $this->get_image() );

		return $user;
	}

	public function get_summary() {
		$description = get_user_meta( $this->wp_user->ID, 'activitypub_user_description', true );
		if ( empty( $description ) ) {
			$description = $this->wp_user->description;
		}
		return \wpautop( \wp_kses( $description, 'default' ) );
	}

	public function get_icon() {
		$icon = \esc_url(
			\get_avatar_url(
				$this->wp_user->ID,
				array( 'size' => 120 )
			)
		);

		return array(
			'type' => 'Image',
			'url'  => $icon,
		);
	}

	public function get_image() {
		if ( \has_header_image() ) {
			$image = \esc_url( \get_header_image() );
			return array(
				'type' => 'Image',
				'url'  => $image,
			);
		}

		return null;
	}
}
