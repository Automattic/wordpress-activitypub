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

		// Getting Started / Introduction to the Fediverse.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'getting-started',
				'title'   => \__( 'Getting Started', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'What is the Fediverse?', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'The Fediverse is a collection of social networks that talk to each other, similar to how email works between different providers. It allows people on different platforms to follow and interact with each other, regardless of which service they use.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'Unlike traditional social media where everyone must use the same service (like Twitter or Facebook), the Fediverse lets you choose where your content lives while still reaching people across many different platforms.', 'activitypub' ) . '</p>' . "\n" .
					'<p style="position: relative; padding-top: 56.25%;"><iframe title="' . \esc_attr__( 'What is the Fediverse?', 'activitypub' ) . '" width="100%" height="100%" src="https://framatube.org/videos/embed/9dRFC6Ya11NCVeYKn8ZhiD?subtitle=' . \esc_attr( \substr( \get_locale(), 0, 2 ) ) . '" frameborder="0" allowfullscreen="" sandbox="allow-same-origin allow-scripts allow-popups allow-forms" style="position: absolute; inset: 0;"></iframe></p>' . "\n" .

					'<h2>' . \esc_html__( 'How WordPress fits into the Fediverse', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'This plugin turns your WordPress blog into part of the Fediverse. When activated, your blog becomes a Fediverse "instance" that can interact with other platforms like Mastodon.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'Your WordPress posts can be followed by people on Mastodon and other Fediverse platforms. Comments, likes, and shares from these platforms can appear on your WordPress site.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'The plugin supports two modes: individual user accounts (each author has their own Fediverse identity) or a whole-blog account (the blog itself has a Fediverse identity).', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'What to expect when federating', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'When your content federates to the Fediverse:', 'activitypub' ) . '</p>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \esc_html__( 'Your posts will appear in the feeds of people who follow you.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'People can comment, like, and share your posts from their Fediverse accounts.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Your featured images, excerpts, and other post elements will be included.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Building a following takes time, just like on any social platform.', 'activitypub' ) . '</li>' . "\n" .
					'</ul>' . "\n" .
					'<p>' . \esc_html__( 'Remember that public posts are truly public in the Fediverse - they can be seen by anyone on any connected platform.', 'activitypub' ) . '</p>',
			)
		);

		// Core Features.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'core-features',
				'title'   => \__( 'Core Features', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'User Accounts vs. Blog Accounts', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'Your WordPress site can participate in the Fediverse in two ways:', 'activitypub' ) . '</p>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( 'Individual user accounts: Each author has their own Fediverse identity (<code>username@yourdomain.com</code>).', 'activitypub' ), $code_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( 'Whole blog account: The blog itself has a Fediverse identity (<code>blog@yourdomain.com</code>).', 'activitypub' ), $code_html ) . '</li>' . "\n" .
					'</ul>' . "\n" .
					'<p>' . \esc_html__( 'User accounts are best when you want each author to have their own following and identity. The blog account is simpler and works well for single-author sites or when you want all content under one identity.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Publishing to the Fediverse', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'When you publish a post on your WordPress site, the ActivityPub plugin automatically shares it with your followers in the Fediverse. Your content appears in their feeds just like posts from other Fediverse platforms such as Mastodon or Pleroma.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'The plugin intelligently formats your content for the Fediverse, including featured images, excerpts, and links back to your original post.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'Before publishing, you can use the Fediverse Preview feature to see exactly how your post will appear to Fediverse users. This helps ensure your content looks great across different platforms. You can access this preview from the post editor sidebar.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Content Visibility Controls', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'The ActivityPub plugin gives you complete control over which content is shared to the Fediverse. By default, public posts are federated while private or password-protected posts remain exclusive to your WordPress site.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'In the WordPress editor, each post has visibility settings that determine whether it appears in the Fediverse. You can find these controls in the editor sidebar under "Fediverse > Visibility." Options include "Public" (visible to everyone in the Fediverse), "Quiet Public" (doesn&#8217;t appear in public timelines), or "Do Not Federate" (keeps the post only on your WordPress site).', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'You can also configure global settings to control which post types (posts, pages, custom post types) are federated by default. This gives you both site-wide control and per-post flexibility to manage exactly how your content is shared.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Receiving Interactions', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'One of the most powerful features of the ActivityPub plugin is its ability to receive and display interactions from across the Fediverse. When someone on Mastodon or another Fediverse platform comments on your post, their comment appears directly in your WordPress comments section, creating a seamless conversation between your blog and the wider Fediverse.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'These Fediverse comments integrate naturally with your existing WordPress comment system. You can moderate them just like regular comments, and any replies you make are automatically federated back to the original commenter, maintaining the conversation thread across platforms.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'Beyond comments, the plugin also tracks likes and shares (boosts) from Fediverse users. These interactions can provide valuable feedback and help you understand how your content is being received across the decentralized social web.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Mentions and Replies', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \wp_kses( \__( 'The ActivityPub plugin enables true cross-platform conversations by supporting mentions and replies. When writing a post, you can mention any Fediverse user by using their full address format. For example, typing <code>@username@domain.com</code> will create a mention that notifies that user, regardless of which Fediverse platform they use.', 'activitypub' ), $code_html ) . '</p>' . "\n" .
					'<p>' . \wp_kses( \__( 'Mentions use the format <code>@username@domain.com</code> and work just like mentions on other social platforms. The mentioned user receives a notification, and your post appears in their mentions timeline. This creates a direct connection between your WordPress site and users across the Fediverse.', 'activitypub' ), $code_html ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'Similarly, when someone mentions your Fediverse identity in their post, you&#8217;ll receive an email notification that you can respond to with a new post. This two-way communication bridge makes your WordPress site a full participant in Fediverse conversations.', 'activitypub' ) . '</p>' . "\n",
			)
		);

		// Editor Blocks.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'editor-blocks',
				'title'   => \__( 'Editor Blocks', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Introduction to ActivityPub Blocks', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'The plugin provides custom blocks for the WordPress Block Editor (Gutenberg) that enhance your Fediverse presence and make it easier to interact with the Fediverse.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Follow Me on the Fediverse', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'This block displays your Fediverse profile so that visitors can follow you directly from your WordPress site.', 'activitypub' ) . '</p>' . "\n" .
					'<figure class="activitypub-block-screenshot">' . "\n" .
					'<img src="' . \esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/follow-me.png' ) . '" alt="' . \esc_attr__( 'Follow Me on the Fediverse block', 'activitypub' ) . '" width="600" height="auto">' . "\n" .
					'<figcaption class="activitypub-screenshot-caption">' . \esc_html__( 'The Follow Me block showing both profile information and follow button.', 'activitypub' ) . '</figcaption>' . "\n" .
					'</figure>' . "\n" .
					'<h4>' . \esc_html__( 'Usage Tips', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'Place this block in your sidebar, footer, or about page to make it easy for visitors to follow you on the Fediverse. The button-only option works well in compact spaces, while the full profile display provides more context about your Fediverse presence.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Fediverse Followers', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'This block displays your followers from the Fediverse on your website, showcasing your community and reach across decentralized networks.', 'activitypub' ) . '</p>' . "\n" .
					'<figure class="activitypub-block-screenshot">' . "\n" .
					'<img src="' . \esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/followers.png' ) . '" alt="' . \esc_attr__( 'Fediverse Followers block', 'activitypub' ) . '" width="600" height="auto">' . "\n" .
					'<figcaption class="activitypub-screenshot-caption">' . \esc_html__( 'The Followers block displaying a list of Fediverse followers with pagination.', 'activitypub' ) . '</figcaption>' . "\n" .
					'</figure>' . "\n" .
					'<h4>' . \esc_html__( 'Usage Tips', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'This block works well on community pages, about pages, or sidebars. The compact style is ideal for sidebars with limited space, while the Lines style provides clear visual separation between followers in wider layouts.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'The block includes pagination controls when you have more followers than the per-page setting, allowing visitors to browse through all your followers.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Fediverse Reactions', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'This block displays likes and reposts from the Fediverse for your content, showing engagement metrics from across federated networks.', 'activitypub' ) . '</p>' . "\n" .
					'<figure class="activitypub-block-screenshot">' . "\n" .
					'<img src="' . \esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/reactions.png' ) . '" alt="' . \esc_attr__( 'Fediverse Reactions block', 'activitypub' ) . '" width="600" height="auto">' . "\n" .
					'<figcaption class="activitypub-screenshot-caption">' . \esc_html__( 'The Reactions block showing likes and reposts from the Fediverse.', 'activitypub' ) . '</figcaption>' . "\n" .
					'</figure>' . "\n" .
					'<h4>' . \esc_html__( 'How It Works', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'The Reactions block dynamically fetches and displays likes and reposts (boosts) that your content receives from across the Fediverse. It updates automatically as new reactions come in, providing real-time feedback on how your content is being received.', 'activitypub' ) . '</p>' . "\n" .
					'<h4>' . \esc_html__( 'Usage Tips', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'This block provides social proof by showing how your content is being received across the Fediverse. It works best at the end of posts or pages where it can display engagement metrics without interrupting the content flow.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Federated Reply', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'This block allows you to respond to posts, notes, videos, and other content on the Fediverse directly within your WordPress posts.', 'activitypub' ) . '</p>' . "\n" .
					'<figure class="activitypub-block-screenshot">' . "\n" .
					'<img src="' . \esc_url( ACTIVITYPUB_PLUGIN_URL . 'assets/img/reply.png' ) . '" alt="' . \esc_attr__( 'Federated Reply block', 'activitypub' ) . '" width="600" height="auto">' . "\n" .
					'<figcaption class="activitypub-screenshot-caption">' . \esc_html__( 'The Federated Reply block with embedded Fediverse content and reply interface.', 'activitypub' ) . '</figcaption>' . "\n" .
					'</figure>' . "\n" .
					'<h4>' . \esc_html__( 'How It Works', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'When you add this block to your post and provide a Fediverse URL, the plugin will:', 'activitypub' ) . '</p>' . "\n" .
					'<ol>' . "\n" .
					'<li>' . \esc_html__( 'Fetch and optionally display the original content you&#8217;re replying to.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'When your post is published, send your reply to the Fediverse.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Create a proper threaded reply that will appear in the original post&#8217;s thread.', 'activitypub' ) . '</li>' . "\n" .
					'</ol>' . "\n" .
					'<h4>' . \esc_html__( 'Important Notes', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'This block only works with URLs from federated social networks. URLs from non-federated platforms may not function as expected. Your reply will be published to the Fediverse when your WordPress post is published.', 'activitypub' ) . '</p>' . "\n" .
					'<h4>' . \esc_html__( 'Usage Tips', 'activitypub' ) . '</h4>' . "\n" .
					'<p>' . \esc_html__( 'Use this block to create responses to Fediverse discussions. It&#8217;s perfect for bloggers who want to participate in Fediverse conversations while maintaining their content on their own WordPress site.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'General Usage Instructions', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'To use any of these blocks:', 'activitypub' ) . '</p>' . "\n" .
					'<ol>' . "\n" .
					'<li>' . \esc_html__( 'Open the Block Editor when creating or editing a post/page.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Click the "+" button to add a new block.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Search for "ActivityPub" or the specific block name.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Select the desired block and configure its settings in the block sidebar.', 'activitypub' ) . '</li>' . "\n" .
					'</ol>' . "\n" .

					'<p>' . \esc_html__( 'These blocks help bridge the gap between your WordPress site and the Fediverse, enabling better integration and engagement with decentralized social networks.', 'activitypub' ) . '</p>',
			)
		);

		// Account Migration.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'account-migration',
				'title'   => \__( 'Account Migration', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Understanding Account Migration', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'Account migration in the Fediverse allows you to move your identity from one platform to another while bringing your followers with you.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'When you migrate properly, your followers are automatically redirected to follow your new account, and your old account can point people to your new one.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \esc_html__( 'This is especially useful if you&#8217;s moving from a Mastodon instance to your WordPress site, or if you&#8217;s changing domains.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Migrating from Mastodon to WordPress', 'activitypub' ) . '</h2>' . "\n" .
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
					'</ol>' . "\n" .

					'<h2>' . \esc_html__( 'Managing Multiple Accounts', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'If you maintain presence on multiple platforms:', 'activitypub' ) . '</p>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \esc_html__( 'Use the Account Aliases feature to link your identities.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Consider which account will be your primary one.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Be clear with your followers about where to find you.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Remember that full migration moves your followers completely.', 'activitypub' ) . '</li>' . "\n" .
					'</ul>',
			)
		);

		// Template Tags.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'template-tags',
				'title'   => \__( 'Template Tags', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'What are Template Tags?', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'Template Tags let you control how your content appears in the Fediverse. They work as shortcodes within your post content templates, allowing you to customize what information is included and how it&#8217;s formatted.', 'activitypub' ) . '</p>' . "\n" .

					'<h2>' . \esc_html__( 'Content Tags', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt><code>[ap_title]</code></dt>' . "\n" .
					'<dd>' . \esc_html__( 'The post&#8217;s title.', 'activitypub' ) . '</dd>' . "\n" .
					'<dt><code>[ap_content apply_filters="yes"]</code></dt>' . "\n" .
					'<dd>' . \wp_kses( \__( 'The post&#8217;s content. With <code>apply_filters</code> you can decide if filters (<code>apply_filters( \'the_content\', $content )</code>) should be applied or not (default is <code>yes</code>). The values can be <code>yes</code> or <code>no</code>. <code>apply_filters</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
					'<dt><code>[ap_excerpt length="400"]</code></dt>' . "\n" .
					'<dd>' . \wp_kses( \__( 'The post&#8217;s excerpt (uses <code>the_excerpt</code> if that is set). If no excerpt is provided, will truncate at <code>length</code> (optional, default = 400).', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
					'<dt><code>[ap_image type="full"]</code></dt>' . "\n" .
					'<dd>' . \wp_kses( __( 'The URL for the post&#8217;s featured image, defaults to full size. The type attribute can be any of the following: <code>thumbnail</code>, <code>medium</code>, <code>large</code>, <code>full</code>. <code>type</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
					'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'Link and Permalink Tags', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt><code>[ap_permalink type="url"]</code></dt>' . "\n" .
					'<dd>' . \wp_kses( \__( 'The post&#8217;s permalink. <code>type</code> can be either: <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). <code>type</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
					'<dt><code>[ap_shortlink type="url"]</code></dt>' . "\n" .
					'<dd>' . \wp_kses( \__( 'The post&#8217;s shortlink. <code>type</code> can be either <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>, to prettify the Shortlinks. <code>type</code> attribute is optional.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .
					'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'Metadata Tags', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt><code>[ap_hashtags]</code></dt>' . "\n" .
					'<dd>' . \esc_html__( 'The post&#8217;s tags as hashtags.', 'activitypub' ) . '</dd>' . "\n" .
					'<dt><code>[ap_hashcats]</code></dt>' . "\n" .
					'<dd>' . \esc_html__( 'The post&#8217;s categories as hashtags.', 'activitypub' ) . '</dd>' . "\n" .
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
					'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'Site Information Tags', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt><code>[ap_blogurl]</code></dt>' . "\n" .
					'<dd>' . \esc_html__( 'The URL to the site.', 'activitypub' ) . '</dd>' . "\n" .
					'<dt><code>[ap_blogname]</code></dt>' . "\n" .
					'<dd>' . \esc_html__( 'The name of the site.', 'activitypub' ) . '</dd>' . "\n" .
					'<dt><code>[ap_blogdesc]</code></dt>' . "\n" .
					'<dd>' . \esc_html__( 'The description of the site.', 'activitypub' ) . '</dd>' . "\n" .
					'</dl>' . "\n" .

					'<p>' . \esc_html__( 'You may also use any Shortcode normally available to you on your site, however be aware that Shortcodes may significantly increase the size of your content depending on what they do.', 'activitypub' ) . '</p>' . "\n" .
					'<p>' . \wp_kses( \__( '<a href="https://github.com/automattic/wordpress-activitypub/issues/new" target="_blank">Let us know</a> if you miss a Template Tag.', 'activitypub' ), $anchor_html ) . '</p>' . "\n",
			)
		);

		// Recommended Plugins.
		if ( ! empty( self::get_recommended_plugins() ) ) {
			\get_current_screen()->add_help_tab(
				array(
					'id'      => 'recommended-plugins',
					'title'   => __( 'Recommended Plugins', 'activitypub' ),
					'content' =>
						'<h2>' . esc_html__( 'Supercharge Your Fediverse Experience', 'activitypub' ) . '</h2>' .
						'<p>' . esc_html__( 'Enhance your WordPress ActivityPub setup with these hand-picked plugins, each adding unique capabilities for a richer Fediverse experience.', 'activitypub' ) . '</p>' .
						self::render_recommended_plugins_list(),
				)
			);
		}

		// Troubleshooting.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'troubleshooting',
				'title'   => \__( 'Troubleshooting', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Common Issues and Solutions', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt>' . \esc_html__( 'My posts aren&#8217;t appearing in the Fediverse', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Check that federation is enabled for your user or blog, verify the post type is set to be federated, and ensure the post is public. Also verify that your site is accessible from the public internet.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Fediverse users can&#8217;t follow my account', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Make sure your WebFinger endpoint is accessible. Try searching for your full username (user@yourdomain.com) from a Fediverse account. Check that your server allows the necessary API requests.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Images aren&#8217;t displaying properly', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Verify that your images are publicly accessible. Check image size limits on the receiving platforms. Consider using different image sizes in your templates.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Comments from the Fediverse aren&#8217;t showing up', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Check your WordPress comment moderation settings. Verify that your inbox endpoint is accessible. Look for any error messages in your logs.', 'activitypub' ) . '</dd>' . "\n" .
					'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'Debugging Federation Issues', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'To verify your WordPress site is properly federating:', 'activitypub' ) . '</p>' . "\n" .
					'<ol>' . "\n" .
					'<li>' . \esc_html__( 'Test your WebFinger endpoint by visiting yourdomain.com/.well-known/webfinger?resource=acct:username@yourdomain.com.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Check that your ActivityPub endpoints are accessible.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Try following your account from a Fediverse account.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Publish a test post and verify it appears for followers.', 'activitypub' ) . '</li>' . "\n" .
					'</ol>' . "\n" .

					'<h2>' . \esc_html__( 'Understanding Error Messages', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'Common error messages you might encounter:', 'activitypub' ) . '</p>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \esc_html__( 'WebFinger resource not found: Your WebFinger endpoint isn&#8217;t configured correctly or the username doesn&#8217;t exist.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Unable to deliver to inbox: The receiving server couldn&#8217;t be reached or rejected the message.', 'activitypub' ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Signature verification failed: Authentication issues between servers.', 'activitypub' ) . '</li>' . "\n" .
					'</ul>' . "\n" .

					'<h2>' . \esc_html__( 'Getting Help', 'activitypub' ) . '</h2>' . "\n" .
					'<p>' . \esc_html__( 'If you&#8217;s still having issues:', 'activitypub' ) . '</p>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( 'Check the <a href="https://wordpress.org/support/plugin/activitypub/" target="_blank">support forum</a> for similar issues.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( 'Report bugs on <a href="https://github.com/automattic/wordpress-activitypub/issues" target="_blank">GitHub</a> with detailed information.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \esc_html__( 'Include your WordPress and PHP versions, along with any error messages when seeking help.', 'activitypub' ) . '</li>' . "\n" .
					'</ul>',
			)
		);

		/* translators: %s: Link to more information */
		$info_string = \esc_html__( 'For more information please visit %s.', 'activitypub' );

		// Glossary.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'glossary',
				'title'   => \__( 'Glossary', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Fediverse Terminology', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt>' . \esc_html__( 'Fediverse', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'A network of interconnected servers using open protocols, primarily ActivityPub, allowing users from different platforms to interact with each other. The term combines "federation" and "universe".', 'activitypub' ) . '</dd>' . "\n" .
					'<dd>' . \esc_html__( 'It is a federated social network running on free open software on a myriad of computers across the globe. Many independent servers are interconnected and allow people to interact with one another. There&#8217;s no one central site: you choose a server to register. This ensures some decentralization and sovereignty of data. Fediverse (also called Fedi) has no built-in advertisements, no tricky algorithms, no one big corporation dictating the rules. Instead we have small cozy communities of like-minded people. Welcome!', 'activitypub' ) . '</dd>' . "\n" .
					'<dd>' . \sprintf( $info_string, '<a href="https://fediverse.party/" target="_blank">fediverse.party</a>' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Federation', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'The process by which servers communicate with each other to share content and interactions across different platforms and instances.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Instance', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'A server running Fediverse software. Your WordPress site with ActivityPub enabled becomes an instance in the Fediverse.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Local Timeline', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Content from users on the same instance. In WordPress context, this would be posts from your WordPress site.', 'activitypub' ) . '</dd>' . "\n" .
					'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'ActivityPub Concepts', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt>' . \esc_html__( 'ActivityPub', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Actor', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'An entity that can perform activities. In WordPress, actors are typically users or the blog itself.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Activity', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'An action performed by an actor, such as creating a post, liking content, or following someone.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Object', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'The target of an activity, such as a post, comment, or profile.', 'activitypub' ) . '</dd>' . "\n" .
					'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'WebFinger and Discovery', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt>' . \esc_html__( 'WebFinger', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'WebFinger is used to discover information about people or other entities on the Internet that are identified by a URI using standard Hypertext Transfer Protocol (HTTP) methods over a secure transport. A WebFinger resource returns a JavaScript Object Notation (JSON) object describing the entity that is queried. The JSON object is referred to as the JSON Resource Descriptor (JRD).', 'activitypub' ) . '</dd>' . "\n" .
					'<dd>' . \esc_html__( 'For a person, the type of information that might be discoverable via WebFinger includes a personal profile address, identity service, telephone number, or preferred avatar. For other entities on the Internet, a WebFinger resource might return JRDs containing link relations that enable a client to discover, for example, that a printer can print in color on A4 paper, the physical location of a server, or other static information.', 'activitypub' ) . '</dd>' . "\n" .
					'<dd>' . "\n" .
					'<blockquote>' . "\n" .
						\wp_kses( \__( 'On Mastodon [and other platforms], user profiles can be hosted either locally on the same website as yours, or remotely on a completely different website. The same username may be used on a different domain. Therefore, a Mastodon user&#8217;s full mention consists of both the username and the domain, in the form <code>@username@domain</code>. In practical terms, <code>@user@example.com</code> is not the same as <code>@user@example.org</code>. If the domain is not included, Mastodon will try to find a local user named <code>@username</code>. However, in order to deliver to someone over ActivityPub, the <code>@username@domain</code> mention is not enough – mentions must be translated to an HTTPS URI first, so that the remote actor&#8217;s inbox and outbox can be found.', 'activitypub' ), array_merge( $code_html, $anchor_html ) ) . "\n" .
						'<cite><a href="https://docs.joinmastodon.org/spec/webfinger/" target="_blank">' . \esc_html__( 'Mastodon Documentation', 'activitypub' ) . '</a></cite>' . "\n" .
					'</blockquote>' . "\n" .
					'</dd>' . "\n" .
					'<dd>' . \sprintf( $info_string, '<a href="https://webfinger.net/" target="_blank">webfinger.net</a>' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Handle', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \wp_kses( \__( 'A user&#8217;s identity in the Fediverse, formatted as <code>@username@domain.com</code>. Similar to an email address, it includes both the username and the server where the account is hosted.', 'activitypub' ), $code_html ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'NodeInfo', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'A standardized way of exposing metadata about a server running one of the distributed social networks. It helps with compatibility and discovery between different Fediverse platforms.', 'activitypub' ) . '</dd>' . "\n" .
					'<dd>' . \sprintf( $info_string, '<a href="https://nodeinfo.diaspora.software/" target="_blank">nodeinfo.diaspora.software</a>' ) . '</dd>',
				'</dl>' . "\n" .

					'<h2>' . \esc_html__( 'WordPress-Specific Terms', 'activitypub' ) . '</h2>' . "\n" .
					'<dl>' . "\n" .
					'<dt>' . \esc_html__( 'Template Tags', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Shortcodes used in the ActivityPub plugin to customize how content appears when federated to the Fediverse.', 'activitypub' ) . '</dd>' . "\n" .

					'<dt>' . \esc_html__( 'Federation Settings', 'activitypub' ) . '</dt>' . "\n" .
					'<dd>' . \esc_html__( 'Configuration options that control how WordPress content is shared with the Fediverse.', 'activitypub' ) . '</dd>' . "\n" .
					'</dl>' . "\n",
			)
		);

		// Resources.
		\get_current_screen()->add_help_tab(
			array(
				'id'      => 'resources',
				'title'   => \__( 'Resources', 'activitypub' ),
				'content' =>
					'<h2>' . \esc_html__( 'Official Resources', 'activitypub' ) . '</h2>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://wordpress.org/plugins/activitypub/" target="_blank">WordPress.org Plugin Page</a> - Official plugin listing with documentation.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://github.com/automattic/wordpress-activitypub" target="_blank">GitHub Repository</a> - Source code and development.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://github.com/automattic/wordpress-activitypub/releases" target="_blank">Release Notes</a> - Latest changes and updates.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'</ul>' . "\n" .

					'<h2>' . \esc_html__( 'Community Support', 'activitypub' ) . '</h2>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://wordpress.org/support/plugin/activitypub/" target="_blank">WordPress.org Support Forums</a> - Get help from the community.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://github.com/automattic/wordpress-activitypub/issues" target="_blank">GitHub Issues</a> - Report bugs or suggest features.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'</ul>' . "\n" .

					'<h2>' . \esc_html__( 'Complementary Plugins', 'activitypub' ) . '</h2>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a> - Enhance shortlinks for better sharing.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://wordpress.org/plugins/webmention/" target="_blank">Webmention</a> - Add Webmention support for additional interactions.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'</ul>' . "\n" .

					'<h2>' . \esc_html__( 'Fediverse Resources', 'activitypub' ) . '</h2>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://fediverse.party/" target="_blank">Fediverse.Party</a> - Introduction to the Fediverse and its platforms.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://joinmastodon.org/" target="_blank">Join Mastodon</a> - Information about Mastodon, a popular Fediverse platform.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://w3c.github.io/activitypub/" target="_blank">ActivityPub Specification</a> - The official W3C specification.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'</ul>' . "\n" .

					'<h2>' . \esc_html__( 'Further Reading', 'activitypub' ) . '</h2>' . "\n" .
					'<ul>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://indieweb.org/" target="_blank">IndieWeb</a> - Movement focused on owning your content and identity online.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'<li>' . \wp_kses( \__( '<a href="https://webfinger.net/" target="_blank">WebFinger Protocol</a> - More information about WebFinger.', 'activitypub' ), $anchor_html ) . '</li>' . "\n" .
					'</ul>',
			)
		);

		// Enhanced Help Sidebar.
		\get_current_screen()->set_help_sidebar(
			'<p><strong>' . \__( 'For more information:', 'activitypub' ) . '</strong></p>' . "\n" .
			'<p><a href="https://wordpress.org/support/plugin/activitypub/">' . \esc_html__( 'Get support', 'activitypub' ) . '</a></p>' . "\n" .
			'<p><a href="https://github.com/Automattic/wordpress-activitypub/issues">' . \esc_html__( 'Report an issue', 'activitypub' ) . '</a></p>' . "\n" .
			'<p><a href="https://github.com/Automattic/wordpress-activitypub/tree/trunk/docs">' . \esc_html__( 'Documentation', 'activitypub' ) . '</a></p>' . "\n" .
			'<p><a href="https://github.com/Automattic/wordpress-activitypub/releases">' . \esc_html__( 'View latest changes', 'activitypub' ) . '</a></p>'
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

	/**
	 * Returns an array of recommended plugins for ActivityPub.
	 */
	public static function get_recommended_plugins() {
		$plugins = array();

		if ( ! \is_plugin_active( 'friends/friends.php' ) ) {
			$plugins['friends'] = array(
				'slug'        => 'friends',
				'author'      => 'Alex Kirk',
				'author_url'  => 'https://profiles.wordpress.org/akirk/',
				'icon'        => 'https://ps.w.org/friends/assets/icon-256x256.png',
				'name'        => \__( 'Friends', 'activitypub' ),
				'description' => \__( 'Follow people on Mastodon or similar platforms and display their posts on your WordPress, making your site a true Fediverse instance.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=friends&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'event-bridge-for-activitypub/event-bridge-for-activitypub.php' ) ) {
			$plugins['event_bridge'] = array(
				'slug'        => 'event-bridge-for-activitypub',
				'author'      => 'André Menrath',
				'author_url'  => 'https://profiles.wordpress.org/andremenrath/',
				'icon'        => 'https://ps.w.org/event-bridge-for-activitypub/assets/icon-256x256.gif',
				'name'        => \__( 'Event Bridge for ActivityPub', 'activitypub' ),
				'description' => \__( 'Make your events discoverable and federate them across decentralized platforms like Mastodon or Gancio.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=event-bridge-for-activitypub&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'enable-mastodon-apps/enable-mastodon-apps.php' ) ) {
			$plugins['enable_mastodon_apps'] = array(
				'slug'        => 'enable-mastodon-apps',
				'author'      => 'Alex Kirk',
				'author_url'  => 'https://profiles.wordpress.org/akirk/',
				'icon'        => 'https://ps.w.org/enable-mastodon-apps/assets/icon-256x256.png',
				'name'        => \__( 'Enable Mastodon Apps', 'activitypub' ),
				'description' => \__( 'Allow Mastodon apps to interact with your WordPress site, letting you write posts from your favorite app.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=enable-mastodon-apps&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'hum/hum.php' ) ) {
			$plugins['hum'] = array(
				'slug'        => 'hum',
				'author'      => 'Will Norris',
				'author_url'  => 'https://profiles.wordpress.org/willnorris/',
				'icon'        => 'https://s.w.org/plugins/geopattern-icon/hum.svg',
				'name'        => \__( 'Hum', 'activitypub' ),
				'description' => \__( 'A personal URL shortener for WordPress, perfect for sharing short links on the Fediverse.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=hum&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'webfinger/webfinger.php' ) ) {
			$plugins['webfinger'] = array(
				'slug'        => 'webfinger',
				'author'      => 'Matthias Pfefferle',
				'author_url'  => 'https://profiles.wordpress.org/pfefferle/',
				'icon'        => 'https://ps.w.org/webfinger/assets/icon-256x256.png',
				'name'        => \__( 'WebFinger', 'activitypub' ),
				'description' => \__( 'WebFinger protocol support for better discovery and compatibility.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=webfinger&TB_iframe=true' ),
			);
		}

		if ( ! \is_plugin_active( 'nodeinfo/nodeinfo.php' ) ) {
			$plugins['nodeinfo'] = array(
				'slug'        => 'nodeinfo',
				'author'      => 'Matthias Pfefferle',
				'author_url'  => 'https://profiles.wordpress.org/pfefferle/',
				'icon'        => 'https://ps.w.org/nodeinfo/assets/icon-256x256.png',
				'name'        => \__( 'NodeInfo', 'activitypub' ),
				'description' => \__( 'Advanced NodeInfo protocol support for better discovery and compatibility.', 'activitypub' ),
				'install_url' => \admin_url( 'plugin-install.php?tab=plugin-information&plugin=nodeinfo&TB_iframe=true' ),
			);
		}

		return $plugins;
	}

	/**
	 * Render recommended plugins as a beautiful, rich showcase for the help tab.
	 */
	public static function render_recommended_plugins_list() {
		$plugins = self::get_recommended_plugins();

		\ob_start();

		echo '<div class="plugin-list widefat">';

		foreach ( $plugins as $plugin ) :
			?>
			<div class="plugin-card plugin-card-<?php echo \esc_attr( $plugin['slug'] ); ?>">
				<div class="plugin-card-top">
					<div class="name column-name">
						<h3>
							<a href="<?php echo \esc_url( $plugin['install_url'] ); ?>" class="thickbox open-plugin-details-modal">
								<?php echo \esc_html( $plugin['name'] ); ?>
								<img src="<?php echo \esc_url( $plugin['icon'] ); ?>" class="plugin-icon" alt="">
							</a>
						</h3>
					</div>
					<div class="action-links">
						<ul class="plugin-action-buttons">
							<li>
								<a href="<?php echo \esc_url( $plugin['install_url'] ); ?>" class="button thickbox open-plugin-details-modal"><?php \esc_html_e( 'More Details', 'activitypub' ); ?></a>
							</li>
						</ul>
					</div>
					<div class="desc column-description">
						<p><?php echo \esc_html( $plugin['description'] ); ?></p>
						<p class="authors"> <cite>By <a href="<?php echo \esc_url( $plugin['author_url'] ); ?>"><?php echo \esc_html( $plugin['author'] ); ?></a></cite></p>
					</div>
				</div>
			</div>
			<?php
		endforeach;

		echo '</div>';

		return \ob_get_clean();
	}
}
