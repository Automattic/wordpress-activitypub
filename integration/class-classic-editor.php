<?php
/**
 * Classic Editor integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Classic Editor integration class.
 *
 * Handles compatibility with the Classic Editor plugin and sites without block editor support.
 */
class Classic_Editor {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_attachments_media_markup', array( self::class, 'filter_attachments_media_markup' ), 10, 2 );
		\add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ) );
		\add_action( 'save_post', array( self::class, 'save_meta_data' ) );
	}

	/**
	 * Filter attachment media markup to use shortcodes instead of blocks.
	 *
	 * @param string $markup         The custom markup. Empty string by default.
	 * @param array  $attachment_ids Array of attachment IDs.
	 *
	 * @return string The generated shortcode markup.
	 */
	public static function filter_attachments_media_markup( $markup, $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return $markup;
		}

		$type = strtok( \get_post_mime_type( $attachment_ids[0] ), '/' );

		// Single video or audio file: use media shortcode.
		if ( 1 === \count( $attachment_ids ) && ( 'video' === $type || 'audio' === $type ) ) {
			return sprintf(
				'[%1$s src="%2$s"]',
				\esc_attr( $type ),
				\esc_url( \wp_get_attachment_url( $attachment_ids[0] ) )
			);
		}

		// Multiple attachments or images: use gallery shortcode.
		return '[gallery ids="' . implode( ',', $attachment_ids ) . '" link="none"]';
	}

	/**
	 * Add ActivityPub meta box to the post editor.
	 *
	 * @param string $post_type The post type.
	 */
	public static function add_meta_box( $post_type ) {
		// Only add for post types that support ActivityPub.
		if ( ! \post_type_supports( $post_type, 'activitypub' ) ) {
			return;
		}

		\add_meta_box(
			'activitypub-settings',
			\__( 'Fediverse ⁂', 'activitypub' ),
			array( self::class, 'render_meta_box' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render the ActivityPub meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function render_meta_box( $post ) {
		// Add nonce for security.
		\wp_nonce_field( 'activitypub_meta_box', 'activitypub_meta_box_nonce' );

		// Get current values.
		$content_warning       = \get_post_meta( $post->ID, 'activitypub_content_warning', true );
		$max_image_attachments = \get_post_meta( $post->ID, 'activitypub_max_image_attachments', true );
		$content_visibility    = self::get_default_visibility( $post );
		$quote_interaction     = \get_post_meta( $post->ID, 'activitypub_interaction_policy_quote', true ) ?: ACTIVITYPUB_INTERACTION_POLICY_ANYONE; // phpcs:ignore Universal.Operators.DisallowShortTernary

		?>
		<p>
			<label for="activitypub_content_warning">
				<strong><?php \esc_html_e( 'Content Warning', 'activitypub' ); ?></strong>
			</label><br />
			<input type="text" id="activitypub_content_warning" name="activitypub_content_warning" value="<?php echo \esc_attr( $content_warning ); ?>" class="widefat" placeholder="<?php \esc_attr_e( 'Optional content warning', 'activitypub' ); ?>" />
			<span class="howto"><?php \esc_html_e( 'Content warnings do not change the content on your site, only in the fediverse.', 'activitypub' ); ?></span>
		</p>

		<p>
			<label for="activitypub_max_image_attachments">
				<strong><?php \esc_html_e( 'Maximum Image Attachments', 'activitypub' ); ?></strong>
			</label><br />
			<input type="number" id="activitypub_max_image_attachments" name="activitypub_max_image_attachments" value="<?php echo \esc_attr( $max_image_attachments ); ?>" min="0" max="10" class="small-text" />
			<span class="howto"><?php \esc_html_e( 'Maximum number of image attachments to include when sharing to the fediverse.', 'activitypub' ); ?></span>
		</p>

		<p>
			<strong><?php \esc_html_e( 'Visibility', 'activitypub' ); ?></strong><br />
			<label>
				<input type="radio" name="activitypub_content_visibility" value="public" <?php \checked( $content_visibility, ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ); ?> />
				<?php \esc_html_e( 'Public', 'activitypub' ); ?>
			</label><br />
			<label>
				<input type="radio" name="activitypub_content_visibility" value="quiet_public" <?php \checked( $content_visibility, ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC ); ?> />
				<?php \esc_html_e( 'Quiet public', 'activitypub' ); ?>
			</label><br />
			<label>
				<input type="radio" name="activitypub_content_visibility" value="local" <?php \checked( $content_visibility, ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ); ?> />
				<?php \esc_html_e( 'Do not federate', 'activitypub' ); ?>
			</label><br />
			<span class="howto"><?php \esc_html_e( 'This adjusts the visibility of a post in the fediverse, but note that it won\'t affect how the post appears on the blog.', 'activitypub' ); ?></span>
		</p>

		<p>
			<label for="activitypub_interaction_policy_quote">
				<strong><?php \esc_html_e( 'Who can quote this post?', 'activitypub' ); ?></strong>
			</label><br />
			<select id="activitypub_interaction_policy_quote" name="activitypub_interaction_policy_quote" class="widefat">
				<option value="anyone" <?php \selected( $quote_interaction, ACTIVITYPUB_INTERACTION_POLICY_ANYONE ); ?>><?php \esc_html_e( 'Anyone', 'activitypub' ); ?></option>
				<option value="followers" <?php \selected( $quote_interaction, ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS ); ?>><?php \esc_html_e( 'Followers only', 'activitypub' ); ?></option>
				<option value="me" <?php \selected( $quote_interaction, ACTIVITYPUB_INTERACTION_POLICY_ME ); ?>><?php \esc_html_e( 'Just me', 'activitypub' ); ?></option>
			</select>
			<span class="howto"><?php \esc_html_e( 'Quoting allows others to cite your post while adding their own commentary.', 'activitypub' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Get default visibility based on post age and federation status.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return string The default visibility value.
	 */
	private static function get_default_visibility( $post ) {
		// If already set, use that value.
		$saved_visibility = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );
		if ( $saved_visibility ) {
			return $saved_visibility;
		}

		// If post is federated, use public.
		$status = \get_post_meta( $post->ID, 'activitypub_status', true );
		if ( 'federated' === $status ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		// If post is older than 1 month, default to local.
		$post_timestamp = \strtotime( $post->post_date );
		$one_month_ago  = \strtotime( '-30 days' );

		if ( $post_timestamp < $one_month_ago ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL;
		}

		// Default to public for new posts.
		return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
	}

	/**
	 * Save ActivityPub meta data.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function save_meta_data( $post_id ) {
		// Check if this is an autosave.
		if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only process for post types that support ActivityPub.
		if ( ! \post_type_supports( \get_post_type( $post_id ), 'activitypub' ) ) {
			return;
		}

		// Check user permissions.
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify nonce is present and valid.
		if ( ! isset( $_POST['activitypub_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['activitypub_meta_box_nonce'] ) ), 'activitypub_meta_box' ) ) {
			return;
		}

		// Save content warning.
		if ( isset( $_POST['activitypub_content_warning'] ) ) {
			$content_warning = \sanitize_text_field( \wp_unslash( $_POST['activitypub_content_warning'] ) );
			if ( ! empty( $content_warning ) ) {
				\update_post_meta( $post_id, 'activitypub_content_warning', $content_warning );
			} else {
				\delete_post_meta( $post_id, 'activitypub_content_warning' );
			}
		}

		// Save max image attachments.
		if ( isset( $_POST['activitypub_max_image_attachments'] ) ) {
			$max_images = \absint( $_POST['activitypub_max_image_attachments'] );
			\update_post_meta( $post_id, 'activitypub_max_image_attachments', $max_images );
		}

		// Save content visibility.
		if ( isset( $_POST['activitypub_content_visibility'] ) ) {
			$visibility = \sanitize_text_field( \wp_unslash( $_POST['activitypub_content_visibility'] ) );
			\update_post_meta( $post_id, 'activitypub_content_visibility', $visibility );
		}

		// Save quote interaction policy.
		if ( isset( $_POST['activitypub_interaction_policy_quote'] ) ) {
			$quote_policy = \sanitize_text_field( \wp_unslash( $_POST['activitypub_interaction_policy_quote'] ) );
			\update_post_meta( $post_id, 'activitypub_interaction_policy_quote', $quote_policy );
		}
	}
}
