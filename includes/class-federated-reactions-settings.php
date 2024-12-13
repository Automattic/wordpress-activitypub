<?php
/**
 * Federated Reactions Settings file.
 *
 * @package ActivityPub
 */

namespace Activitypub;

/**
 * Federated Reactions Settings class.
 */
class Federated_Reactions_Settings {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_post_meta' ), 11 );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/**
	 * Register post meta for federated reactions.
	 */
	public static function register_post_meta() {
		$ap_post_types = get_post_types_by_support( 'activitypub' );
		foreach ( $ap_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'activitypub_reactions_enabled',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => function ( $value ) {
						return in_array( $value, array( '0', '1' ), true ) ? $value : '1';
					},
				)
			);
		}
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			'discussion',
			'activitypub_reactions_enabled',
			array(
				'type'         => 'boolean',
				'show_in_rest' => true,
				'default'      => true,
			)
		);

		add_settings_field(
			'activitypub_reactions_enabled',
			__( 'Federated Reactions', 'activitypub' ),
			array( self::class, 'render_reactions_enabled_field' ),
			'discussion'
		);
	}

	/**
	 * Render the reactions enabled field.
	 */
	public static function render_reactions_enabled_field() {
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Federated Reactions', 'activitypub' ); ?></legend>
			<label>
				<input type="checkbox" name="activitypub_reactions_enabled" value="1" <?php checked( '1', get_option( 'activitypub_reactions_enabled', '1' ) ); ?> />
				<?php esc_html_e( 'Show federated reactions on posts', 'activitypub' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'This can be overridden on individual posts.', 'activitypub' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Check if reactions are enabled for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return bool
	 */
	public static function is_reactions_enabled( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$post_setting = get_post_meta( $post_id, 'activitypub_reactions_enabled', true );

		// If meta exists, check if it's '1'.
		if ( '' !== $post_setting ) {
			return '1' === $post_setting;
		}

		// Otherwise use global setting.
		return get_option( 'activitypub_reactions_enabled', '1' ) === '1';
	}
}
