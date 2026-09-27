<?php
/**
 * Card 10343706274, item CR1: Settings > Credits nested boxes four deep
 * (section card > Pricing & Payments box > boxed rows > gateway cards >
 * rows). Flattened to the pattern every other settings section uses - a
 * bare `<h2>` heading outside any box, then one card of form-table rows;
 * Direct Payment Gateways is one heading with each gateway as its own
 * sibling card. This guards both halves of that fix: the markup no longer
 * nests a `.wbam-card` inside another one, and every field name/id the
 * gateways and pricing rows post under is unchanged.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Credits_Settings;

class Test_Credits_Settings_Flat_Cards extends Pro_Test_Case {

	private int $admin_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down(): void {
		delete_option( 'wbcom_credits_gateway_settings_wbam-pro' );
		delete_option( 'wbam_credit_price_cents' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function render(): string {
		ob_start();
		( new Credits_Settings() )->render();
		return (string) ob_get_clean();
	}

	/**
	 * DOM-level regression guard for the nesting bug: no element carrying
	 * the `wbam-card` class token may contain a descendant that also
	 * carries it. `.form-table` nested inside a `.wbam-card` (a gateway's
	 * own field rows) is the sanctioned exception used site-wide and is
	 * not part of this check - only `.wbam-card`-in-`.wbam-card`.
	 */
	public function test_no_card_is_nested_inside_another_card(): void {
		$html = $this->render();

		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<!DOCTYPE html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();

		$xpath      = new \DOMXPath( $doc );
		$card_query = "//*[contains(concat(' ', normalize-space(@class), ' '), ' wbam-card ')]";
		$cards      = $xpath->query( $card_query );

		$this->assertGreaterThanOrEqual(
			3,
			$cards->length,
			'Expected at least 3 top-level cards (Stripe, PayPal, Credit Mappings) - the nesting check is meaningless with none rendered.'
		);

		foreach ( $cards as $card ) {
			$nested = $xpath->query( '.' . $card_query, $card );
			$this->assertSame(
				0,
				$nested->length,
				'A .wbam-card must not contain another .wbam-card (found nesting inside: ' . $card->getAttribute( 'class' ) . ').'
			);
		}
	}

	/** The old bespoke wrapper classes this flatten removed must not linger anywhere in the markup. */
	public function test_bespoke_nesting_wrapper_classes_are_gone(): void {
		$html = $this->render();

		foreach ( array( 'wbam-credits-section', 'wbam-gateways-section' ) as $dead_class ) {
			$this->assertStringNotContainsString(
				'"' . $dead_class,
				$html,
				"The flattened Credits tab must not re-introduce class=\"{$dead_class}\"."
			);
			$this->assertStringNotContainsString(
				' ' . $dead_class . '"',
				$html,
				"The flattened Credits tab must not re-introduce a \"{$dead_class}\" class token."
			);
		}
	}

	/** Every field name/id a merchant might already have bookmarked in a password manager, or that JS/tests target, survives the markup flatten untouched. */
	public function test_every_pricing_and_gateway_field_name_survives_the_flatten(): void {
		// The "Price per unit of balance" row only renders when a site sells
		// below face value (card 10343726476, owner decision) - force that
		// condition so this field-name-survives-the-flatten check still
		// covers it.
		update_option( 'wbam_credit_price_cents', 1 );
		$html = $this->render();

		// Pricing & Payments (now a bare <h2> + one merged form-table).
		$this->assertStringContainsString( 'name="wbam_credit_price"', $html );
		$this->assertStringContainsString( 'id="wbam_credit_price"', $html );
		$this->assertStringContainsString( 'name="wbam_credits_manual_topup"', $html );
		$this->assertStringContainsString( 'id="wbam_credits_manual_topup"', $html );
		$this->assertStringContainsString( 'name="wbam_credits_settings_nonce"', $html );

		// Direct Payment Gateways: every field the SDK's own gateway schema
		// declares, for every gateway the SDK registers - not hardcoded to
		// Stripe/PayPal so a future SDK gateway is covered automatically.
		$slug     = \WBAM_Pro\Core\Credits_Bridge::SLUG;
		$registry = \Wbcom\Credits\Gateways\Gateway_Registry::for_slug( $slug );

		$gateways_seen = 0;
		foreach ( $registry->get_all() as $gateway_id => $gateway ) {
			++$gateways_seen;
			foreach ( $gateway->get_settings_fields() as $field ) {
				$key = (string) ( $field['key'] ?? '' );
				$this->assertNotSame( '', $key, "Gateway {$gateway_id} declared a field with no key." );

				// Never applied in WB Ad Manager: buyers always return to the
				// Balance tab (card 10344383905), so these are not drawn.
				if ( in_array( $key, array( 'success_url', 'cancel_url' ), true ) ) {
					$this->assertStringNotContainsString( sprintf( 'name="wbam_gateways[%s][%s]"', $gateway_id, $key ), $html );
					continue;
				}

				$name_attr = sprintf( 'name="wbam_gateways[%s][%s]"', $gateway_id, $key );
				$this->assertStringContainsString(
					$name_attr,
					$html,
					"Field {$key} for gateway {$gateway_id} must keep posting under {$name_attr}."
				);
			}

			$this->assertStringContainsString(
				'data-gateway="' . $gateway_id . '"',
				$html,
				"Gateway {$gateway_id}'s card must keep its data-gateway attribute."
			);
		}

		$this->assertGreaterThanOrEqual( 1, $gateways_seen, 'The SDK must register at least one built-in gateway for this assertion to mean anything.' );
	}
}
