<?php
/**
 * Inspired by the way elementor handles addons.
 *
 * @link https://github.com/elementor/elementor/
 */

namespace Activitypub;

use WP_Post;
use WP_Comment;

use function Activitypub\camel_to_snake_case;
use function Activitypub\snake_to_camel_case;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ActivityPub transformers manager.
 *
 * ActivityPub transformers manager handler class is responsible for registering and
 * initializing all the supported WP-Pobject to ActivityPub transformers.
 *
 * @since version_number_transformer_management_placeholder
 */

class Transformers_Manager {
	const DEFAULT_TRANSFORMER_MAPPING = array(
		'post' => ACTIVITYPUB_DEFAULT_TRANSFORMER,
		'page' => ACTIVITYPUB_DEFAULT_TRANSFORMER,
	);

	/**
	 * Transformers.
	 *
	 * Holds the list of all the ActivityPub transformers. Default is `null`.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access private
	 *
	 * @var \ActivityPub\Transformer_Base[]
	 */
	private $transformers = null;

	/**
	 * Module instance.
	 *
	 * Holds the transformer instance.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access protected
	 *
	 * @var Module
	 */
	protected static $_instances = [];


	/**
	 * Instance.
	 *
	 * Ensures only one instance of the transformer manager class is loaded or can be loaded.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access public
	 * @static
	 *
	 * @return Module An instance of the class.
	 */
	public static function instance() {
		$class_name = static::class_name();

		if ( empty( static::$_instances[ $class_name ] ) ) {
			static::$_instances[ $class_name ] = new static();
		}

		return static::$_instances[ $class_name ];
	}

	/**
	 * Class name.
	 *
	 * Retrieve the name of the class.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access public
	 * @static
	 */
	public static function class_name() {
		return get_called_class();
	}

    /**
	 * Transformers manager constructor.
	 *
	 * Initializing ActivityPub transformers manager.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access public
	*/
	public function __construct() {
		$this->require_files();
	}

	/**
	 * Require files.
	 *
	 * Require ActivityPub transformer base class.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access private
	*/
	private function require_files() {
		require ACTIVITYPUB_PLUGIN_DIR . 'includes/class-transformer-base.php';
	}

	/**
	 * Checks if a transformer is registered.
	 *
	 * @since version_number_transformer_management_placeholder
	 *
	 * @param string $name Transformer name including namespace.
	 * @return bool True if the block type is registered, false otherwise.
	 */
	public function is_registered( $name ) {
		return isset( $this->transformers[ $name ] );
	}

	/**
	 * Register a transformer.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access public
	 *
	 * @param \ActivityPub\Transformer_Base $transformer_instance ActivityPub Transformer.
	 *
	 * @return bool True if the ActivityPub transformer was registered.
	 */
	public function register( Transformer_Base $transformer_instance) {

		if ( ! $transformer_instance instanceof Transformer_Base ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'ActivityPub transformer instance must be a of \ActivityPub\Transformer_Base class.' ),
				'version_number_transformer_management_placeholder'
			);
			return false;
		}
		
		$transformer_name = $transformer_instance->get_name();
		if ( preg_match( '/[A-Z]+/', $transformer_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'ActivityPub transformer names must not contain uppercase characters.' ),
				'version_number_transformer_management_placeholder'
			);
			return false;
		}

		$name_matcher = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
		if ( ! preg_match( $name_matcher, $transformer_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'ActivityPub transformer names must contain a namespace prefix. Example: my-plugin/my-custom-transformer' ),
				'version_number_transformer_management_placeholder'
			);
			return false;
		}

		if ( $this->is_registered( $transformer_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Block name. */
				sprintf( __( 'ActivityPub transformer with name "%s" is already registered.' ), $transformer_name ),
				'version_number_transformer_management_placeholder'
			);
			return false;
		}	

		/**
		 * Should the ActivityPub transformer be registered.
		 *
		 * @since version_number_transformer_management_placeholder
		 *
		 * @param bool $should_register Should the ActivityPub transformer be registered. Default is `true`.
		 * @param \ActivityPub\Transformer_Base $transformer_instance Widget instance.
		 */
		// TODO: does not implementing this slow down the website? -> compare with gutenberg block registration.
		// $should_register = apply_filters( 'activitypub/transformers/is_transformer_enabled', true, $transformer_instance );

		// if ( ! $should_register ) {
		// 	return false;
		// }

		$this->transformers[ $transformer_name ] = $transformer_instance;

		return true;
	}

	/**
	 * Init transformers.
	 *
	 * Initialize ActivityPub transformer manager.
	 * Include the builtin transformers by default and add third party ones.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access private
	*/
	private function init_transformers() {
		$builtin_transformers = [
			'post'
		];

		$this->transformers = [];

		foreach ( $builtin_transformers as $transformer_name ) {
			include ACTIVITYPUB_PLUGIN_DIR . 'includes/transformer/class-' . $transformer_name . '.php';

			$class_name = ucfirst( $transformer_name );

			$class_name = '\Activitypub\Transformer_' . $class_name;

			$this->register( new $class_name() );
		}

		/**
		 * Let other transformers register.
		 *
		 * Fires after the built-in Activitypub transformers are registered.
		 *
		 * @since version_number_transformer_management_placeholder
		 *
		 * @param Transformers_Manager $this The widgets manager.
		 */
		do_action( 'activitypub/transformers/register', $this );
	}


	/**
	 * Get available ActivityPub transformers.
	 *
	 * Retrieve the registered transformers list. If given a transformer name
	 * it returns the given transformer if it is registered.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access public
	 *
	 * @param string $transformers Optional. Transformer name. Default is null.
	 *
	 * @return Transformer_Base|Transformer_Base[]|null Registered transformers.
	*/
	public function get_transformers( $transformer_name = null ) {
		if ( is_null( $this->transformers ) ) {
			$this->init_transformers();
		}

		if ( null !== $transformer_name ) {
			return isset( $this->transformers[ $transformer_name ] ) ? $this->transformers[ $transformer_name ] : null;
		}

		return $this->transformers;
	}

	/**
	 * Get the mapped ActivityPub transformer.
	 * 
	 * Returns a new instance of the needed WordPress to ActivityPub transformer.
	 *
	 * @since version_number_transformer_management_placeholder
	 * @access public
	 *
     * @param WP_Post|WP_Comment $wp_post The WordPress Post/Comment.
	 *
	 * @return Transformer_Base|null Registered transformers.
	*/
	public function get_transformer( $object ) {
		switch ( get_class( $object ) ) {
			case 'WP_Post':
				$post_type = get_post_type( $object );
				$transformer_mapping = \get_option( 'activitypub_transformer_mapping', self::DEFAULT_TRANSFORMER_MAPPING );
				$transformer_name = $transformer_mapping[ $post_type ];
				$transformer_instance = new ( $this->get_transformers( $transformer_name ) );
				$transformer_instance->set_wp_post( $object );
				return $transformer_instance;
			case 'WP_Comment':
				return new Comment( $object );
			default:
				return apply_filters( 'activitypub_transformer', null, $object, get_class( $object ) );
		}
	}
}	

