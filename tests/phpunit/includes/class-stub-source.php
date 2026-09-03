<?php
/**
 * Stub conversation source for tests.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Source;

/**
 * A source that reports a fixed set of objects.
 *
 * Lets the builder's own behaviour, deduplication, ordering, validation and the caps, be tested
 * without standing up a fixture server for each of the real sources.
 */
class Stub_Source implements Source {

	/**
	 * The objects this source reports.
	 *
	 * @var array
	 */
	private $objects;

	/**
	 * Constructor.
	 *
	 * @param array $objects The objects to report.
	 */
	public function __construct( $objects ) {
		$this->objects = $objects;
	}

	/**
	 * Always applicable.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return bool Always true.
	 */
	public function supports( $activity_object ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return true;
	}

	/**
	 * Report the fixed objects.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return array The objects.
	 */
	public function parse( $activity_object ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->objects;
	}
}
