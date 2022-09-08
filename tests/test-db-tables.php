<?php

/**
 * Class SampleTest
 *
 * @package Wp_Meta_Optimizer
 */

use function PHPUnit\Framework\assertIsArray;

/**
 * Sample test case.
 */
class WPMO_DBTablesTest extends WP_UnitTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        WPMetaOptimizer\Install::install();
    } 

     protected function tearDown(): void
    {
        parent::tearDown();
    } 

    /**
     * A single example test.
     */
    public function testCheckDBTable()
    {
        global $wpdb;
        $this->assertEquals($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->postmeta}_wpmo'") , $wpdb->postmeta .'_wpmo');
        $this->assertEquals($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->commentmeta}_wpmo'") , $wpdb->commentmeta .'_wpmo');
        $this->assertEquals($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->usermeta}_wpmo'") , $wpdb->usermeta .'_wpmo');
        $this->assertEquals($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->termmeta}_wpmo'") , $wpdb->termmeta .'_wpmo');
    }
}
