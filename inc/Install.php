<?php

namespace WPMetaOptimizer;

class Install
{
    public static function install()
    {
        global $wpdb;

        if (!function_exists('dbDelta'))
            require_once(ABSPATH . str_replace('/', DIRECTORY_SEPARATOR, '/wp-admin/includes/upgrade.php'));

        $tables = array(
            'post' => $wpdb->postmeta . '_wpmo',
            'comment' => $wpdb->commentmeta . '_wpmo',
            'user' => $wpdb->usermeta . '_wpmo',
            'term' => $wpdb->termmeta . '_wpmo'
        );

        foreach ($tables as $type => $table) {
            if ($wpdb->get_var("show tables like '$table'") != $table) {
                $sql = "CREATE TABLE `{$table}` (
                  `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `{$type}_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                   PRIMARY KEY (`meta_id`),
                   UNIQUE KEY `{$type}_id` (`{$type}_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

                dbDelta($sql);
                dbDelta("ALTER TABLE `{$table}` ROW_FORMAT=DYNAMIC;");
            }
        }
    }
}
