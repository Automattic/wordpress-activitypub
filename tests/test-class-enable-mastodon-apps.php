<?php
class Test_Enable_Mastodon_Apps extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( '\Enable_Mastodon_Apps\Entity\Entity' ) ) {
			self::markTestSkipped( 'The Enable_Mastodon_Apps plugin is not active.' );
		}
	}

	public function test_api_account_external() {
		$account = apply_filters( 'mastodon_api_account', array(), 'alex@kirk.at' );
		$this->assertNotEmpty( $account );
		$account = $account->to_array();
		$this->assertArrayHasKey( 'id', $account );
		$this->assertArrayHasKey( 'username', $account );
		$this->assertArrayHasKey( 'acct', $account );
		$this->assertArrayHasKey( 'display_name', $account );
		$this->assertArrayHasKey( 'url', $account );
		$this->assertEquals( 'https://alex.kirk.at/author/alex/', $account['url'] );
		$this->assertEquals( 'Alex Kirk', $account['display_name'] );
	}
}
