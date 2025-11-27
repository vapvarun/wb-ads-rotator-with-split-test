<?php
/**
 * Content Analyzer Unit Tests
 *
 * @package WB_Ad_Manager
 */

namespace WBAM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WBAM\Modules\Targeting\Content_Analyzer;

/**
 * Content Analyzer Test class.
 */
class ContentAnalyzerTest extends TestCase {

	/**
	 * Content Analyzer instance.
	 *
	 * @var Content_Analyzer
	 */
	private $analyzer;

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		// Clear singleton instance for fresh tests.
		$reflection = new \ReflectionClass( Content_Analyzer::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$this->analyzer = Content_Analyzer::get_instance();
		$this->analyzer->clear_cache();
	}

	/**
	 * Test get_character_count.
	 */
	public function test_get_character_count() {
		$content = '<p>Hello World</p>';
		$count   = $this->analyzer->get_character_count( $content );

		$this->assertEquals( 11, $count );
	}

	/**
	 * Test get_character_count with HTML.
	 */
	public function test_get_character_count_strips_html() {
		$content = '<p><strong>Hello</strong> <em>World</em></p>';
		$count   = $this->analyzer->get_character_count( $content );

		$this->assertEquals( 11, $count );
	}

	/**
	 * Test get_word_count.
	 */
	public function test_get_word_count() {
		$content = '<p>This is a test sentence with seven words.</p>';
		$count   = $this->analyzer->get_word_count( $content );

		$this->assertEquals( 8, $count );
	}

