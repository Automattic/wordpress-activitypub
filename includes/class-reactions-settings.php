<?php
/**
 * Reactions Settings file.
 *
 * @package ActivityPub
 */

namespace Activitypub;

/**
 *  Reactions Settings class.
 */
class Reactions_Settings {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register_post_meta' ), 11 );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		if ( self::show_reactions_on_posts() ) {
			add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ) );
			add_action( 'save_post', array( self::class, 'save_meta_box' ) );
		}
	}

	/**
	 * Register post meta for federated reactions.
	 */
	public static function register_post_meta() {
		$ap_post_types = get_post_types_by_support( 'activitypub' );
		foreach ( $ap_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				'activitypub_show_reactions_on_posts',
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
			'activitypub',
			'activitypub_show_reactions_on_posts',
			array(
				'type'         => 'boolean',
				'show_in_rest' => true,
				'default'      => true,
			)
		);

		add_settings_field(
			'activitypub_show_reactions_on_posts',
			__( 'Federated Reactions', 'activitypub' ),
			array( self::class, 'render_reactions_enabled_field' ),
			'activitypub',
			'activity'
		);
	}

	/**
	 * Render the reactions enabled field.
	 */
	public static function render_reactions_enabled_field() {
		if ( ! wp_is_block_theme() ) {
			?>
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Federated Reactions', 'activitypub' ); ?></legend>
				<label>
					<input type="checkbox" name="activitypub_show_reactions_on_posts" value="1" <?php checked( '1', get_option( 'activitypub_show_reactions_on_posts', '1' ) ); ?> />
					<?php esc_html_e( 'Show federated reactions on posts.', 'activitypub' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'This can be overridden on individual posts.', 'activitypub' ); ?>
				</p>
			</fieldset>
			<?php
		} else {
			$editor_url = add_query_arg(
				array(
					'postType' => 'wp_template',
					'postId'   => get_stylesheet() . '//single',
					'canvas'   => 'edit',
				),
				admin_url( 'site-editor.php' )
			);
			?>
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %s: URL to edit Single Posts template */
						__( 'The Reactions block is automatically inserted into your Single Posts template. To disable it, select the Content block in the Single Posts template, and uncheck "Fediverse Reactions" under ActivityPub in the sidebar. <a href="%s">Edit Single Posts template</a>', 'activitypub' ),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
					esc_url( $editor_url )
				);
				?>
			</p>
			<?php
		}
	}

	/**
	 * Add meta box for classic editor.
	 */
	public static function add_meta_box() {
		$post_type = get_post_type();

		// Return early if block editor is active.
		if ( use_block_editor_for_post_type( $post_type ) ) {
			return;
		}

		if ( post_type_supports( $post_type, 'activitypub' ) ) {
			add_meta_box(
				'activitypub_reactions',
				__( 'ActivityPub Reactions', 'activitypub' ),
				array( self::class, 'render_meta_box' ),
				$post_type,
				'side'
			);
		}
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'activitypub_reactions_meta_box', 'activitypub_reactions_meta_box_nonce' );
		$value = get_post_meta( $post->ID, 'activitypub_show_reactions_on_posts', true );
		if ( '' === $value ) {
			$value = '1'; // Default to enabled.
		}
		?>
		<label>
			<input type="checkbox" name="activitypub_show_reactions_on_posts" value="1" <?php checked( '1', $value ); ?> />
			<?php esc_html_e( 'Add federated reactions to posts.', 'activitypub' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When disabled, federated reactions will be hidden for this post.', 'activitypub' ); ?>
		</p>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_meta_box( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['activitypub_reactions_meta_box_nonce'] ) ||
			! wp_verify_nonce( wp_unslash( $_POST['activitypub_reactions_meta_box_nonce'] ), 'activitypub_reactions_meta_box' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['activitypub_show_reactions_on_posts'] ) ? '1' : '0';
		update_post_meta( $post_id, 'activitypub_show_reactions_on_posts', $value );
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

		$post_setting = get_post_meta( $post_id, 'activitypub_show_reactions_on_posts', true );

		// If meta exists, check if it's '1', otherwise default to true since global setting is enabled.
		return '' === $post_setting || '1' === $post_setting;
	}

	/**
	 * Check if reactions are enabled globally.
	 *
	 * @return bool
	 */
	public static function show_reactions_on_posts() {
		return get_option( 'activitypub_show_reactions_on_posts', '1' ) === '1';
	}
}
