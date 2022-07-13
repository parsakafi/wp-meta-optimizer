<?php

/**
 * Plugin Name: WP Meta Optimizer
 * Version: 1.0
 * Plugin URI: https://parsa.ws
 * Description: Optimize Meta Tables
 * Author: Parsa Kafi
 * Author URI: https://parsa.ws
 */

namespace WPMetaOptimizer;

defined('ABSPATH') || die();

require_once __DIR__ . '/inc/Base.php';
require_once __DIR__ . '/inc/Install.php';
require_once __DIR__ . '/inc/Helpers.php';
require_once __DIR__ . '/inc/Options.php';
require_once __DIR__ . '/inc/Actions.php';

define('WPMETAOPTIMIZER_PLUGIN_KEY', 'wp-meta-optimizer');
define('WPMETAOPTIMIZER_PLUGIN_NAME', 'WP Meta Optimizer');

class WPMetaOptimizer extends Base
{
    protected $Helpers;

    function __construct()
    {
        parent::__construct();

        $this->Helpers = new Helpers();
        new Actions($this->Helpers);

        $actionPriority = 99999999;

        foreach ($this->tables as $type => $table)
            add_filter('get_' . $type . '_metadata', [$this, 'getMeta'], $actionPriority, 5);

        add_action('add_post_meta', [$this, 'addPostMeta'], $actionPriority, 3);
        add_action('update_post_meta', [$this, 'updatePostMeta'], $actionPriority, 4);

        add_action('add_comment_meta', [$this, 'addCommentMeta'], $actionPriority, 3);
        add_action('update_comment_meta', [$this, 'updateCommentMeta'], $actionPriority, 4);

        add_action('add_term_meta', [$this, 'addTermMeta'], $actionPriority, 3);
        add_action('update_term_meta', [$this, 'updateTermMeta'], $actionPriority, 4);

        add_action('add_user_meta', [$this, 'addUserMeta'], $actionPriority, 3);
        add_action('update_user_meta', [$this, 'updateUserMeta'], $actionPriority, 4);
    }

    function addPostMeta($objectID, $metaKey, $metaValue)
    {
        $this->addMeta('post', $objectID, $metaKey, $metaValue);
    }

    function updatePostMeta($metaID, $objectID, $metaKey, $metaValue)
    {
        $this->updateMeta('post', $metaID, $objectID, $metaKey, $metaValue);
    }

    function addCommentMeta($objectID, $metaKey, $metaValue)
    {
        $this->addMeta('comment', $objectID, $metaKey, $metaValue);
    }

    function updateCommentMeta($metaID, $objectID, $metaKey, $metaValue)
    {
        $this->updateMeta('comment', $metaID, $objectID, $metaKey, $metaValue);
    }

    function addTermMeta($objectID, $metaKey, $metaValue)
    {
        $this->addMeta('term', $objectID, $metaKey, $metaValue);
    }

    function updateTermMeta($metaID, $objectID, $metaKey, $metaValue)
    {
        $this->updateMeta('term', $metaID, $objectID, $metaKey, $metaValue);
    }

    function addUserMeta($objectID, $metaKey, $metaValue)
    {
        $this->addMeta('user', $objectID, $metaKey, $metaValue);
    }

    function updateUserMeta($metaID, $objectID, $metaKey, $metaValue)
    {
        $this->updateMeta('user', $metaID, $objectID, $metaKey, $metaValue);
    }

    function getMeta($value, $objectID, $metaKey, $single, $metaType)
    {
        global $wpdb;

        if ($this->Helpers->checkInBlackWhiteList($metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaKey, 'white_list') === false)
            return $value;

        $metaCache = wp_cache_get($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . "_{$metaType}_meta");

        if ($metaCache !== false)
            return $metaCache;

        $tableName = $this->Helpers->getTableName($metaType);

        if (!$tableName)
            return $value;

        $sql = "SELECT `{$metaKey}` FROM `{$tableName}` WHERE {$metaType}_id = {$objectID}";
        $row = $wpdb->get_row($sql, ARRAY_A);

        if ($row && isset($row[$metaKey])) {
            $row[$metaKey] = maybe_unserialize($row[$metaKey]);

            //$fieldType = $this->getTableColumnType($tableName, $metaKey);
            //if (in_array($fieldType, $this->intTypes))
            // $row[$metaKey] = intval($row[$metaKey]);

            wp_cache_set($objectID . '_' . $metaKey, $row[$metaKey], WPMETAOPTIMIZER_PLUGIN_KEY . "_{$metaType}_meta");
        }

        return isset($row[$metaKey]) ? $row[$metaKey] : $value;
    }

    function addMeta($metaType, $objectID, $metaKey, $metaValue)
    {
        if ($this->Helpers->checkInBlackWhiteList($metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaKey, 'white_list') === false)
            return false;

        $result = $this->insertMeta($metaType, $objectID, $metaKey, $metaValue);

        return $result;
    }

    function updateMeta($metaType, $metaID, $objectID, $metaKey, $metaValue)
    {
        return $this->addMeta($metaType, $objectID, $metaKey, $metaValue);
    }

    private function insertMeta($metaType, $objectID, $metaKey, $metaValue)
    {
        global $wpdb;

        $tableName = $this->Helpers->getTableName($metaType);
        if (!$tableName)
            return false;

        $addTableColumn = $this->Helpers->addTableColumn($tableName, $metaType, $metaKey, $metaValue);
        if (!$addTableColumn)
            return false;

        $column = sanitize_key($metaType . '_id');

        $checkInserted = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE {$column} = %d",
                $objectID
            )
        );

        if (is_bool($metaValue))
            $metaValue = intval($metaValue);

        $metaValue = maybe_serialize($metaValue);

        if ($checkInserted) {
            $result = $wpdb->update(
                $tableName,
                [$metaKey => $metaValue, 'updated_at' => $this->now],
                [$column => $objectID]
            );

            wp_cache_delete($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . '_post_meta');

            return $result;
        } else {
            $result = $wpdb->insert(
                $tableName,
                [
                    $column => $objectID,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    $metaKey => $metaValue
                ]
            );
            if (!$result)
                return false;

            return (int) $wpdb->insert_id;
        }
    }
}

new WPMetaOptimizer();
register_activation_hook(__FILE__, array('WPMetaOptimizer\Install', 'install'));
