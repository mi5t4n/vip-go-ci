<?php

namespace Vipgoci\tests;

require_once( __DIR__ . '/IncludesForTests.php' );

use PHPUnit\Framework\TestCase;

// phpcs:disable PSR1.Files.SideEffects

final class VipgociRunInitVarsTest extends TestCase {
	/**
	 * @covers ::vipgoci_run_init_vars
	 */
	public function testRunInitVars() {
		list(
			$startup_time,
			$results,
			$options,
			$options_recognized,
			$prs_implicated
		) = vipgoci_run_init_vars();

		$this->assertTrue(
			( is_numeric( $startup_time ) )
			&&
			( 0 < $startup_time ),
			'Invalid value for $startup_time variable'
		);

		$this->assertTrue(
			( isset(
				$results['issues']
			) )
			&&
			( ! empty(
				$results['stats']
			) ),
			'Invalid value for $results variable'
		);

		$this->assertTrue(
			in_array( 'help', $options_recognized ),
			'\'help\' not found in $options_recognized variable'
		);

		$this->assertNull(
			$prs_implicated,
			'$prs_implicated variable is not null'
		);
	}
}
