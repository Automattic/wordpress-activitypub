<?php
namespace Activitypub\Integration;

use DateTime;
use Activitypub\Webfinger as Webfinger_Util;
use Activitypub\Http;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Integration\Nodeinfo;
use Enable_Mastodon_Apps\Mastodon_API;
use Enable_Mastodon_Apps\Entity\Account;
use Enable_Mastodon_Apps\Entity\Status;
use Enable_Mastodon_Apps\Entity\Media_Attachment;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\is_user_type_disabled;
use function Activitypub\is_user_disabled;

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
		\add_filter( 'mastodon_api_account', array( self::class, 'api_account_external' ), 15, 2 );
		\add_filter( 'mastodon_api_account', array( self::class, 'api_account_internal' ), 9, 2 );
		\add_filter( 'mastodon_api_search', array( self::class, 'api_search' ), 40, 2 );
		\add_filter( 'mastodon_api_search', array( self::class, 'api_search_by_url' ), 40, 2 );
		\add_filter( 'mastodon_api_get_posts_query_args', array( self::class, 'api_get_posts_query_args' ) );
		\add_filter( 'mastodon_api_statuses', array( self::class, 'api_statuses_external' ), 10, 2 );
		\add_filter( 'mastodon_api_status_context', array( self::class, 'api_get_replies' ), 10, 23 );
		\add_action( 'mastodon_api_update_credentials', array( self::class, 'api_update_credentials' ), 10, 2 );
	}

	/**
	 * Map user to blog if user is disabled
	 *
	 * @param int $user_id The user id
	 *
	 * @return int The user id
	 */
	public static function maybe_map_user_to_blog( $user_id ) {
		if (
			is_user_type_disabled( 'user' ) &&
			! is_user_type_disabled( 'blog' ) &&
			// check if the blog user is permissible for this user
			user_can( $user_id, 'activitypub' )
		) {
			return Users::BLOG_USER_ID;
		}

		return $user_id;
	}

	/**
	 * Update profile data for Mastodon API.
	 *
	 * @param array $data    The data to act on
	 * @param int   $user_id The user id
	 * @return array         The possibly-filtered data (data that's saved gets unset from the array)
	 */
	public static function api_update_credentials( $data, $user_id ) {
		if ( empty( $user_id ) ) {
			return $data;
		}

		$user_id = self::maybe_map_user_to_blog( $user_id );
		$user    = Users::get_by_id( $user_id );
		if ( ! $user || is_wp_error( $user ) ) {
			return $data;
		}

		// User::update_icon and other update_* methods check data validity, so we don't need to do it here.
		if ( isset( $data['avatar'] ) && $user->update_icon( $data['avatar'] ) ) {
			// unset the avatar so it doesn't get saved again by other plugins.
			// Ditto for all other fields below.
			unset( $data['avatar'] );
		}

		if ( isset( $data['header'] ) && $user->update_header( $data['header'] ) ) {
			unset( $data['header'] );
		}

		if ( isset( $data['display_name'] ) && $user->update_name( $data['display_name'] ) ) {
			unset( $data['display_name'] );
		}

		if ( isset( $data['note'] ) && $user->update_summary( $data['note'] ) ) {
			unset( $data['note'] );
		}

		if ( isset( $data['fields_attributes'] ) ) {
			Extra_Fields::set_extra_fields_from_mastodon_api( $user_id, $data['fields_attributes'] );
			unset( $data['fields_attributes'] );
		}

		return $data;
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
		$user_id               = self::maybe_map_user_to_blog( $user_id );
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
				$account->discoverable = false;
				$account->noindex = false;
				$account->fields = array();
				$account->emojis = array();

				return $account;
			},
			$activitypub_followers
		);

		$followers = array_merge( $mastodon_followers, $followers );

		return $followers;
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
		if ( $user_data || ( is_numeric( $user_id ) && $user_id ) ) {
			// Only augment.
			return $user_data;
		}

		$user = Users::get_by_various( $user_id );

		if ( $user && ! is_wp_error( $user ) ) {
			return $user_data;
		}

		$uri = Webfinger_Util::resolve( $user_id );

		if ( ! $uri || is_wp_error( $uri ) ) {
			return $user_data;
		}

		$account = self::get_account_for_actor( $uri );
		if ( $account ) {
			return $account;
		}

		return $user_data;
	}

	public static function api_account_internal( $user_data, $user_id ) {
		$user_id_to_use = self::maybe_map_user_to_blog( $user_id );
		$user = Users::get_by_id( $user_id_to_use );

		if ( ! $user || is_wp_error( $user ) ) {
			return $user_data;
		}

		// convert user to account.
		$account = new Account();
		// even if we have a blog user, maintain the provided user_id so as not to confuse clients
		$account->id = (int) $user_id;
		$account->username = $user->get_preferred_username();
		$account->acct = $account->username;
		$account->display_name = $user->get_name();
		$account->note = $user->get_summary();
		$account->source['note'] = wp_strip_all_tags( $account->note, true );
		$account->url = $user->get_url();

		$icon = $user->get_icon();
		$account->avatar = $icon['url'];
		$account->avatar_static = $account->avatar;

		$header = $user->get_image();
		if ( $header ) {
			$account->header = $header['url'];
			$account->header_static = $account->header;
		}

		$account->created_at = new DateTime( $user->get_published() );

		$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );
		$query_args = array(
			'post_type' => $post_types,
			'posts_per_page' => 1,
		);
		if ( $user_id > 0 ) {
			$query_args['author'] = $user_id;
		}
		$posts = \get_posts( $query_args );
		$account->last_status_at = ! empty( $posts ) ? new DateTime( $posts[0]->post_date_gmt ) : $account->created_at;

		$account->fields = Extra_Fields::get_extra_fields_for_mastodon_api( $user_id_to_use );
		// Now do it in source['fields'] with stripped tags
		$account->source['fields'] = \array_map(
			function ( $field ) {
				$field['value'] = \wp_strip_all_tags( $field['value'], true );
				return $field;
			},
			$account->fields
		);

		$account->followers_count = Followers::count_followers( $user->get__id() );

		return $account;
	}

	private static function get_account_for_actor( $uri ) {
		if ( ! is_string( $uri ) ) {
			return null;
		}
		$data = get_remote_metadata_by_actor( $uri );

		if ( ! $data || is_wp_error( $data ) ) {
			return null;
		}
		$account = new Account();

		$acct = Webfinger_Util::uri_to_acct( $uri );
		if ( str_starts_with( $acct, 'acct:' ) ) {
			$acct = substr( $acct, 5 );
		}

		$account->id             = $acct;
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
			$account->header        = $data['image']['url'];
			$account->header_static = $data['image']['url'];
		}
		if ( ! isset( $data['published'] ) ) {
			$data['published'] = 'now';
		}
		$account->created_at = new DateTime( $data['published'] );

		return $account;
	}

	public static function api_search_by_url( $search_data, $request ) {
		$p = \wp_parse_url( $request->get_param( 'q' ) );
		if ( ! $p || ! isset( $p['host'] ) ) {
			return $search_data;
		}

		$object = Http::get_remote_object( $request->get_param( 'q' ), true );
		if ( is_wp_error( $object ) || ! isset( $object['attributedTo'] ) ) {
			return $search_data;
		}

		$account = self::get_account_for_actor( $object['attributedTo'] );
		if ( ! $account ) {
			return $search_data;
		}

		$status = self::activity_to_status( $object, $account );
		if ( $status ) {
			$search_data['statuses'][] = $status;
		}

		return $search_data;
	}

	public static function api_search( $search_data, $request ) {
		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return $search_data;
		}

		$q = $request->get_param( 'q' );
		if ( ! $q ) {
			return $search_data;
		}
		$q = sanitize_text_field( wp_unslash( $q ) );

		$followers = Followers::get_followers( $user_id, 40, null, array( 's' => $q ) );
		if ( ! $followers ) {
			return $search_data;
		}

		foreach ( $followers as $follower ) {
			$acct = Webfinger_Util::uri_to_acct( $follower->get_id() );

			if ( $acct && ! is_wp_error( $acct ) ) {
				$acct = \str_replace( 'acct:', '', $acct );
			} else {
				$acct = $follower->get_url();
			}

			$account = new Account();
			$account->id = \strval( $follower->get__id() );
			$account->username = $follower->get_preferred_username();
			$account->acct = $acct;
			$account->display_name = $follower->get_name();
			$account->url = $follower->get_url();
			$account->uri = $follower->get_id();
			$account->avatar = $follower->get_icon_url();
			$account->avatar_static = $follower->get_icon_url();
			$account->created_at = new DateTime( $follower->get_published() );
			$account->last_status_at = new DateTime( $follower->get_published() );
			$account->note = $follower->get_summary();
			$account->header = $follower->get_image_url();
			$account->header_static = $follower->get_image_url();

			$search_data['accounts'][] = $account;
		}

		return $search_data;
	}

	public static function api_get_posts_query_args( $args ) {
		if ( isset( $args['author'] ) && is_string( $args['author'] ) ) {
			$uri = Webfinger_Util::resolve( $args['author'] );
			if ( $uri && ! is_wp_error( $uri ) ) {
				$args['activitypub'] = $uri;
				unset( $args['author'] );
			}
		}

		return $args;
	}

	private static function activity_to_status( $item, $account ) {
		if ( isset( $item['object'] ) ) {
			$object = $item['object'];
		} else {
			$object = $item;
		}

		if ( ! isset( $object['type'] ) || 'Note' !== $object['type'] ) {
			return null;
		}

		$status = new Status();
		$status->id         = $object['id'];
		$status->created_at = new DateTime( $object['published'] );
		$status->content    = $object['content'];
		$status->account    = $account;

		if ( ! empty( $object['inReplyTo'] ) ) {
			$status->in_reply_to_id = $object['inReplyTo'];
		}

		if ( ! empty( $object['visibility'] ) ) {
			$status->visibility = $object['visibility'];
		}
		if ( ! empty( $object['url'] ) ) {
			$status->url = $object['url'];
			$status->uri = $object['url'];
		} else {
			$status->uri = $object['id'];
		}

		if ( ! empty( $object['attachment'] ) ) {
			$status->media_attachments = array_map(
				function ( $attachment ) {
					$default_attachment = array(
						'url' => null,
						'mediaType' => null,
						'name' => null,
						'width' => 0,
						'height' => 0,
						'blurhash' => null,
					);

					$attachment = array_merge( $default_attachment, $attachment );

					$media_attachment = new Media_Attachment();
					$media_attachment->id = $attachment['url'];
					$media_attachment->type = strtok( $attachment['mediaType'], '/' );
					$media_attachment->url = $attachment['url'];
					$media_attachment->preview_url = $attachment['url'];
					$media_attachment->description = $attachment['name'];
					if ( $attachment['blurhash'] ) {
						$media_attachment->blurhash = $attachment['blurhash'];
					}
					if ( $attachment['width'] > 0 && $attachment['height'] > 0 ) {
						$media_attachment->meta = array(
							'original' => array(
								'width'  => $attachment['width'],
								'height' => $attachment['height'],
								'size'   => $attachment['width'] . 'x' . $attachment['height'],
								'aspect' => $attachment['width'] / $attachment['height'],
							),
						);}
					return $media_attachment;
				},
				$object['attachment']
			);
		}

		return $status;
	}

	public static function api_statuses_external( $statuses, $args ) {
		if ( ! isset( $args['activitypub'] ) ) {
			return $statuses;
		}

		$data = get_remote_metadata_by_actor( $args['activitypub'] );

		if ( ! $data || is_wp_error( $data ) || ! isset( $data['outbox'] ) ) {
			return $statuses;
		}

		$outbox = Http::get_remote_object( $data['outbox'], true );
		if ( is_wp_error( $outbox ) || ! isset( $outbox['first'] ) ) {
			return $statuses;
		}

		$account = self::get_account_for_actor( $args['activitypub'] );
		if ( ! $account ) {
			return $statuses;
		}
		$limit = 10;
		if ( isset( $args['posts_per_page'] ) ) {
			$limit = $args['posts_per_page'];
		}
		if ( $limit > 40 ) {
			$limit = 40;
		}
		$activitypub_statuses = array();
		$url = $outbox['first'];
		$tries = 0;
		while ( $url ) {
			if ( ++$tries > 3 ) {
				break;
			}

			$posts = Http::get_remote_object( $url, true );
			if ( is_wp_error( $posts ) ) {
				return $statuses;
			}

			$new_statuses = array_map(
				function ( $item ) use ( $account, $args ) {
					if ( $args['exclude_replies'] ) {
						if ( isset( $item['object']['inReplyTo'] ) && $item['object']['inReplyTo'] ) {
							return null;
						}
					}
					return self::activity_to_status( $item, $account );
				},
				$posts['orderedItems']
			);
			$activitypub_statuses = array_merge( $activitypub_statuses, array_filter( $new_statuses ) );
			$url = $posts['next'];

			if ( count( $activitypub_statuses ) >= $limit ) {
				break;
			}
		}

		return array_slice( $activitypub_statuses, 0, $limit );
	}

	public static function api_get_replies( $context, $post_id, $url ) {
		$meta = Http::get_remote_object( $url, true );
		if ( is_wp_error( $meta ) || ! isset( $meta['replies']['first']['next'] ) ) {
			return $context;
		}

		$replies_url = $meta['replies']['first']['next'];
		$replies = Http::get_remote_object( $replies_url, true );
		if ( is_wp_error( $replies ) || ! isset( $replies['items'] ) ) {
			return $context;
		}

		foreach ( $replies['items'] as $url ) {
			$response = Http::get( $url, true );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				continue;
			}
			$status = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! $status || is_wp_error( $status ) ) {
				continue;
			}

			$account = self::get_account_for_actor( $status['attributedTo'] );
			$status = self::activity_to_status( $status, $account );
			if ( $status ) {
				$context['descendants'][ $status->id ] = $status;
			}
		}

		return $context;
	}
}
