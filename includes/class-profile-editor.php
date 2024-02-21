<?php

namespace Activitypub;

class Profile_Editor {

	public static function init() {
		\add_action( 'admin_init', array( self::class, 'register_scripts' ) );
	}

	public static function register_scripts() {
		$asset_data = include ACTIVITYPUB_PLUGIN_DIR . '/build/profile-editor/index.asset.php';

		\wp_register_script(
			'activitypub-profile-editor',
			ACTIVITYPUB_PLUGIN_URL . 'build/profile-editor/index.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		\wp_register_style(
			'activitypub-profile-editor',
			ACTIVITYPUB_PLUGIN_URL . 'build/profile-editor/style-index.css',
			array(),
			$asset_data['version']
		);

		$data = array(
			'namespace' => ACTIVITYPUB_REST_NAMESPACE,
			'enabled' => array(
				'site' => ! is_user_type_disabled( 'blog' ),
				'users' => ! is_user_type_disabled( 'user' ),
			),
		);
		$js = sprintf( 'var _activityPubOptions = %s;', wp_json_encode( $data ) );
		\wp_add_inline_script( 'activitypub-profile-editor', $js, 'before' );

		// @todo this is just for testing, scope it better elsewhere
		\wp_enqueue_script( 'activitypub-profile-editor' );
		\wp_enqueue_style( 'activitypub-profile-editor' );
		\wp_enqueue_media();
	}
}
