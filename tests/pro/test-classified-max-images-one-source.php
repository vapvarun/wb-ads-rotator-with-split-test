<?php
/**
 * wbam_classified_max_images must gate every reader of the max-images
 * limit, not just the localized JS value (card 10343765758: "applied at
 * only 1 of 6 readers"). A filtered site that lowers the limit must have
 * Classified_Manager::create() actually enforce the lower number.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Max_Images_One_Source extends Pro_Test_Case {

	public function tear_down(): void {
		remove_all_filters( 'wbam_classified_max_images' );
		parent::tear_down();
	}

	public function test_helper_applies_the_filter(): void {
		add_filter(
			'wbam_classified_max_images',
			static function () {
				return 2;
			}
		);

		$this->assertSame( 2, Settings_Helper::classified_max_images() );
	}

	public function test_manager_submit_enforces_the_filtered_limit(): void {
		add_filter(
			'wbam_classified_max_images',
			static function () {
				return 1;
			}
		);

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$term       = self::factory()->term->create( array( 'taxonomy' => Classified_Manager::TAXONOMY_CATEGORY ) );
		$image_ids  = self::factory()->attachment->create_many( 2 );

		$result = Classified_Manager::get_instance()->submit(
			$advertiser,
			array(
				'title'      => 'Test listing',
				'categories' => array( (int) $term ),
				'image_ids'  => $image_ids,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'too_many_images', $result->get_error_code() );
	}
}
