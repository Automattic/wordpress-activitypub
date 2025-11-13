# PHP Code Examples

## Table of Contents
- [Complete Class Example](#complete-class-example)
- [Transformer Example](#transformer-example)
- [Handler Example](#handler-example)
- [REST Endpoint Example](#rest-endpoint-example)
- [Integration Example](#integration-example)

## Complete Class Example

### Full-featured ActivityPub Class

```php
<?php
/**
 * Notification manager class file.
 *
 * @package Activitypub
 * @since 2.0.0
 */

namespace Activitypub;

use Activitypub\Collection\Followers;
use Activitypub\Activity\Create;
use WP_Error;

/**
 * Notification Manager Class.
 *
 * Handles sending notifications to followers when new content is published.
 *
 * @since 2.0.0
 */
class Notification_Manager {
    /**
     * The singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Notification queue.
     *
     * @var array
     */
    private $queue = array();

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the notification manager.
     */
    private function init() {
        \add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 10, 3 );
        \add_action( 'activitypub_send_notifications', array( $this, 'process_queue' ) );
        \add_filter( 'activitypub_notification_recipients', array( $this, 'filter_recipients' ), 10, 2 );
    }

    /**
     * Handle post status transitions.
     *
     * @param string  $new_status New post status.
     * @param string  $old_status Old post status.
     * @param WP_Post $post       Post object.
     *
     * @return void
     */
    public function handle_post_transition( $new_status, $old_status, $post ) {
        // Only notify for newly published posts.
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        // Check if post type supports ActivityPub.
        if ( ! \post_type_supports( $post->post_type, 'activitypub' ) ) {
            return;
        }

        // Add to queue.
        $this->queue_notification( $post );
    }

    /**
     * Queue a notification for sending.
     *
     * @param WP_Post $post The post to notify about.
     *
     * @return bool True on success, false on failure.
     */
    public function queue_notification( $post ) {
        if ( ! $post instanceof \WP_Post ) {
            return false;
        }

        $notification = array(
            'post_id'   => $post->ID,
            'author_id' => $post->post_author,
            'timestamp' => time(),
        );

        $this->queue[] = $notification;

        // Schedule processing if not already scheduled.
        if ( ! \wp_next_scheduled( 'activitypub_send_notifications' ) ) {
            \wp_schedule_single_event( time() + 10, 'activitypub_send_notifications' );
        }

        return true;
    }

    /**
     * Process the notification queue.
     */
    public function process_queue() {
        if ( empty( $this->queue ) ) {
            return;
        }

        foreach ( $this->queue as $key => $notification ) {
            $result = $this->send_notification( $notification );

            if ( ! \is_wp_error( $result ) ) {
                unset( $this->queue[ $key ] );
            }
        }

        // Re-index array.
        $this->queue = array_values( $this->queue );
    }

    /**
     * Send a notification.
     *
     * @param array $notification The notification data.
     *
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    private function send_notification( $notification ) {
        $post = \get_post( $notification['post_id'] );

        if ( ! $post ) {
            return new \WP_Error(
                'activitypub_post_not_found',
                \__( 'Post not found', 'activitypub' ),
                array( 'post_id' => $notification['post_id'] )
            );
        }

        // Get recipients.
        $recipients = $this->get_recipients( $notification['author_id'] );

        if ( empty( $recipients ) ) {
            return true; // No recipients, but not an error.
        }

        /**
         * Filter the recipients before sending.
         *
         * @param array $recipients List of recipient URLs.
         * @param array $notification Notification data.
         */
        $recipients = \apply_filters( 'activitypub_notification_recipients', $recipients, $notification );

        // Create activity.
        $activity = new Create();
        $activity->set_object( $post );

        // Send to each recipient.
        $errors = array();
        foreach ( $recipients as $recipient ) {
            $result = $this->send_to_inbox( $activity, $recipient );

            if ( \is_wp_error( $result ) ) {
                $errors[] = $result;
            }
        }

        if ( ! empty( $errors ) ) {
            return new \WP_Error( 'activitypub_notification_errors', \__( 'Some notifications failed to send', 'activitypub' ), $errors );
        }

        return true;
    }

    /**
     * Get recipients for notifications.
     *
     * @param int $user_id The user ID.
     *
     * @return array List of inbox URLs.
     */
    private function get_recipients( $user_id ) {
        $followers = Followers::get( $user_id );

        if ( empty( $followers ) ) {
            return array();
        }

        $inboxes = array();
        foreach ( $followers as $follower ) {
            if ( ! empty( $follower['inbox'] ) ) {
                $inboxes[] = $follower['inbox'];
            }
        }

        return array_unique( $inboxes );
    }

    /**
     * Send activity to an inbox.
     *
     * @param Activity $activity The activity to send.
     * @param string   $inbox    The inbox URL.
     *
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    private function send_to_inbox( $activity, $inbox ) {
        $response = \wp_remote_post(
            $inbox,
            array(
                'body'    => \wp_json_encode( $activity->to_array() ),
                'headers' => array(
                    'Content-Type' => 'application/activity+json',
                ),
                'timeout' => 30,
            )
        );

        if ( \is_wp_error( $response ) ) {
            return $response;
        }

        $code = \wp_remote_retrieve_response_code( $response );

        if ( $code >= 400 ) {
            return new \WP_Error(
                'activitypub_inbox_error',
                \sprintf(
                    /* translators: 1: HTTP status code, 2: Inbox URL */
                    \__( 'Inbox returned %1$d for %2$s', 'activitypub' ),
                    $code,
                    $inbox
                )
            );
        }

        return true;
    }

    /**
     * Filter notification recipients.
     *
     * @param array $recipients  Current recipients.
     * @param array $notification Notification data.
     *
     * @return array Filtered recipients.
     */
    public function filter_recipients( $recipients, $notification ) {
        // Remove blocked actors.
        $blocked = \get_option( 'activitypub_blocked_actors', array() );

        if ( ! empty( $blocked ) ) {
            $recipients = array_diff( $recipients, $blocked );
        }

        // Limit number of recipients.
        $max_recipients = \apply_filters( 'activitypub_max_recipients', 100 );

        if ( count( $recipients ) > $max_recipients ) {
            $recipients = array_slice( $recipients, 0, $max_recipients );
        }

        return $recipients;
    }
}
```

## Transformer Example

### Custom Post Type Transformer

```php
<?php
/**
 * Event transformer class file.
 *
 * @package Activitypub
 * @subpackage Transformer
 */

namespace Activitypub\Transformer;

/**
 * Transform Event posts to ActivityPub Event objects.
 */
class Event extends Post {
    /**
     * Get the ActivityPub type.
     *
     * @return string
     */
    public function get_type() {
        return 'Event';
    }

    /**
     * Transform to ActivityPub format.
     *
     * @return array
     */
    public function transform() {
        $object = parent::transform();

        // Add Event-specific properties.
        $object['startTime'] = $this->get_start_time();
        $object['endTime']   = $this->get_end_time();
        $object['location']  = $this->get_location();

        return $object;
    }

    /**
     * Get event start time.
     *
     * @return string ISO 8601 formatted datetime.
     */
    protected function get_start_time() {
        $start = \get_post_meta( $this->post->ID, 'event_start', true );

        if ( ! $start ) {
            return '';
        }

        return \gmdate( 'c', strtotime( $start ) );
    }

    /**
     * Get event end time.
     *
     * @return string ISO 8601 formatted datetime.
     */
    protected function get_end_time() {
        $end = \get_post_meta( $this->post->ID, 'event_end', true );

        if ( ! $end ) {
            return '';
        }

        return \gmdate( 'c', strtotime( $end ) );
    }

    /**
     * Get event location.
     *
     * @return array Location object.
     */
    protected function get_location() {
        $venue = \get_post_meta( $this->post->ID, 'event_venue', true );

        if ( ! $venue ) {
            return null;
        }

        return array(
            'type'    => 'Place',
            'name'    => $venue,
            'address' => $this->get_address(),
        );
    }

    /**
     * Get event address.
     *
     * @return array|null Address object.
     */
    private function get_address() {
        $address = \get_post_meta( $this->post->ID, 'event_address', true );

        if ( ! $address ) {
            return null;
        }

        return array(
            'type'            => 'PostalAddress',
            'streetAddress'   => $address['street'] ?? '',
            'addressLocality' => $address['city'] ?? '',
            'postalCode'      => $address['zip'] ?? '',
            'addressCountry'  => $address['country'] ?? '',
        );
    }
}
```

## Handler Example

### Incoming Activity Handler

```php
<?php
/**
 * Like activity handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;

/**
 * Handle incoming Like activities.
 */
class Like {
    /**
     * Initialize the handler.
     */
    public static function init() {
        \add_action( 'activitypub_handle_like', array( self::class, 'handle' ), 10, 2 );
    }

    /**
     * Handle a Like activity.
     *
     * @param array $activity The activity object.
     * @param int   $user_id  The target user ID.
     *
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public static function handle( $activity, $user_id ) {
        // Validate activity.
        $valid = self::validate_activity( $activity );

        if ( \is_wp_error( $valid ) ) {
            return $valid;
        }

        // Get the liked object.
        $object_id = self::get_object_id( $activity['object'] );

        if ( ! $object_id ) {
            return new WP_Error(
                'activitypub_invalid_object',
                \__( 'Could not determine liked object', 'activitypub' )
            );
        }

        // Store the like.
        $result = Interactions::add_like(
            $object_id,
            $activity['actor'],
            $activity['id']
        );

        if ( \is_wp_error( $result ) ) {
            return $result;
        }

        /**
         * Fires after a Like activity is processed.
         *
         * @param array $activity The activity object.
         * @param int   $user_id  The target user ID.
         * @param int   $object_id The liked object ID.
         */
        \do_action( 'activitypub_like_received', $activity, $user_id, $object_id );

        return true;
    }

    /**
     * Validate the activity.
     *
     * @param array $activity The activity to validate.
     *
     * @return true|WP_Error True if valid, WP_Error otherwise.
     */
    private static function validate_activity( $activity ) {
        if ( empty( $activity['type'] ) || 'Like' !== $activity['type'] ) {
            return new WP_Error(
                'activitypub_invalid_type',
                \__( 'Activity type must be Like', 'activitypub' )
            );
        }

        if ( empty( $activity['actor'] ) ) {
            return new WP_Error(
                'activitypub_missing_actor',
                \__( 'Activity must have an actor', 'activitypub' )
            );
        }

        if ( empty( $activity['object'] ) ) {
            return new WP_Error(
                'activitypub_missing_object',
                \__( 'Activity must have an object', 'activitypub' )
            );
        }

        return true;
    }

    /**
     * Get the local object ID from the activity object.
     *
     * @param string|array $object The object reference.
     *
     * @return int|false Post ID or false if not found.
     */
    private static function get_object_id( $object ) {
        // Handle string reference.
        if ( is_string( $object ) ) {
            $url = $object;
        } elseif ( is_array( $object ) && isset( $object['id'] ) ) {
            $url = $object['id'];
        } else {
            return false;
        }

        // Try to find post by ActivityPub ID.
        $posts = \get_posts(
            array(
                'meta_key'       => 'activitypub_id',
                'meta_value'     => $url,
                'posts_per_page' => 1,
                'post_type'      => 'any',
                'fields'         => 'ids',
            )
        );

        if ( ! empty( $posts ) ) {
            return $posts[0];
        }

        // Try to find by permalink.
        $post_id = \url_to_postid( $url );

        if ( $post_id ) {
            return $post_id;
        }

        return false;
    }
}
```

## REST Endpoint Example

### Custom REST API Endpoint

```php
<?php
/**
 * Statistics REST endpoint.
 *
 * @package Activitypub
 * @subpackage Rest
 */

namespace Activitypub\Rest;

/**
 * ActivityPub statistics endpoint.
 */
class Statistics {
    /**
     * Initialize the endpoint.
     */
    public static function init() {
        \add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
    }

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        \register_rest_route(
            ACTIVITYPUB_REST_NAMESPACE,
            '/statistics',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( self::class, 'get_statistics' ),
                    'permission_callback' => array( self::class, 'get_permissions_check' ),
                    'args'                => self::get_collection_params(),
                ),
            )
        );
    }

    /**
     * Check permissions for the request.
     *
     * @param \WP_REST_Request $request The request object.
     *
     * @return true|\WP_Error
     */
    public static function get_permissions_check( $request ) {
        if ( ! \current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'rest_forbidden', \__( 'You do not have permission to view statistics.', 'activitypub' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Get statistics.
     *
     * @param \WP_REST_Request $request The request object.
     *
     * @return \WP_REST_Response
     */
    public static function get_statistics( $request ) {
        $user_id = $request->get_param( 'user_id' );
        $period  = $request->get_param( 'period' ) ?? 'week';

        $stats = array(
            'followers'     => self::get_follower_count( $user_id ),
            'following'     => self::get_following_count( $user_id ),
            'posts'         => self::get_post_count( $user_id, $period ),
            'interactions'  => self::get_interaction_count( $user_id, $period ),
        );

        return new \WP_REST_Response( $stats, 200 );
    }

    /**
     * Get collection parameters.
     *
     * @return array
     */
    public static function get_collection_params() {
        return array(
            'user_id' => array(
                'description'       => \__( 'User ID to get statistics for.', 'activitypub' ),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'period' => array(
                'description'       => \__( 'Time period for statistics.', 'activitypub' ),
                'type'              => 'string',
                'enum'              => array( 'day', 'week', 'month', 'year' ),
                'default'           => 'week',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Get follower count.
     *
     * @param int|null $user_id User ID.
     *
     * @return int
     */
    private static function get_follower_count( $user_id = null ) {
        // Implementation.
        return 0;
    }

    /**
     * Get following count.
     *
     * @param int|null $user_id User ID.
     *
     * @return int
     */
    private static function get_following_count( $user_id = null ) {
        // Implementation.
        return 0;
    }

    /**
     * Get post count.
     *
     * @param int|null $user_id User ID.
     * @param string   $period  Time period.
     *
     * @return int
     */
    private static function get_post_count( $user_id = null, $period = 'week' ) {
        // Implementation.
        return 0;
    }

    /**
     * Get interaction count.
     *
     * @param int|null $user_id User ID.
     * @param string   $period  Time period.
     *
     * @return int
     */
    private static function get_interaction_count( $user_id = null, $period = 'week' ) {
        // Implementation.
        return 0;
    }
}
```

## Integration Example

### Third-Party Plugin Integration

```php
<?php
/**
 * WooCommerce integration.
 *
 * @package Activitypub
 * @subpackage Integration
 */

namespace Activitypub\Integration;

use Activitypub\Transformer\Base;

/**
 * WooCommerce integration for ActivityPub.
 */
class Woocommerce {
    /**
     * Initialize the integration.
     */
    public static function init() {
        \add_filter( 'activitypub_transformer', array( self::class, 'filter_transformer' ), 10, 2 );
        \add_filter( 'activitypub_post_types', array( self::class, 'add_post_types' ) );
        \add_action( 'woocommerce_order_status_completed', array( self::class, 'handle_order_complete' ) );
    }

    /**
     * Filter transformer for WooCommerce objects.
     *
     * @param Base     $transformer Current transformer.
     * @param \WP_Post $post        Post object.
     *
     * @return Base
     */
    public static function filter_transformer( $transformer, $post ) {
        if ( 'product' === $post->post_type ) {
            require_once __DIR__ . '/transformer/class-product.php';
            return new \Transformer\Product( $post );
        }

        return $transformer;
    }

    /**
     * Add WooCommerce post types.
     *
     * @param array $post_types Current post types.
     *
     * @return array
     */
    public static function add_post_types( $post_types ) {
        $post_types[] = 'product';
        return $post_types;
    }

    /**
     * Handle completed orders.
     *
     * @param int $order_id Order ID.
     */
    public static function handle_order_complete( $order_id ) {
        $order = \wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        /**
         * Fires when a WooCommerce order is completed.
         *
         * @param \WC_Order $order The order object.
         */
        \do_action( 'activitypub_woocommerce_order_complete', $order );
    }
}
```
