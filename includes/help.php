<?php

\get_current_screen()->add_help_tab(
	array(
		'id'      => 'template-tags',
		'title'   => \__( 'Template Tags', 'activitypub' ),
		'content' =>
			'<p>' . __( 'The following Template Tags are available:', 'activitypub' ) . '</p>' .
			'<dl>' .
				'<dt><code>[ap_title]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s title.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_content apply_filters="yes"]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s content. With <code>apply_filters</code> you can decide if filters (<code>apply_filters( \'the_content\', $content )</code>) should be applied or not (default is <code>yes</code>). The values can be <code>yes</code> or <code>no</code>. <code>apply_filters</code> attribute is optional.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_excerpt length="400"]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s excerpt (uses <code>the_excerpt</code> if that is set). If no excerpt is provided, will truncate at <code>length</code> (optional, default = 400).', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_permalink type="url"]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s permalink. <code>type</code> can be either: <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). <code>type</code> attribute is optional.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_shortlink type="url"]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s shortlink. <code>type</code> can be either <code>url</code> or <code>html</code> (an &lt;a /&gt; tag). I can recommend <a href="https://wordpress.org/plugins/hum/" target="_blank">Hum</a>, to prettify the Shortlinks. <code>type</code> attribute is optional.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_hashtags]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s tags as hashtags.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_hashcats]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s categories as hashtags.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_image type=full]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The URL for the post\'s featured image, defaults to full size. The type attribute can be any of the following: <code>thumbnail</code>, <code>medium</code>, <code>large</code>, <code>full</code>. <code>type</code> attribute is optional.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_author]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The author\'s name.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_authorurl]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The URL to the author\'s profile page.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_date]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s date.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_time]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s time.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_datetime]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The post\'s date/time formated as "date @ time".', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_blogurl]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The URL to the site.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_blogname]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The name of the site.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
				'<dt><code>[ap_blogdesc]</code></dt>' .
				'<dd>' . \wp_kses( __( 'The description of the site.', 'activitypub' ), array( 'code' => array() ) ) . '</dd>' .
			'</dl>' .
			'<p>' . __( 'You may also use any Shortcode normally available to you on your site, however be aware that Shortcodes may significantly increase the size of your content depending on what they do.', 'activitypub' ) . '</p>' .
			'<p>' . __( 'Note: the old Template Tags are now deprecated and automatically converted to the new ones.', 'activitypub' ) . '</p>' .
			'<p>' . \wp_kses( \__( '<a href="https://github.com/pfefferle/wordpress-activitypub/issues/new" target="_blank">Let me know</a> if you miss a Template Tag.', 'activitypub' ), 'activitypub' ) . '</p>',
	)
);

\get_current_screen()->add_help_tab(
	array(
		'id'      => 'glossary',
		'title'   => \__( 'Glossary', 'activitypub' ),
		'content' =>
			'<p><h2>' . \__( 'Fediverse', 'activitypub' ) . '</h2></p>' .
			'<p>' . \__( 'The Fediverse is a new word made of two words: "federation" + "universe"', 'activitypub' ) . '</p>' .
			'<p>' . \__( 'It is a federated social network running on free open software on a myriad of computers across the globe. Many independent servers are interconnected and allow people to interact with one another. There\'s no one central site: you choose a server to register. This ensures some decentralization and sovereignty of data. Fediverse (also called Fedi) has no built-in advertisements, no tricky algorithms, no one big corporation dictating the rules. Instead we have small cozy communities of like-minded people. Welcome!', 'activitypub' ) . '</p>' .
			'<p>' . \__( 'For more informations please visit <a href="https://fediverse.party/" target="_blank">fediverse.party</a>', 'activitypub' ) . '</p>' .
			'<p><h2>' . \__( 'ActivityPub', 'activitypub' ) . '</h2></p>' .
			'<p>' . \__( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ) . '</p>' .
			'<p><h2>' . \__( 'WebFinger', 'activitypub' ) . '</h2></p>' .
			'<p>' . \__( 'WebFinger is used to discover information about people or other entities on the Internet that are identified by a URI using standard Hypertext Transfer Protocol (HTTP) methods over a secure transport. A WebFinger resource returns a JavaScript Object Notation (JSON) object describing the entity that is queried. The JSON object is referred to as the JSON Resource Descriptor (JRD).', 'activitypub' ) . '</p>' .
			'<p>' . \__( 'For a person, the type of information that might be discoverable via WebFinger includes a personal profile address, identity service, telephone number, or preferred avatar. For other entities on the Internet, a WebFinger resource might return JRDs containing link relations that enable a client to discover, for example, that a printer can print in color on A4 paper, the physical location of a server, or other static information.', 'activitypub' ) . '</p>' .
			'<p>' . \__( 'On Mastodon [and other Plattforms], user profiles can be hosted either locally on the same website as yours, or remotely on a completely different website. The same username may be used on a different domain. Therefore, a Mastodon user\'s full mention consists of both the username and the domain, in the form <code>@username@domain</code>. In practical terms, <code>@user@example.com</code> is not the same as <code>@user@example.org</code>. If the domain is not included, Mastodon will try to find a local user named <code>@username</code>. However, in order to deliver to someone over ActivityPub, the <code>@username@domain</code> mention is not enough – mentions must be translated to an HTTPS URI first, so that the remote actor\'s inbox and outbox can be found. (This paragraph is copied from the <a href="https://docs.joinmastodon.org/spec/webfinger/" target="_blank">Mastodon Documentation</a>)', 'activitypub' ) . '</p>' .
			'<p>' . \__( 'For more informations please visit <a href="https://webfinger.net/" target="_blank">webfinger.net</a>', 'activitypub' ) . '</p>' .
			'<p><h2>' . \__( 'NodeInfo', 'activitypub' ) . '</h2></p>' .
			'<p>' . \__( 'NodeInfo is an effort to create a standardized way of exposing metadata about a server running one of the distributed social networks. The two key goals are being able to get better insights into the user base of distributed social networking and the ability to build tools that allow users to choose the best fitting software and server for their needs.', 'activitypub' ) . '</p>' .
			'<p>' . \__( 'For more informations please visit <a href="http://nodeinfo.diaspora.software/" target="_blank">nodeinfo.diaspora.software</a>', 'activitypub' ) . '</p>',
	)
);

\get_current_screen()->set_help_sidebar(
	'<p><strong>' . \__( 'For more information:', 'activitypub' ) . '</strong></p>' .
	'<p>' . \__( '<a href="https://wordpress.org/support/plugin/activitypub/">Get support</a>', 'activitypub' ) . '</p>' .
	'<p>' . \__( '<a href="https://github.com/automattic/wordpress-activitypub/issues">Report an issue</a>', 'activitypub' ) . '</p>'
);
