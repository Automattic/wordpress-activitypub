<?php
/**
 * ActivityPub User Settings Fields Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Moderation;

/**
 * Class to handle all user settings fields and callbacks.
 */
class User_Settings_Fields {
	/**
	 * Initialize the settings fields.
	 */
	public static function init() {
		add_action( 'load-profile.php', array( self::class, 'register_settings' ) );
	}

	/**
	 * Register all settings fields.
	 */
	public static function register_settings() {
		// Mark checklist item as done.
		\update_option( 'activitypub_checklist_profile_setup_visited', '1' );

		\add_settings_section(
			'activitypub_user_profile',
			\esc_html__( 'ActivityPub', 'activitypub' ),
			array( self::class, 'section_description' ),
			'activitypub_user_settings',
			array(
				'before_section' => '<section id="activitypub">',
				'after_section'  => '</section>',
			)
		);

		\add_settings_field(
			'activitypub_profile_url',
			\esc_html__( 'Profile URL', 'activitypub' ),
			array( self::class, 'profile_url_callback' ),
			'activitypub_user_settings',
			'activitypub_user_profile'
		);

		\add_settings_field(
			'activitypub_description',
			\esc_html__( 'Biography', 'activitypub' ),
			array( self::class, 'description_callback' ),
			'activitypub_user_settings',
			'activitypub_user_profile',
			array( 'label_for' => 'activitypub_description' )
		);

		\add_settings_field(
			'activitypub_header_image',
			\esc_html__( 'Header Image', 'activitypub' ),
			array( self::class, 'header_image_callback' ),
			'activitypub_user_settings',
			'activitypub_user_profile',
			array( 'label_for' => 'activitypub_header_image' )
		);

		\add_settings_field(
			'activitypub_notifications',
			\esc_html__( 'Email Notifications', 'activitypub' ),
			array( self::class, 'notifications_callback' ),
			'activitypub_user_settings',
			'activitypub_user_profile'
		);

		\add_settings_field(
			'activitypub_extra_fields',
			\esc_html__( 'Extra Fields', 'activitypub' ),
			array( self::class, 'extra_fields_callback' ),
			'activitypub_user_settings',
			'activitypub_user_profile'
		);

		\add_settings_field(
			'activitypub_also_known_as',
			\esc_html__( 'Account Aliases', 'activitypub' ),
			array( self::class, 'also_known_as_callback' ),
			'activitypub_user_settings',
			'activitypub_user_profile',
			array( 'label_for' => 'activitypub_also_known_as' )
		);

		// Add moderation section.
		\add_settings_section(
			'activitypub_user_moderation',
			\esc_html__( 'Moderation', 'activitypub' ),
			array( self::class, 'moderation_section_description' ),
			'activitypub_user_settings',
			array(
				'before_section' => '<section id="activitypub-moderation">',
				'after_section'  => '</section>',
			)
		);

		\add_settings_field(
			'activitypub_user_blocked_domains',
			\esc_html__( 'Blocked Domains', 'activitypub' ),
			array( self::class, 'blocked_domains_callback' ),
			'activitypub_user_settings',
			'activitypub_user_moderation'
		);

		\add_settings_field(
			'activitypub_user_blocked_keywords',
			\esc_html__( 'Blocked Keywords', 'activitypub' ),
			array( self::class, 'blocked_keywords_callback' ),
			'activitypub_user_settings',
			'activitypub_user_moderation'
		);
	}

	/**
	 * Section description callback.
	 */
	public static function section_description() {
		echo '<p>' . \esc_html__( 'Define what others can see on your public Fediverse profile and next to your posts. With a profile picture and a fully completed profile, you are more likely to gain interactions and followers.', 'activitypub' ) . '</p>';
		echo '<p>' . \esc_html__( 'The ActivityPub plugin tries to take as much information as possible from your profile settings. However, the following settings are not supported by WordPress or should be adjusted independently of the WordPress settings.', 'activitypub' ) . '</p>';
	}

