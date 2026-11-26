<?php
/**
 * Term Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Extended_Object\Category;

/**
 * Term Transformer Class.
 *
 * Transforms WordPress terms into their ActivityPub representations.
 *
 * By default:
 * - `category` becomes a Category ({@link https://fediverse.codeberg.page/fep/fep/2fa1/ FEP-2fa1}).
 * - All other taxonomies become an OrderedCollection.
 *
 * Custom transformers can extend this class and override `get_type()`
 * and `to_category()` to support additional taxonomies and vocabularies.
 *
 * @property \WP_Term $item The WordPress term being transformed.
 */
class Term extends Base {
	/**
	 * Transforms the WP_Term object to its ActivityPub representation.
	 *
	 * @return Base_Object|Category The ActivityPub object.
	 */
	public function to_object() {
		if ( 'Category' === $this->get_type() ) {
			return $this->to_category();
		}

		return $this->to_collection();
	}

	/**
	 * Gets the ActivityPub type for the term.
	 *
	 * Child transformers can override this method to assign a different
	 * ActivityPub representation to custom taxonomies.
	 *
	 * @return string The ActivityPub type.
	 */
	protected function get_type() {
		if ( 'category' === $this->item->taxonomy ) {
			return 'Category';
		}

		return 'OrderedCollection';
	}

	/**
	 * Gets the ID of the term.
	 *
	 * Uses a stable identifier that does not depend on the term slug.
	 *
	 * @return string The term's stable ID.
	 */
	public function to_id() {
		return $this->get_id();
	}

	/**
	 * Returns the stable ID of the Term.
	 *
	 * Uses term_id query parameter to ensure the ID remains stable
	 * even if the term slug is changed.
	 *
	 * @return string The Term's stable ID.
	 */
	public function get_id() {
		return \add_query_arg( 'term_id', $this->item->term_id, \home_url( '/' ) );
	}

	/**
	 * Returns the URL of the Term.
	 *
	 * @return string The Term's URL (term link).
	 */
	public function get_url() {
		$term_link = \get_term_link( $this->item );

		if ( \is_wp_error( $term_link ) ) {
			return '';
		}

		return \esc_url_raw( $term_link );
	}

	/**
	 * Returns the name of the term as plain text.
	 *
	 * Term names are stored HTML-escaped (e.g. "Cars &amp; Boats").
	 *
	 * @return string The term's name.
	 */
	public function get_name() {
		return \wp_specialchars_decode( $this->item->name, ENT_QUOTES );
	}

	/**
	 * Returns the description of the term as plain text.
	 *
	 * @return string The term's description, or an empty string.
	 */
	public function get_summary() {
		return \wp_specialchars_decode( \wp_strip_all_tags( $this->item->description ), ENT_QUOTES );
	}

	/**
	 * Returns the stable ID of the parent term.
	 *
	 * The parent represents the broader category. Remote servers cannot
	 * reliably discover the hierarchy from the category ID alone, so the
	 * parent ID is included in the object itself.
	 *
	 * @return string The parent's stable ID, or an empty string if there is no parent.
	 */
	public function get_broader() {
		if ( empty( $this->item->parent ) ) {
			return '';
		}

		$parent = \get_term(
			$this->item->parent,
			$this->item->taxonomy
		);

		if ( ! $parent || \is_wp_error( $parent ) ) {
			return '';
		}

		return ( new self( $parent ) )->get_id();
	}

	/**
	 * Transforms the term to a Category (FEP-2fa1).
	 *
	 * A Category represents a concept. Its stable ID identifies the concept,
	 * while its URL points to the readable archive page.
	 *
	 * Child transformers can override this method to extend the Category
	 * with additional vocabulary terms, such as SKOS properties.
	 *
	 * @return Base_Object The Category.
	 */
	protected function to_category() {
		$category_object = new Category();

		$category_object->set_id( $this->get_id() );
		$category_object->set_url( $this->get_url() );
		$category_object->set_name( $this->get_name() );

		$summary = $this->get_summary();

		if ( '' !== $summary ) {
			$category_object->set_summary( $summary );
		}

		$broader = $this->get_broader();

		if ( '' !== $broader ) {
			$category_object->set_broader( $broader );
		}

		return $category_object;
	}

	/**
	 * Transforms the term to an OrderedCollection.
	 *
	 * This is the default representation for taxonomies that are not
	 * explicitly transformed into a Category.
	 *
	 * @return Base_Object The OrderedCollection.
	 */
	protected function to_collection() {
		$base_object               = new Base_Object();
		$base_object->{'@context'} = 'https://www.w3.org/ns/activitystreams';

		$base_object->set_type( 'OrderedCollection' );
		$base_object->set_id( $this->get_id() );
		$base_object->set_url( $this->get_url() );

		return $base_object;
	}
}
