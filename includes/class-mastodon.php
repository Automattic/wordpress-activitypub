<?php
namespace Activitypub;

/**
 * ActivityPub Mastodon Class
 *
 * @author Matthias Pfefferle & Greg Ross
 *
 * @see https://docs.joinmastodon.org/api/
 */
class Mastodon {
	/**
	 * Returns a user's profile info
	 *
	 * @param string $follower   The follower to retrieve the profile data for.
	 *
	 * @return WP_Error|array   A WP_Error object if an error occurs, or an array of the decoded json profile
	 */
	public static function resolve( $follower ) {
		// Get the server name we're going to query.
		$server = parse_url( $follower, PHP_URL_HOST );

		// Get the full path from the follower.
		$path = parse_url( $follower, PHP_URL_PATH );

		// Now grab the last component of the path and format it.
		$account = '@' . basename( $path ) . '@' . $server;

		// Now setup the url to grab the profile data from.
		$url = \add_query_arg( 'q', $account, 'https://' . $server . '/api/v2/search' );
		if ( ! \wp_http_validate_url( $url ) ) {
			return new \WP_Error( 'invalid_mastodon_api_url', null, $url );
		}

		// Retrieve the user data.
		$response = \wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 0,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return new \WP_Error( 'mastodon_url_not_accessible', null, $url );
		}

		$response_code = \wp_remote_retrieve_response_code( $response );

		// Get the body and decode it.
		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		if ( ! is_array( $body ) && array_key_exists( 'accounts', $body ) ) {
			return new \WP_Error( 'mastodon_url_invalid_response', null, $url );
		}

		// Setup our return variable.
		$data = false;

		// Check to see if we have some results.
		if( is_array( $body['accounts'] ) && count( $body['accounts'] ) > 0 ) {

			// Loop through the accounts returned, there may be multiple from different instances of Mastodon.
			foreach( $body['accounts'] as $account ) {
				// Decode the account url to get the host name.
				$host = parse_url( $account['url'], PHP_URL_HOST );

				// Assume that the first account with the same server name is the one we're looking for.
				if( $host == $server ) {
					// Store the account data.
					$data = $account;

					// Stop looking for more.
					break;
				}
			}
		} else {
			return new \WP_Error( 'mastodon_no_accounts', null, $url );
		}

		// If we didn't find an account that matches, return an error.
		if( $data === false ) {
			return new \WP_Error( 'mastodon_account_not_found', null, $url );
		}

		// Finally return the result.
		return $data;
	}
}
