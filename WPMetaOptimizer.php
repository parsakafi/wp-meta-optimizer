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

        foreach ($this->tables as $type => $table) {
            add_filter('get_' . $type . '_metadata', [$this, 'getMeta'], $actionPriority, 5);
            add_filter('add_' . $type . '_metadata', [$this, 'add' . ucwords($type) . 'Meta'], $actionPriority, 5);
            add_filter('update_' . $type . '_metadata', [$this, 'update' . ucwords($type) . 'Meta'], $actionPriority, 5);
            add_action('deleted_' . $type . '_meta', [$this, 'delete' . ucwords($type) . 'Meta'], $actionPriority, 4);
        }
    }

    function addPostMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('post', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    function updatePostMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('post', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    function deletePostMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('post', $objectID, $metaKey);
    }

    function addCommentMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('comment', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    function updateCommentMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('comment', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    function deleteCommentMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('comment', $objectID, $metaKey);
    }

    function addTermMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('term', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    function updateTermMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('term', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    function deleteTermMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('term', $objectID, $metaKey);
    }

    function addUserMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('user', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    function updateUserMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('user', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    function deleteUserMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('user', $objectID, $metaKey);
    }

    function getMeta($value, $objectID, $metaKey, $single, $metaType)
    {
        global $wpdb;

        //if ($metaKey === '')
        //    return $value;

        if (defined('IMPORT_PROCESS_WPMO'))
            return $value;

        if (!$this->Helpers->checkMetaType($metaType))
            return $value;

        if ($metaType === 'post' && !$this->Helpers->checkPostType($objectID))
            return $value;

        if ($this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'white_list') === false)
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

    function addMeta($metaType, $check, $objectID, $metaKey, $metaValue, $unique)
    {
        if (!$this->Helpers->checkMetaType($metaType))
            return $check;

        if ($metaType === 'post' && !$this->Helpers->checkPostType($objectID))
            return $check;

        if ($this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'white_list') === false)
            return $check;

        $result = $this->Helpers->insertMeta(
            [
                'metaType' => $metaType,
                'objectID' => $objectID,
                'metaKey' => $metaKey,
                'metaValue' => $metaValue,
                'unique' => $unique,
                'addMeta' => true
            ]
        );

        return $this->Helpers->checkDontSaveInDefaultTable($metaType) ? $result : $check;
    }

    function updateMeta($metaType, $check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        if (!$this->Helpers->checkMetaType($metaType))
            return $check;

        if ($metaType === 'post' && !$this->Helpers->checkPostType($objectID))
            return $check;

        if ($this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'white_list') === false)
            return $check;

        $result = $this->Helpers->insertMeta(
            [
                'metaType' => $metaType,
                'objectID' => $objectID,
                'metaKey' => $metaKey,
                'metaValue' => $metaValue,
                'unique' => false,
                'prevValue' => $prevValue
            ]
        );

        return $this->Helpers->checkDontSaveInDefaultTable($metaType) ? $result : $check;
    }

    private function deleteMeta($type, $objectID, $metaKey)
    {
        global $wpdb;

        $tableName = $this->Helpers->getTableName($type);
        if (!$tableName)
            return false;

        $column = sanitize_key($type . '_id');

        $result = $wpdb->update(
            $tableName,
            [$metaKey => null, 'updated_at' => $this->now],
            [$column => $objectID]
        );

        wp_cache_delete($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . '_' . $type . '_meta');

        return $result;
    }
}

new WPMetaOptimizer();
register_activation_hook(__FILE__, array('WPMetaOptimizer\Install', 'install'));