	/**
	 * Profile URL field callback.
	 */
	public static function profile_url_callback() {
		$user = Actors::get_by_id( \get_current_user_id() );
		?>
		<p>
			<?php
			\printf(
				// translators: 1: the webfinger resource, 2: the author URL.
				\esc_html__( '%1$s or %2$s', 'activitypub' ),
				'<code>' . \esc_html( $user->get_webfinger() ) . '</code>',
				'<code>' . \esc_url( $user->get_url() ) . '</code>'
			);
			?>
		</p>
		<p class="description">
			<?php
			\printf(
				// translators: the webfinger resource.
				\esc_html__( 'Follow "@%s" by searching for it on Mastodon, Friendica, etc.', 'activitypub' ),
				\esc_html( $user->get_webfinger() )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Description field callback.
	 */
	public static function description_callback() {
		$description = \get_user_option( 'activitypub_description', \get_current_user_id() );
		$placeholder = \get_user_meta( \get_current_user_id(), 'description', true );
		?>
		<textarea name="activitypub_description" id="activitypub_description" rows="5" cols="30" placeholder="<?php echo \esc_attr( $placeholder ); ?>"><?php echo \esc_html( $description ); ?></textarea>
		<p class="description"><?php \esc_html_e( 'If you wish to use different biographical info for the fediverse, enter your alternate bio here.', 'activitypub' ); ?></p>
		<?php
	}

	/**
	 * Header image field callback.
	 */
	public static function header_image_callback() {
		$header_image              = \get_user_option( 'activitypub_header_image', \get_current_user_id() );
		$classes_for_upload_button = 'button upload-button button-add-media button-add-header-image';
		$classes_for_update_button = 'button';
		$classes_for_wrapper       = '';

		if ( (int) $header_image ) {
			$classes_for_wrapper         .= ' has-header-image';
			$classes_for_button           = $classes_for_update_button;
			$classes_for_button_on_change = $classes_for_upload_button;
		} else {
			$classes_for_wrapper         .= ' hidden';
			$classes_for_button           = $classes_for_upload_button;
			$classes_for_button_on_change = $classes_for_update_button;
		}
		?>
		<div id="activitypub-header-image-preview-wrapper" class="<?php echo \esc_attr( $classes_for_wrapper ); ?>">
			<img id="activitypub-header-image-preview" src="<?php echo \esc_url( \wp_get_attachment_url( $header_image ) ); ?>" style="max-width: 100%;" alt="" />
		</div>
		<button
			type="button"
			id="activitypub-choose-from-library-button"
			class="<?php echo \esc_attr( $classes_for_button ); ?>"
			data-alt-classes="<?php echo \esc_attr( $classes_for_button_on_change ); ?>"
			data-choose-text="<?php \esc_attr_e( 'Choose a Header Image', 'activitypub' ); ?>"
			data-update-text="<?php \esc_attr_e( 'Change Header Image', 'activitypub' ); ?>"
			data-update="<?php \esc_attr_e( 'Set as Header Image', 'activitypub' ); ?>"
			data-width="1500"
			data-height="500"
			<?php
			if ( ! \current_user_can( 'edit_others_posts' ) ) :
				\printf( 'data-user-id="%s"', \esc_attr( \get_current_user_id() ) );
			endif;
			?>
			data-state="<?php echo \esc_attr( (int) $header_image ); ?>">
			<?php echo (int) $header_image ? \esc_html__( 'Change Header Image', 'activitypub' ) : \esc_html__( 'Choose a Header Image', 'activitypub' ); ?>
		</button>
		<button
			id="activitypub-remove-header-image"
			type="button"
			<?php echo (int) $header_image ? 'class="button button-secondary reset"' : 'class="button button-secondary reset hidden"'; ?>>
			<?php \esc_html_e( 'Remove Header Image', 'activitypub' ); ?>
		</button>
		<input type="hidden" name="activitypub_header_image" id="activitypub_header_image" value="<?php echo \esc_attr( $header_image ); ?>">
		<?php
	}

	/**
	 * Notifications field callback.
	 */
	public static function notifications_callback() {
		?>
		<fieldset id="activitypub-notifications">
			<p>
				<label>
					<input type="checkbox" name="activitypub_mailer_new_follower" id="activitypub_mailer_new_follower" value="1" <?php \checked( 1, \get_user_option( 'activitypub_mailer_new_follower' ) ); ?> />
					<?php \esc_html_e( 'New Followers', 'activitypub' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="activitypub_mailer_new_dm" id="activitypub_mailer_new_dm" value="1" <?php \checked( 1, \get_user_option( 'activitypub_mailer_new_dm' ) ); ?> />
					<?php \esc_html_e( 'Direct Messages', 'activitypub' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="activitypub_mailer_new_mention" id="activitypub_mailer_new_mention" value="1" <?php \checked( 1, \get_user_option( 'activitypub_mailer_new_mention' ) ); ?> />
					<?php \esc_html_e( 'New Mentions', 'activitypub' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Extra fields callback.
	 */
	public static function extra_fields_callback() {
		$extra_fields = Extra_Fields::get_actor_fields( \get_current_user_id() );
		?>
		<p class="description">
			<?php \esc_html_e( 'Your homepage, social profiles, pronouns, age, anything you want.', 'activitypub' ); ?>
		</p>

		<?php if ( ! empty( $extra_fields ) ) : ?>
		<table class="widefat striped activitypub-extra-fields" role="presentation" style="margin: 15px 0;">
			<?php foreach ( $extra_fields as $extra_field ) : ?>
				<tr>
					<td><?php echo \esc_html( $extra_field->post_title ); ?></td>
					<td><?php echo \wp_kses_post( \get_the_excerpt( $extra_field ) ); ?></td>
					<td>
						<a href="<?php echo \esc_url( \get_edit_post_link( $extra_field->ID ) ); ?>" class="button">
							<?php \esc_html_e( 'Edit', 'activitypub' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<p class="extra-fields-nav">
			<a href="<?php echo \esc_url( \admin_url( '/post-new.php?post_type=ap_extrafield' ) ); ?>" class="button">
				<?php \esc_html_e( 'Add new', 'activitypub' ); ?>
			</a>
			<a href="<?php echo \esc_url( \admin_url( '/edit.php?post_type=ap_extrafield' ) ); ?>">
				<?php \esc_html_e( 'Manage all', 'activitypub' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Also Known As field callback.
	 */
	public static function also_known_as_callback() {
		$also_known_as = \get_user_option( 'activitypub_also_known_as', \get_current_user_id() );
		?>
		<textarea
			class="large-text"
			name="activitypub_also_known_as"
			id="activitypub_also_known_as"
			rows="5"
		><?php echo \esc_textarea( implode( PHP_EOL, (array) $also_known_as ) ); ?></textarea>
		<p class="description">
			<?php \esc_html_e( 'If you&#8217;re moving from another account to this one, you&#8217;ll need to create an alias here first before transferring your followers. This step is safe, reversible, and doesn&#8217;t affect anything on its own. The migration itself is initiated from your old account.', 'activitypub' ); ?>
		</p>
		<p class="description">
			<?php echo \wp_kses_post( \__( 'Enter one account per line. Profile links or usernames like <code>@username@example.com</code> are accepted and will be automatically normalized to the correct format.', 'activitypub' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Moderation section description callback.
	 */
	public static function moderation_section_description() {
		echo '<p>' . \esc_html__( 'Configure personal blocks to filter ActivityPub content you don\'t want to see.', 'activitypub' ) . '</p>';
	}

	/**
	 * Blocked domains field callback.
	 */
	public static function blocked_domains_callback() {
		$user_id         = \get_current_user_id();
		$blocked_domains = Moderation::get_user_blocks( $user_id )['domains'];
		?>
		<p class="description"><?php \esc_html_e( 'Block entire ActivityPub instances by domain name.', 'activitypub' ); ?></p>

		<div class="activitypub-user-block-list" data-user-id="<?php echo \esc_attr( $user_id ); ?>">
			<?php if ( ! empty( $blocked_domains ) ) : ?>
			<table class="widefat striped activitypub-blocked-domain" role="presentation" style="max-width: 500px; margin: 15px 0;">
				<?php foreach ( $blocked_domains as $domain ) : ?>
					<tr>
						<td><?php echo \esc_html( $domain ); ?></td>
						<td style="width: 80px;">
							<button type="button" class="button button-small remove-user-block-btn" data-type="domain" data-value="<?php echo \esc_attr( $domain ); ?>">
								<?php \esc_html_e( 'Remove', 'activitypub' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>

			<div class="add-user-block-form" style="display: flex; max-width: 500px; gap: 8px;">
				<input type="text" class="regular-text" id="new_user_domain" placeholder="<?php \esc_attr_e( 'example.com', 'activitypub' ); ?>" style="flex: 1; min-width: 0;" />
				<button type="button" class="button add-user-block-btn" data-type="domain" style="flex-shrink: 0; white-space: nowrap;">
					<?php \esc_html_e( 'Add Block', 'activitypub' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Blocked keywords field callback.
	 */
	public static function blocked_keywords_callback() {
		$user_id          = \get_current_user_id();
		$blocked_keywords = Moderation::get_user_blocks( $user_id )['keywords'];
		?>
		<p class="description"><?php \esc_html_e( 'Block ActivityPub content containing specific keywords.', 'activitypub' ); ?></p>

		<div class="activitypub-user-block-list" data-user-id="<?php echo \esc_attr( $user_id ); ?>">
			<?php if ( ! empty( $blocked_keywords ) ) : ?>
			<table class="widefat striped activitypub-blocked-keyword" role="presentation" style="max-width: 500px; margin: 15px 0;">
				<?php foreach ( $blocked_keywords as $keyword ) : ?>
					<tr>
						<td><?php echo \esc_html( $keyword ); ?></td>
						<td style="width: 80px;">
							<button type="button" class="button button-small remove-user-block-btn" data-type="keyword" data-value="<?php echo \esc_attr( $keyword ); ?>">
								<?php \esc_html_e( 'Remove', 'activitypub' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>

			<div class="add-user-block-form" style="display: flex; max-width: 500px; gap: 8px;">
				<input type="text" class="regular-text" id="new_user_keyword" placeholder="<?php \esc_attr_e( 'spam keyword', 'activitypub' ); ?>" style="flex: 1; min-width: 0;" />
				<button type="button" class="button add-user-block-btn" data-type="keyword" style="flex-shrink: 0; white-space: nowrap;">
					<?php \esc_html_e( 'Add Block', 'activitypub' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
