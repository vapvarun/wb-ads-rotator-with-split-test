<?php
/**
 * Vocabulary, wave 6 (card 10343726476).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Links\Partnership;
use WBAM_Pro\Admin\Campaigns_List_Table;

class Test_Vocabulary_Wave6 extends Pro_Test_Case {

	/** The guard read one line at a time and missed __(\n 'text') and _n()'s plural. */
	public function test_the_guard_reads_calls_split_across_lines(): void {
		$dir = get_temp_dir() . 'wbam-vocab-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/probe.php', "<?php\n_n(\n\t'One ad',\n\t'Your classified listings',\n\t\$n\n);\n__(\n\t'Top up your wallet'\n);\n" );

		exec( 'bash ' . escapeshellarg( WBAM_PRO_PATH . 'bin/check-retired-vocab-in-i18n.sh' ) . ' ' . escapeshellarg( $dir ) . ' 2>&1', $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		array_map( 'wp_delete_file', glob( $dir . '/*' ) );
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

		$this->assertSame( 1, $code );
		$report = implode( "\n", $out );
		// A hit is reported on the line its call starts.
		$this->assertStringContainsString( "probe.php:2:hard-coded item word: use Settings_Helper::get_classifieds_label() via sprintf instead: _n(...) -> 'Your classified listings'", $report, 'The _n() plural form.' );
		$this->assertStringContainsString( 'probe.php:7:retired word: wallet', $report, 'The __( call split from its string.' );
	}

	public function test_both_plugins_pass_the_guard(): void {
		exec( 'bash ' . escapeshellarg( WBAM_PRO_PATH . 'bin/check-retired-vocab-in-i18n.sh' ) . ' ' . escapeshellarg( untrailingslashit( WBAM_PRO_PATH ) ) . ' ' . escapeshellarg( untrailingslashit( WBAM_PATH ) ) . ' 2>&1', $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$this->assertSame( 0, $code, implode( "\n", $out ) );
	}

	public function test_partnerships_wait_in_pending_review(): void {
		$this->assertSame( 'Pending review', Partnership::get_statuses()['pending'] );
	}

	public function test_ended_is_one_campaign_tab_covering_both_statuses(): void {
		$_GET['status'] = 'ended';
		$method         = new \ReflectionMethod( Campaigns_List_Table::class, 'filter_args' );
		$method->setAccessible( true );
		$args = $method->invoke( null );
		unset( $_GET['status'] );

		$this->assertSame( array( 'completed', 'expired' ), $args['status'] );
	}
}
