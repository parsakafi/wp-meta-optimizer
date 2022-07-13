<?php

namespace WPMetaOptimizer;

class Base
{
    public $now, $tables,
        $optionKey = 'wp_meta_optimizer',
        $intTypes =  ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'],
        $floatTypes = ['FLOAT', 'DOUBLE', 'DECIMAL'],
        $charTypes = ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'],
        $dateTypes = ['DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR'],
        $ignoreTableColumns = ['meta_id', 'created_at', 'updated_at'],
        $ignoreNativeMetaKeys = []; //['_edit_lock', '_edit_last'];

    function __construct()
    {
        global $wpdb;

        $this->now = current_time('mysql');

        $this->tables = array(
            'post' => [
                'table' => $wpdb->postmeta . '_wpmo',
                'title' => __('Post Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'comment' => [
                'table' => $wpdb->commentmeta . '_wpmo',
                'title' => __('Comment Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'term' => [
                'table' => $wpdb->termmeta . '_wpmo',
                'title' => __('Term Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'user' => [
                'table' => $wpdb->usermeta . '_wpmo',
                'title' => __('User Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
        );
    }
}
