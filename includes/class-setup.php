<?php
namespace Activitypub;

use WP_Roles;

/**
 * ActivityPub Migration Class
 *
 * @author Matthias Pfefferle
 */
class Setup {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::add_roles();
		self::add_activitypub_capability();
		self::generate_blog_user();
		self::generate_aplication_user();
	}

	/**
	 * Add the ActivityPub capability to all users that can publish posts
	 *
	 * @return void
	 */
	private static function add_activitypub_capability() {
		// get all WP_User objects that can publish posts
		$users = \get_users(
			array(
				'capability__in' => array( 'publish_posts' ),
			)
		);

		// add ActivityPub capability to all users that can publish posts
		foreach ( $users as $user ) {
			$user->add_cap( 'activitypub' );
		}
	}

	/**
	 * Generate the ActivityPub Blog User
	 *
	 * @return void
	 */
	public static function generate_blog_user() {
		$user_pass = wp_generate_password( 15, true, true );

		// check if domain host has a subdomain
		$host = \wp_parse_url( \get_home_url(), \PHP_URL_HOST );
		$host = \preg_replace( '/^www\./i', '', $host );

		wp_insert_user(
			array(
				'user_login'  => $host,
				'user_pass'   => $user_pass,
				'display_name' => 'The ActivityPub Blog User',
				'description' => \get_bloginfo( 'description' ),
				'role'        => 'activitypub_blog',
			)
		);
	}

	/**
	 * Generate the ActivityPub Application User
	 *
	 * @return void
	 */
	public static function generate_aplication_user() {
		$user_pass = wp_generate_password( 15, true, true );

		wp_insert_user(
			array(
				'user_login'   => 'application',
				'display_name' => 'The ActivityPub Application User',
				'user_pass'    => $user_pass,
				'description'  => \get_bloginfo( 'description' ),
				'role'         => 'activitypub_application',
			)
		);
	}

	/**
	 * Add the ActivityPub roles to the site
	 *
	 * @return void
	 */
	public static function add_roles() {
		$default_roles = array(
			'activitypub_application' => _x( 'ActivityPub Application', 'User role', 'activitypub' ),
			'activitypub_blog'        => _x( 'ActivityPub Blog', 'User role', 'activitypub' ),
		);

		$roles = new WP_Roles();

		foreach ( $default_roles as $type => $name ) {
			$role = false;
			foreach ( $roles->roles as $slug => $data ) {
				if ( isset( $data['capabilities'][ $type ] ) ) {
					$role = get_role( $slug );
					break;
				}
			}
			if ( ! $role ) {
				$role = add_role( $type, $name, self::get_role_capabilities( $type ) );
				continue;
			}

			// This might update missing capabilities.
			foreach ( array_keys( self::get_role_capabilities( $type ) ) as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	public static function get_role_capabilities( $role ) {
		$capabilities = array();

		$capabilities['activitypub_application'] = array(
			'activitypub' => true,
		);

		$capabilities['activitypub_blog'] = array(
			'activitypub' => true,
		);

		if ( ! isset( $capabilities[ $role ] ) ) {
			return array();
		}

		return $capabilities[ $role ];
	}
}
