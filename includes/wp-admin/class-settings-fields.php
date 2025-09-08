<?php
/**
 * ActivityPub Settings Fields Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Moderation;

use function Activitypub\home_host;

/**
 * Class Settings_Fields.
 */
class Settings_Fields {
	/**
	 * Initialize the settings fields.
	 */
	public static function init() {
		add_action( 'load-settings_page_activitypub', array( self::class, 'register_settings_fields' ) );
	}

	/**
	 * Register settings fields.
	 */
	public static function register_settings_fields() {
		// Add settings sections.
		add_settings_section(
			'activitypub_profiles',
			__( 'Profiles', 'activitypub' ),
			'__return_empty_string',
			'activitypub_settings'
		);

		add_settings_section(
			'activitypub_activities',
			__( 'Activities', 'activitypub' ),
			'__return_empty_string',
			'activitypub_settings'
		);

		add_settings_section(
			'activitypub_general',
			__( 'General', 'activitypub' ),
			'__return_empty_string',
			'activitypub_settings'
		);

		add_settings_section(
			'activitypub_server',
			__( 'Server', 'activitypub' ),
			'__return_empty_string',
			'activitypub_settings'
		);

		add_settings_section(
			'activitypub_moderation',
			\esc_html__( 'Moderation', 'activitypub' ),
			array( self::class, 'render_moderation_section_description' ),
			'activitypub_settings'
		);

		// Add settings fields.
		add_settings_field(
			'activitypub_actor_mode',
			__( 'Enable profiles by type', 'activitypub' ),
			array( self::class, 'render_actor_mode_field' ),
			'activitypub_settings',
			'activitypub_profiles'
		);

		$object_type = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );
		if ( 'note' === $object_type ) {
			add_settings_field(
				'activitypub_custom_post_content',
				__( 'Post content', 'activitypub' ),
				array( self::class, 'render_custom_post_content_field' ),
				'activitypub_settings',
				'activitypub_activities',
				array( 'label_for' => 'activitypub_custom_post_content' )
			);
		}

		add_settings_field(
			'activitypub_max_image_attachments',
			__( 'Media attachments', 'activitypub' ),
			array( self::class, 'render_max_image_attachments_field' ),
			'activitypub_settings',
			'activitypub_activities',
			array( 'label_for' => 'activitypub_max_image_attachments' )
		);

		add_settings_field(
			'activitypub_support_post_types',
			__( 'Supported post types', 'activitypub' ),
			array( self::class, 'render_support_post_types_field' ),
			'activitypub_settings',
			'activitypub_activities'
		);

		add_settings_field(
			'activitypub_allow_interactions',
			__( 'Post interactions', 'activitypub' ),
			array( self::class, 'render_allow_interactions_field' ),
			'activitypub_settings',
			'activitypub_activities'
		);

		add_settings_field(
			'activitypub_use_hashtags',
			__( 'Hashtags', 'activitypub' ),
			array( self::class, 'render_use_hashtags_field' ),
			'activitypub_settings',
			'activitypub_activities',
			array( 'label_for' => 'activitypub_use_hashtags' )
		);

		add_settings_field(
			'activitypub_use_opengraph',
			__( 'OpenGraph', 'activitypub' ),
			array( self::class, 'render_use_opengraph_field' ),
			'activitypub_settings',
			'activitypub_general',
			array( 'label_for' => 'activitypub_use_opengraph' )
		);

		add_settings_field(
			'activitypub_attribution_domains',
			__( 'Attribution Domains', 'activitypub' ),
			array( self::class, 'render_attribution_domains_field' ),
			'activitypub_settings',
			'activitypub_general',
			array( 'label_for' => 'activitypub_attribution_domains' )
		);

		add_settings_field(
			'activitypub_relays',
			__( 'Relays', 'activitypub' ),
			array( self::class, 'render_relays_field' ),
			'activitypub_settings',
			'activitypub_server',
			array( 'label_for' => 'activitypub_relays' )
		);

		add_settings_field(
			'activitypub_site_blocked_domains',
			\esc_html__( 'Blocked Domains', 'activitypub' ),
			array( self::class, 'render_site_blocked_domains_field' ),
			'activitypub_settings',
			'activitypub_moderation'
		);

