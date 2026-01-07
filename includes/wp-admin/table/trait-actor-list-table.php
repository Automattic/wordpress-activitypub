<?php
/**
 * Actor Table Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin\Table;

/**
 * Actor Table Trait.
 */
trait Actor_List_Table {
	/**
	 * Returns the lowercase short classname which is used as keys for filtering.
	 *
	 * @return string
	 */
	protected static function actor_list_table_key() {
		return strtolower( ( new \ReflectionClass( static::class ) )->getShortName() );
	}

	/**
	 * Column default.
	 *
	 * @param array  $item        Item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		/**
		 * Filters the displayed value for a column in the ActivityPub Following list table.
		 *
		 * Allows plugins to provide custom output for individual columns.
		 * If a non-null value is returned, it will be used instead of the
		 * default column rendering logic.
		 *
		 * @since 7.9.0
		 *
		 * @param string|null $value       The column value. Default null.
		 * @param string      $column_name The name of the current column.
		 * @param array       $item        The current following item data.
		 * @param int         $user_id     The user id of the local actor.
		 */
		$value = \apply_filters(
			'activitypub_' . $this->actor_list_table_key() . '_column_value',
			null,
			$column_name,
			$item,
			$this->user_id
		);

		if ( null !== $value ) {
			return $value;
		}

		if ( ! \array_key_exists( $column_name, $item ) ) {
			return \esc_html__( 'None', 'activitypub' );
		}
		return \esc_html( $item[ $column_name ] );
	}

	/**
	 * Returns sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$columns = array(
			'username'   => array( 'username', true ),
			'post_title' => array( 'post_title', true ),
			'modified'   => array( 'modified', false ),
		);

		/**
		 * Filters the sortable columns in the ActivityPub Following list table.
		 *
		 * Allows plugins to register additional sortable columns or modify the default sortable behavior.
		 *
		 * @since 7.9.0
		 *
		 * @param array<string, array> $columns   Sortable columns in the format
		 *                                                       `column_id => array( orderby, is_sortable_default )`.
		 * @param int                  $user_id   The user id of the local actor.
		 */
		return \apply_filters( 'activitypub_' . $this->actor_list_table_key() . '_sortable_columns', $columns, $this->user_id );
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$key     = $this->actor_list_table_key();
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'username'   => \__( 'Username', 'activitypub' ),
			'post_title' => \__( 'Name', 'activitypub' ),
			'webfinger'  => \__( 'Profile', 'activitypub' ),
			'modified'   => \__( 'Last updated', 'activitypub' ),
		);

		if ( 'blocked_actors' === $key ) {
			$columns['modified'] = \__( 'Blocked date', 'activitypub' );
		}

		/**
		 * Filters the columns displayed in the ActivityPub Following list table.
		 *
		 * Allows plugins to add, remove, or reorder columns shown in the
		 * Following list table on the ActivityPub admin screen.
		 *
		 * @since 7.9.0
		 *
		 * @param string[] $columns Array of columns.
		 * @param int      $user_id   The user id of the local actor.
		 */
		return \apply_filters( 'activitypub_' . $key . '_columns', $columns, $this->user_id );
	}

	/**
	 * Sanitizes and normalizes an actor search term.
	 *
	 * @param string $search The search term.
	 * @return string The normalized search term.
	 */
	public function normalize_search_term( $search ) {
		$search = \sanitize_text_field( $search );
		$search = \str_replace( array( 'acct:', 'http://', 'https://', 'www.' ), '', $search );
		$search = \str_replace( '@', ' ', $search );

		return \trim( $search );
	}

	/**
	 * Get the action URL for a follower.
	 *
	 * @param string $action   The action.
	 * @param string $follower The follower ID.
	 * @return string The action URL.
	 */
	private function get_action_url( $action, $follower ) {
		return \wp_nonce_url(
			\add_query_arg(
				array(
					'action'   => $action,
					'follower' => $follower,
				)
			),
			$action . '-follower_' . $follower
		);
	}
}
