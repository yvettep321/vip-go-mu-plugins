<?php

namespace Automattic\VIP\Performance;

class lastpostmodified_Test extends \WP_UnitTestCase {
	protected $post;

	public function setUp() {
		parent::setUp();

		$this->post = $this->factory->post->create_and_get( [ 'post_status' => 'draft' ] );
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test__transition_post_status__save_on_publish() {
		\wp_transition_post_status( 'publish', 'publish', $this->post );

		$this->assertEquals( 1, did_action( 'wpcom_vip_bump_lastpostmodified' ) );
	}

	public function test__transition_post_status__save_on_update() {
		\wp_transition_post_status( 'publish', 'publish', $this->post );

		$this->assertEquals( 1, did_action( 'wpcom_vip_bump_lastpostmodified' ) );
	}

	public function test__transition_post_status__ignore_non_publish_status() {
		\wp_transition_post_status( 'draft', 'future', $this->post );

		$this->assertEquals( 0, did_action( 'wpcom_vip_bump_lastpostmodified' ) );
	}

	public function test__transition_post_status__ignore_non_public_post_type() {
		$this->post->post_type = 'book';

		\wp_transition_post_status( 'publish', 'publish', $this->post );

		$this->assertEquals( 0, did_action( 'wpcom_vip_bump_lastpostmodified' ) );
	}

	public function test__bump_lastpostmodified() {
		$this->post->post_type = 'book';
		$this->post->post_modified = '2003-05-27 00:00:00';
		$this->post->post_modified_gmt = '2003-05-27 05:00:00';

		Last_Post_Modified::bump_lastpostmodified( $this->post );

		$blog_actual = Last_Post_Modified::get_lastpostmodified( 'blog', 'book' );
		$this->assertEquals( '2003-05-27 00:00:00', $blog_actual );
		$gmt_actual = Last_Post_Modified::get_lastpostmodified( 'gmt', 'book' );
		$this->assertEquals( '2003-05-27 05:00:00', $gmt_actual );
		$server_actual = Last_Post_Modified::get_lastpostmodified( 'server', 'book' );
		$this->assertEquals( '2003-05-27 05:00:00', $server_actual );

	}

	public function test__override_lastpostmodified__is_set() {
		Last_Post_Modified::update_lastpostmodified( '2003-05-27', 'gmt', 'post' );

		$actual = get_lastpostmodified( 'gmt', 'post' );

		$this->assertEquals( '2003-05-27', $actual );
	}

	public function test__override_lastpostmodified__is_not_set() {
		$actual = get_lastpostmodified( 'gmt', 'post' );

		$this->assertEquals( $this->post_modified_gmt, $actual );
	}
}
