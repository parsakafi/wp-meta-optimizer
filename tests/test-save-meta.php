<?php

/**
 * Class SampleTest
 *
 * @package Wp_Meta_Optimizer
 */

/**
 * Sample test case.
 */
class WPMO_MetaSaveTest extends WP_UnitTestCase
{

    public function set_up()
    {
        parent::set_up();

        WPMetaOptimizer\Install::install();

        update_option('active_plugins', array(
            'wp-meta-optimizer/WPMetaOptimizer.php',
        ));

        wp_set_current_user(1);
    }

    public function testSaveMeta()
    {
        WPMetaOptimizer\Install::install();

        global $wpdb, $wp_filter;
        $postID = 1;
        $metaValue = time();
        $meta_key = 'image_id_wpmo_test';
        update_post_meta($postID, $meta_key, $metaValue);

        $row = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta}_wpmo", ARRAY_A);
        // fwrite(STDERR, print_r($row, TRUE));
        // fwrite(STDERR, print_r(get_option('wp_meta_optimizer'), TRUE));
        // fwrite(STDERR, print_r(get_option('active_plugins'), TRUE));
        // fwrite(STDERR, print_r($row, TRUE));

        // fwrite(STDERR, print_r($wp_filter['get_post_metadata'], TRUE));
        $this->assertEquals($metaValue, get_post_meta($postID, $meta_key, true));

        $tableName = $wpdb->postmeta . '_wpmo';
        // $this->assertEquals($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->postmeta}_wpmo'") , $tableName);
        // $this->assertEquals($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->postmeta}_wpmo'") , $tableName);
        // $this->assertTrue(has_filter('get_post_metadata'));
        // $this->assertTrue(Util::has_action('get_post_metadata'));
        $this->assertTrue(class_exists('WPMetaOptimizer\WPMetaOptimizer'));
        // $this->assertTableExists($wpdb->postmeta);
    }
}
