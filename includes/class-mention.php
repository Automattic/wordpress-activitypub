<?php
namespace Activitypub;

/**
 * ActivityPub Mention Class
 *
 * @author Alex Kirk
 */
class Mention {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'the_content', array( '\Activitypub\Mention', 'the_content' ), 99, 2 );
		\add_filter( 'activitypub_extract_mentions', array( '\Activitypub\Mention', 'extract_mentions' ), 99, 2 );
	}

	/**
	 * Filter to replace the #tags in the content with links
	 *
	 * @param string $the_content the post-content
	 *
	 * @return string the filtered post-content
	 */
	public static function the_content( $the_content ) {
		$the_content = \preg_replace_callback( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/i', array( '\Activitypub\Mention', 'replace_with_links' ), $the_content );

		return $the_content;
	}

	/**
	 * A callback for preg_replace to build the term links
	 *
	 * @param array $result the preg_match results
	 * @return string the final string
	 */
	public static function replace_with_links( $result ) {
		$metadata = \ActivityPub\get_remote_metadata_by_actor( $result[0] );

		if ( ! is_wp_error( $metadata ) ) {
			$username = $metadata['name'];
			if ( ! empty( $metadata['preferredUsername'] ) ) {
				$username = $metadata['preferredUsername'];
			}
			$username = '@<span>' . $username . '</span>';
			return \sprintf( '<a rel="mention" class="u-url mention href="%s">%s</a>', $metadata['url'], $username );
		}

		return $username;
	}

	public static function extract_mentions( $mentions, \ActivityPub\Model\Post $post ) {
		\preg_match_all( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/i', $post->get_content(), $matches );
		foreach ( $matches[0] as $match ) {
			$link = \Activitypub\Rest\Webfinger::resolve( $match );
			if ( ! is_wp_error( $link ) ) {
				$mentions[ $match ] = $link;
			}
		}
		return $mentions;

	}
}
