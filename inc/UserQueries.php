<?php

namespace WPMetaOptimizer;

class UserQueries
{
    public static $instance = null;
    private $Queries;

    function __construct($Queries)
    {
        $this->Queries = $Queries;

        add_action('pre_user_query', [$this, 'changeUserQuery'], 9999);
    }

    /**
     * 
     * 
     * @parm WP_User_Query $query 
     */
    function changeUserQuery($query)
    {
        $qv = $this->Queries->queryVars; // $this->Queries->queryVars = $query->query_vars;

        $qv['order'] = isset($qv['order']) ? strtoupper($qv['order']) : '';
        $order       = $this->Queries->parseOrder($qv['order']);

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

            if (!$parsed)
                continue;

            if ('nicename__in' === $_orderby || 'login__in' === $_orderby) {
                $orderby_array[] = $parsed;
            } else {
                $orderby_array[] = $parsed . ' ' . $this->Queries->parseOrder($_order);
            }
        }

        // If no valid clauses were found, order by user_login.
        if (empty($orderby_array)) {
            $orderby_array[] = "user_login $order";
        }

        $query->query_orderby = 'ORDER BY ' . implode(', ', $orderby_array);

        // Remove DISTINCT from query fields
        $query->query_fields = trim(str_replace('DISTINCT', '', $query->query_fields));
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

        $meta_query_clauses = $this->Queries->metaQuery->get_clauses();

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
            $this->Queries->query_from .= " LEFT OUTER JOIN (
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
        } elseif ('include' === $orderby && !empty($this->Queries->queryVars['include'])) {
            $include     = wp_parse_id_list($this->Queries->queryVars['include']);
            $include_sql = implode(',', $include);
            $_orderby    = "FIELD( $wpdb->users.ID, $include_sql )";
        } elseif ('nicename__in' === $orderby) {
            $sanitized_nicename__in = array_map('esc_sql', $this->Queries->queryVars['nicename__in']);
            $nicename__in           = implode("','", $sanitized_nicename__in);
            $_orderby               = "FIELD( user_nicename, '$nicename__in' )";
        } elseif ('login__in' === $orderby) {
            $sanitized_login__in = array_map('esc_sql', $this->Queries->queryVars['login__in']);
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
     * Returns an instance of class
     * @return UserQueries
     */
    static function getInstance($Queries)
    {
        if (self::$instance == null)
            self::$instance = new UserQueries($Queries);

        return self::$instance;
    }
}
