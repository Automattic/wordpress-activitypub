<?php
namespace Activitypub\Peer;

/**
 * ActivityPub Followers DB-Class
 *
 * @author Matthias Pfefferle
 */
class Followers {
	public static function get_followers( $author_id ) {
		$extended_followers = self::get_followers_extended( $author_id );

		$followers = array();

		foreach( $extended_followers as $follower ) {
			$followers[] = $follower['ID'];
		}

		return $followers;
	}

	public static function get_followers_extended( $author_id, $limit = 0, $offset = 0 ) {
		GLOBAL $wpdb;

		$followers_table = $wpdb->prefix . 'ap_followers';
		$follower_table = $wpdb->prefix . 'ap_follower';
		$services_table = $wpdb->prefix . 'ap_services';

		$offset_sql = '';
		$limit_sql = '';

		$sql = $wpdb->prepare( "SELECT $followers_table.follower, since, $follower_table.avatar, $follower_table.description, $follower_table.is_bot, $follower_table.name, $follower_table.description, $follower_table.last_updated, $services_table.service, $services_table.version, $services_table.server, $services_table.open_reg FROM `$followers_table` INNER JOIN `$follower_table` ON $followers_table.follower=$follower_table.follower INNER JOIN `$services_table` ON $follower_table.server=$services_table.server WHERE $followers_table.ID = %d", $author_id );

		if( $limit > 0 ) {
			$sql = $wpdb->prepare( $sql . ' LIMIT %d, %d', $offset, $limit );
		}

		$sql .= ';';

		$results = $wpdb->get_results( $sql, ARRAY_A );

		if( is_array( $results ) && count( $results ) > 0 ) {
			return $results;
		}

		return array();
	}

	public static function count_followers( $author_id ) {
		$followers = self::get_followers( $author_id );

		return \count( $followers );
	}

	public static function count_followers_extended( $author_id ) {
		GLOBAL $wpdb;

		$sql = $wpdb->prepare( 'SELECT count( follower ) FROM `' . $wpdb->prefix . 'ap_followers' . '` WHERE ID = %d;', $author_id );

		$result = $wpdb->get_var( $sql );

		return intval( $result );
	}

	public static function add_follower( $actor, $author_id ) {
		if ( ! \is_string( $actor ) ) {
			if (
				\is_array( $actor ) &&
				isset( $actor['type'] ) &&
				'Person' === $actor['type'] &&
				isset( $actor['id'] ) &&
				false !== \filter_var( $actor['id'], \FILTER_VALIDATE_URL )
			) {
				$actor = $actor['id'];
			}

			return new \WP_Error(
				'invalid_actor_object',
				\__( 'Unknown Actor schema', 'activitypub' ),
				array(
					'status' => 404,
				)
			);
		}

		self::store_follower( $actor, $author_id );

		$server = parse_url( $actor, PHP_URL_HOST );

		$service_data = self::store_service_info( $server );

		if( count( $service_data ) > 0 ) {
			$service = $service_data['service'];
		} else {
			$service = false;
		}

		self::store_follower_info( $actor, $service, $server );
	}

	public static function store_follower( $follower, $author_id ){
		GLOBAL $wpdb;

		// Store the follower in the followers table.
		$data = array(
						'ID'       => $author_id,
						'follower' => $follower,
						'since'    => date( 'Y-m-d H:i:s', time() ),
					);

		if( count( self::get_follower( $follower, $author_id ) ) > 0 ) {
			$wpdb->update( $wpdb->prefix . 'ap_followers', $data, array( 'follower' => $follower, 'ID' => $author_id ) );
		} else {
			$wpdb->insert( $wpdb->prefix . 'ap_followers', $data );
		}

	}

	public static function get_follower( $follower, $author_id ) {
		GLOBAL $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . 'ap_followers` WHERE follower = %s AND ID = %d;', $follower, $author_id );
		$results = $wpdb->get_row( $sql, ARRAY_A );

		if( is_array( $results ) && count( $results ) > 0 ) {
			return $results;
		}

