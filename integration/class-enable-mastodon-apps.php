<?php
/**
 * Enable Mastodon Apps integration class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;
use Activitypub\Mention;
use Activitypub\Transformer\Factory;
use Activitypub\Webfinger as Webfinger_Util;
use Enable_Mastodon_Apps\Entity\Account;
use Enable_Mastodon_Apps\Entity\Media_Attachment;
use Enable_Mastodon_Apps\Entity\Notification;
use Enable_Mastodon_Apps\Entity\Status;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\is_user_type_disabled;

/**
 * Class Enable_Mastodon_Apps.
 *
 * This class is used to enable Mastodon Apps to work with ActivityPub.
 *
 * @see https://github.com/akirk/enable-mastodon-apps
 */
class Enable_Mastodon_Apps {
	/**
	 * Default limit for notifications.
	 *
	 * @var int
	 */
	const DEFAULT_NOTIFICATION_LIMIT = 15;

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'mastodon_api_account_followers', array( self::class, 'api_account_followers' ), 10, 2 );
		\add_filter( 'mastodon_api_account', array( self::class, 'api_account_external' ), 15, 2 );
		\add_filter( 'mastodon_api_account', array( self::class, 'api_account_internal' ), 9, 2 );
		\add_filter( 'mastodon_api_status', array( self::class, 'api_status' ), 9, 2 );
		\add_filter( 'mastodon_api_search', array( self::class, 'api_search' ), 40, 2 );
		\add_filter( 'mastodon_api_search', array( self::class, 'api_search_by_url' ), 40, 2 );
		\add_filter( 'mastodon_api_get_posts_query_args', array( self::class, 'api_get_posts_query_args' ) );
		\add_filter( 'mastodon_api_statuses', array( self::class, 'api_statuses_external' ), 10, 2 );
		\add_filter( 'mastodon_api_status_context', array( self::class, 'api_get_replies' ), 10, 3 );
		\add_filter( 'mastodon_api_update_credentials', array( self::class, 'api_update_credentials' ), 10, 2 );
		\add_filter( 'mastodon_api_submit_status_text', array( Mention::class, 'the_content' ) );
		\add_filter( 'mastodon_api_notifications_get', array( self::class, 'api_notifications_get' ), 10, 2 );
	}

	/**
	 * Map user to blog if user is disabled.
	 *
	 * @param int $user_id The user id.
	 *
	 * @return int The user id.
	 */
	public static function maybe_map_user_to_blog( $user_id ) {
		if (
			is_user_type_disabled( 'user' ) &&
			! is_user_type_disabled( 'blog' ) &&
			// Check if the blog user is permissible for this user.
			user_can( $user_id, 'activitypub' )
		) {
			return Actors::BLOG_USER_ID;
		}

		return $user_id;
	}

	/**
	 * Update profile data for Mastodon API.
	 *
	 * @param array $data    The data to act on.
	 * @param int   $user_id The user id.
	 * @return array         The possibly-filtered data (data that's saved gets unset from the array).
	 */
	public static function api_update_credentials( $data, $user_id ) {
		if ( empty( $user_id ) ) {
			return $data;
		}

		$user_id = self::maybe_map_user_to_blog( $user_id );
		$user    = Actors::get_by_id( $user_id );
		if ( ! $user || is_wp_error( $user ) ) {
			return $data;
		}

		// User::update_icon and other update_* methods check data validity, so we don't need to do it here.
		if ( isset( $data['avatar'] ) && $user->update_icon( $data['avatar'] ) ) {
			// Unset the avatar so it doesn't get saved again by other plugins.
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
			self::set_extra_fields( $user_id, $data['fields_attributes'] );
			unset( $data['fields_attributes'] );
		}

		return $data;
	}

	/**
	 * Get extra fields for Mastodon API.
	 *
	 * @param int $user_id The user id to act on.
	 * @return array The extra fields.
	 */
	private static function get_extra_fields( $user_id ) {
		$ret    = array();
		$fields = Extra_Fields::get_actor_fields( $user_id );

		foreach ( $fields as $field ) {
			$ret[] = array(
				'name'  => $field->post_title,
				'value' => Extra_Fields::get_formatted_content( $field ),
			);
		}

		return $ret;
	}

	/**
	 * Set extra fields for Mastodon API.
	 *
	 * @param int   $user_id The user id to act on.
	 * @param array $fields The fields to set. It is assumed to be the entire set of desired fields.
	 */
	private static function set_extra_fields( $user_id, $fields ) {
		// The Mastodon API submits a simple hash for every field.
		// We can reasonably assume a similar order for our operations below.
		$ids       = wp_list_pluck( Extra_Fields::get_actor_fields( $user_id ), 'ID' );
		$is_blog   = Actors::BLOG_USER_ID === $user_id;
		$post_type = $is_blog ? Extra_Fields::BLOG_POST_TYPE : Extra_Fields::USER_POST_TYPE;

		foreach ( $fields as $i => $field ) {
			$post_id  = $ids[ $i ] ?? null;
			$has_post = $post_id && \get_post( $post_id );
			$args     = array(
				'post_title'   => $field['name'],
				'post_content' => Extra_Fields::make_paragraph_block( $field['value'] ),
			);

			if ( $has_post ) {
				$args['ID'] = $ids[ $i ];
				\wp_update_post( $args );
			} else {
				$args['post_type']   = $post_type;
				$args['post_status'] = 'publish';
				if ( ! $is_blog ) {
					$args['post_author'] = $user_id;
				}
				\wp_insert_post( $args );
			}
		}

		// Delete any remaining fields.
		if ( \count( $fields ) < \count( $ids ) ) {
			$to_delete = \array_slice( $ids, \count( $fields ) );
			foreach ( $to_delete as $id ) {
				\wp_delete_post( $id, true );
			}
		}
	}

	/**
	 * Add followers to Mastodon API.
	 *
	 * @param array  $followers An array of followers.
	 * @param string $user_id   The user id.
	 *
	 * @return array The filtered followers
	 */
	public static function api_account_followers( $followers, $user_id ) {
		$user_id               = self::maybe_map_user_to_blog( $user_id );
		$activitypub_followers = Followers::get_many( $user_id, 40 );
		$mastodon_followers    = array();

		foreach ( $activitypub_followers as $follower ) {
			$actor = Remote_Actors::get_actor( $follower );
			if ( ! $actor || \is_wp_error( $actor ) ) {
				continue;
			}

			$account = self::actor_to_account( $actor );

			$account->followers_count = 0;
			$account->following_count = 0;
			$account->statuses_count  = 0;
			$account->bot             = false;
			$account->locked          = false;
			$account->group           = false;
			$account->discoverable    = false;
			$account->noindex         = false;
			$account->fields          = array();
			$account->emojis          = array();

			$mastodon_followers[] = $account;
		}

		return array_merge( $mastodon_followers, $followers );
	}

	/**
	 * Resolve external accounts for Mastodon API
	 *
	 * @param Account $user_data The user data.
	 * @param string  $user_id   The user id.
	 *
	 * @return Account The filtered Account.
	 */
	public static function api_account_external( $user_data, $user_id ) {
		if ( $user_data || ( is_numeric( $user_id ) && $user_id ) ) {
			// Only augment.
			return $user_data;
		}

		$user = Actors::get_by_various( $user_id );

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

	/**
	 * Resolve internal accounts for Mastodon API
	 *
	 * @param Account $user_data The user data.
	 * @param string  $user_id   The user id.
	 *
	 * @return Account The filtered Account.
	 */
	public static function api_account_internal( $user_data, $user_id ) {
		$user_id_to_use = self::maybe_map_user_to_blog( $user_id );
		$user           = Actors::get_by_id( $user_id_to_use );

		if ( ! $user || is_wp_error( $user ) ) {
			return $user_data;
		}

		// Convert user to account.
		$account = new Account();
		// Even if we have a blog user, maintain the provided user_id so as not to confuse clients.
		$account->id             = (int) $user_id;
		$account->username       = $user->get_preferred_username();
		$account->acct           = $account->username;
		$account->display_name   = $user->get_name();
		$account->note           = $user->get_summary();
		$account->source['note'] = wp_strip_all_tags( $account->note, true );
		$account->url            = $user->get_url();

		$icon                   = $user->get_icon();
		$account->avatar        = $icon['url'];
		$account->avatar_static = $account->avatar;

		$header = $user->get_image();
		if ( $header ) {
			$account->header        = $header['url'];
			$account->header_static = $account->header;
		}

		$account->created_at = new \DateTime( $user->get_published() );

		$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );
		$query_args = array(
			'post_type'      => $post_types,
			'posts_per_page' => 1,
		);
		if ( $user_id > 0 ) {
			$query_args['author'] = $user_id;
		}
		$posts                   = \get_posts( $query_args );
		$account->last_status_at = ! empty( $posts ) ? new \DateTime( $posts[0]->post_date_gmt ) : $account->created_at;

		$account->fields = self::get_extra_fields( $user_id_to_use );
		// Now do it in source['fields'] with stripped tags.
		$account->source['fields'] = \array_map(
			static function ( $field ) {
				$field['value'] = \wp_strip_all_tags( $field['value'], true );
				return $field;
			},
			$account->fields
		);

		$account->followers_count = Followers::count( $user_id );

		return $account;
	}

	/**
	 * Use our representation of posts to power each status item.
	 * Includes proper referencing of 3rd party comments that arrived via federation.
	 *
	 * @param null|Status $status The status, typically null to allow later filters their shot.
	 * @param int         $post_id The post ID.
	 * @return Status|null The status.
	 */
	public static function api_status( $status, $post_id ) {
		$post = \get_post( $post_id );
		if ( ! $post ) {
			return $status;
		}

		return self::api_post_status( $post_id );
	}

	/**
	 * Transforms a WordPress post into a Mastodon-compatible status object.
	 *
	 * Takes a post ID, transforms it into an ActivityPub object, and converts
	 * it to a Mastodon API status format including the author's account info.
	 *
	 * @param int $post_id The WordPress post ID to transform.
	 * @return Status|null The Mastodon API status object, or null if the post is not found
	 */
	private static function api_post_status( $post_id ) {
		$post = Factory::get_transformer( get_post( $post_id ) );
		if ( is_wp_error( $post ) ) {
			return null;
		}

		$data    = $post->to_object()->to_array();
		$account = self::api_account_internal( null, get_post_field( 'post_author', $post_id ) );

		return self::activity_to_status( $data, $account, $post_id );
	}

	/**
	 * Get account for actor.
	 *
	 * @param string|Actor $actor_or_uri The Actor object or URI.
	 *
	 * @return Account|null The account.
	 */
	private static function get_account_for_actor( $actor_or_uri ) {
		// If it's already an Actor object, use it directly.
		if ( $actor_or_uri instanceof Actor ) {
			return self::actor_to_account( $actor_or_uri );
		}

		if ( ! \is_string( $actor_or_uri ) || empty( $actor_or_uri ) ) {
			return null;
		}

		// Fetch actor from cache or remote.
		$actor_post = Remote_Actors::fetch_by_uri( $actor_or_uri );
		if ( ! $actor_post || \is_wp_error( $actor_post ) ) {
			return null;
		}

		$actor = Remote_Actors::get_actor( $actor_post );
		if ( ! $actor || \is_wp_error( $actor ) ) {
			return null;
		}

		return self::actor_to_account( $actor );
	}

	/**
	 * Convert an Actor object to an Account.
	 *
	 * @param Actor $actor The actor object.
	 *
	 * @return Account The account.
	 */
	private static function actor_to_account( $actor ) {
		$account = new Account();

		$actor_id = $actor->get__id();
		if ( ! $actor_id ) {
			$actor_id = $actor->get_id();
		}

		$account->id           = \strval( $actor_id );
		$account->username     = $actor->get_preferred_username();
		$account->acct         = $actor->get_webfinger();
		$account->display_name = $actor->get_name();
		$account->url          = $actor->get_url();
		$account->created_at   = new \DateTime( 'now' );

		$icon   = $actor->get_icon();
		$avatar = null;
		if ( $icon ) {
			if ( \is_array( $icon ) && isset( $icon['url'] ) ) {
				$avatar = $icon['url'];
			} elseif ( \is_string( $icon ) ) {
				$avatar = $icon;
			}
		}
		if ( $avatar ) {
			$account->avatar        = $avatar;
			$account->avatar_static = $avatar;
		}

		$summary = $actor->get_summary();
		if ( $summary ) {
			$account->note = $summary;
		}

		$image  = $actor->get_image();
		$header = null;
		if ( $image ) {
			if ( \is_array( $image ) && isset( $image['url'] ) ) {
				$header = $image['url'];
			} elseif ( \is_string( $image ) ) {
				$header = $image;
			}
		}
		if ( $header ) {
			$account->header        = $header;
			$account->header_static = $header;
		}

		$published = $actor->get_published();
		if ( $published ) {
			$account->created_at = new \DateTime( $published );
		}

		return $account;
	}

	/**
	 * Search by URL for Mastodon API.
	 *
	 * @param array  $search_data The search data.
	 * @param object $request     The request object.
	 *
	 * @return array The filtered search data.
	 */
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

	/**
	 * Search for Mastodon API.
	 *
	 * @param array  $search_data The search data.
	 * @param object $request     The request object.
	 *
	 * @return array The filtered search data.
	 */
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

		$followers = Followers::get_many( $user_id, 40, null, array( 's' => $q ) );
		if ( ! $followers ) {
			return $search_data;
		}

		foreach ( $followers as $follower ) {
			$actor = Remote_Actors::get_actor( $follower );
			if ( ! $actor || \is_wp_error( $actor ) ) {
				continue;
			}

			$account = self::actor_to_account( $actor );

			$account->uri = $actor->get_id();

			$search_data['accounts'][] = $account;
		}

		return $search_data;
	}

	/**
	 * Get posts query args for Mastodon API.
	 *
	 * @param array $args The query arguments.
	 *
	 * @return array The filtered args.
	 */
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

	/**
	 * Convert an activity to a status.
	 *
	 * @param array   $item    The activity.
	 * @param Account $account The account.
	 * @param int     $post_id The post ID. Optional, but will be preferred in the Status.
	 *
	 * @return Status|null The status.
	 */
	private static function activity_to_status( $item, $account, $post_id = null ) {
		if ( isset( $item['object'] ) ) {
			$object = $item['object'];
		} else {
			$object = $item;
		}

		if ( ! isset( $object['type'] ) || ! in_array( $object['type'], array( 'Article', 'Note' ), true ) || ! $account ) {
			return null;
		}

		$status             = new Status();
		$status->id         = $post_id ?? $object['id'];
		$status->created_at = new \DateTime( $object['published'] );
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
				static function ( $attachment ) {
					$default_attachment = array(
						'url'       => null,
						'mediaType' => null,
						'name'      => null,
						'width'     => 0,
						'height'    => 0,
						'blurhash'  => null,
					);

					$attachment = array_merge( $default_attachment, $attachment );

					$media_attachment              = new Media_Attachment();
					$media_attachment->id          = $attachment['url'];
					$media_attachment->type        = strtok( $attachment['mediaType'], '/' );
					$media_attachment->url         = $attachment['url'];
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

	/**
	 * Get posts for Mastodon API.
	 *
	 * @param array $statuses The statuses.
	 * @param array $args     The arguments.
	 *
	 * @return array The filtered statuses.
	 */
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
		$url                  = $outbox['first'];
		$tries                = 0;
		while ( $url ) {
			if ( ++$tries > 3 ) {
				break;
			}

			$posts = Http::get_remote_object( $url, true );
			if ( is_wp_error( $posts ) ) {
				return $statuses;
			}

			$new_statuses         = array_map(
				static function ( $item ) use ( $account, $args ) {
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
			$url                  = $posts['next'] ?? null;

			if ( count( $activitypub_statuses ) >= $limit ) {
				break;
			}
		}

		return array_slice( $activitypub_statuses, 0, $limit );
	}

	/**
	 * Get replies for Mastodon API.
	 *
	 * @param array  $context The context.
	 * @param int    $post_id The post id.
	 * @param string $url     The URL.
	 *
	 * @return array The filtered context.
	 */
	public static function api_get_replies( $context, $post_id, $url ) {
		$meta = Http::get_remote_object( $url, true );
		if ( is_wp_error( $meta ) || ! isset( $meta['replies']['first']['next'] ) ) {
			return $context;
		}

		if ( ! empty( $meta['replies']['first']['items'] ) ) {
			$replies = $meta['replies']['first'];
		} elseif ( isset( $meta['replies']['first']['next'] ) ) {
			$replies_url = $meta['replies']['first']['next'];
			$replies     = Http::get_remote_object( $replies_url, true );
			if ( is_wp_error( $replies ) || ! isset( $replies['items'] ) ) {
				return $context;
			}
		} else {
			return $context;
		}

		foreach ( $replies['items'] as $reply ) {
			if ( isset( $reply['id'] ) && is_string( $reply['id'] ) && isset( $reply['content'] ) && is_string( $reply['content'] ) ) {
				$status = $reply;
			} else {
				if ( is_string( $reply ) ) {
					$url = $reply;
				} elseif ( isset( $reply['url'] ) && is_string( $reply['url'] ) ) {
					$url = $reply['url'];
				} else {
					continue;
				}
				$response = Http::get( $url, array(), true );
				if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
					continue;
				}
				$status = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! $status || is_wp_error( $status ) ) {
					continue;
				}
			}

			$account = self::get_account_for_actor( $status['attributedTo'] );
			$status  = self::activity_to_status( $status, $account );
			if ( $status ) {
				$context['descendants'][ $status->id ] = $status;
			}
		}

		return $context;
	}

	/**
	 * Add repost and like notifications from ActivityPub comments.
	 *
	 * @param array  $notifications The notifications array.
	 * @param object $request       The request object.
	 *
	 * @return array The filtered notifications.
	 */
	public static function api_notifications_get( $notifications, $request ) {
		$types         = $request->get_param( 'types' );
		$exclude_types = $request->get_param( 'exclude_types' );

		$include_reblog    = ( ! \is_array( $types ) || \in_array( 'reblog', $types, true ) ) &&
							( ! \is_array( $exclude_types ) || ! \in_array( 'reblog', $exclude_types, true ) );
		$include_favourite = ( ! \is_array( $types ) || \in_array( 'favourite', $types, true ) ) &&
							( ! \is_array( $exclude_types ) || ! \in_array( 'favourite', $exclude_types, true ) );
		$include_follow    = ( ! \is_array( $types ) || \in_array( 'follow', $types, true ) ) &&
							( ! \is_array( $exclude_types ) || ! \in_array( 'follow', $exclude_types, true ) );

		if ( ! $include_reblog && ! $include_favourite && ! $include_follow ) {
			return $notifications;
		}

		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return $notifications;
		}

		$limit = $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : self::DEFAULT_NOTIFICATION_LIMIT;

		if ( ! \class_exists( Notification::class ) ) {
			return $notifications;
		}

		// Get reblog/favourite notifications from comments.
		if ( $include_reblog || $include_favourite ) {
			$comment_types = array();
			if ( $include_reblog ) {
				$comment_types[] = 'repost';
			}
			if ( $include_favourite ) {
				$comment_types[] = 'like';
			}

			$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );

			$comments = \get_comments(
				array(
					'post_author' => $user_id,
					'post_type'   => $post_types,
					'type__in'    => $comment_types,
					'number'      => $limit,
					'orderby'     => 'comment_date',
					'order'       => 'DESC',
				)
			);

			foreach ( $comments as $comment ) {
				$type = 'repost' === $comment->comment_type ? 'reblog' : 'favourite';

				$account = self::get_account_for_comment( $comment );
				if ( ! $account ) {
					continue;
				}

				$status = self::api_post_status( $comment->comment_post_ID );
				if ( ! $status ) {
					continue;
				}

				$notification             = new Notification();
				$notification->id         = \strval( $comment->comment_ID );
				$notification->type       = $type;
				$notification->created_at = \mysql2date( 'Y-m-d\TH:i:s.000P', $comment->comment_date, false );
				$notification->account    = $account;
				$notification->status     = $status;

				$notifications[] = $notification;
			}
		}

		// Get follow notifications from followers.
		if ( $include_follow ) {
			$notifications = self::add_follow_notifications( $notifications, $user_id, $limit );
		}

		// Sort by date descending.
		\usort(
			$notifications,
			static function ( $a, $b ) {
				$a_date = \is_array( $a ) ? $a['created_at'] : $a->created_at;
				$b_date = \is_array( $b ) ? $b['created_at'] : $b->created_at;
				return \strcmp( $b_date, $a_date );
			}
		);

		return \array_slice( $notifications, 0, $limit );
	}

	/**
	 * Add follow notifications from ActivityPub followers.
	 *
	 * @param array $notifications The notifications array.
	 * @param int   $user_id       The user ID.
	 * @param int   $limit         The limit.
	 *
	 * @return array The notifications array with follow notifications added.
	 */
	private static function add_follow_notifications( $notifications, $user_id, $limit ) {
		$user_id = self::maybe_map_user_to_blog( $user_id );

		$followers = Followers::get_many(
			$user_id,
			$limit,
			null,
			array(
				'orderby' => 'post_date',
				'order'   => 'DESC',
			)
		);

		foreach ( $followers as $follower ) {
			$actor = Remote_Actors::get_actor( $follower );
			if ( ! $actor || \is_wp_error( $actor ) ) {
				continue;
			}

			$account = self::get_account_for_actor( $actor );
			if ( ! $account ) {
				continue;
			}

			$notification             = new Notification();
			$notification->id         = \strval( $follower->ID );
			$notification->type       = 'follow';
			$notification->created_at = \mysql2date( 'Y-m-d\TH:i:s.000P', $follower->post_date, false );
			$notification->account    = $account;

			$notifications[] = $notification;
		}

		return $notifications;
	}

	/**
	 * Get account for a comment from cached data.
	 *
	 * @param object $comment The comment object.
	 *
	 * @return Account|null The account.
	 */
	private static function get_account_for_comment( $comment ) {
		$default_avatar = \get_avatar_url( $comment->comment_author_email ?: '', array( 'size' => 96 ) );

		// Try to get cached remote actor data.
		$remote_actor_id = \get_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', true );
		if ( $remote_actor_id ) {
			$actor = Remote_Actors::get_actor( $remote_actor_id );
			if ( $actor && ! \is_wp_error( $actor ) ) {
				$account = self::actor_to_account( $actor );

				// Use remote actor post ID as account ID.
				$account->id = \strval( $remote_actor_id );

				// Use default avatar if actor has none.
				if ( empty( $account->avatar ) ) {
					$account->avatar        = $default_avatar;
					$account->avatar_static = $default_avatar;
				}

				return $account;
			}
		}

		// Fallback to comment author data.
		if ( empty( $comment->comment_author_url ) ) {
			return null;
		}

		$account                = new Account();
		$account->id            = $comment->comment_author_url;
		$account->username      = $comment->comment_author;
		$account->acct          = $comment->comment_author_email ?: $comment->comment_author;
		$account->display_name  = $comment->comment_author;
		$account->url           = $comment->comment_author_url;
		$account->avatar        = $default_avatar;
		$account->avatar_static = $default_avatar;
		$account->created_at    = new \DateTime( $comment->comment_date );

		return $account;
	}
}
