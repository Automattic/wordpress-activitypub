<?php
/**
 * Media Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Attachments;
use Activitypub\Collection\Actors;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\Transformer\Base;

use function Activitypub\get_attachment_ap_id;

/**
 * ActivityPub Media Controller.
 *
 * Implements the SocialCG `uploadMedia` endpoint and serves the
 * canonical AP-JSON representation of a WordPress attachment.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/MediaUpload
 * @see https://github.com/swicg/activitypub-api/issues/6
 */
class Media_Controller extends \WP_REST_Controller {
	use Verification;

	/**
	 * Allowed top-level media types.
	 *
	 * @var string[]
	 */
	const ALLOWED_TOP_LEVEL_TYPES = array( 'image', 'audio', 'video' );

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/(?:users|actors)/(?P<user_id>[-]?\d+)/uploadMedia',
			array(
				'args' => array(
					'user_id' => array(
						'description'       => 'The ID of the user or actor.',
						'type'              => 'integer',
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_item' ),
					'permission_callback' => array( $this, 'upload_permissions_check' ),
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/media/(?P<attachment_id>\d+)',
			array(
				'args' => array(
					'attachment_id' => array(
						'description' => 'The ID of the WordPress attachment.',
						'type'        => 'integer',
						'minimum'     => 1,
						'required'    => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Validate that a user_id resolves to a known actor.
	 *
	 * Mirrors `Outbox_Controller::validate_user_id()` so callers cannot
	 * upload on behalf of an actor that the current actor-mode doesn't allow.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_user_id( $value ) {
		$actor = Actors::get_by_id( $value );
		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}
		return true;
	}

	/**
	 * Permission callback for the upload route.
	 *
	 * Requires the `upload` OAuth scope (in addition to a valid Bearer token)
	 * and that the authenticated user matches the `user_id` in the URL.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function upload_permissions_check( $request ) {
		$result = OAuth_Server::check_oauth_permission( $request, Scope::UPLOAD );
		if ( true !== $result ) {
			return $result;
		}

		$owner_check = $this->verify_owner( $request );
		if ( true !== $owner_check ) {
			return $owner_check;
		}

		if ( ! \current_user_can( 'upload_files' ) ) {
			return new \WP_Error(
				'activitypub_cannot_upload',
				\__( 'You do not have permission to upload media.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /media/{attachment_id} — return the AP representation of an attachment.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function get_item( $request ) {
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$object        = $this->build_attachment_object( $attachment_id );

		if ( \is_wp_error( $object ) ) {
			return $object;
		}

		$response = new \WP_REST_Response( $object, 200 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );
		return $response;
	}

	/**
	 * POST /actors/{user_id}/uploadMedia — accept a multipart upload.
	 *
	 * Accepts the W3C-wiki shape (`object` JSON + `file` binary) and the
	 * Pleroma shape (just `file`). Always returns the bare media object;
	 * never auto-wraps in a Create. Per the wiki spec the endpoint is not
	 * the outbox, so the client is responsible for any follow-up publish.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function upload_item( $request ) {
		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) ? $files['file'] : null;

		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return new \WP_Error(
				'activitypub_missing_file',
				\__( 'A "file" part is required.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Optional `object` part: a JSON-LD shell that supplies name (alt text), etc.
		$shell      = array();
		$raw_object = $request->get_param( 'object' );
		if ( ! empty( $raw_object ) ) {
			$decoded = \json_decode( $raw_object, true );
			if ( ! \is_array( $decoded ) ) {
				return new \WP_Error(
					'activitypub_invalid_object',
					\__( 'The "object" part must be valid JSON.', 'activitypub' ),
					array( 'status' => 400 )
				);
			}
			$shell = $decoded;
		}

		// Pleroma-style: a top-level `description` form field is a synonym for `object.name`.
		if ( empty( $shell['name'] ) ) {
			$description = $request->get_param( 'description' );
			if ( ! empty( $description ) && \is_string( $description ) ) {
				$shell['name'] = $description;
			}
		}

		// Run through wp_handle_upload to apply WP's MIME validation and upload_mimes filter.
		if ( ! \function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$overrides = array(
			'test_form' => false,
			'action'    => 'activitypub_upload_media',
		);

		$uploaded = \wp_handle_upload( $file, $overrides );

		if ( isset( $uploaded['error'] ) ) {
			return new \WP_Error(
				'activitypub_upload_failed',
				\sanitize_text_field( (string) $uploaded['error'] ),
				array( 'status' => 400 )
			);
		}

		$top_level = \strtok( (string) $uploaded['type'], '/' );
		if ( ! \in_array( $top_level, self::ALLOWED_TOP_LEVEL_TYPES, true ) ) {
			\wp_delete_file( $uploaded['file'] );
			return new \WP_Error(
				'activitypub_unsupported_media_type',
				\__( 'Unsupported media type.', 'activitypub' ),
				array( 'status' => 415 )
			);
		}

		// Apply the same image-optimization the import path uses
		// (resize + WebP conversion when supported).
		$optimized = Attachments::optimize_image( $uploaded['file'], Attachments::MAX_IMAGE_DIMENSION );
		if ( $optimized !== $uploaded['file'] ) {
			$uploaded['file'] = $optimized;
			$uploaded['type'] = \wp_check_filetype( $optimized )['type'] ?? $uploaded['type'];
		}

		// Insert the file as a media library attachment.
		$user_id = (int) $request->get_param( 'user_id' );
		// For the blog actor (user_id = 0) fall back to the acting user so post_author is never 0.
		$author     = $user_id > 0 ? $user_id : \get_current_user_id();
		$title      = isset( $shell['name'] ) ? \sanitize_text_field( $shell['name'] ) : \wp_basename( $uploaded['file'] );
		$attachment = array(
			'post_mime_type' => $uploaded['type'],
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $author,
		);

		$attachment_id = \wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( \is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			\wp_delete_file( $uploaded['file'] );
			return new \WP_Error(
				'activitypub_attachment_insert_failed',
				\__( 'Failed to register uploaded file as a media library attachment.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Generate metadata (dimensions, intermediate sizes, etc.).
		if ( ! \function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		\wp_update_attachment_metadata(
			$attachment_id,
			\wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] )
		);

		// If the shell carries a name (typically alt text for images), store it.
		if ( ! empty( $shell['name'] ) && 'image' === $top_level ) {
			\update_post_meta( $attachment_id, '_wp_attachment_image_alt', \sanitize_text_field( $shell['name'] ) );
		}

		$object = $this->build_attachment_object( $attachment_id );

		if ( \is_wp_error( $object ) ) {
			\wp_delete_attachment( $attachment_id, true );
			return $object;
		}

		$response = new \WP_REST_Response( $object, 201 );
		$response->header( 'Location', $object['id'] );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );
		return $response;
	}

	/**
	 * Build the AP object for a given attachment.
	 *
	 * @param int $attachment_id The WordPress attachment ID.
	 * @return array|\WP_Error AP object on success, error otherwise.
	 */
	protected function build_attachment_object( $attachment_id ) {
		if ( 'attachment' !== \get_post_type( $attachment_id ) ) {
			return new \WP_Error(
				'activitypub_attachment_not_found',
				\__( 'Attachment not found.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$mime_type = (string) \get_post_mime_type( $attachment_id );
		$top_level = \strtok( $mime_type, '/' );

		if ( ! \in_array( $top_level, self::ALLOWED_TOP_LEVEL_TYPES, true ) ) {
			return new \WP_Error(
				'activitypub_unsupported_media_type',
				\__( 'Unsupported media type.', 'activitypub' ),
				array( 'status' => 415 )
			);
		}

		$alt = \get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		/*
		 * Reuse the existing transformer (protected method; use anonymous-class shim).
		 * Known smell acknowledged in plan — leave as-is until Task 4 or later refactor.
		 */
		$transformer = new class( null ) extends Base {
			/**
			 * Expose the protected transform_attachment method.
			 *
			 * @param array $media The media array with 'id' and optional 'alt'.
			 * @return array The ActivityStreams attachment array.
			 */
			public function expose_transform_attachment( $media ) {
				return $this->transform_attachment( $media );
			}
		};

		$object = $transformer->expose_transform_attachment(
			array(
				'id'  => $attachment_id,
				'alt' => $alt,
			)
		);

		if ( empty( $object ) || ! isset( $object['type'] ) ) {
			return new \WP_Error(
				'activitypub_attachment_transform_failed',
				\__( 'Could not build ActivityPub object for attachment.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		$object['@context'] = 'https://www.w3.org/ns/activitystreams';
		$object['id']       = get_attachment_ap_id( $attachment_id );

		return $object;
	}
}
