<?php
/**
 * Dashboard Widget Class.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;

use function Activitypub\count_followers;
use function Activitypub\is_user_type_disabled;
use function Activitypub\user_can_activitypub;

/**
 * ActivityPub Dashboard Widget.
 *
 * Provides a Fediverse Stats widget for the WordPress dashboard.
 */
class Dashboard_Widget {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'wp_dashboard_setup', array( self::class, 'register' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public static function register() {
		if ( ! user_can_activitypub( \get_current_user_id() ) && ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		\wp_add_dashboard_widget(
			'activitypub_stats',
			\__( 'Fediverse Stats', 'activitypub' ),
			array( self::class, 'render' )
		);
	}

	/**
	 * Get the user ID for stats.
	 *
	 * Returns the blog user ID if user type is disabled, otherwise the current user ID.
	 *
	 * @return int The user ID.
	 */
	private static function get_stats_user_id() {
		if ( is_user_type_disabled( 'user' ) ) {
			return Actors::BLOG_USER_ID;
		}

		return \get_current_user_id();
	}

	/**
	 * Render the dashboard widget.
	 */
	public static function render() {
		$user_id = self::get_stats_user_id();
		$stats   = self::get_stats( $user_id );

		\add_filter( 'number_format_i18n', '\Activitypub\custom_large_numbers', 10, 2 );

		self::render_stats_grid( $stats );
		self::render_onboarding( $stats, $user_id );

		\remove_filter( 'number_format_i18n', '\Activitypub\custom_large_numbers' );
	}

	/**
	 * Get stats for a given user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array {
	 *     Stats for the user.
	 *
	 *     @type int $followers Number of followers.
	 *     @type int $following Number of accounts being followed.
	 *     @type int $likes     Number of likes received.
	 *     @type int $reposts   Number of reposts received.
	 *     @type int $comments  Number of federated comments received.
	 * }
	 */
	public static function get_stats( $user_id ) {
		$post_author_query = array();

		if ( Actors::BLOG_USER_ID !== $user_id ) {
			$post_author_query = array( 'post_author' => $user_id );
		}

		$following_count = 0;
		if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
			$following_count = (int) Following::count( $user_id );
		}

		return array(
			'followers' => (int) count_followers( $user_id ),
			'following' => $following_count,
			'likes'     => (int) self::count_interactions_by_type( 'like', $post_author_query ),
			'reposts'   => (int) self::count_interactions_by_type( 'repost', $post_author_query ),
			'comments'  => (int) self::count_interactions_by_type( 'comment', $post_author_query ),
		);
	}

	/**
	 * Count interactions by comment type with activitypub protocol meta.
	 *
	 * @param string $type               The comment type (e.g. 'like', 'repost').
	 * @param array  $post_author_query  Optional. Array with 'post_author' key to scope to a specific user's posts.
	 *
	 * @return int The count of matching interactions.
	 */
	private static function count_interactions_by_type( $type, $post_author_query ) {
		$args = array(
			'status'     => 'approve',
			'type'       => $type,
			'count'      => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
				),
			),
		);

		if ( ! empty( $post_author_query['post_author'] ) ) {
			$args['post_author'] = $post_author_query['post_author'];
		}

		return (int) \get_comments( $args );
	}

	/**
	 * Render the stats grid.
	 *
	 * @param array $stats The stats array from get_stats().
	 */
	private static function render_stats_grid( $stats ) {
		$cards = array(
			array(
				'icon'  => 'dashicons-groups',
				'label' => \__( 'Followers', 'activitypub' ),
				'value' => $stats['followers'],
			),
		);

		if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
			$cards[] = array(
				'icon'  => 'dashicons-admin-users',
				'label' => \__( 'Following', 'activitypub' ),
				'value' => $stats['following'],
			);
		}

		$cards[] = array(
			'icon'  => 'dashicons-heart',
			'label' => \__( 'Likes', 'activitypub' ),
			'value' => $stats['likes'],
		);
		$cards[] = array(
			'icon'  => 'dashicons-controls-repeat',
			'label' => \__( 'Reposts', 'activitypub' ),
			'value' => $stats['reposts'],
		);
		$cards[] = array(
			'icon'  => 'dashicons-admin-comments',
			'label' => \__( 'Comments', 'activitypub' ),
			'value' => $stats['comments'],
		);

		?>
		<div class="activitypub-dashboard-stats-grid">
			<?php foreach ( $cards as $card ) : ?>
				<div class="activitypub-dashboard-stat-card">
					<span class="dashicons <?php echo \esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
					<span class="activitypub-dashboard-stat-label"><?php echo \esc_html( $card['label'] ); ?></span>
					<span class="activitypub-dashboard-stat-value"><?php echo \esc_html( \number_format_i18n( $card['value'] ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render onboarding tips.
	 *
	 * @param array $stats   The stats array from get_stats().
	 * @param int   $user_id The user ID.
	 */
	private static function render_onboarding( $stats, $user_id ) {
		$tips = array();

		if ( 0 === $stats['followers'] ) {
			$tips[] = \sprintf(
				/* translators: %s: URL to the ActivityPub settings page. */
				\__( 'Share your Fediverse handle from the <a href="%s">settings page</a> so people can find and follow you.', 'activitypub' ),
				\esc_url( \admin_url( 'options-general.php?page=activitypub' ) )
			);

			if ( \wp_is_block_theme() ) {
				$tips[] = \sprintf(
					/* translators: %s: URL to the site editor. */
					\__( 'Add a <strong>Follow Me</strong> block to your site using the <a href="%s">site editor</a>.', 'activitypub' ),
					\esc_url( \admin_url( 'site-editor.php' ) )
				);
			}
		}

		/**
		 * Filters the dashboard widget onboarding tips.
		 *
		 * @param array $tips    The array of tip HTML strings.
		 * @param array $stats   The stats array.
		 * @param int   $user_id The user ID.
		 */
		$tips = \apply_filters( 'activitypub_dashboard_widget_tips', $tips, $stats, $user_id );

		if ( empty( $tips ) ) {
			return;
		}

		?>
		<div class="activitypub-dashboard-onboarding">
			<?php foreach ( $tips as $tip ) : ?>
				<p><?php echo \wp_kses_post( $tip ); ?></p>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
