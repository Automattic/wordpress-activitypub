<?php
/**
 * Glossary Help Tab template.
 *
 * @package Activitypub
 */

/* translators: %s: Link to more information */
$info_string = __( 'For more information please visit %s.', 'activitypub' );
$allow_html  = array(
	'code' => array(),
	'a'    => array(
		'href'   => true,
		'target' => true,
	),
)
?>

<h2><?php esc_html_e( 'Fediverse Terminology', 'activitypub' ); ?></h2>
<dl>
	<dt><?php esc_html_e( 'Fediverse', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'A network of interconnected servers using open protocols, primarily ActivityPub, allowing users from different platforms to interact with each other. The term combines &#8220;federation&#8221; and &#8220;universe&#8221;.', 'activitypub' ); ?></dd>
	<dd><?php esc_html_e( 'It is a federated social network running on free open software on a myriad of computers across the globe. Many independent servers are interconnected and allow people to interact with one another. There&#8217;s no one central site: you choose a server to register. This ensures some decentralization and sovereignty of data. Fediverse (also called Fedi) has no built-in advertisements, no tricky algorithms, no one big corporation dictating the rules. Instead we have small cozy communities of like-minded people. Welcome!', 'activitypub' ); ?></dd>
	<dd><?php printf( esc_html( $info_string ), '<a href="https://fediverse.party/" target="_blank">fediverse.party</a>' ); ?></dd>

	<dt><?php esc_html_e( 'Federation', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'The process by which servers communicate with each other to share content and interactions across different platforms and instances.', 'activitypub' ); ?></dd>

	<dt><?php esc_html_e( 'Instance', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'A server running Fediverse software. Your WordPress site with ActivityPub enabled becomes an instance in the Fediverse.', 'activitypub' ); ?></dd>

	<dt><?php esc_html_e( 'Local Timeline', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Content from users on the same instance. In WordPress context, this would be posts from your WordPress site.', 'activitypub' ); ?></dd>
</dl>

<h2><?php esc_html_e( 'ActivityPub Concepts', 'activitypub' ); ?></h2>
<dl>
	<dt><?php esc_html_e( 'ActivityPub', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ); ?></dd>

	<dt><?php esc_html_e( 'Actor', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'An entity that can perform activities. In WordPress, actors are typically users or the blog itself.', 'activitypub' ); ?></dd>

	<dt><?php esc_html_e( 'Activity', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'An action performed by an actor, such as creating a post, liking content, or following someone.', 'activitypub' ); ?></dd>

	<dt><?php esc_html_e( 'Object', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'The target of an activity, such as a post, comment, or profile.', 'activitypub' ); ?></dd>
</dl>

<h2><?php esc_html_e( 'WebFinger and Discovery', 'activitypub' ); ?></h2>
<dl>
	<dt><?php esc_html_e( 'WebFinger', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'WebFinger is used to discover information about people or other entities on the Internet that are identified by a URI using standard Hypertext Transfer Protocol (HTTP) methods over a secure transport. A WebFinger resource returns a JavaScript Object Notation (JSON) object describing the entity that is queried. The JSON object is referred to as the JSON Resource Descriptor (JRD).', 'activitypub' ); ?></dd>
	<dd><?php esc_html_e( 'For a person, the type of information that might be discoverable via WebFinger includes a personal profile address, identity service, telephone number, or preferred avatar. For other entities on the Internet, a WebFinger resource might return JRDs containing link relations that enable a client to discover, for example, that a printer can print in color on A4 paper, the physical location of a server, or other static information.', 'activitypub' ); ?></dd>
	<dd>
		<blockquote>
			<?php echo wp_kses( __( 'On Mastodon [and other platforms], user profiles can be hosted either locally on the same website as yours, or remotely on a completely different website. The same username may be used on a different domain. Therefore, a Mastodon user&#8217;s full mention consists of both the username and the domain, in the form <code>@username@domain</code>. In practical terms, <code>@user@example.com</code> is not the same as <code>@user@example.org</code>. If the domain is not included, Mastodon will try to find a local user named <code>@username</code>. However, in order to deliver to someone over ActivityPub, the <code>@username@domain</code> mention is not enough – mentions must be translated to an HTTPS URI first, so that the remote actor&#8217;s inbox and outbox can be found.', 'activitypub' ), $allow_html ); ?>
			<cite><a href="https://docs.joinmastodon.org/spec/webfinger/" target="_blank"><?php esc_html_e( 'Mastodon Documentation', 'activitypub' ); ?></a></cite>
		</blockquote>
	</dd>
	<dd><?php printf( esc_html( $info_string ), '<a href="https://webfinger.net/" target="_blank">webfinger.net</a>' ); ?></dd>

	<dt><?php esc_html_e( 'Handle', 'activitypub' ); ?></dt>
	<dd><?php echo wp_kses( __( 'A user&#8217;s identity in the Fediverse, formatted as <code>@username@domain.com</code>. Similar to an email address, it includes both the username and the server where the account is hosted.', 'activitypub' ), array( 'code' => array() ) ); ?></dd>

	<dt><?php esc_html_e( 'NodeInfo', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'A standardized way of exposing metadata about a server running one of the distributed social networks. It helps with compatibility and discovery between different Fediverse platforms.', 'activitypub' ); ?></dd>
	<dd><?php printf( esc_html( $info_string ), '<a href="https://nodeinfo.diaspora.software/" target="_blank">nodeinfo.diaspora.software</a>' ); ?></dd>
</dl>

<h2><?php esc_html_e( 'WordPress-Specific Terms', 'activitypub' ); ?></h2>
<dl>
	<dt><?php esc_html_e( 'Template Tags', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Shortcodes used in the ActivityPub plugin to customize how content appears when federated to the Fediverse.', 'activitypub' ); ?></dd>
	<dt><?php esc_html_e( 'Federation Settings', 'activitypub' ); ?></dt>
	<dd><?php esc_html_e( 'Configuration options that control how WordPress content is shared with the Fediverse.', 'activitypub' ); ?></dd>
</dl>
