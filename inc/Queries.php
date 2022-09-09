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

    /**
     * Query vars set by the user.
     *
     * @since 3.1.0
     * @var array
     */
    public $queryVars;

    function __construct()
    {
        parent::__construct();

        $this->Helpers = Helpers::getInstance();
        $this->metaQuery = new MetaQuery(false, $this->Helpers);

        add_action('init', [$this, 'runTestQuery']);

        if ($this->Helpers->checkSupportWPQuery()) {
            add_filter('get_meta_sql', [$this, 'changeMetaSQL'], 9999, 6);

            PostQueries::getInstance($this);
            CommentQueries::getInstance($this);
            UserQueries::getInstance($this);
            TermQueries::getInstance($this);
        }
    }

    /**
     * Change meta SQL of default WP meta types
     *
     * @param string[] $sql               Array containing the query's JOIN and WHERE clauses.
     * @param array    $queries           Array of meta queries.
     * @param string   $type              Type of meta. Possible values include but are not limited
     *                                    to 'post', 'comment', 'blog', 'term', and 'user'.
     * @param string   $primaryTable     Primary table.
     * @param string   $primaryIDColumn Primary column ID.
     * @param object   $context           The main query object that corresponds to the type, for
     *                                    example a `WP_Query`, `WP_User_Query`, or `WP_Site_Query`.
    * @return string                      SQL Query
     */
    function changeMetaSQL($sql, $queries, $type, $primaryTable, $primaryIDColumn, $context)
    {
        if (!is_object($context))
            return $sql;

        $this->metaQuery = new MetaQuery(false, $this->Helpers);

        $this->queryVars = $this->getQueryVars($type, $context->query_vars);

        // Parse meta query.
        $this->metaQuery->parse_query_vars($this->queryVars);

        if (!empty($this->metaQuery->queries))
            $sql = $this->metaQuery->get_sql($type, $primaryTable, $primaryIDColumn, $this);

        return $sql;
    }

    /**
     * Get query variables
     *
     * @param string $type      Meta type
     * @param array $queryVars  Current query vars
     * @return array
     */
    public function getQueryVars($type, $queryVars)
    {
        // Change Meta Key
        if (isset($queryVars['meta_key']) && $queryVars['meta_key'])
            $queryVars['meta_key'] = $this->Helpers->translateColumnName($type, $queryVars['meta_key']);

        // Change Meta Query
        if (isset($queryVars['meta_query']) && is_array($queryVars['meta_query']))
            foreach ($queryVars['meta_query'] as $key => $query)
                if (isset($query['key'])) {
                    $keyIndex = $key;
                    if (is_string($key))
                        $keyIndex = $this->Helpers->translateColumnName($type, $key);
                    if ($keyIndex !== $key)
                        unset($queryVars['meta_query'][$key]);
                    $query['key'] = $this->Helpers->translateColumnName($type, $query['key']);
                    $queryVars['meta_query'][$keyIndex] = $query;
                }

        // Change OrderBy
        if (isset($queryVars['orderby']) && is_array($queryVars['orderby'])) {
            foreach ($queryVars['orderby'] as $key => $order) {
                $keyIndex = $key;
                if (is_string($key))
                    $keyIndex = $this->Helpers->translateColumnName($type, $key);
                if ($keyIndex !== $key)
                    unset($queryVars['orderby'][$key]);

                $queryVars['orderby'][$keyIndex] = $order;
            }
        }

        return $queryVars;
    }

    function runTestQuery()
    {
        if (!is_admin() && isset($_GET['wpmotest'])) {
            echo '<pre>';

            // update_post_meta(1, 'meta_id', 444);
            // update_comment_meta(2, 'comment_id', 1);
            // update_user_meta(1, 'post_id', 1);

            $query = new WP_Query(array(
                // 'meta_key' => 'post_id',
                // 'meta_key' => 'custom_meta',
                // 'meta_compare' => 'NOT EXISTS',
                // 'meta_compare' => 'NOT IN',
                // 'meta_value' => 'be1',
                // 'meta_value' => [3, 5],

                'fields' => 'ids',
                'orderby' => array(
                    'post_id' => 'DESC'
                ),
                // 'orderby' => 'meta_value',
                // 'meta_key' => 'custom_meta',

                'meta_query' => array(
                    'relation' => 'OR',
                    'post_id' => array(
                        'key' => 'post_id',
                        'compare' => 'EXISTS',
                        'type' => 'NUMERIC'
                    ),
                    // 'custom_meta' => array(
                    //     'key' => 'custom_meta',
                    //     'compare' => 'EXISTS',
                    //     'type' => 'NUMERIC'
                    //     // 'value' => [0, 80000],
                    //     // 'type' => 'NUMERIC',
                    // ),
                ),

                'no_found_rows' => true
            ));

            var_dump(trim($query->request));
            echo '<br><br>';
            var_dump($query->posts);
            echo '<br><br>';

            $args = array(
                'fields' => 'ids',
                'no_found_rows' => true,
                'orderby' => array(
                    'comment_id' => 'DESC'
                ),
                'meta_query' => array(
                    'relation' => 'OR',
                    'comment_id' => array(
                        'key' => 'comment_id',
                        'compare' => '=',
                        'value' => 20,
                        'type' => 'NUMERIC'
                    ),
                    array(
                        'key' => 'post_id',
                        'compare' => 'EXISTS',
                        'type' => 'NUMERIC'
                    )
                )
            );
            $commentQuery = new \WP_Comment_Query($args);

            var_dump(trim($commentQuery->request));
            echo '<br><br>';
            var_dump($commentQuery->comments);
            echo '<br><br>';


            $searchQuery = new \WP_User_Query(array(
                'fields' => 'ids',
                'no_found_rows' => true,
                'count_total' => false,
                'orderby' => array(
                    'post_id' => 'DESC'
                ),
                'meta_query' => array(
                    'relation' => 'OR',
                    'post_id' => array(
                        'key' => 'post_id',
                        'compare' => 'EXISTS',
                        'type' => 'NUMERIC'
                    )
                )
            ));
            var_dump(trim($searchQuery->request));
            echo '<br><br>';
            var_dump($searchQuery->get_results());
            echo '<br><br>';



            $args = array(
                'fields' => 'ids',
                'taxonomy' => 'category',
                'orderby' => array(
                    'image_id' => 'DESC'
                ),
                'meta_query' => array(
                    'image_id' => array(
                        'key' => 'image_id',
                        'compare' => 'EXISTS'
                    )
                ),
                'order'      => 'ASC',
                'hide_empty' => false,
            );
            $termQuery = new \WP_Term_Query($args);

            var_dump(trim($termQuery->request));
            echo '<br><br>';
            var_dump($termQuery->get_terms());
            echo '<br><br>';

            exit;
        }
    }

    /**
     * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
     * @copyright Base on WordPress parse_order method
     *
     * @since 4.0.0
     *
     * @param string $order The 'order' query variable.
     * @return string The sanitized 'order' query variable.
     */
    public function parseOrder($order)
    {
        if (!is_string($order) || empty($order)) {
            return 'DESC';
        }

        if ('ASC' === strtoupper($order)) {
            return 'ASC';
        } else {
            return 'DESC';
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
