<?php
/**
 * Stream Connector integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Actors;

use function Activitypub\url_to_authorid;
use function Activitypub\url_to_commentid;

/**
 * Stream Connector for ActivityPub.
 *
 * This class is a Stream Connector for the Stream plugin.
 *
 * @see https://wordpress.org/plugins/stream/
 */
class Stream_Connector extends \WP_Stream\Connector {
	/**
	 * Connector slug.
	 *
	 * @var string
	 */
	public $name = 'activitypub';

	/**
	 * Actions registered for this connector.
	 *
	 * @var array
	 */
	public $actions = array(
		'activitypub_handled_follow',
		'activitypub_sent_to_inbox',
		'activitypub_outbox_processing_complete',
		'activitypub_outbox_processing_batch_complete',
	);

	/**
	 * Return translated connector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'ActivityPub', 'activitypub' );
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array
	 */
	public function get_context_labels() {
		return array();
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array
	 */
	public function get_action_labels() {
		return array(
			'processed' => __( 'Processed', 'activitypub' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'processed' === $record->action ) {
			$error = json_decode( $record->get_meta( 'error', true ), true );

			if ( $error ) {
				$message = sprintf(
					'<details><summary>%1$s</summary><pre>%2$s</pre></details>',
					__( 'Inbox Error', 'activitypub' ),
					wp_json_encode( $error )
				);

				$links[ $message ] = '';
			}

			$debug = json_decode( $record->get_meta( 'debug', true ), true );

			if ( $debug ) {
				$message = sprintf(
					'<details><summary>%1$s</summary><pre>%2$s</pre></details>',
					__( 'Debug', 'activitypub' ),
					wp_json_encode( $debug )
				);

				$links[ $message ] = '';
			}
		}

		return $links;
	}

	/**
	 * Callback for activitypub_handled_follow.
	 *
	 * @param array         $activity     The ActivityPub activity data.
	 * @param int|null      $user_id      The local user ID, or null if not applicable.
	 * @param mixed         $state        Status or WP_Error object indicating the result of the follow handling.
	 * @param \WP_Post|null $context   The WP_Post object representing the remote actor/follower.
	 */
	public function callback_activitypub_handled_follow( $activity, $user_id, $state, $context ) {
		$actor_url = \is_object( $context ) && ! \is_wp_error( $context ) ? $context->guid : $activity['actor'];

		$this->log(
			\sprintf(
				// translators: %s is a URL.
				\__( 'New Follower: %s', 'activitypub' ),
				$actor_url
			),
			array(
				'activity'     => \wp_json_encode( $activity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'remote_actor' => \wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			),
			null,
			'notification',
			'follow',
			$user_id
		);
	}

	/**
	 * Callback for activitypub_outbox_processing_complete.
	 *
	 * @param array  $inboxes        The inboxes.
	 * @param string $json           The ActivityPub Activity JSON.
	 * @param int    $actor_id       The actor ID.
	 * @param int    $outbox_item_id The Outbox item ID.
	 */
	public function callback_activitypub_outbox_processing_complete( $inboxes, $json, $actor_id, $outbox_item_id ) {
		$outbox_item = \get_post( $outbox_item_id );
		$outbox_data = $this->prepare_outbox_data_for_response( $outbox_item );

		$this->log(
			sprintf(
				// translators: %s is a URL.
				__( 'Outbox processing complete: %s', 'activitypub' ),
				$outbox_data['title']
			),
			array(
				'debug' => wp_json_encode(
					array(
						'actor_id'       => $actor_id,
						'outbox_item_id' => $outbox_item_id,
					)
				),
			),
			$outbox_data['id'],
			$outbox_data['type'],
			'processed'
		);
	}

	/**
	 * Callback for activitypub_outbox_processing_batch_complete.
	 *
	 * @param array  $inboxes The inboxes.
	 * @param string $json The ActivityPub Activity JSON.
	 * @param int    $actor_id The actor ID.
	 * @param int    $outbox_item_id The Outbox item ID.
	 * @param int    $batch_size The batch size.
	 * @param int    $offset The offset.
	 */
	public function callback_activitypub_outbox_processing_batch_complete( $inboxes, $json, $actor_id, $outbox_item_id, $batch_size, $offset ) {
		$outbox_item = \get_post( $outbox_item_id );
		$outbox_data = $this->prepare_outbox_data_for_response( $outbox_item );

		$this->log(
			sprintf(
				// translators: %s is a URL.
				__( 'Outbox processing batch complete: %s', 'activitypub' ),
				$outbox_data['title']
			),
			array(
				'debug' => wp_json_encode(
					array(
						'actor_id'       => $actor_id,
						'outbox_item_id' => $outbox_item_id,
						'batch_size'     => $batch_size,
						'offset'         => $offset,
					)
				),
			),
			$outbox_data['id'],
			$outbox_data['type'],
			'processed'
		);
	}

	/**
	 * Get the title of the outbox object.
	 *
	 * @param \WP_Post $outbox_item The outbox item.
	 *
	 * @return array The title, object ID, and object type of the outbox object.
	 */
	protected function prepare_outbox_data_for_response( $outbox_item ) {
		$object_id    = $outbox_item->ID;
		$object_type  = $outbox_item->post_type;
		$object_title = $outbox_item->post_title;

		$post_id = url_to_postid( $outbox_item->post_title );
		if ( $post_id ) {
			$post = get_post( $post_id );

			$object_id    = $post_id;
			$object_type  = $post->post_type;
			$object_title = $post->post_title;
		} else {
			$comment_id = url_to_commentid( $outbox_item->post_title );
			if ( $comment_id ) {
				$comment = get_comment( $comment_id );

				$object_id    = $comment_id;
				$object_type  = 'comments';
				$object_title = $comment->comment_content;
			} else {
				$author_id = url_to_authorid( $outbox_item->post_title );
				if ( null !== $author_id ) {
					$object_id   = $author_id;
					$object_type = 'profiles';

					if ( $author_id ) {
						$object_title = get_userdata( $author_id )->display_name;
					} elseif ( Actors::BLOG_USER_ID === $author_id ) {
						$object_title = __( 'Blog User', 'activitypub' );
					} elseif ( Actors::APPLICATION_USER_ID === $author_id ) {
						$object_title = __( 'Application User', 'activitypub' );
					}
				}
			}
		}

		return array(
			'id'    => $object_id,
			'type'  => $object_type,
			'title' => $object_title,
		);
	}
}
