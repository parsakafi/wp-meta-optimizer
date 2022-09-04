<?php

namespace WPMetaOptimizer;

use WP_Meta_Query;
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

            add_filter('posts_groupby',  [$this, 'changePostsGroupBy'], 9999, 2);
            add_filter('posts_orderby', [$this, 'changePostsOrderBy'], 9999, 2);

            add_filter('comments_clauses', [$this, 'changeCommentsClauses'], 9999, 2);

            add_action('pre_user_query', [$this, 'changeUserQuery'], 9999);
        }
    }

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
            $queryVars = $this->getQueryVars('post', $query->query);
            $this->metaQuery->parse_query_vars($queryVars);

            $query->set('orderby', $queryVars['orderby']);

            $orderByQuery = $query->get('orderby');
            $orderQuery = $query->get('order');

            // Order by.
            if (!empty($orderByQuery) && 'none' !== $orderByQuery) {
                $orderby_array = array();

                if (is_array($orderByQuery)) {
                    foreach ($orderByQuery as $_orderby => $order) {
                        $orderby = addslashes_gpc(urldecode($_orderby));
                        // var_dump($orderby);
                        $parsed  = $this->postParseOrderby($orderby);

                        if (!$parsed)
                            continue;

                        $orderby_array[] = $parsed . ' ' . $this->parse_order($order);
                    }
                    $orderBy_ = implode(', ', $orderby_array);
                } else {
                    $orderByQuery = urldecode($orderByQuery);
                    $orderByQuery = addslashes_gpc($orderByQuery);

                    foreach (explode(' ', $orderByQuery) as $i => $orderby) {
                        $parsed = $this->postParseOrderby($orderby);
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
    }

    private function getQueryVars($type, $queryVars)
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
            update_user_meta(1, 'post_id', 1);

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
    protected function postParseOrderby($orderby)
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
                if (!empty($this->queryVars['post__in'])) {
                    $orderby_clause = "FIELD({$wpdb->posts}.ID," . implode(',', array_map('absint', $this->queryVars['post__in'])) . ')';
                }
                break;
            case 'post_parent__in':
                if (!empty($this->queryVars['post_parent__in'])) {
                    $orderby_clause = "FIELD( {$wpdb->posts}.post_parent," . implode(', ', array_map('absint', $this->queryVars['post_parent__in'])) . ' )';
                }
                break;
            case 'post_name__in':
                if (!empty($this->queryVars['post_name__in'])) {
                    $post_name__in        = array_map('sanitize_title_for_query', $this->queryVars['post_name__in']);
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

    function changeCommentsClauses($clauses, $query)
    {
        global $wpdb;

        // Change GroupBy
        if (isset($clauses['groupby']))
            $clauses['groupby'] = '';

        // Change OrderBy
        $order = ('ASC' === strtoupper($this->queryVars['order'])) ? 'ASC' : 'DESC';

        // Disable ORDER BY with 'none', an empty array, or boolean false.
        if (in_array($this->queryVars['orderby'], array('none', array(), false), true)) {
            $orderby = '';
        } elseif (!empty($this->queryVars['orderby'])) {
            $ordersby = is_array($this->queryVars['orderby']) ?
                $this->queryVars['orderby'] :
                preg_split('/[,\s]/', $this->queryVars['orderby']);

            $orderby_array            = array();
            $found_orderby_comment_id = false;
            foreach ($ordersby as $_key => $_value) {
                if (!$_value) {
                    continue;
                }

                if (is_int($_key)) {
                    $_orderby = $_value;
                    $_order   = $order;
                } else {
                    $_orderby = $_key;
                    $_order   = $_value;
                }

                if (!$found_orderby_comment_id && in_array($_orderby, array('comment_ID', 'comment__in'), true)) {
                    $found_orderby_comment_id = true;
                }

                $parsed = $this->commentParseOrderby($_orderby);

                if (!$parsed)
                    continue;

                if ('comment__in' === $_orderby) {
                    $orderby_array[] = $parsed;
                    continue;
                }

                $orderby_array[] = $parsed . ' ' . $this->parse_order($_order);
            }

            // If no valid clauses were found, order by comment_date_gmt.
            if (empty($orderby_array)) {
                $orderby_array[] = "$wpdb->comments.comment_date_gmt $order";
            }

            // To ensure determinate sorting, always include a comment_ID clause.
            if (!$found_orderby_comment_id) {
                $comment_id_order = '';

                // Inherit order from comment_date or comment_date_gmt, if available.
                foreach ($orderby_array as $orderby_clause) {
                    if (preg_match('/comment_date(?:_gmt)*\ (ASC|DESC)/', $orderby_clause, $match)) {
                        $comment_id_order = $match[1];
                        break;
                    }
                }

                // If no date-related order is available, use the date from the first available clause.
                if (!$comment_id_order) {
                    foreach ($orderby_array as $orderby_clause) {
                        if (false !== strpos('ASC', $orderby_clause)) {
                            $comment_id_order = 'ASC';
                        } else {
                            $comment_id_order = 'DESC';
                        }

                        break;
                    }
                }

                // Default to DESC.
                if (!$comment_id_order) {
                    $comment_id_order = 'DESC';
                }

                $orderby_array[] = "$wpdb->comments.comment_ID $comment_id_order";
            }

            $orderby = implode(', ', $orderby_array);
        } else {
            $orderby = "$wpdb->comments.comment_date_gmt $order";
        }

        $clauses['orderby'] = $orderby;

        return $clauses;
    }

    /**
     * Parse and sanitize 'orderby' keys passed to the comment query.
     *
     * @since 4.2.0
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $orderby Alias for the field to order by.
     * @return string|false Value to used in the ORDER clause. False otherwise.
     */
    protected function commentParseOrderby($orderby)
    {
        global $wpdb;

        $allowed_keys = array(
            'comment_agent',
            'comment_approved',
            'comment_author',
            'comment_author_email',
            'comment_author_IP',
            'comment_author_url',
            'comment_content',
            'comment_date',
            'comment_date_gmt',
            'comment_ID',
            'comment_karma',
            'comment_parent',
            'comment_post_ID',
            'comment_type',
            'user_id',
        );

        $meta_query_clauses = $this->metaQuery->get_clauses();

        $primary_meta_key   = '';
        $primary_meta_query = false;
        if (!empty($meta_query_clauses)) {
            $primary_meta_query = isset($meta_query_clauses[$orderby]) ? $meta_query_clauses[$orderby] : reset($meta_query_clauses);

            if (!empty($primary_meta_query['key'])) {
                $primary_meta_key = $primary_meta_query['key'];
                $allowed_keys[]   = $primary_meta_key;
            }

            $allowed_keys[] = $this->queryVars['meta_key'];
            $allowed_keys[] = 'meta_value';
            $allowed_keys[] = 'meta_value_num';
        }

        if ($meta_query_clauses) {
            $allowed_keys = array_merge($allowed_keys, array_keys($meta_query_clauses));
        }

        $commentMetaTable = $this->Helpers->getMetaTableName('comment');
        $parsed = false;
        if ($this->queryVars['meta_key'] === $orderby || 'meta_value' === $orderby) {
            //$parsed = "$commentMetaTable.meta_value";
            if (!empty($primary_meta_query['type'])) {
                $parsed = "CAST({$primary_meta_query['alias']}.{$primary_meta_key} AS {$primary_meta_query['cast']})";
            } else {
                $parsed = "{$primary_meta_query['alias']}.{$primary_meta_key}";
            }
        } elseif ('meta_value_num' === $orderby) {
            // $parsed = "$commentMetaTable.meta_value+0";
            $parsed = "{$primary_meta_query['alias']}.{$primary_meta_key}+0";
        } elseif ('comment__in' === $orderby) {
            $comment__in = implode(',', array_map('absint', $this->queryVars['comment__in']));
            $parsed      = "FIELD( {$wpdb->comments}.comment_ID, $comment__in )";
        } elseif (in_array($orderby, $allowed_keys, true)) {
            if (array_key_exists($orderby, $meta_query_clauses)) {
                // $orderby corresponds to a meta_query clause.
                $meta_clause    = $meta_query_clauses[$orderby];
                $parsed = "CAST({$meta_clause['alias']}.{$primary_meta_key} AS {$meta_clause['cast']})";
            } else {
                // Default: order by post field.
                $parsed = "{$wpdb->posts}.post_" . sanitize_key($orderby);
            }

            // if (isset($meta_query_clauses[$orderby])) {
            //     $meta_clause = $meta_query_clauses[$orderby];
            //     $parsed      = sprintf('CAST(%s.meta_value AS %s)', esc_sql($meta_clause['alias']), esc_sql($meta_clause['cast']));
            // } else {
            //     $parsed = "$wpdb->comments.$orderby";
            // }
        }

        return $parsed;
    }

    /**
     * 
     * 
     * @parm WP_User_Query $query 
     */
    function changeUserQuery($query)
    {
        $qv = $query->query_vars;

        $qv['order'] = isset($qv['order']) ? strtoupper($qv['order']) : '';
        $order       = $this->parse_order($qv['order']);

        if (empty($qv['orderby'])) {
            // Default order is by 'user_login'.
            $ordersby = array('user_login' => $order);
        } elseif (is_array($qv['orderby'])) {
            $ordersby = $qv['orderby'];
        } else {
            // 'orderby' values may be a comma- or space-separated list.
            $ordersby = preg_split('/[,\s]+/', $qv['orderby']);
        }

        $orderby_array = array();
        foreach ($ordersby as $_key => $_value) {
            if (!$_value) {
                continue;
            }

            if (is_int($_key)) {
                // Integer key means this is a flat array of 'orderby' fields.
                $_orderby = $_value;
                $_order   = $order;
            } else {
                // Non-integer key means this the key is the field and the value is ASC/DESC.
                $_orderby = $_key;
                $_order   = $_value;
            }

            $parsed = $this->userParseOrderby($_orderby, $query);

            if (!$parsed) {
                continue;
            }

            if ('nicename__in' === $_orderby || 'login__in' === $_orderby) {
                $orderby_array[] = $parsed;
            } else {
                $orderby_array[] = $parsed . ' ' . $this->parse_order($_order);
            }
        }

        // If no valid clauses were found, order by user_login.
        if (empty($orderby_array)) {
            $orderby_array[] = "user_login $order";
        }

        $query->query_orderby = 'ORDER BY ' . implode(', ', $orderby_array);
    }

    /**
     * Parses and sanitizes 'orderby' keys passed to the user query.
     *
     * @since 4.2.0
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $orderby Alias for the field to order by.
     * @return string Value to used in the ORDER clause, if `$orderby` is valid.
     */
    protected function userParseOrderby($orderby, $query)
    {
        global $wpdb;

        $meta_query_clauses = $this->metaQuery->get_clauses();

        $primary_meta_key   = '';
        $primary_meta_query = false;
        if (!empty($meta_query_clauses)) {
            $primary_meta_query = isset($meta_query_clauses[$orderby]) ? $meta_query_clauses[$orderby] : reset($meta_query_clauses);

            if (!empty($primary_meta_query['key'])) {
                $primary_meta_key = $primary_meta_query['key'];
            }
        }

        $_orderby = '';
        if (in_array($orderby, array('login', 'nicename', 'email', 'url', 'registered'), true)) {
            $_orderby = 'user_' . $orderby;
        } elseif (in_array($orderby, array('user_login', 'user_nicename', 'user_email', 'user_url', 'user_registered'), true)) {
            $_orderby = $orderby;
        } elseif ('name' === $orderby || 'display_name' === $orderby) {
            $_orderby = 'display_name';
        } elseif ('post_count' === $orderby) {
            // @todo Avoid the JOIN.
            $where             = get_posts_by_author_sql('post');
            $this->query_from .= " LEFT OUTER JOIN (
				SELECT post_author, COUNT(*) as post_count
				FROM $wpdb->posts
				$where
				GROUP BY post_author
			) p ON ({$wpdb->users}.ID = p.post_author)
			";
            $_orderby          = 'post_count';
        } elseif ('ID' === $orderby || 'id' === $orderby) {
            $_orderby = 'ID';
        } elseif ('meta_value' === $orderby || $query->get('meta_key') == $orderby) {
            // $_orderby = "$wpdb->usermeta.meta_value";
            if (!empty($primary_meta_query['type'])) {
                $_orderby = "CAST({$primary_meta_query['alias']}.{$primary_meta_key} AS {$primary_meta_query['cast']})";
            } else {
                $_orderby = "{$primary_meta_query['alias']}.{$primary_meta_key}";
            }
        } elseif ('meta_value_num' === $orderby) {
            // $_orderby = "$wpdb->usermeta.meta_value+0";
            $_orderby = "{$primary_meta_query['alias']}.{$primary_meta_key}+0";
        } elseif ('include' === $orderby && !empty($this->query_vars['include'])) {
            $include     = wp_parse_id_list($this->query_vars['include']);
            $include_sql = implode(',', $include);
            $_orderby    = "FIELD( $wpdb->users.ID, $include_sql )";
        } elseif ('nicename__in' === $orderby) {
            $sanitized_nicename__in = array_map('esc_sql', $this->query_vars['nicename__in']);
            $nicename__in           = implode("','", $sanitized_nicename__in);
            $_orderby               = "FIELD( user_nicename, '$nicename__in' )";
        } elseif ('login__in' === $orderby) {
            $sanitized_login__in = array_map('esc_sql', $this->query_vars['login__in']);
            $login__in           = implode("','", $sanitized_login__in);
            $_orderby            = "FIELD( user_login, '$login__in' )";
        } elseif (isset($meta_query_clauses[$orderby])) {
            $meta_clause = $meta_query_clauses[$orderby];
            // $_orderby    = sprintf('CAST(%s.meta_value AS %s)', esc_sql($meta_clause['alias']), esc_sql($meta_clause['cast']));
            $_orderby = "CAST({$meta_clause['alias']}.{$primary_meta_key} AS {$meta_clause['cast']})";
        }
        
        return $_orderby;
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