	/**
	 * Test get_word_count with empty content.
	 */
	public function test_get_word_count_empty() {
		$content = '';
		$count   = $this->analyzer->get_word_count( $content );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test get_word_count with only whitespace.
	 */
	public function test_get_word_count_whitespace() {
		$content = '   <p>   </p>   ';
		$count   = $this->analyzer->get_word_count( $content );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test get_paragraph_count.
	 */
	public function test_get_paragraph_count() {
		$content = '<p>First paragraph.</p><p>Second paragraph.</p><p>Third paragraph.</p>';
		$count   = $this->analyzer->get_paragraph_count( $content );

		$this->assertEquals( 3, $count );
	}

	/**
	 * Test get_paragraph_count with attributes.
	 */
	public function test_get_paragraph_count_with_attributes() {
		$content = '<p class="intro">First.</p><p id="main" style="color:red;">Second.</p>';
		$count   = $this->analyzer->get_paragraph_count( $content );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test get_heading_count.
	 */
	public function test_get_heading_count() {
		$content = '<h1>Title</h1><h2>Subtitle</h2><h2>Another</h2><h3>Section</h3>';
		$counts  = $this->analyzer->get_heading_count( $content );

		$this->assertEquals( 1, $counts['h1'] );
		$this->assertEquals( 2, $counts['h2'] );
		$this->assertEquals( 1, $counts['h3'] );
		$this->assertEquals( 0, $counts['h4'] );
		$this->assertEquals( 4, $counts['total'] );
	}

	/**
	 * Test get_image_count.
	 */
	public function test_get_image_count() {
		$content = '<p>Text</p><img src="image1.jpg"><p>More text</p><img src="image2.png" alt="test">';
		$count   = $this->analyzer->get_image_count( $content );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test get_link_count.
	 */
	public function test_get_link_count() {
		$content = '<p><a href="http://example.com">Link 1</a> and <a href="#anchor">Link 2</a></p>';
		$count   = $this->analyzer->get_link_count( $content );

		$this->assertEquals( 2, $count );
	}

	/**
	 * Test get_reading_time short content.
	 */
	public function test_get_reading_time_short() {
		$content = '<p>This is a short sentence.</p>';
		$time    = $this->analyzer->get_reading_time( $content );

		$this->assertEquals( 1, $time ); // Minimum 1 minute.
	}

	/**
	 * Test get_reading_time long content.
	 */
	public function test_get_reading_time_long() {
		// Generate content with ~400 words (2 min reading time at 200 wpm).
		$words   = array_fill( 0, 400, 'word' );
		$content = '<p>' . implode( ' ', $words ) . '</p>';
		$time    = $this->analyzer->get_reading_time( $content );

		$this->assertEquals( 2, $time );
	}

	/**
	 * Test get_content_length_category short.
	 */
	public function test_get_content_length_category_short() {
		$words   = array_fill( 0, 100, 'word' );
		$content = '<p>' . implode( ' ', $words ) . '</p>';

		$this->assertEquals( 'short', $this->analyzer->get_content_length_category( $content ) );
	}

	/**
	 * Test get_content_length_category medium.
	 */
	public function test_get_content_length_category_medium() {
		$words   = array_fill( 0, 500, 'word' );
		$content = '<p>' . implode( ' ', $words ) . '</p>';

		$this->assertEquals( 'medium', $this->analyzer->get_content_length_category( $content ) );
	}

	/**
	 * Test get_content_length_category long.
	 */
	public function test_get_content_length_category_long() {
		$words   = array_fill( 0, 1500, 'word' );
		$content = '<p>' . implode( ' ', $words ) . '</p>';

		$this->assertEquals( 'long', $this->analyzer->get_content_length_category( $content ) );
	}

	/**
	 * Test get_content_length_category very long.
	 */
	public function test_get_content_length_category_very_long() {
		$words   = array_fill( 0, 2500, 'word' );
		$content = '<p>' . implode( ' ', $words ) . '</p>';

		$this->assertEquals( 'very_long', $this->analyzer->get_content_length_category( $content ) );
	}

	/**
	 * Test analyze method returns all expected keys.
	 */
	public function test_analyze_returns_all_keys() {
		$content  = '<p>Test content</p>';
		$analysis = $this->analyzer->analyze( $content );

		$this->assertArrayHasKey( 'character_count', $analysis );
		$this->assertArrayHasKey( 'word_count', $analysis );
		$this->assertArrayHasKey( 'paragraph_count', $analysis );
		$this->assertArrayHasKey( 'heading_count', $analysis );
		$this->assertArrayHasKey( 'image_count', $analysis );
		$this->assertArrayHasKey( 'link_count', $analysis );
		$this->assertArrayHasKey( 'reading_time', $analysis );
		$this->assertArrayHasKey( 'content_length', $analysis );
	}

	/**
	 * Test analyze caches results.
	 */
	public function test_analyze_caches_results() {
		$content = '<p>Test content for caching</p>';

		$analysis1 = $this->analyzer->analyze( $content );
		$analysis2 = $this->analyzer->analyze( $content );

		$this->assertEquals( $analysis1, $analysis2 );
	}

	/**
	 * Test get_suggested_positions for short content.
	 */
	public function test_get_suggested_positions_short_content() {
		$words     = array_fill( 0, 100, 'word' );
		$content   = '<p>' . implode( ' ', $words ) . '</p>';
		$positions = $this->analyzer->get_suggested_positions( $content );

		$this->assertCount( 1, $positions );
		$this->assertEquals( 'after_content', $positions[0]['type'] );
	}

	/**
	 * Test get_suggested_positions for medium content.
	 */
	public function test_get_suggested_positions_medium_content() {
		$paragraphs = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$words        = array_fill( 0, 100, 'word' );
			$paragraphs[] = '<p>' . implode( ' ', $words ) . '</p>';
		}
		$content   = implode( '', $paragraphs );
		$positions = $this->analyzer->get_suggested_positions( $content );

		$this->assertGreaterThan( 1, count( $positions ) );
	}

	/**
	 * Test meets_requirements with default requirements.
	 */
	public function test_meets_requirements_default_pass() {
		$paragraphs = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$words        = array_fill( 0, 50, 'word' );
			$paragraphs[] = '<p>' . implode( ' ', $words ) . '</p>';
		}
		$content = implode( '', $paragraphs );

		$this->assertTrue( $this->analyzer->meets_requirements( $content ) );
	}

	/**
	 * Test meets_requirements fails with insufficient words.
	 */
	public function test_meets_requirements_insufficient_words() {
		$content = '<p>Short content.</p><p>Too short.</p>';

		$this->assertFalse( $this->analyzer->meets_requirements( $content ) );
	}

	/**
	 * Test meets_requirements fails with insufficient paragraphs.
	 */
	public function test_meets_requirements_insufficient_paragraphs() {
		$words   = array_fill( 0, 200, 'word' );
		$content = '<p>' . implode( ' ', $words ) . '</p>';

		$this->assertFalse( $this->analyzer->meets_requirements( $content ) );
	}

	/**
	 * Test meets_requirements with custom requirements.
	 */
	public function test_meets_requirements_custom() {
		// Content with enough words and paragraphs to meet custom requirements.
		$content = '<p>This is some test content with enough words.</p><p>And here is another paragraph with more words.</p>';

		$custom_reqs = array(
			'min_words'      => 5,
			'min_paragraphs' => 2,
			'min_characters' => 10,
		);

		$this->assertTrue( $this->analyzer->meets_requirements( $content, $custom_reqs ) );
	}

	/**
	 * Test clear_cache method.
	 */
	public function test_clear_cache() {
		$content = '<p>Test content</p>';

		// Populate cache.
		$this->analyzer->analyze( $content );

		// Clear cache.
		$this->analyzer->clear_cache();

		// Verify cache is cleared by analyzing again.
		$analysis = $this->analyzer->analyze( $content );
		$this->assertNotEmpty( $analysis );
	}
}
