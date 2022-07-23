<?php

namespace WPMetaOptimizer;

use WP_Query;

class Queries extends Base
{
    public static $instance = null;
    protected $Helpers;

    /**
     * Metadata query container.
     *
     * @since 3.2.0
     * @var MetaQuery A meta query instance.
     */
    public $metaQuery = false;

    function __construct()
    {
        parent::__construct();

        $this->Helpers = Helpers::getInstance();

        add_action('init', [$this, 'runTestQuery']);
        add_filter('get_meta_sql', [$this, 'changeMetaSQL'], 9999, 6);
        add_filter('posts_orderby', [$this, 'changePostsOrderBy'], 9999, 2);
    }

    /**
     * Filters the ORDER BY clause of the query.
     *
     * @since 1.5.1
     *
     * @param string   $orderBy The ORDER BY clause of the query.
     * @param WP_Query $query   The WP_Query instance (passed by reference).
     */
    function changePostsOrderBy($orderBy, $query)
    {
        if (in_array($query->get('orderby'), ['meta_value', 'meta_value_num']) && $metaKey = $query->get('meta_key', false)) {
            $metaTableName = $this->Helpers->getMetaTableName('post');
            $wpMetaTableName = $query->meta_query->meta_table; // $this->Helpers->getWPMetaTableName('post');

            if (strpos($orderBy, $wpMetaTableName) !== false)
                $orderBy = str_replace([$wpMetaTableName, 'meta_value'], [$metaTableName, $metaKey], $orderBy);
        }

        return $orderBy;
    }

    function changeMetaSQL($sql, $queries, $type, $primaryTable, $primaryIDColumn, $context)
    {
        // Parse meta query.
        $this->metaQuery = new MetaQuery(false, $this->Helpers);
        $this->metaQuery->parse_query_vars($context->query_vars);

        if (!empty($this->metaQuery->queries)) {
            $sql = $this->metaQuery->get_sql($type, $primaryTable, $primaryIDColumn, $this);
        }

        return $sql;
    }

    function runTestQuery()
    {
        if (!is_admin() && isset($_GET['wpmotest'])) {
            $query = new WP_Query(array(
                // 'meta_key' => 'subtitle_new',
                // 'meta_key' => 'custom_meta',
                // 'meta_compare' => 'NOT EXISTS',
                // 'meta_compare' => 'NOT IN',
                // 'meta_value' => 'be1',
                // 'meta_value' => [3, 5],

                'orderby' => 'meta_value',
                'meta_key' => 'subtitle_new',

                'meta_query' => array(
                    // 'relation' => 'OR',
                    array(
                        'key' => 'subtitle_new',
                        'compare' => 'EXISTS',
                    ),
                    /*  array(
                        'key' => 'custom_meta',
                        'compare' => 'IN',
                        'value' => [2, 8],
                        'type' => 'NUMERIC',
                    ), */
                ),

                'no_found_rows' => true
            ));

            echo '<pre>';
            // var_dump(trim($query->request));
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
