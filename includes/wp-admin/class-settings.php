<?php
/**
 * Settings file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Model\Blog;
use Activitypub\Sanitize;
use function Activitypub\user_can_activitypub;

/**
 * ActivityPub Settings Class.
 */
class Settings {
	/**
	 * Initialize the class, registering WordPress hooks,
	 */
	public static function init() {
		\add_action( 'admin_init', array( self::class, 'register_settings' ), 11 );
		\add_action( 'admin_menu', array( self::class, 'add_settings_page' ) );

		\add_action( 'load-settings_page_activitypub', array( self::class, 'handle_welcome_query_arg' ) );
		\add_filter( 'screen_settings', array( self::class, 'add_screen_option' ), 10, 2 );
		\add_filter( 'screen_options_show_submit', array( self::class, 'screen_options_show_submit' ), 10, 2 );
	}

	/**
	 * Register ActivityPub settings
	 */
	public static function register_settings() {
		\register_setting(
			'activitypub',
			'activitypub_post_content_type',
			array(
				'type'         => 'string',
				'description'  => \__( 'Use title and link, summary, full or custom content', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'title', 'excerpt', 'content' ),
					),
				),
				'default'      => 'content',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_custom_post_content',
			array(
				'type'         => 'string',
				'description'  => \__( 'Define your own custom post template', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => ACTIVITYPUB_CUSTOM_POST_CONTENT,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_max_image_attachments',
			array(
				'type'        => 'integer',
				'description' => \__( 'Number of images to attach to posts.', 'activitypub' ),
				'default'     => ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_object_type',
			array(
				'type'         => 'string',
				'description'  => \__( 'The Activity-Object-Type', 'activitypub' ),
				'show_in_rest' => array(
					'schema' => array(
						'enum' => array( 'note', 'wordpress-post-format' ),
					),
				),
				'default'      => ACTIVITYPUB_DEFAULT_OBJECT_TYPE,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_use_hashtags',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Add hashtags in the content as native tags and replace the #tag with the tag-link', 'activitypub' ),
				'default'     => '0',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_use_opengraph',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Automatically add "fediverse:creator" OpenGraph tags for Authors and the Blog-User.', 'activitypub' ),
				'default'     => '1',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_support_post_types',
			array(
				'type'         => 'string',
				'description'  => \esc_html__( 'Enable ActivityPub support for post types', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => array( 'post' ),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_actor_mode',
			array(
				'type'        => 'integer',
				'description' => \__( 'Choose your preferred Actor-Mode.', 'activitypub' ),
				'default'     => ACTIVITYPUB_ACTOR_MODE,
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_attribution_domains',
			array(
				'type'              => 'string',
				'description'       => \__( 'Websites allowed to credit you.', 'activitypub' ),
				'default'           => \Activitypub\home_host(),
				'sanitize_callback' => array( Sanitize::class, 'host_list' ),
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_allow_likes',
			array(
				'type'              => 'integer',
				'description'       => \__( 'Allow likes.', 'activitypub' ),
				'default'           => '1',
				'sanitize_callback' => 'absint',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_allow_reposts',
			array(
				'type'              => 'integer',
				'description'       => \__( 'Allow reposts.', 'activitypub' ),
				'default'           => '1',
				'sanitize_callback' => 'absint',
			)
		);

		\register_setting(
			'activitypub',
			'activitypub_relays',
			array(
				'type'              => 'array',
				'description'       => \__( 'Relays', 'activitypub' ),
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'url_list' ),
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_outbox_purge_days',
			array(
				'type'        => 'integer',
				'description' => \__( 'Number of days to keep items in the Outbox.', 'activitypub' ),
				'default'     => 180,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_vary_header',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Add the Vary header to the ActivityPub response.', 'activitypub' ),
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_authorized_fetch',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Require HTTP signature authentication.', 'activitypub' ),
				'default'     => false,
			)
		);

		\register_setting(
			'activitypub_advanced',
			'activitypub_shared_inbox',
			array(
				'type'        => 'boolean',
				'description' => \__( 'Enable the shared inbox.', 'activitypub' ),
				'default'     => false,
			)
		);

		// Blog-User Settings.
		\register_setting(
			'activitypub_blog',
			'activitypub_blog_description',
			array(
				'type'         => 'string',
				'description'  => \esc_html__( 'The Description of the Blog-User', 'activitypub' ),
				'show_in_rest' => true,
				'default'      => '',
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_identifier',
			array(
				'type'              => 'string',
				'description'       => \esc_html__( 'The Identifier of the Blog-User', 'activitypub' ),
				'show_in_rest'      => true,
				'default'           => Blog::get_default_username(),
				'sanitize_callback' => array( Sanitize::class, 'blog_identifier' ),
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_header_image',
			array(
				'type'        => 'integer',
				'description' => \__( 'The Attachment-ID of the Sites Header-Image', 'activitypub' ),
				'default'     => null,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_mailer_new_dm',
			array(
				'type'        => 'integer',
				'description' => 'Send a notification when someone sends a user of the blog a direct message.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_mailer_new_follower',
			array(
				'type'        => 'integer',
				'description' => 'Send a notification when someone starts to follow a user of the blog.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_mailer_new_mention',
			array(
				'type'        => 'integer',
				'description' => 'Send a notification when someone mentions a user of the blog.',
				'default'     => 1,
			)
		);

		\register_setting(
			'activitypub_blog',
			'activitypub_blog_user_also_known_as',
			array(
				'type'              => 'array',
				'description'       => 'An array of URLs that the blog user is known by.',
				'default'           => array(),
				'sanitize_callback' => array( Sanitize::class, 'url_list' ),
			)
		);
	}

	/**
	 * Load settings page.
	 */
	public static function settings_page() {
		$show_welcome_tab  = \get_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', true );
		$show_advanced_tab = \get_user_meta( \get_current_user_id(), 'activitypub_show_advanced_tab', true );
		$default_tab       = $show_welcome_tab ? 'welcome' : 'settings';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? \sanitize_key( $_GET['tab'] ) : $default_tab;

		// Redirect welcome tab to settings if skipped.
		if ( 'welcome' === $tab && ! $show_welcome_tab ) {
			$tab = 'settings';
		}

		$settings_tabs = array();

		if ( $show_welcome_tab ) {
			$settings_tabs['welcome'] = array(
				'label'    => __( 'Welcome', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/welcome.php',
			);
		}

		$settings_tabs['settings'] = array(
			'label'    => __( 'Settings', 'activitypub' ),
			'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/settings.php',
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( isset( $_GET['tab'] ) && 'advanced' === $_GET['tab'] ) || $show_advanced_tab ) {
			$settings_tabs['advanced'] = array(
				'label'    => \__( 'Advanced', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/advanced-settings.php',
			);
		}

		if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
			$settings_tabs['blog-profile'] = array(
				'label'    => __( 'Blog Profile', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-settings.php',
			);
			$settings_tabs['followers']    = array(
				'label'    => __( 'Followers', 'activitypub' ),
				'template' => ACTIVITYPUB_PLUGIN_DIR . 'templates/blog-followers-list.php',
			);
		}

		/**
		 * Filters the tabs displayed in the ActivityPub settings.
		 *
		 * @param array $settings_tabs The tabs to display.
		 */
		$custom_tabs   = \apply_filters( 'activitypub_admin_settings_tabs', array() );
		$settings_tabs = \array_merge( $settings_tabs, $custom_tabs );

		switch ( $tab ) {
			case 'blog-profile':
				\wp_enqueue_media();
				\wp_enqueue_script( 'activitypub-header-image' );
				break;
			case 'welcome':
				\wp_enqueue_script( 'plugin-install' );
				\add_thickbox();
				\wp_enqueue_script( 'updates' );
				break;
		}

		if ( ! isset( $settings_tabs[ $tab ] ) ) {
			$tab = $default_tab;
		}

		// Only show tabs if there are more than one.
		if ( \count( $settings_tabs ) <= 1 ) {
			$labels = array();
		} else {
			$labels = \wp_list_pluck( $settings_tabs, 'label' );
		}

		$args         = \array_fill_keys( \array_keys( $labels ), '' );
		$args[ $tab ] = 'active';
		$args['tabs'] = $labels;

		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/admin-header.php', true, $args );
		\load_template( $settings_tabs[ $tab ]['template'] );
	}

	/**
	 * Adds the ActivityPub settings to the Help tab.
	 */
	public static function add_settings_help_tab() {
		$code_html   = array( 'code' => array() );
		$anchor_html = array(
			'a' => array(
				'href'   => true,
				'target' => true,
			),
		);

		if ( user_can_activitypub( \get_current_user_id() ) ) {
			$webfinger = Actors::get_by_id( \get_current_user_id() )->get_webfinger();
		} else {
			$webfinger = ( new Blog() )->get_webfinger();
		}

		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'template-tags',
				'title'   => \__( 'Template Tags', 'activitypub' ),
				'content' => '<h2>' . \esc_html__( 'The following Template Tags are available:', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
						'<dt><code>[ap_title]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The post&#8217;s title.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_content apply_filters="yes"]</code></dt>' . "\n" .
						'<dd>' . \wp_kses( \__( 'The post&#8217;s content. With <code>apply_filters</code> you can decide if filters (<code>apply_filters( \'the_content\', $content )</code>) should be applied or not (default is <code>yes</code>). The values can be <code>yes</code> or <code>no</code>. <code>apply_filters</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
						'<dt><code>[ap_excerpt length="400"]</code></dt>' . "\n" .
						'<dd>' . \wp_kses( \__( 'The post&#8217;s excerpt (uses <code>the_excerpt</code> if that is set). If no excerpt is provided, will truncate at <code>length</code> (optional, default = 400).', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
						'<dt><code>[ap_permalink type="url"]</code></dt>' . "\n" .
						'<dd>' . \wp_kses( \__( 'The post&#8217;s permalink. <code>type</code> can be either: <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). <code>type</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
						'<dt><code>[ap_shortlink type="url"]</code></dt>' . "\n" .
						'<dd>' . \wp_kses( \__( 'The post&#8217;s shortlink. <code>type</code> can be either <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>, to prettify the Shortlinks. <code>type</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
						'<dt><code>[ap_hashtags]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The post&#8217;s tags as hashtags.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_hashcats]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The post&#8217;s categories as hashtags.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_image type=full]</code></dt>' . "\n" .
						'<dd>' . \wp_kses( __( 'The URL for the post&#8217;s featured image, defaults to full size. The type attribute can be any of the following: <code>thumbnail</code>, <code>medium</code>, <code>large</code>, <code>full</code>. <code>type</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
						'<dt><code>[ap_author]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The author&#8217;s name.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_authorurl]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The URL to the author&#8217;s profile page.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_date]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The post&#8217;s date.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_time]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The post&#8217;s time.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_datetime]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The post&#8217;s date/time formated as "date @ time".', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_blogurl]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The URL to the site.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_blogname]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The name of the site.', 'activitypub' ) . '</dd>' . "\n" .
						'<dt><code>[ap_blogdesc]</code></dt>' . "\n" .
						'<dd>' . \esc_html__( 'The description of the site.', 'activitypub' ) . '</dd>' . "\n" .
					'</dl>' . "\n" .
					'<p>' . \esc_html__( 'You may also use any Shortcode normally available to you on your site, however be aware that Shortcodes may significantly increase the size of your content depending on what they do.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'Note: the old Template Tags are now deprecated and automatically converted to the new ones.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \wp_kses( \__( '<a href="https://github.com/automattic/wordpress-activitypub/issues/new" target="_blank">Let us know</a> if you miss a Template Tag.', 'activitypub' ), $anchor_html ) . '</p>',
			)
		);

		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'account-migration',
				'title'   => \__( 'Account Migration', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Migrating Between Mastodon and WordPress', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'The ActivityPub plugin allows you to migrate your account between WordPress and Mastodon (or other ActivityPub-compatible platforms) while bringing your followers with you.', 'activitypub' ) . '</p>' . "\n" .

					'<h3>' . \esc_html__( 'Migrating from Mastodon to WordPress', 'activitypub' ) . '</h3>' . "\n" .
					'<ol>' . "\n" .
					'<li>' . \wp_kses(
						\sprintf(
							/* translators: %s is the URL to the profile page */
							\__( 'In your WordPress profile, go to the <a href="%s">Account Aliases</a> section and add your Mastodon profile URL (e.g., <code>https://mastodon.social/@username</code>).', 'activitypub' ),
							\esc_url( \admin_url( 'profile.php#activitypub_blog_user_also_known_as' ) )
						),
						array_merge( $code_html, $anchor_html )
					) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Save your WordPress profile changes.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Log in to your Mastodon account.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Go to Preferences > Account > Move to a different account.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \wp_kses(
						\sprintf(
							/* translators: %s is the user's ActivityPub username */
							\__( 'Enter your WordPress ActivityPub username (e.g., <code>%s</code>) in the "Handle of the new account" field.', 'activitypub' ),
							\esc_html( $webfinger )
						),
						$code_html
					) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Confirm the migration in Mastodon by entering your password.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Your followers will be notified and redirected to follow your WordPress account.', 'activitypub' ) . '</li>' . "\n" .
					'</ol>' . "\n",
			)
		);

		/* translators: %s: Link to more information */
		$info_string = \esc_html__( 'For more information please visit %s.', 'activitypub' );

		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'glossary',
				'title'   => \__( 'Glossary', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Fediverse', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'The Fediverse is a new word made of two words: "federation" + "universe"', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'It is a federated social network running on free open software on a myriad of computers across the globe. Many independent servers are interconnected and allow people to interact with one another. There&#8217;s no one central site: you choose a server to register. This ensures some decentralization and sovereignty of data. Fediverse (also called Fedi) has no built-in advertisements, no tricky algorithms, no one big corporation dictating the rules. Instead we have small cozy communities of like-minded people. Welcome!', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \sprintf( $info_string, '<a href="https://fediverse.party/" target="_blank">fediverse.party</a>' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'ActivityPub', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'WebFinger', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'WebFinger is used to discover information about people or other entities on the Internet that are identified by a URI using standard Hypertext Transfer Protocol (HTTP) methods over a secure transport. A WebFinger resource returns a JavaScript Object Notation (JSON) object describing the entity that is queried. The JSON object is referred to as the JSON Resource Descriptor (JRD).', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'For a person, the type of information that might be discoverable via WebFinger includes a personal profile address, identity service, telephone number, or preferred avatar. For other entities on the Internet, a WebFinger resource might return JRDs containing link relations that enable a client to discover, for example, that a printer can print in color on A4 paper, the physical location of a server, or other static information.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \wp_kses( \__( 'On Mastodon [and other platforms], user profiles can be hosted either locally on the same website as yours, or remotely on a completely different website. The same username may be used on a different domain. Therefore, a Mastodon user&#8217;s full mention consists of both the username and the domain, in the form <code>@username@domain</code>. In practical terms, <code>@user@example.com</code> is not the same as <code>@user@example.org</code>. If the domain is not included, Mastodon will try to find a local user named <code>@username</code>. However, in order to deliver to someone over ActivityPub, the <code>@username@domain</code> mention is not enough – mentions must be translated to an HTTPS URI first, so that the remote actor&#8217;s inbox and outbox can be found. (This paragraph is copied from the <a href="https://docs.joinmastodon.org/spec/webfinger/" target="_blank">Mastodon Documentation</a>)', 'activitypub' ), array_merge( $code_html, $anchor_html ) ) . '</p>' . "\n" .
					'<p>' . \sprintf( $info_string, '<a href="https://webfinger.net/" target="_blank">webfinger.net</a>' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'NodeInfo', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'NodeInfo is an effort to create a standardized way of exposing metadata about a server running one of the distributed social networks. The two key goals are being able to get better insights into the user base of distributed social networking and the ability to build tools that allow users to choose the best fitting software and server for their needs.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \sprintf( $info_string, '<a href="http://nodeinfo.diaspora.software/" target="_blank">nodeinfo.diaspora.software</a>' ) . '</p>',
			)
		);

		\get_current_screen()->set_help_sidebar(
			'<p><strong>' . \__( 'For more information:', 'activitypub' ) . '</strong></p>' . "\n" .
			'<p>' . \__( '<a href="https://wordpress.org/support/plugin/activitypub/">Get support</a>', 'activitypub' ) . '</p>' . "\n" .
			'<p>' . \__( '<a href="https://github.com/automattic/wordpress-activitypub/issues">Report an issue</a>', 'activitypub' ) . '</p>'
		);
	}

	/**
	 * Handle 'welcome' query arg.
	 */
	public static function handle_welcome_query_arg() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['welcome'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$welcome_checked = empty( \sanitize_text_field( \wp_unslash( $_GET['welcome'] ) ) ) ? 0 : 1;
			\update_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', $welcome_checked );
			\wp_safe_redirect( \admin_url( 'options-general.php?page=activitypub&tab=settings' ) );
			exit;
		}
	}

	/**
	 * Add screen option.
	 *
	 * @param string $screen_settings The screen settings.
	 * @param object $screen          The screen object.
	 *
	 * @return string The screen settings.
	 */
	public static function add_screen_option( $screen_settings, $screen ) {
		if ( 'settings_page_activitypub' !== $screen->id ) {
			return $screen_settings;
		}

		// Verify screen options nonce.
		if ( isset( $_POST['screenoptionnonce'] ) ) {
			$nonce = \sanitize_text_field( \wp_unslash( $_POST['screenoptionnonce'] ) );
			if ( ! \wp_verify_nonce( $nonce, 'screen-options-nonce' ) ) {
				return $screen_settings;
			}
		}

		if ( isset( $_POST['activitypub_show_welcome_tab'] ) ) {
			$welcome         = \sanitize_text_field( \wp_unslash( $_POST['activitypub_show_welcome_tab'] ) );
			$welcome_checked = empty( $welcome ) ? 0 : 1;
			\update_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', $welcome_checked );
		}

		if ( isset( $_POST['activitypub_show_advanced_tab'] ) ) {
			$advanced_settings         = \sanitize_text_field( \wp_unslash( $_POST['activitypub_show_advanced_tab'] ) );
			$advanced_settings_checked = empty( $advanced_settings ) ? 0 : 1;
			\update_user_meta( \get_current_user_id(), 'activitypub_show_advanced_tab', $advanced_settings_checked );
		}

		$screen_settings = '<fieldset>
		<legend class="screen-layout">' . \esc_html__( 'Settings Pages', 'activitypub' ) . '</legend>
		<p>
			' . \esc_html__( 'Some settings pages can be shown or hidden by using the checkboxes.', 'activitypub' ) . '
		</p>
		<div class="metabox-prefs-container">
			<label for="activitypub_show_welcome_tab">
				<input name="activitypub_show_welcome_tab" type="hidden" value="0" />
				<input name="activitypub_show_welcome_tab" type="checkbox" id="activitypub_show_welcome_tab" value="1" ' . \checked( 1, \get_user_meta( \get_current_user_id(), 'activitypub_show_welcome_tab', true ), false ) . ' />
				' . \esc_html__( 'Welcome Page', 'activitypub' ) . '
			</label>
			<label for="activitypub_show_advanced_tab">
				<input name="activitypub_show_advanced_tab" type="hidden" value="0" />
				<input name="activitypub_show_advanced_tab" type="checkbox" id="activitypub_show_advanced_tab" value="1" ' . \checked( 1, \get_user_meta( \get_current_user_id(), 'activitypub_show_advanced_tab', true ), false ) . ' />
				' . \esc_html__( 'Advanced Settings', 'activitypub' ) . '
			</label>
		</div>
	</fieldset>';

		return $screen_settings;
	}

	/**
	 * Show the submit button on the screen options page.
	 *
	 * @param bool   $show_submit Whether to show the submit button.
	 * @param object $screen      The screen object.
	 *
	 * @return bool Whether to show the submit button.
	 */
	public static function screen_options_show_submit( $show_submit, $screen ) {
		if ( 'settings_page_activitypub' !== $screen->id ) {
			return $show_submit;
		}

		return true;
	}
}
