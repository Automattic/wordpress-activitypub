<?php
namespace Activitypub\Integration;

use Activitypub\Webfinger as Webfinger_Util;
use Activitypub\Collection\Followers;

class Enable_Mastodon_Apps {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'mastodon_api_account_followers', array( self::class, 'api_account_followers' ), 10, 2 );
		\add_filter( 'mastodon_api_account', array( self::class, 'api_account_external' ), 10, 2 );
	}

	/**
	 * Add followers to Mastodon API
	 *
	 * @param array           $followers An array of followers
	 * @param string          $user_id   The user id
	 * @param WP_REST_Request $request   The request object
	 *
	 * @return array The filtered followers
	 */
	public static function api_account_followers( $followers, $user_id ) {
		$activitypub_followers = Followers::get_followers( $user_id, 40 );
		$mastodon_followers    = array_map(
			function ( $item ) {
				$acct = Webfinger_Util::uri_to_acct( $item->get_id() );

				if ( $acct && ! is_wp_error( $acct ) ) {
					$acct = \str_replace( 'acct:', '', $acct );
				} else {
					$acct = $item->get_url();
				}

				$activitypub_follower = array(
					'id' => \strval( $item->get__id() ),
					'username' => $item->get_preferred_username(),
					'acct' => $acct,
					'display_name' => $item->get_name(),
					'url' => $item->get_url(),
					'uri' => $item->get_id(),
					'avatar' => $item->get_icon_url(),
					'avatar_static' => $item->get_icon_url(),
					'created_at' => gmdate( DATE_W3C, strtotime( $item->get_published() ) ),
					'last_status_at' => gmdate( DATE_W3C, strtotime( $item->get_published() ) ),
					'note' => $item->get_summary(),
					'header' => $item->get_image_url(),
					'header_static' => $item->get_image_url(),
					'followers_count' => 0,
					'following_count' => 0,
					'statuses_count' => 0,
					'bot' => false,
					'locked' => false,
					'group' => false,
					'discoversable' => false,
					'indexable' => false,
					'hide_collections' => false,
					'noindex' => false,
					'fields' => array(),
					'emojis' => array(),
					'roles' => array(),
				);

				return $activitypub_follower;
			},
			$activitypub_followers
		);

		$followers = array_merge( $mastodon_followers, $followers );

		return $followers;
	}

	public static function api_account_external( $user_data, $user_id ) {
		if ( ! preg_match( '/^' . ACTIVITYPUB_USERNAME_REGEXP . '$/', $user_id ) ) {
			return $user_data;
		}

		$uri = \Activitypub\Webfinger::resolve( $user_id );
		if ( ! $uri ) {
			return $user_data;
		}
		$acct = Webfinger_Util::uri_to_acct( $uri );
		$data = \Activitypub\get_remote_metadata_by_actor( $uri );
		if ( ! $data ) {
			return $user_data;
		}

		$account = new \Enable_Mastodon_Apps\Entity\Account();

		$account->id             = strval( $user_id );
		$account->username       = $acct;
		$account->acct           = $acct;
		$account->display_name   = $data['name'];
		$account->url            = $uri;
		$account->note           = $data['summary'];
		if ( isset( $data['icon']['type'] ) &&  isset( $data['icon']['url'] ) && 'Image' === $data['icon']['type']) {
		$account->avatar         = $data['icon']['url'];
		$account->avatar_static  = $data['icon']['url'];
		}
		if ( isset( $data['image'] ) ) {
			$account->header         = $data['image'];
			$account->header_static  = $data['image'];
		}
		$account->created_at    = new \DateTime( $data['published'] );

		return $account;
	}
}
