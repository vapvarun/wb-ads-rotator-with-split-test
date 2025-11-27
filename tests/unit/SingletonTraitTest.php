<?php
/**
 * Singleton Trait Unit Tests
 *
 * @package WB_Ad_Manager
 */

namespace WBAM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WBAM\Core\Singleton;

/**
 * Test class using Singleton trait.
 */
class TestSingletonClass {
	use Singleton;

	/**
	 * Test property.
	 *
	 * @var string
	 */
	public $test_value = 'initial';
}

/**
 * Singleton Trait Test class.
 */
class SingletonTraitTest extends TestCase {

	/**
	 * Set up before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		// Reset singleton instance.
		$reflection = new \ReflectionClass( TestSingletonClass::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Test get_instance returns same instance.
	 */
	public function test_get_instance_returns_same_instance() {
		$instance1 = TestSingletonClass::get_instance();
		$instance2 = TestSingletonClass::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test get_instance returns correct class.
	 */
	public function test_get_instance_returns_correct_class() {
		$instance = TestSingletonClass::get_instance();

		$this->assertInstanceOf( TestSingletonClass::class, $instance );
	}

	/**
	 * Test state persists across calls.
	 */
	public function test_state_persists_across_calls() {
		$instance1 = TestSingletonClass::get_instance();
		$instance1->test_value = 'modified';

		$instance2 = TestSingletonClass::get_instance();

		$this->assertEquals( 'modified', $instance2->test_value );
	}

	/**
	 * Test __wakeup throws exception.
	 */
	public function test_wakeup_throws_exception() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cannot unserialize singleton' );

		$instance   = TestSingletonClass::get_instance();
		$serialized = serialize( $instance );
		unserialize( $serialized );
	}

	/**
	 * Test clone is protected.
	 */
	public function test_clone_is_protected() {
		$reflection = new \ReflectionMethod( TestSingletonClass::class, '__clone' );

		$this->assertTrue( $reflection->isProtected() );
	}

	/**
	 * Test instance property exists.
	 */
	public function test_instance_property_exists() {
		$reflection = new \ReflectionClass( TestSingletonClass::class );

		$this->assertTrue( $reflection->hasProperty( 'instance' ) );
	}

	/**
	 * Test instance property is protected static.
	 */
	public function test_instance_property_is_protected_static() {
		$reflection = new \ReflectionClass( TestSingletonClass::class );
		$property   = $reflection->getProperty( 'instance' );

		$this->assertTrue( $property->isProtected() );
		$this->assertTrue( $property->isStatic() );
	}
}
