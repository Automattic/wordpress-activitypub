<?php
/**
 * Client Configuration controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Client Configuration Controller.
 *
 * Describes the limits a client needs to know before it submits content.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/65ca/fep-65ca.md
 */
class Client_Config_Controller extends \WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The REST base for this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'client-config';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/1.0',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Retrieves the discovery document.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$data = array(
			'links' => array(
				array(
					'rel'  => 'https://w3id.org/fep/65ca/1.0',
					'href' => get_rest_url_by_path( $this->rest_base . '/1.0' ),
				),
			),
		);

		$response = \rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}

	/**
	 * Retrieves the configuration document.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function get_item( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$links = array(
			'login'            => \wp_login_url(),
			'password_reset'   => \wp_lostpassword_url(),
			'account_settings' => \admin_url( 'profile.php' ),
		);

		if ( \get_option( 'users_can_register' ) ) {
			$links['registration'] = \wp_registration_url();
		}

		$privacy = \get_privacy_policy_url();
		if ( $privacy ) {
			$links['privacy'] = $privacy;
		}

		$data = array(
			'version'  => '1.0',
			'composer' => array(
				'max_length' => null,
			),
			'accounts' => array(
				'username' => array(
					'changeable' => false,
				),
			),
			'links'    => $links,
		);

		$response = \rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}
}
