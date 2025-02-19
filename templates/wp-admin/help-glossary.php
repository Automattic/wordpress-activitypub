<?php
/**
 * Help template for glossary.
 *
 * @package Activitypub
 */

?>

<h2><?php esc_html_e( 'Fediverse', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'The Fediverse is a new word made of two words: "federation" + "universe"', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'It is a federated social network running on free open software on a myriad of computers across the globe. Many independent servers are interconnected and allow people to interact with one another. There&#8217;s no one central site: you choose a server to register. This ensures some decentralization and sovereignty of data. Fediverse (also called Fedi) has no built-in advertisements, no tricky algorithms, no one big corporation dictating the rules. Instead we have small cozy communities of like-minded people. Welcome!', 'activitypub' ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: Link to more information */
		esc_html__( 'For more information please visit %s', 'activitypub' ),
		'<a href="https://fediverse.party/" target="_blank">fediverse.party</a>'
	);
	?>
</p>
<h2><?php esc_html_e( 'ActivityPub', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'ActivityPub is a decentralized social networking protocol based on the ActivityStreams 2.0 data format. ActivityPub is an official W3C recommended standard published by the W3C Social Web Working Group. It provides a client to server API for creating, updating and deleting content, as well as a federated server to server API for delivering notifications and subscribing to content.', 'activitypub' ); ?></p>
<h2><?php esc_html_e( 'WebFinger', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'WebFinger is used to discover information about people or other entities on the Internet that are identified by a URI using standard Hypertext Transfer Protocol (HTTP) methods over a secure transport. A WebFinger resource returns a JavaScript Object Notation (JSON) object describing the entity that is queried. The JSON object is referred to as the JSON Resource Descriptor (JRD).', 'activitypub' ); ?></p>
<p><?php esc_html_e( 'For a person, the type of information that might be discoverable via WebFinger includes a personal profile address, identity service, telephone number, or preferred avatar. For other entities on the Internet, a WebFinger resource might return JRDs containing link relations that enable a client to discover, for example, that a printer can print in color on A4 paper, the physical location of a server, or other static information.', 'activitypub' ); ?></p>
<p>
	<?php
	echo wp_kses(
		__( 'On Mastodon [and other platforms], user profiles can be hosted either locally on the same website as yours, or remotely on a completely different website. The same username may be used on a different domain. Therefore, a Mastodon user&#8217;s full mention consists of both the username and the domain, in the form <code>@username@domain</code>. In practical terms, <code>@user@example.com</code> is not the same as <code>@user@example.org</code>. If the domain is not included, Mastodon will try to find a local user named <code>@username</code>. However, in order to deliver to someone over ActivityPub, the <code>@username@domain</code> mention is not enough – mentions must be translated to an HTTPS URI first, so that the remote actor&#8217;s inbox and outbox can be found. (This paragraph is copied from the <a href="https://docs.joinmastodon.org/spec/webfinger/" target="_blank">Mastodon Documentation</a>)', 'activitypub' ),
		array(
			'code' => true,
			'a'    => array(
				'href'   => true,
				'target' => true,
			),
		)
	);
	?>
</p>
<p>
	<?php
	printf(
		/* translators: %s: Link to more information */
		esc_html__( 'For more information please visit %s', 'activitypub' ),
		'<a href="https://webfinger.net/" target="_blank">webfinger.net</a>'
	);
	?>
</p>
<h2><?php esc_html_e( 'NodeInfo', 'activitypub' ); ?></h2>
<p><?php esc_html_e( 'NodeInfo is an effort to create a standardized way of exposing metadata about a server running one of the distributed social networks. The two key goals are being able to get better insights into the user base of distributed social networking and the ability to build tools that allow users to choose the best fitting software and server for their needs.', 'activitypub' ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: Link to more information */
		esc_html__( 'For more information please visit %s', 'activitypub' ),
		'<a href="http://nodeinfo.diaspora.software/" target="_blank">nodeinfo.diaspora.software</a>'
	);
	?>
</p>
