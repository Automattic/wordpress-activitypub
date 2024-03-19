<?php
namespace Activitypub\Integration;

use DateTime;
use Activitypub\Webfinger as Webfinger_Util;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Integration\Nodeinfo;
use Enable_Mastodon_Apps\Entity\Account;

use function Activitypub\get_remote_metadata_by_actor;

/**
 * Class Enable_Mastodon_Apps
 *
 * This class is used to enable Mastodon Apps to work with ActivityPub
 *
 * @see https://github.com/akirk/enable-mastodon-apps
 */
class Enable_Mastodon_Apps {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'mastodon_api_account_followers', array( self::class, 'api_account_followers' ), 10, 2 );
		\add_filter( 'mastodon_api_account', array( self::class, 'api_account' ), 20, 2 );
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

				$account = new Account();
				$account->id = \strval( $item->get__id() );
				$account->username = $item->get_preferred_username();
				$account->acct = $acct;
				$account->display_name = $item->get_name();
				$account->url = $item->get_url();
				$account->uri = $item->get_id();
				$account->avatar = $item->get_icon_url();
				$account->avatar_static = $item->get_icon_url();
				$account->created_at = new DateTime( $item->get_published() );
				$account->last_status_at = new DateTime( $item->get_published() );
				$account->note = $item->get_summary();
				$account->header = $item->get_image_url();
				$account->header_static = $item->get_image_url();
				$account->followers_count = 0;
				$account->following_count = 0;
				$account->statuses_count = 0;
				$account->bot = false;
				$account->locked = false;
				$account->group = false;
				$account->discoversable = false;
				$account->indexable = false;
				$account->hide_collections = false;
				$account->noindex = false;
				$account->fields = array();
				$account->emojis = array();
				$account->roles = array();

				return $account;
			},
			$activitypub_followers
		);

		$followers = array_merge( $mastodon_followers, $followers );

		return $followers;
	}

	/**
	 * Add followers count to Mastodon API
	 *
	 * @param Enable_Mastodon_Apps\Entity\Account $account The account
	 * @param int                                 $user_id The user id
	 *
	 * @return Enable_Mastodon_Apps\Entity\Account The filtered Account
	 */
	public static function api_account( $account, $user_id ) {
		if ( ! $account instanceof Account ) {
			return $account;
		}

		$user = Users::get_by_id( $user_id );

		if ( ! $user || is_wp_error( $user ) ) {
			return $account;
		}

		$header = $user->get_image();
		if ( $header ) {
			$account->header = $header['url'];
			$account->header_static = $header['url'];
		}

		foreach ( $user->get_attachment() as $attachment ) {
			if ( 'PropertyValue' === $attachment['type'] ) {
				$account->fields[] = array(
					'name' => $attachment['name'],
					'value' => $attachment['value'],
				);
			}
		}

		$account->acct = $user->get_webfinger();
		$account->note = $user->get_summary();
		$account->followers_count = Followers::count_followers( $user_id );
		return $account;
	}

	/**
	 * Resolve external accounts for Mastodon API
	 *
	 * @param Enable_Mastodon_Apps\Entity\Account $user_data The user data
	 * @param string                              $user_id   The user id
	 *
	 * @return Enable_Mastodon_Apps\Entity\Account The filtered Account
	 */
	public static function api_account_external( $user_data, $user_id ) {
		if ( ! preg_match( '/^' . ACTIVITYPUB_USERNAME_REGEXP . '$/', $user_id ) ) {
			return $user_data;
		}

		$uri = Webfinger_Util::resolve( $user_id );

		if ( ! $uri ) {
			return $user_data;
		}

		$acct = Webfinger_Util::uri_to_acct( $uri );
		$data = get_remote_metadata_by_actor( $uri );

		if ( ! $data || is_wp_error( $data ) ) {
			return $user_data;
		}

		if ( $user_data instanceof Account ) {
				$account = $user_data;
		} else {
				$account = new Account();
		}

		$account->id             = strval( $user_id );
		$account->username       = $acct;
		$account->acct           = $acct;
		$account->display_name   = $data['name'];
		$account->url            = $uri;
		if ( ! empty( $data['summary'] ) ) {
			$account->note = $data['summary'];
		}

		if (
			isset( $data['icon']['type'] ) &&
			isset( $data['icon']['url'] ) &&
			'Image' === $data['icon']['type']
		) {
			$account->avatar        = $data['icon']['url'];
			$account->avatar_static = $data['icon']['url'];
		}

		if ( isset( $data['image'] ) ) {
			$account->header        = $data['image'];
			$account->header_static = $data['image'];
		}

		$account->created_at = new DateTime( $data['published'] );

		return $account;
	}
}