		return array();
	}

	public static function store_service_info( $server ) {
		GLOBAL $wpdb;

		$ni = new Activitypub\Nodeinfo();

		// Retrieve the nodeinfo.
		$nodeinfo = $ni->resolve( $server );

		// Setup variables.
		$service = $version = $open_reg = null;

		// Make sure we got some data from nodeinfo.
		if( is_array( $nodeinfo ) ) {
			// Make sure the software name/version exist.
			if ( array_key_exists( 'software', $nodeinfo ) && is_array( $nodeinfo['software'] ) && array_key_exists( 'name', $nodeinfo['software'] ) && array_key_exists( 'version', $nodeinfo['software'] ) ) {
				$service = $nodeinfo['software']['name'];
				$version = $nodeinfo['software']['version'];
			}

			// Make sure the open registration data exists.
			if( array_key_exists( 'openRegistrations', $nodeinfo ) ) {
				$open_reg = (bool) $nodeinfo['openRegistrations'];
			}

			if( $service ) {
				$data = array(
								'service'      => $service,
								'server'       => $server,
								'version'      => $version,
								'open_reg'     => $open_reg,
								'last_updated' => date( 'Y-m-d H:i:s', time() ),
							);

				if( count( self::get_service_info( $service ) ) > 0 ) {
					$wpdb->update( $wpdb->prefix . 'ap_services', $data, array( 'server' => $server ) );
				} else {
					$wpdb->insert( $wpdb->prefix . 'ap_services', $data );
				}
			}
		}

		return $data;
	}

	public static function get_service_info( $server ) {
		GLOBAL $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'ap_services WHERE server = %s;', $server );
		$raw_services = $wpdb->get_results( $sql, ARRAY_A );

		var_dump($sql);

		if( is_array( $raw_services ) ) {
			return $raw_services[0];
		}

		return array();

	}

	public static function store_follower_info( $follower, $service, $server ) {
		GLOBAL $wpdb;

		$avatar = $profile = $name = $description = $is_bot = null;

		if( $service == 'mastodon' ) {
			$mastodon_info = self::get_mastodon_user_info( $follower );

			if( is_array( $mastodon_info ) ) {
				if( array_key_exists( 'avatar', $mastodon_info ) ) {
					$avatar = $mastodon_info['avatar'];
				}

				if( array_key_exists( 'url', $mastodon_info ) ) {
					$profile = $mastodon_info['url'];
				}

				if( array_key_exists( 'display_name', $mastodon_info ) ) {
					$name = $mastodon_info['display_name'];
				}

				if( array_key_exists( 'note', $mastodon_info ) ) {
					$description = $mastodon_info['note'];
				}

				if( array_key_exists( 'bot', $mastodon_info ) ) {
					$is_bot = $mastodon_info['bot'];
				}
			}
		}

		$data = array(
						'follower'     => $follower,
						'server'       => $server,
						'avatar'       => $avatar,
						'profile'      => $profile,
						'name'         => $name,
						'description'  => $description,
						'is_bot'       => $is_bot,
						'last_updated' => date( 'Y-m-d H:i:s', time() ),
					);

		if( count( self::get_follower_info( $follower ) ) > 0 ) {
			$wpdb->update( $wpdb->prefix . 'ap_follower', $data, array( 'follower' => $follower ) );
		} else {
			$wpdb->insert( $wpdb->prefix . 'ap_follower', $data );
		}

	}

	public static function get_follower_info( $follower ) {
		GLOBAL $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . 'ap_follower` WHERE follower = %s;', $follower );
		$results = $wpdb->get_row( $sql, ARRAY_A );

		if( is_array( $results ) && count( $results ) > 0 ) {
			return $results[0];
		}

		return array();

	}

	public static function get_mastodon_user_info( $follower ) {
		$ma = new Activitypub\Mastodon();

		$mastodon_info = $ma->resolve( $follower );

		if( $is_array( $mastodon_info ) ) {
			return $mastodon_info;
		}

		return array();
	}

	public static function remove_follower( $actor, $author_id ) {
		GLOBAL $wpdb;

		// Remove the follower in the followers table.
		$where = array(
						'ID'       => $author_id,
						'follower' => $actor,
					);

		$wpdb->delete( $wpdb->prefix . 'ap_followers', $where );

		// Check to see if this is the last user that followers the user.
		$sql = $wpdb->prepare( 'SELECT count( follower ) FROM `' . $wpdb->prefix . 'ap_followers' . '` WHERE follower = %s;', $actor );
		$result = $wpdb->get_var( $sql );

		// If there are no more users following this follower, remove them from the follower table.
		if( $result == 0 ) {
			$follower = self::get_follower_info( $actor );

			$where = array(
							'follower' => $actor,
						);

			$wpdb->delete( $wpdb->prefix . 'ap_follower', $where );

			// Now see if this was the last follower of the service.
			$sql = $wpdb->prepare( 'SELECT count( server ) FROM `' . $wpdb->prefix . 'ap_follower' . '` WHERE server = %s;', $follower['server'] );
			$result = $wpdb->get_var( $sql );

			// If there are now more followers with this service, remove it as well.
			if( $result == 0 ) {
				$where = array(
								'server' => $follower['server'],
							);

				$wpdb->delete( $wpdb->prefix . 'ap_services', $where );
			}

		}
	}
}