		add_settings_field(
			'activitypub_site_blocked_keywords',
			\esc_html__( 'Blocked Keywords', 'activitypub' ),
			array( self::class, 'render_site_blocked_keywords_field' ),
			'activitypub_settings',
			'activitypub_moderation'
		);
	}

	/**
	 * Render actor mode field.
	 */
	public static function render_actor_mode_field() {
		$disabled = ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) && ACTIVITYPUB_SINGLE_USER_MODE ) ||
						( \defined( 'ACTIVITYPUB_DISABLE_USER' ) && ACTIVITYPUB_DISABLE_USER ) ||
						( \defined( 'ACTIVITYPUB_DISABLE_BLOG_USER' ) && ACTIVITYPUB_DISABLE_BLOG_USER );

		if ( $disabled ) :
			?>
			<p class="description">
				<?php esc_html_e( '⚠ This setting is defined through server configuration by your blog&#8217;s administrator.', 'activitypub' ); ?>
			</p>
			<?php
			return;
		endif;

		$value = get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		?>
		<fieldset class="actor-mode-selection">
			<div class="row">
				<input type="radio" id="actor-mode" name="activitypub_actor_mode" value="<?php echo esc_attr( ACTIVITYPUB_ACTOR_MODE ); ?>" <?php checked( ACTIVITYPUB_ACTOR_MODE, $value ); ?> />
				<div>
					<label for="actor-mode"><strong><?php esc_html_e( 'Author Profiles Only', 'activitypub' ); ?></strong></label>
					<p class="description">
						<?php echo wp_kses( __( 'Every author on this blog (with the <code>activitypub</code> capability) gets their own ActivityPub profile.', 'activitypub' ), array( 'code' => array() ) ); ?>
						<strong>
							<?php
							echo wp_kses(
								sprintf(
								// translators: %s is a URL.
									__( 'You can add/remove the capability in the <a href="%s">user settings.</a>', 'activitypub' ),
									admin_url( '/users.php' )
								),
								array( 'a' => array( 'href' => array() ) )
							);
							?>
						</strong>
						<?php echo wp_kses( __( 'Select all the users you want to update, choose the method from the drop-down list and click on the "Apply" button.', 'activitypub' ), array( 'code' => array() ) ); ?>
					</p>
				</div>
			</div>
			<div class="row">
				<input type="radio" id="blog-mode" name="activitypub_actor_mode" value="<?php echo esc_attr( ACTIVITYPUB_BLOG_MODE ); ?>" <?php checked( ACTIVITYPUB_BLOG_MODE, $value ); ?> />
				<div>
					<label for="blog-mode"><strong><?php esc_html_e( 'Blog profile only', 'activitypub' ); ?></strong></label>
					<p class="description">
						<?php esc_html_e( 'Your blog becomes a single ActivityPub profile and every post will be published under this profile instead of the individual author profiles.', 'activitypub' ); ?>
					</p>
				</div>
			</div>
			<div class="row">
				<input type="radio" id="actor-blog-mode" name="activitypub_actor_mode" value="<?php echo esc_attr( ACTIVITYPUB_ACTOR_AND_BLOG_MODE ); ?>" <?php checked( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, $value ); ?> />
				<div>
					<label for="actor-blog-mode"><strong><?php esc_html_e( 'Both author and blog profiles', 'activitypub' ); ?></strong></label>
					<p class="description">
						<?php esc_html_e( "This combines both modes. Users can be followed individually, while following the blog will show boosts of individual user's posts.", 'activitypub' ); ?>
					</p>
			</div>
		</div>
	</fieldset>
		<?php
	}

	/**
	 * Render custom post content field.
	 */
	public static function render_custom_post_content_field() {
		$value = get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
		?>
		<p><strong><?php esc_html_e( 'These settings only apply if you use the "Note" Object-Type setting above.', 'activitypub' ); ?></strong></p>
		<p>
			<textarea id="activitypub_custom_post_content" name="activitypub_custom_post_content" rows="10" cols="50" class="large-text" placeholder="<?php echo esc_attr( ACTIVITYPUB_CUSTOM_POST_CONTENT ); ?>"><?php echo esc_textarea( wp_kses( $value, 'post' ) ); ?></textarea>
			<details>
				<summary><?php esc_html_e( 'See a list of ActivityPub Template Tags.', 'activitypub' ); ?></summary>
				<div class="description">
					<ul>
						<li><code>[ap_title]</code> - <?php esc_html_e( 'The post&#8217;s title.', 'activitypub' ); ?></li>
						<li><code>[ap_content]</code> - <?php esc_html_e( 'The post&#8217;s content.', 'activitypub' ); ?></li>
						<li><code>[ap_excerpt]</code> - <?php esc_html_e( 'The post&#8217;s excerpt (may be truncated).', 'activitypub' ); ?></li>
						<li><code>[ap_permalink]</code> - <?php esc_html_e( 'The post&#8217;s permalink.', 'activitypub' ); ?></li>
						<li><code>[ap_shortlink]</code> - <?php echo wp_kses( __( 'The post&#8217;s shortlink. I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>.', 'activitypub' ), 'default' ); ?></li>
						<li><code>[ap_hashtags]</code> - <?php esc_html_e( 'The post&#8217;s tags as hashtags.', 'activitypub' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'You can find the full list with all possible attributes in the help section on the top-right of the screen.', 'activitypub' ); ?></p>
				</div>
			</details>
		</p>
		<?php
	}

	/**
	 * Render max image attachments field.
	 */
	public static function render_max_image_attachments_field() {
		$value = get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );
		?>
		<input id="activitypub_max_image_attachments" value="<?php echo esc_attr( $value ); ?>" name="activitypub_max_image_attachments" type="number" min="0" max="10" class="small-text" />
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					// translators: %s is a number.
					__( 'The number of media (images, audio, video) to attach to posts. Default: <code>%s</code>', 'activitypub' ),
					esc_html( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS )
				),
				'default'
			);
			?>
		</p>
		<p class="description">
			<em>
				<?php esc_html_e( 'Note: audio and video attachments are only supported from Block Editor.', 'activitypub' ); ?>
			</em>
		</p>
		<?php
	}

	/**
	 * Render support post types field.
	 */
	public static function render_support_post_types_field() {
		$post_types           = get_post_types( array( 'public' => true ), 'objects' );
		$supported_post_types = (array) get_option( 'activitypub_support_post_types', array( 'post' ) );
		?>
		<fieldset>
			<?php esc_html_e( 'Automatically publish items of the selected post types to the fediverse:', 'activitypub' ); ?>
			<ul>
			<?php foreach ( $post_types as $post_type ) : ?>
				<li>
					<input type="checkbox" id="activitypub_support_post_type_<?php echo esc_attr( $post_type->name ); ?>" name="activitypub_support_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $supported_post_types, true ) ); ?> />
					<label for="activitypub_support_post_type_<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->label ); ?></label>
					<span class="description">
						<?php echo esc_html( \Activitypub\get_post_type_description( $post_type ) ); ?>
					</span>
				</li>
			<?php endforeach; ?>
			</ul>
		</fieldset>
		<?php
	}

	/**
	 * Render allow interactions field.
	 */
	public static function render_allow_interactions_field() {
		if ( defined( 'ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS' ) && ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			echo '<p class="description">' . \esc_html__( '⚠ This setting is defined through server configuration by your blog&#8217;s administrator.', 'activitypub' ) . '</p>';
			return;
		}

		$allow_likes   = get_option( 'activitypub_allow_likes', '1' );
		$allow_reposts = get_option( 'activitypub_allow_reposts', '1' );
		$auto_approve  = get_option( 'activitypub_auto_approve_reactions', '0' );
		?>
		<fieldset>
			<p>
				<label>
					<input type="checkbox" name="activitypub_allow_likes" value="1" <?php checked( '1', $allow_likes ); ?> />
					<?php esc_html_e( 'Receive likes', 'activitypub' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="activitypub_allow_reposts" value="1" <?php checked( '1', $allow_reposts ); ?> />
					<?php esc_html_e( 'Receive reblogs (boosts)', 'activitypub' ); ?>
				</label>
			</p>
			<p class="interactions description"><?php esc_html_e( 'Types of interactions from the Fediverse your blog should accept.', 'activitypub' ); ?></p>
			<p>
				<label>
					<input type="checkbox" name="activitypub_auto_approve_reactions" value="1" <?php checked( '1', $auto_approve ); ?> />
					<?php esc_html_e( 'Auto approve reactions', 'activitypub' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render use hashtags field.
	 */
	public static function render_use_hashtags_field() {
		$value = get_option( 'activitypub_use_hashtags', '1' );
		?>
		<p>
			<label>
				<input type="checkbox" id="activitypub_use_hashtags" name="activitypub_use_hashtags" value="1" <?php checked( '1', $value ); ?> />
				<?php echo wp_kses( __( 'Add hashtags in the content as native tags and replace the <code>#tag</code> with the tag link.', 'activitypub' ), 'default' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Render use OpenGraph field.
	 */
	public static function render_use_opengraph_field() {
		$value = get_option( 'activitypub_use_opengraph', '1' );
		?>
		<p>
			<label>
				<input type="checkbox" id="activitypub_use_opengraph" name="activitypub_use_opengraph" value="1" <?php checked( '1', $value ); ?> />
				<?php echo wp_kses( __( 'Automatically add <code>&lt;meta name="fediverse:creator" /&gt;</code> tags for Authors and the Blog-User. You can read more about the feature on the <a href="https://blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/" target="_blank">Mastodon Blog</a>.', 'activitypub' ), 'post' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Render attribution domains field.
	 */
	public static function render_attribution_domains_field() {
		$value = get_option( 'activitypub_attribution_domains', home_host() );
		?>
		<textarea
			id="activitypub_attribution_domains"
			name="activitypub_attribution_domains"
			class="large-text"
			cols="50" rows="5"
			placeholder="<?php echo esc_attr( home_host() ); ?>"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Websites allowed to credit you, one per line. Protects from false attributions.', 'activitypub' ); ?></p>
		<?php
	}

	/**
	 * Render relays field.
	 */
	public static function render_relays_field() {
		$value = \get_option( 'activitypub_relays', array() );
		?>
		<textarea
			id="activitypub_relays"
			name="activitypub_relays"
			class="large-text"
			cols="50"
			rows="5"
		><?php echo \esc_textarea( implode( PHP_EOL, $value ) ); ?></textarea>
		<p class="description">
			<?php echo \wp_kses( \__( 'A <strong>Fediverse-Relay</strong> distributes content across instances, expanding reach, engagement, and discoverability, especially for smaller instances.', 'activitypub' ), 'default' ); ?>
		</p>
		<p class="description">
			<?php
			echo \wp_kses(
				\__( 'Enter the <strong>Inbox-URLs</strong> (e.g. <code>https://relay.example.com/inbox</code>) of the relays you want to use, one per line.', 'activitypub' ),
				array(
					'strong' => array(),
					'code'   => array(),
				)
			);
			?>
			<?php echo \wp_kses( \__( 'You can find a list of public relays on <a href="https://relaylist.com/" target="_blank">relaylist.com</a> or on <a href="https://fedidb.org/software/activity-relay" target="_blank">FediDB</a>.', 'activitypub' ), 'default' ); ?>
		</p>
		<?php
	}

	/**
	 * Render moderation section description.
	 */
	public static function render_moderation_section_description() {
		echo '<p>' . \esc_html__( 'Configure site-wide moderation settings. These blocks will affect all users and ActivityPub content on your site.', 'activitypub' ) . '</p>';
	}

	/**
	 * Render site blocked domains field.
	 */
	public static function render_site_blocked_domains_field() {
		$blocked_domains = Moderation::get_site_blocks()['domains'];
		?>
		<p class="description"><?php \esc_html_e( 'Block entire ActivityPub instances by domain name.', 'activitypub' ); ?></p>

		<div class="activitypub-site-block-list">
			<?php if ( ! empty( $blocked_domains ) ) : ?>
			<table class="widefat striped activitypub-site-blocked-domain" role="presentation" style="max-width: 500px; margin: 15px 0;">
				<?php foreach ( $blocked_domains as $domain ) : ?>
					<tr>
						<td><?php echo \esc_html( $domain ); ?></td>
						<td style="width: 80px;">
							<button type="button" class="button button-small remove-site-block-btn" data-type="domain" data-value="<?php echo \esc_attr( $domain ); ?>">
								<?php \esc_html_e( 'Remove', 'activitypub' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>

			<div class="add-site-block-form" style="display: flex; max-width: 500px; gap: 8px;">
				<input type="text" class="regular-text" id="new_site_domain" placeholder="<?php \esc_attr_e( 'example.com', 'activitypub' ); ?>" style="flex: 1; min-width: 0;" />
				<button type="button" class="button add-site-block-btn" data-type="domain" style="flex-shrink: 0; white-space: nowrap;">
					<?php \esc_html_e( 'Add Block', 'activitypub' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render site blocked keywords field.
	 */
	public static function render_site_blocked_keywords_field() {
		$blocked_keywords = Moderation::get_site_blocks()['keywords'];
		?>
		<p class="description"><?php \esc_html_e( 'Block ActivityPub content containing specific keywords.', 'activitypub' ); ?></p>

		<div class="activitypub-site-block-list">
			<?php if ( ! empty( $blocked_keywords ) ) : ?>
			<table class="widefat striped activitypub-site-blocked-keyword" role="presentation" style="max-width: 500px; margin: 15px 0;">
				<?php foreach ( $blocked_keywords as $keyword ) : ?>
					<tr>
						<td><?php echo \esc_html( $keyword ); ?></td>
						<td style="width: 80px;">
							<button type="button" class="button button-small remove-site-block-btn" data-type="keyword" data-value="<?php echo \esc_attr( $keyword ); ?>">
								<?php \esc_html_e( 'Remove', 'activitypub' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>

			<div class="add-site-block-form" style="display: flex; max-width: 500px; gap: 8px;">
				<input type="text" class="regular-text" id="new_site_keyword" placeholder="<?php \esc_attr_e( 'spam keyword', 'activitypub' ); ?>" style="flex: 1; min-width: 0;" />
				<button type="button" class="button add-site-block-btn" data-type="keyword" style="flex-shrink: 0; white-space: nowrap;">
					<?php \esc_html_e( 'Add Block', 'activitypub' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
