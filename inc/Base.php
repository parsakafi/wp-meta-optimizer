<?php

namespace WPMetaOptimizer;

class Base
{
    public $now, $tables, $wpMetaTables,
        $intTypes =  ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'],
        $floatTypes = ['FLOAT', 'DOUBLE', 'DECIMAL'],
        $charTypes = ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'],
        $dateTypes = ['DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR'],
        $ignoreTableColumns = ['meta_id', 'created_at', 'updated_at'],
        $ignorePostTypes = ['wp_block', 'wp_navigation', 'acf-field-group'],
        $ignoreWPMetaKeys = array(
            'post' => ['_edit_lock', '_edit_last'],
            'comment' => [],
            'user' => ['session_tokens', 'wp_capabilities'],
            'term' => []
        ),
        $cantChangeWPMetaKeys = array(
            'post' => ['_thumbnail_id', '_encloseme', '_wp_old_slug', '_pingme', '_wp_page_template'],
            'comment' => [],
            'user' => [
                'session_tokens', 'wp_capabilities', 'admin_color', 'community-events-location',
                'comment_shortcuts', 'first_name', 'last_name', 'nickname', 'description',
                'locale', 'metaboxhidden_nav-menus', 'nav_menu_recently_edited',
                'show_admin_bar_front', 'syntax_highlighting', 'show_welcome_panel',
                'use_ssl', 'wp_dashboard_quick_press_last_post_id', 'wp_user-settings', 'wp_user-settings-time',
                'wp_user_level', 'rich_editing', 'managenav-menuscolumnshidden', 'dismissed_wp_pointers'
            ],
            'term' => []
        ),
        $reservedKeysSuffix = '_wpmork';

    function __construct()
    {
        global $wpdb;

        $this->now = current_time('mysql');

        $this->wpPrimaryTables = array(
            'post' => $wpdb->posts,
            'comment' => $wpdb->comments,
            'user' => $wpdb->users,
            'term' => $wpdb->terms
        );

        $this->wpMetaTables = array(
            'post' => $wpdb->postmeta,
            'comment' => $wpdb->commentmeta,
            'user' => $wpdb->usermeta,
            'term' => $wpdb->termmeta
        );

        $this->tables = array(
            'post' => [
                'table' => $wpdb->postmeta . '_wpmo',
                'name' => __('Post'),
                'title' => __('Post Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'comment' => [
                'table' => $wpdb->commentmeta . '_wpmo',
                'name' => __('Comment'),
                'title' => __('Comment Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'user' => [
                'table' => $wpdb->usermeta . '_wpmo',
                'name' => __('User'),
                'title' => __('User Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'term' => [
                'table' => $wpdb->termmeta . '_wpmo',
                'name' => __('Term', WPMETAOPTIMIZER_PLUGIN_KEY),
                'title' => __('Term Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ]
        );
    }
}
