<?php
/**
 * Test Timer Listener for PHPUnit.
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped,PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;

/**
 * Activitypub Testcase Timer class.
 */
class Activitypub_Testcase_Timer implements TestListener {
	use TestListenerDefaultImplementation;

	/**
	 * Store test start times.
	 *
	 * @var array
	 */
	private $test_start_times = array();

	/**
	 * Store slow tests.
	 *
	 * @var array
	 */
	private $slow_tests = array();

	/**
	 * Threshold for slow tests in seconds.
	 *
	 * @var float
	 */
	private $slow_threshold = 0.2; // 200ms

	/**
	 * A test started.
	 *
	 * @param Test $test The test case.
	 */
	public function startTest( Test $test ): void {
		$this->test_start_times[ $test->getName() ] = microtime( true );
	}

	/**
	 * A test ended.
	 *
	 * @param Test  $test The test case.
	 * @param float $time Time taken.
	 */
	public function endTest( Test $test, $time ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$test_name = $test->getName();
		if ( ! isset( $this->test_start_times[ $test_name ] ) ) {
			return;
		}

		$duration = microtime( true ) - $this->test_start_times[ $test_name ];
		if ( $duration >= $this->slow_threshold ) {
			$this->slow_tests[] = array(
				'name'     => sprintf( '%s::%s', get_class( $test ), $test_name ),
				'duration' => $duration,
			);
		}

		unset( $this->test_start_times[ $test_name ] );
	}

	/**
	 * A test suite ended.
	 *
	 * @param TestSuite $suite The test suite.
	 */
	public function endTestSuite( TestSuite $suite ): void {
		if ( $suite->getName() === 'ActivityPub' && ! empty( $this->slow_tests ) ) {
			usort(
				$this->slow_tests,
				function ( $a, $b ) {
					return $b['duration'] <=> $a['duration'];
				}
			);

			echo "\n\nSlow Tests (>= {$this->slow_threshold}s):\n";
			foreach ( $this->slow_tests as $test ) {
				printf(
					"  \033[33m%.3fs\033[0m %s\n",
					$test['duration'],
					$test['name']
				);
			}
			echo "\n";
		}
	}
}
