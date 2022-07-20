<?php

namespace WPMetaOptimizer;

use WP_Query;

class Queries extends Base
{
    public static $instance = null;
    protected $Helpers;

    function __construct()
    {
        parent::__construct();

        $this->Helpers = Helpers::getInstance();

        add_action('init', [$this, 'runTestQuery']);
        add_filter('posts_join_paged', [$this, 'changeQueryJoin'], 9999, 2);
        add_filter('posts_where_paged', [$this, 'changeQueryWhere'], 9999, 2);
    }

    function changeQueryWhere($where, $query)
    {
        global $wpdb;

        $where_ = $where;

        if (strpos($where_, $wpdb->postmeta . '.meta_key') !== false) {
            $pattern = "/(?<={$wpdb->postmeta}\.meta_key = \')(.*?)(?=\')/";

            if (preg_match($pattern, $where_, $matches)) {
                $postMetaTable = $this->Helpers->getTableName('post');

                foreach ($matches as $field) {
                    if ($this->Helpers->checkColumnExists($postMetaTable, $field))
                        $where_ = str_replace("{$wpdb->postmeta}.meta_key = '{$field}'", "{$postMetaTable}.$field IS NOT NULL", $where_);
                    else {
                        // TODO: Reset all changes to default sql query.
                        return $where;
                    }
                }

                $where = $where_;
            }
        }

        return $where;
    }

    function changeQueryJoin($join, $query)
    {
        global $wpdb;

        if (strpos($join, $wpdb->postmeta) !== false) {
            $postMetaTable = $this->Helpers->getTableName('post');
            $join = str_replace($wpdb->postmeta, $postMetaTable, $join);
        }

        return $join;
    }

    function runTestQuery()
    {
        if (!is_admin() && isset($_GET['wpmotest'])) {
            $query = new WP_Query(array('meta_key' => 'subtitle_new'));

            echo '<pre>';
            var_dump($query->posts);
            exit;
        }
    }

    /**
     * Returns an instance of class
     * @return Queries
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new Queries();

        return self::$instance;
    }
}
