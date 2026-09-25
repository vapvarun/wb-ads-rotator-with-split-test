<?php
/**
 * Pro deactivation unschedules every cron event it owns.
 *
 * Regression guard for Basecamp card 10163616849: the deactivation
 * handler carried a hand-written 6-hook list that drifted from the
 * scheduler, leaving 8 events firing forever on every site that ever
 * deactivated Pro — including a campaign-budgets event whose custom
 * 15-minute interval WP could no longer resolve. Cleanup now derives
 * from one registry (Cron_Manager::owned_hooks()), and activation
 * clean-slates before rescheduling so legacy orphans heal on upgrade.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Cron_Manager;

class Test_Cron_Deactivation_Cleanup extends Pro_Test_Case {

	public function test_owned_hooks_cover_registry_and_module_crons(): void {
		$owned = Cron_Manager::get_instance()->owned_hooks();

		$must_cover = array(
			// Hooks the old hand-written list orphaned (low-balance cron since retired).
			'wbam_pro_daily_cleanup',
			'wbam_pro_hourly_billing',
			'wbam_pro_weekly_reports',
			'wbam_check_campaign_budgets',
			'wbam_cleanup_audit_log',
			'wbam_expire_classifieds',
			'wbam_expire_upgrades',
			// Module-owned hooks the old list did cover — must stay covered.
			'wbam_scan_posts_cron',
			'wbam_check_link_health_cron',
			'wbam_process_classified_billing',
			'wbam_classified_expiration_warnings',
		);

		foreach ( $must_cover as $hook ) {
			$this->assertContains( $hook, $owned, "Deactivation cleanup must know about {$hook}." );
		}
	}

	public function test_deactivate_clears_every_owned_hook(): void {
		$manager = Cron_Manager::get_instance();
		$manager->schedule_all();
		// Simulate module-owned events being live too.
		wp_schedule_event( time(), 'daily', 'wbam_scan_posts_cron' );
		wp_schedule_event( time(), 'hourly', 'wbam_process_classified_billing' );

		Cron_Manager::deactivate();

		foreach ( $manager->owned_hooks() as $hook ) {
			$this->assertFalse( wp_next_scheduled( $hook ), "{$hook} must not survive deactivation." );
		}
	}

	public function test_activation_heals_a_legacy_orphan(): void {
		// A degenerate leftover from an old-code deactivation: single
		// (non-repeating) event because its custom interval was lost.
		wp_clear_scheduled_hook( 'wbam_check_campaign_budgets' );
		wp_schedule_single_event( time() + 100, 'wbam_check_campaign_budgets' );

		Cron_Manager::activate();

		$event = wp_get_scheduled_event( 'wbam_check_campaign_budgets' );
		$this->assertNotFalse( $event );
		$this->assertSame( 'wbam_fifteen_minutes', $event->schedule, 'Reactivation must restore the recurring 15-minute schedule, not keep the degenerate one-shot.' );
	}
}
