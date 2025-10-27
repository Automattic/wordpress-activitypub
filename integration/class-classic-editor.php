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
		\add_action( 'post_comment_status_meta_box-options', array( self::class, 'add_quote_policy_field' ) );
		\add_action( 'save_post', array( self::class, 'save_quote_policy' ) );
	}

	/**
	 * Add quote policy field to the Discussion meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public static function add_quote_policy_field( $post ) {
		// Only add field for post types that support ActivityPub.
		if ( ! \post_type_supports( $post->post_type, 'activitypub' ) ) {
			return;
		}

		// Add nonce for security.
		\wp_nonce_field( 'activitypub_quote_policy', 'activitypub_quote_policy_nonce' );

		// Get current value.
		$quote_interaction_policy = \get_post_meta( $post->ID, 'activitypub_interaction_policy_quote', true ) ?: ACTIVITYPUB_INTERACTION_POLICY_ANYONE; // phpcs:ignore Universal.Operators.DisallowShortTernary
		?>
		<br class="clear" />
		<label for="activitypub_allow_quotes" class="selectit">
			<input name="activitypub_allow_quotes" type="checkbox" id="activitypub_allow_quotes" value="1" <?php \checked( $quote_interaction_policy, ACTIVITYPUB_INTERACTION_POLICY_ANYONE ); ?> />
			<?php \esc_html_e( 'Allow quotes from the Fediverse', 'activitypub' ); ?>
		</label>
		<?php
	}

	/**
	 * Save quote policy data.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function save_quote_policy( $post_id ) {
		// Check if this is an autosave.
		if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only process if nonce is set (meaning we're saving from the post editor).
		if ( ! isset( $_POST['activitypub_quote_policy_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['activitypub_quote_policy_nonce'] ) ), 'activitypub_quote_policy' ) ) {
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

		// Save quote interaction policy based on checkbox.
		if ( '1' === \sanitize_text_field( \wp_unslash( $_POST['activitypub_allow_quotes'] ?? '0' ) ) ) {
			// Checked: allow anyone to quote (default value).
			\update_post_meta( $post_id, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );
		} else {
			// Unchecked: only allow "just me" to quote.
			\update_post_meta( $post_id, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_ME );
		}
	}
}
