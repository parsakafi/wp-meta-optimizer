<?php

/**
 * Plugin Name: WP Meta Optimizer
 * Version: 1.0
 * Plugin URI: https://parsa.ws
 * Description: You can use WP Meta Optimizer to make your WordPress website load faster if you use meta information, for example Post/Comment/User/Term metas.
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
require_once __DIR__ . '/inc/Queries.php';
require_once __DIR__ . '/inc/MetaQuery.php';
require_once __DIR__ . '/inc/PostQueries.php';
require_once __DIR__ . '/inc/CommentQueries.php';
require_once __DIR__ . '/inc/UserQueries.php';
require_once __DIR__ . '/inc/TermQueries.php';
require_once __DIR__ . '/inc/Integration.php';

define('WPMETAOPTIMIZER_PLUGIN_KEY', 'wp-meta-optimizer');
define('WPMETAOPTIMIZER_PLUGIN_NAME', 'WP Meta Optimizer');
define('WPMETAOPTIMIZER_PLUGIN_FILE_PATH', __FILE__);
define('WPMETAOPTIMIZER_CACHE_EXPIRE', 30);

class WPMetaOptimizer extends Base
{
    public static $instance = null;
    protected $Helpers, $Options;

    function __construct()
    {
        parent::__construct();

        $this->Helpers = Helpers::getInstance();
        $this->Options = Options::getInstance();
        Actions::getInstance();
        Queries::getInstance();
        Integration::getInstance();

        $actionPriority = 99999999;

        $types = array_keys($this->Options->getOption('meta_save_types', []));
        foreach ($types as $type) {
            if ($type == 'hidden')
                continue;
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

        $tableName = $this->Helpers->getMetaTableName($metaType);
        if (!$tableName)
            return $value;

        if (!$this->Helpers->checkMetaType($metaType))
            return $value;

        if ($metaType === 'post' && !$this->Helpers->checkPostType($objectID))
            return $value;

        if ($this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'white_list') === false)
            return $value;

        $metaKey = $this->Helpers->translateColumnName($metaType, $metaKey);

        if (!$this->Helpers->checkColumnExists($tableName, $metaType, $metaKey))
            return $value;

        $metaRow = wp_cache_get($tableName . '_' . $metaType . '_' . $objectID . '_row', WPMETAOPTIMIZER_PLUGIN_KEY);
        if ($metaRow === false) {
            $tableColumns = $this->Helpers->getTableColumns($tableName, $metaType, true);
            $tableColumns = '`' . implode('`,`', $tableColumns) . '`';
            $sql = "SELECT {$tableColumns} FROM `{$tableName}` WHERE {$metaType}_id = {$objectID}";
            $metaRow = $wpdb->get_row($sql, ARRAY_A);
            wp_cache_set($tableName . '_' . $metaType . '_' . $objectID . '_row', $metaRow, WPMETAOPTIMIZER_PLUGIN_KEY, WPMETAOPTIMIZER_CACHE_EXPIRE);
        }

        if (is_array($metaRow) && isset($metaRow[$metaKey])) {
            $metaValue = maybe_unserialize($metaRow[$metaKey]);
            return $single && is_array($metaValue) && isset($metaValue[0]) ? $metaValue[0] : $metaValue;
        }

        return $value;

        /* $metaCache = wp_cache_get($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . "_{$metaType}_meta");

        if ($metaCache !== false)
            return $single && is_array($metaCache) && isset($metaCache[0]) ? $metaCache[0] : $metaCache;

        $sql = "SELECT `{$metaKey}` FROM `{$tableName}` WHERE {$metaType}_id = {$objectID}";
        $row = $wpdb->get_row($sql, ARRAY_A);

        $metaValue = null;
        if ($row && isset($row[$metaKey])) {
            $metaValue = maybe_unserialize($row[$metaKey]);

            //$fieldType = $this->getTableColumnType($tableName, $metaKey);
            //if (in_array($fieldType, $this->intTypes))
            // $row[$metaKey] = intval($row[$metaKey]);

            wp_cache_set($objectID . '_' . $metaKey, $metaValue, WPMETAOPTIMIZER_PLUGIN_KEY . "_{$metaType}_meta", WPMETAOPTIMIZER_CACHE_EXPIRE);
        }

        if ($metaValue)
            return $single && is_array($metaValue) && isset($metaValue[0]) ? $metaValue[0] : $metaValue;
        else
            return $value; */
    }

    function addMeta($metaType, $check, $objectID, $metaKey, $metaValue, $unique)
    {
        if (!$this->Helpers->checkMetaType($metaType))
            return $check;

        if ($metaType === 'post' && !$this->Helpers->checkPostType($objectID))
            return $check;

        if ($this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($metaType, $metaKey, 'white_list') === false)
            return $check;

        $metaKey = $this->Helpers->translateColumnName($metaType, $metaKey);

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

        $tableName = $this->Helpers->getMetaTableName($metaType);
        wp_cache_delete($tableName . '_' . $metaType . '_' . $objectID . '_row', WPMETAOPTIMIZER_PLUGIN_KEY);

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

        $metaKey = $this->Helpers->translateColumnName($metaType, $metaKey);

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

        $tableName = $this->Helpers->getMetaTableName($metaType);
        wp_cache_delete($tableName . '_' . $metaType . '_' . $objectID . '_row', WPMETAOPTIMIZER_PLUGIN_KEY);

        return $this->Helpers->checkDontSaveInDefaultTable($metaType) ? $result : $check;
    }

    private function deleteMeta($metaType, $objectID, $metaKey)
    {
        global $wpdb;

        $tableName = $this->Helpers->getMetaTableName($metaType);
        if (!$tableName)
            return false;

        $column = sanitize_key($metaType . '_id');

        $metaKey = $this->Helpers->translateColumnName($metaType, $metaKey);

        $result = $wpdb->update(
            $tableName,
            [$metaKey => null, 'updated_at' => $this->now],
            [$column => $objectID]
        );

        wp_cache_delete($tableName . '_' . $metaType . '_' . $objectID . '_row', WPMETAOPTIMIZER_PLUGIN_KEY);

        return $result;
    }

    /**
     * Returns an instance of class
     * @return WPMetaOptimizer
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new WPMetaOptimizer();

        return self::$instance;
    }
}

WPMetaOptimizer::getInstance();
register_activation_hook(__FILE__, array('WPMetaOptimizer\Install', 'install'));
