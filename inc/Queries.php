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
        $this->metaQuery = new MetaQuery(false, $this->Helpers);

        add_action('init', [$this, 'runTestQuery']);

        if ($this->Helpers->checkSupportWPQuery()) {
            add_filter('get_meta_sql', [$this, 'changeMetaSQL'], 9999, 6);
            add_filter('posts_orderby', [$this, 'changePostsOrderBy'], 9999, 2);
            add_filter('posts_groupby',  [$this, 'changePostsGroupBy'], 9999, 2);
        }
    }

    /**
     * Filters the GROUP BY clause of the query.
     *
     * @since 2.0.0
     *
     * @param string   $groupby The GROUP BY clause of the query.
     * @param WP_Query $query   The WP_Query instance (passed by reference).
     */
    function changePostsGroupBy($groupby, $query)
    {
        return "";
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
        global $wpdb;

        if (is_array($query->get('orderby')) || in_array($query->get('orderby'), ['meta_value', 'meta_value_num'])) {
            $this->metaQuery->parse_query_vars($query->query);

            $orderByQuery = $query->get('orderby');
            $orderQuery = $query->get('order');

            // Order by.
            if (!empty($orderByQuery) && 'none' !== $orderByQuery) {
                $orderby_array = array();

                if (is_array($orderByQuery)) {
                    foreach ($orderByQuery as $_orderby => $order) {
                        $orderby = addslashes_gpc(urldecode($_orderby));
                        $parsed  = $this->parse_orderby($orderby);

                        if (!$parsed) {
                            continue;
                        }

                        $orderby_array[] = $parsed . ' ' . $this->parse_order($order);
                    }
                    $orderBy_ = implode(', ', $orderby_array);
                } else {
                    $orderByQuery = urldecode($orderByQuery);
                    $orderByQuery = addslashes_gpc($orderByQuery);

                    foreach (explode(' ', $orderByQuery) as $i => $orderby) {
                        $parsed = $this->parse_orderby($orderby);
                        // Only allow certain values for safety.
                        if (!$parsed) {
                            continue;
                        }

                        $orderby_array[] = $parsed;
                    }
                    $orderBy_ = implode(' ' . $orderQuery . ', ', $orderby_array);

                    if (empty($orderBy_)) {
                        $orderBy_ = "{$wpdb->posts}.post_date " . $orderQuery;
                    } elseif (!empty($orderQuery)) {
                        $orderBy_ .= " {$orderQuery}";
                    }
                }

                return $orderBy_;
            }
        }

        return $orderBy;
        //

        if ((is_array($query->get('orderby')) || in_array($query->get('orderby'), ['meta_value', 'meta_value_num'])) && $metaKey = $query->get('meta_key', false)) {
            $metaTableName = $this->Helpers->getMetaTableName('post');
            $wpMetaTableName = $query->meta_query->meta_table; // $this->Helpers->getWPMetaTableName('post');

            if (strpos($orderBy, $wpMetaTableName) !== false)
                $orderBy = str_replace([$wpMetaTableName, 'meta_value'], [$metaTableName, $metaKey], $orderBy);
        }
        return $orderBy;
    }

    function changeMetaSQL($sql, $queries, $type, $primaryTable, $primaryIDColumn, $context)
    {
        if (!is_object($context))
            return $sql;

        // Parse meta query.
        $this->metaQuery->parse_query_vars($context->query_vars);

        if (!empty($this->metaQuery->queries)) {
            $sql = $this->metaQuery->get_sql($type, $primaryTable, $primaryIDColumn, $this);
        }

        return $sql;
    }

    function runTestQuery()
    {
        if (!is_admin() && isset($_GET['wpmotest'])) {
            echo '<pre>';

            // update_post_meta(1, 'bool', '');

            $query = new WP_Query(array(
                // 'meta_key' => 'subtitle_new',
                // 'meta_key' => 'custom_meta',
                // 'meta_compare' => 'NOT EXISTS',
                // 'meta_compare' => 'NOT IN',
                // 'meta_value' => 'be1',
                // 'meta_value' => [3, 5],

                'fields' => 'ids',
                'orderby' => array(
                    'subtitle_new' => 'ASC',
                    'custom_meta' => 'ASC',
                ),
                // 'orderby' => 'meta_value',
                // 'meta_key' => 'custom_meta',

                'meta_query' => array(
                    'relation' => 'OR',
                    'subtitle_new' => array(
                        'key' => 'subtitle_new',
                        'compare' => 'EXISTS',
                        'type' => 'CHAR'
                    ),
                    'custom_meta' => array(
                        'key' => 'custom_meta',
                        'compare' => 'EXISTS',
                        'type' => 'NUMERIC'
                        // 'value' => [0, 80000],
                        // 'type' => 'NUMERIC',
                    ),
                ),

                'no_found_rows' => true
            ));

            var_dump(trim($query->request));
            echo '<br><br>';
            var_dump($query->posts);
            exit;
        }
    }

    /**
     * Converts the given orderby alias (if allowed) to a properly-prefixed value.
     *
     * @since 4.0.0
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $orderby Alias for the field to order by.
     * @return string|false Table-prefixed value to used in the ORDER clause. False otherwise.
     */
    protected function parse_orderby($orderby)
    {
        global $wpdb;

        // Used to filter values.
        $allowed_keys = array(
            'post_name',
            'post_author',
            'post_date',
            'post_title',
            'post_modified',
            'post_parent',
            'post_type',
            'name',
            'author',
            'date',
            'title',
            'modified',
            'parent',
            'type',
            'ID',
            'menu_order',
            'comment_count',
            'rand',
            'post__in',
            'post_parent__in',
            'post_name__in',
        );

        $primary_meta_key   = '';
        $primary_meta_query = false;
        $meta_clauses       = $this->metaQuery->get_clauses();

        if (!empty($meta_clauses)) {
            $primary_meta_query = isset($meta_clauses[$orderby]) ? $meta_clauses[$orderby] : reset($meta_clauses);

            if (!empty($primary_meta_query['key'])) {
                $primary_meta_key = $primary_meta_query['key'];
                $allowed_keys[]   = $primary_meta_key;
            }

            $allowed_keys[] = 'meta_value';
            $allowed_keys[] = 'meta_value_num';
            $allowed_keys   = array_merge($allowed_keys, array_keys($meta_clauses));
        }

        // If RAND() contains a seed value, sanitize and add to allowed keys.
        $rand_with_seed = false;
        if (preg_match('/RAND\(([0-9]+)\)/i', $orderby, $matches)) {
            $orderby        = sprintf('RAND(%s)', (int) $matches[1]);
            $allowed_keys[] = $orderby;
            $rand_with_seed = true;
        }

        if (!in_array($orderby, $allowed_keys, true)) {
            return false;
        }

        $orderby_clause = '';

        switch ($orderby) {
            case 'post_name':
            case 'post_author':
            case 'post_date':
            case 'post_title':
            case 'post_modified':
            case 'post_parent':
            case 'post_type':
            case 'ID':
            case 'menu_order':
            case 'comment_count':
                $orderby_clause = "{$wpdb->posts}.{$orderby}";
                break;
            case 'rand':
                $orderby_clause = 'RAND()';
                break;
            case $primary_meta_key:
            case 'meta_value':
                if (!empty($primary_meta_query['type'])) {
                    $orderby_clause = "CAST({$primary_meta_query['alias']}.{$primary_meta_key} AS {$primary_meta_query['cast']})";
                } else {
                    $orderby_clause = "{$primary_meta_query['alias']}.{$primary_meta_key}";
                }
                break;
            case 'meta_value_num':
                $orderby_clause = "{$primary_meta_query['alias']}.{$primary_meta_key}+0";
                break;
            case 'post__in':
                if (!empty($this->query_vars['post__in'])) {
                    $orderby_clause = "FIELD({$wpdb->posts}.ID," . implode(',', array_map('absint', $this->query_vars['post__in'])) . ')';
                }
                break;
            case 'post_parent__in':
                if (!empty($this->query_vars['post_parent__in'])) {
                    $orderby_clause = "FIELD( {$wpdb->posts}.post_parent," . implode(', ', array_map('absint', $this->query_vars['post_parent__in'])) . ' )';
                }
                break;
            case 'post_name__in':
                if (!empty($this->query_vars['post_name__in'])) {
                    $post_name__in        = array_map('sanitize_title_for_query', $this->query_vars['post_name__in']);
                    $post_name__in_string = "'" . implode("','", $post_name__in) . "'";
                    $orderby_clause       = "FIELD( {$wpdb->posts}.post_name," . $post_name__in_string . ' )';
                }
                break;
            default:
                if (array_key_exists($orderby, $meta_clauses)) {
                    // $orderby corresponds to a meta_query clause.
                    $meta_clause    = $meta_clauses[$orderby];
                    $orderby_clause = "CAST({$meta_clause['alias']}.{$primary_meta_key} AS {$meta_clause['cast']})";
                } elseif ($rand_with_seed) {
                    $orderby_clause = $orderby;
                } else {
                    // Default: order by post field.
                    $orderby_clause = "{$wpdb->posts}.post_" . sanitize_key($orderby);
                }

                break;
        }

        return $orderby_clause;
    }

    /**
     * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
     *
     * @since 4.0.0
     *
     * @param string $order The 'order' query variable.
     * @return string The sanitized 'order' query variable.
     */
    protected function parse_order($order)
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
