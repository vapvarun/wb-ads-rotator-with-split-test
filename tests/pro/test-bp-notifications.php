<?php
/**
 * Rejection-notification hook contract - regression gate for bug #9793827140
 * (notification not sent on ad rejection due to hook name mismatch).
 *
 * BuddyPress is not required. What matters is that the action the notification
 * class listens to is the one the submission manager fires, and that firing it
 * with the documented payload reaches listeners without blowing up.
 *
 * Previously this fired the hook with `0` in place of the submission object.
 * Email_Notifications::send_ad_rejected() immediately calls
 * $submission->get_advertiser(), so the test guaranteed the fatal its own
 * comment said it was checking for. It has errored on every run since.
 * Now it passes the shape the hook documents.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\AdSubmissions\Ad_Submission;

class Test_BP_Notifications extends Pro_Test_Case {

	private const REJECTION_HOOK = 'wbam_pro_ad_submission_rejected';

	/**
	 * The listener and the firer must agree on the name. A rename on one side
	 * only is exactly the bug this file exists for.
	 */
	public function test_rejection_hook_is_the_one_the_notifier_listens_to(): void {
		$this->assertTrue(
			has_action( self::REJECTION_HOOK ),
			'Nothing is listening to ' . self::REJECTION_HOOK . ' - the notifier and the submission manager have drifted apart.'
		);
	}

	/**
	 * Fire it the way the submission manager does: an Ad_Submission plus a
	 * reason string. An empty submission is a safe payload here because
	 * get_advertiser() returns null when advertiser_id is unset, so the
	 * production listener takes its early return instead of sending mail.
	 */
	public function test_rejection_action_reaches_listeners_with_documented_payload(): void {
		$ran = false;

		add_action(
			self::REJECTION_HOOK,
			function ( $submission, $reason ) use ( &$ran ) {
				$ran = ( $submission instanceof Ad_Submission ) && 'test reason' === $reason;
			},
			10,
			2
		);

		do_action( self::REJECTION_HOOK, new Ad_Submission(), 'test reason' );

		$this->assertTrue(
			$ran,
			'The rejection hook did not reach listeners with a submission object and a reason.'
		);
	}

	/**
	 * Ads and money messages stay on the plugin (email + portal inbox).
	 * BuddyPress is only a place ads render: no bells, no activity posts
	 * (owner 2026-09-27, card 10344410479).
	 */
	public function test_buddypress_carries_no_ads_or_money_messages(): void {
		$src = file_get_contents( WBAM_PRO_PATH . 'includes/Modules/BuddyPress/class-buddypress-integration.php' );
		$this->assertIsString( $src );
		foreach ( array( 'bp_notifications_add_notification', 'bp_activity_add', 'bp_notifications_get_notifications_for_user', 'bp_notifications_get_registered_components' ) as $bp_api ) {
			$this->assertStringNotContainsString( $bp_api, $src );
		}
		$this->assertNotFalse( has_action( 'wbam_pro_ad_submission_approved' ), 'Plugin email must still listen for ad approval.' );
		$this->assertNotFalse( has_action( 'wbam_campaign_started' ), 'Plugin email must still listen for campaign start.' );
	}

	/**
	 * The 4.3.19 upgrade deletes the bells older versions wrote, and only
	 * those.
	 */
	public function test_upgrade_deletes_only_the_old_wbam_bells(): void {
		global $wpdb;
		$table = $wpdb->base_prefix . 'bp_notifications';
		// A real table (SHOW TABLES cannot see the suite's temporary ones);
		// DDL commits the test transaction, so this test cleans up by hand.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} ( id bigint(20) NOT NULL AUTO_INCREMENT, user_id bigint(20) NOT NULL, component_name varchar(75) NOT NULL, component_action varchar(75) NOT NULL, is_new tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (id) )" ); // phpcs:ignore WordPress.DB
		$wpdb->insert( $table, array( 'user_id' => 7, 'component_name' => 'wbam', 'component_action' => 'ad_approved' ) );
		$wpdb->insert( $table, array( 'user_id' => 7, 'component_name' => 'wbam', 'component_action' => 'campaign_started' ) );
		$wpdb->insert( $table, array( 'user_id' => 7, 'component_name' => 'messages', 'component_action' => 'new_message' ) );

		$upgrade = new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_4_3_19' );
		$upgrade->setAccessible( true );
		$upgrade->invoke( null );

		$left = $wpdb->get_col( "SELECT component_name FROM {$table} WHERE user_id = 7" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DROP TABLE {$table}" ); // phpcs:ignore WordPress.DB
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB

		$this->assertSame( array( 'messages' ), $left );
	}
}
