<?php
/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_activitypub_context() {
	$context = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/security/v1',
		array(
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'sensitive' => 'as:sensitive',
			'movedTo' => array(
				'@id' => 'as:movedTo',
				'@type' => '@id',
			),
			'Hashtag' => 'as:Hashtag',
			'ostatus' => 'http://ostatus.org#',
			'atomUri' => 'ostatus:atomUri',
			'inReplyToAtomUri' => 'ostatus:inReplyToAtomUri',
			'conversation' => 'ostatus:conversation',
			'toot' => 'http://joinmastodon.org/ns#',
			'Emoji' => 'toot:Emoji',
			'focalPoint' => array(
				'@container' => '@list',
				'@id' => 'toot:focalPoint',
			),
			'featured' => array(
				'@id' => 'toot:featured',
				'@type' => '@id',
			),
			'schema' => 'http://schema.org#',
			'PropertyValue' => 'schema:PropertyValue',
			'value' => 'schema:value',
		),
	);

	return apply_filters( 'activitypub_json_context', $context );
}
