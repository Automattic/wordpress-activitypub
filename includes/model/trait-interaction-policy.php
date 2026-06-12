<?php
/**
 * Interaction Policy trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

/**
 * Interaction Policy trait.
 *
 * Computes the actor-level interaction policy for local actors. Lives in the
 * model layer on purpose: the generic Activity classes are vocabulary only
 * and must not contain application logic.
 *
 * May only be used in classes extending {@see \Activitypub\Activity\Actor}:
 * it overrides the inherited `get_interaction_policy()` accessor (via
 * `parent::`) and relies on the abstract methods declared below.
 *
 * @since unreleased
 */
trait Interaction_Policy {

	/**
	 * Get the ID of the actor.
	 *
	 * @return string The ID.
	 */
	abstract public function get_id();

	/**
	 * Get the followers collection URL of the actor.
	 *
	 * @return string|null The followers collection URL.
	 */
	abstract public function get_followers();

	/**
	 * Get the actor-level interaction policy.
	 *
	 * Overrides the magic property accessor on Base_Object so that we always
	 * compute the policy from the current site setting rather than returning a
	 * cached property value. Currently only emits `canFeature` (FEP-7aa9).
	 * Driven by the site option `activitypub_default_feature_policy` and
	 * defaults to denying all featured-collection requests, in line with
	 * FEP-7aa9's "absence of policy = no consent" rule.
	 *
	 * @see https://w3id.org/fep/7aa9
	 *
	 * @since 9.0.0
	 *
	 * @return array
	 */
	public function get_interaction_policy() {
		return array_merge( (array) parent::get_interaction_policy(), array( 'canFeature' => $this->build_can_feature_policy() ) );
	}

	/**
	 * Build the `canFeature` policy array from the site option.
	 *
	 * @return array
	 */
	protected function build_can_feature_policy() {
		$policy = \get_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ME );

		switch ( $policy ) {
			case ACTIVITYPUB_INTERACTION_POLICY_ANYONE:
				return array( 'automaticApproval' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
			case ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS:
				return array( 'automaticApproval' => array( $this->get_followers() ) );
			case ACTIVITYPUB_INTERACTION_POLICY_ME:
			default:
				return array( 'automaticApproval' => array( $this->get_id() ) );
		}
	}
}
