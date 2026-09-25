<?php
/**
 * Only a thread's participants can open it.
 *
 * The dashboard's Messages tab loaded any ?thread=N and marked it read, so a
 * member could read other members' conversations by changing the number.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Messaging\Message_Manager;

class Test_Message_Thread_Access extends Pro_Test_Case {

	public function test_thread_opens_only_for_its_participants(): void {
		global $wpdb;
		$buyer    = (int) self::factory()->user->create();
		$seller   = (int) self::factory()->user->create();
		$outsider = (int) self::factory()->user->create();

		$wpdb->insert(
			$wpdb->prefix . 'wbam_message_threads',
			array(
				'participant_a' => $buyer,
				'participant_b' => $seller,
				'classified_id' => 0,
				'status'        => 'active',
			)
		);
		$thread_id = (int) $wpdb->insert_id;
		$manager   = Message_Manager::get_instance();

		$this->assertNotNull( $manager->get_thread_for_user( $thread_id, $buyer ) );
		$this->assertNotNull( $manager->get_thread_for_user( $thread_id, $seller ) );
		$this->assertNull( $manager->get_thread_for_user( $thread_id, $outsider ) );
		$this->assertNull( $manager->get_thread_for_user( $thread_id, 0 ) );
	}
}
