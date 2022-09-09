<?php

namespace WPMetaOptimizer;

/**
 * Comment API: CommentQueries class.
 *
 * @package WPMetaOptimizer
 * @subpackage Comments
 * @since 1.0
 */

class CommentQueries
{
    public static $instance = null;
    private $Queries;

    function __construct($Queries)
    {
        $this->Queries = $Queries;

        add_filter('comments_clauses', [$this, 'changeCommentsClauses'], 9999, 2);
    }

    /**  
     * Filters the comment query clauses.
     * @copyright Base on WP_Comment_Query:get_comments method.
     * 
     * @param string[]         $clauses An associative array of comment query clauses.
     * @param \WP_Comment_Query $query   Current instance of WP_Comment_Query (passed by reference).
     */
    function changeCommentsClauses($clauses, $query)
    {
        global $wpdb;

        // Change GroupBy
        if (isset($clauses['groupby']))
            $clauses['groupby'] = '';

        // Change OrderBy
        $order = ('ASC' === strtoupper($this->Queries->queryVars['order'])) ? 'ASC' : 'DESC';

        // Disable ORDER BY with 'none', an empty array, or boolean false.
        if (in_array($this->Queries->queryVars['orderby'], array('none', array(), false), true)) {
            $orderby = '';
        } elseif (!empty($this->Queries->queryVars['orderby'])) {
            $ordersby = is_array($this->Queries->queryVars['orderby']) ?
                $this->Queries->queryVars['orderby'] :
                preg_split('/[,\s]/', $this->Queries->queryVars['orderby']);

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

                $orderby_array[] = $parsed . ' ' . $this->Queries->parseOrder($_order);
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
     * @copyright Base on WP_Comment_Query:parse_orderby method.
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

        $meta_query_clauses = $this->Queries->metaQuery->get_clauses();

        $primary_meta_key   = '';
        $primary_meta_query = false;
        if (!empty($meta_query_clauses)) {
            $primary_meta_query = isset($meta_query_clauses[$orderby]) ? $meta_query_clauses[$orderby] : reset($meta_query_clauses);

            if (!empty($primary_meta_query['key'])) {
                $primary_meta_key = $primary_meta_query['key'];
                $allowed_keys[]   = $primary_meta_key;
            }

            $allowed_keys[] = $this->Queries->queryVars['meta_key'];
            $allowed_keys[] = 'meta_value';
            $allowed_keys[] = 'meta_value_num';
        }

        if ($meta_query_clauses) {
            $allowed_keys = array_merge($allowed_keys, array_keys($meta_query_clauses));
        }

        $parsed = false;
        if ($this->Queries->queryVars['meta_key'] === $orderby || 'meta_value' === $orderby) {
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
     * Returns an instance of class
     * @return CommentQueries
     */
    static function getInstance($Queries)
    {
        if (self::$instance == null)
            self::$instance = new CommentQueries($Queries);

        return self::$instance;
    }
}
