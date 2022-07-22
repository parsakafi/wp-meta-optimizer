<?php

namespace WPMetaOptimizer;

use WP_Query, WP_Meta_Query;

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
        // add_filter('posts_clauses', [$this, 'changePostsQueryClauses'], 9999, 2);
        add_filter('get_meta_sql', [$this, 'changeMetaSQL'], 9999, 6);
    }

    function changeMetaSQL($sql, $queries, $type, $primaryTable, $primaryIDColumn, $context)
    {
        global $wpdb;

        /*  $primaryTable = $this->Helpers->getWPPrimaryTableName($type);
        if (!$primaryTable)
            return $sql; */

        $metaTable = $this->Helpers->getWPMetaTableName($type);
        $metaNewTable = $this->Helpers->getMetaTableName($type);

        $primaryColumn = 'ID';
        if (in_array($type, ['term', 'comment']))
            $primaryColumn = $type . '_ID';

        $wheres = [];

        //$sql['join'] = str_replace($metaTable, $metaNewTable, $sql['join']);


        // Parse meta query.
        $this->metaQuery = new MetaQuery(false, $this->Helpers);
        $this->metaQuery->parse_query_vars($context->query_vars);

        if (!empty($this->metaQuery->queries)) {
            $sql_ = $this->metaQuery->get_sql($type, $primaryTable, $primaryIDColumn, $this);
            echo '<pre>';
            var_dump($sql_);

            $sql = $sql_;
        }

        return $sql;
    }

    function changePostsQueryClauses($pieces, $query)
    {
        global $wpdb;

        $pieces_ = $pieces;
        $postMetaTable = $this->Helpers->getMetaTableName('post');

        // Change Join
        if (strpos($pieces_['join'], $wpdb->postmeta) !== false)
            $pieces_['join'] = str_replace($wpdb->postmeta, $postMetaTable, $pieces_['join']);
        else
            return $pieces;

        // Change Where
        if (strpos($pieces_['where'], $wpdb->postmeta . '.meta_key') !== false) {
            $pattern = "/(?<=" . $wpdb->postmeta . "\.meta_key = \')(.*?)(?=\')/";

            if (preg_match_all($pattern, $pieces_['where'], $matches) && isset($matches[0])) {
                $fields = $matches[0];

                foreach ($fields as $field) {
                    if ($this->Helpers->checkColumnExists($postMetaTable, $field)) {
                        $pieces_['where'] = str_replace("{$wpdb->postmeta}.meta_key = '{$field}'", "{$postMetaTable}.$field IS NOT NULL", $pieces_['where']);
                    } else
                        return $pieces;
                }
            }
        }

        if (isset($fields) && is_array($fields) && strpos($pieces_['where'], $wpdb->postmeta . '.meta_value') !== false) {
            $pattern = "/(?<=" . $wpdb->postmeta . "\.meta_value = \')(.*?)(?=\')/";

            if (preg_match_all($pattern, $pieces_['where'], $matches) && isset($matches[0])) {
                foreach ($matches[0] as $i => $metaValue) {
                    $pieces_['where'] = str_replace("{$wpdb->postmeta}.meta_value = '{$metaValue}'", "{$postMetaTable}.$fields[$i] = '$metaValue'", $pieces_['where']);
                }
            }
        }

        echo '<pre>';
        var_dump($pieces);
        var_dump($query);

        return $pieces_;
    }

    function runTestQuery()
    {
        if (!is_admin() && isset($_GET['wpmotest'])) {
            $query = new WP_Query(array(
                'meta_key' => 'subtitle_new',
                'meta_value' => 'be1',
                'meta_compare' => 'NOT EXISTS'
            ));

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
