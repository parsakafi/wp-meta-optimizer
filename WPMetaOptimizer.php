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

// Check run from WP
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
define('WPMETAOPTIMIZER_OPTION_KEY', 'wp_meta_optimizer');
define('WPMETAOPTIMIZER_PLUGIN_NAME', 'WP Meta Optimizer');
define('WPMETAOPTIMIZER_PLUGIN_FILE_PATH', __FILE__);
define('WPMETAOPTIMIZER_CACHE_EXPIRE', 30);

/**
 * Main class run WP Meta Optimizer plugin
 */
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

    /**
     * Adds a meta field to the given post.
     *
     * @param null|bool $check      Whether to allow adding metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param bool      $unique     Whether the specified meta key should be unique for the object.
     * 
     * @return int|false The meta ID on success, false on failure.
     */
    function addPostMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('post', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    /**
     * Updates a post meta field based on the given post ID.
     *
     * @param null|bool $check      Whether to allow updating metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param mixed     $prevValue  Optional. Previous value to check before updating.
     *                              If specified, only update existing metadata entries with
     *                              this value. Otherwise, update all entries.
     * 
     * @return int|bool The new meta field ID if a field with the given key didn't exist
     *                  and was therefore added, true on successful update,
     *                  false on failure or if the value passed to the function
     *                  is the same as the one that is already in the database.
     */
    function updatePostMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('post', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    /**
     * Removes metadata matching criteria from a post.
     * Fires after WP meta removed
     *
     * @param string[] $metaIDs    An array of metadata entry IDs to delete.
     * @param int      $objectID   ID of the object metadata is for.
     * @param string   $metaKey    Metadata key.
     * @param mixed    $metaValue Metadata value.
     * 
     * @return void
     * 
     * @todo Check $metaValue for remove specefic value
     */
    function deletePostMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('post', $objectID, $metaKey);
    }

    /**
     * Adds a meta field to the given comment.
     *
     * @param null|bool $check      Whether to allow adding metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param bool      $unique     Whether the specified meta key should be unique for the object.
     * 
     * @return int|false The meta ID on success, false on failure.
     */
    function addCommentMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('comment', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    /**
     * Updates a comment meta field based on the given post ID.
     *
     * @param null|bool $check      Whether to allow updating metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param mixed     $prevValue  Optional. Previous value to check before updating.
     *                              If specified, only update existing metadata entries with
     *                              this value. Otherwise, update all entries.
     * 
     * @return int|bool The new meta field ID if a field with the given key didn't exist
     *                  and was therefore added, true on successful update,
     *                  false on failure or if the value passed to the function
     *                  is the same as the one that is already in the database.
     */
    function updateCommentMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('comment', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    /**
     * Removes metadata matching criteria from a comment.
     * Fires after WP meta removed
     *
     * @param string[] $metaIDs    An array of metadata entry IDs to delete.
     * @param int      $objectID   ID of the object metadata is for.
     * @param string   $metaKey    Metadata key.
     * @param mixed    $metaValue Metadata value.
     * 
     * @return void
     * 
     * @todo Check $metaValue for remove specefic value
     */
    function deleteCommentMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('comment', $objectID, $metaKey);
    }

    /**
     * Adds a meta field to the given term.
     *
     * @param null|bool $check      Whether to allow adding metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param bool      $unique     Whether the specified meta key should be unique for the object.
     * 
     * @return int|false The meta ID on success, false on failure.
     */
    function addTermMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('term', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    /**
     * Updates a term meta field based on the given term ID.
     *
     * @param null|bool $check      Whether to allow updating metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param mixed     $prevValue  Optional. Previous value to check before updating.
     *                              If specified, only update existing metadata entries with
     *                              this value. Otherwise, update all entries.
     * 
     * @return int|bool The new meta field ID if a field with the given key didn't exist
     *                  and was therefore added, true on successful update,
     *                  false on failure or if the value passed to the function
     *                  is the same as the one that is already in the database.
     */
    function updateTermMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('term', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    /**
     * Removes metadata matching criteria from a term.
     * Fires after WP meta removed
     *
     * @param string[] $metaIDs    An array of metadata entry IDs to delete.
     * @param int      $objectID   ID of the object metadata is for.
     * @param string   $metaKey    Metadata key.
     * @param mixed    $metaValue Metadata value.
     * 
     * @return void
     * 
     * @todo Check $metaValue for remove specefic value
     */
    function deleteTermMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('term', $objectID, $metaKey);
    }

    /**
     * Adds a meta field to the given user.
     *
     * @param null|bool $check      Whether to allow adding metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param bool      $unique     Whether the specified meta key should be unique for the object.
     * 
     * @return int|false The meta ID on success, false on failure.
     */
    function addUserMeta($check, $objectID, $metaKey, $metaValue, $unique)
    {
        return $this->addMeta('user', $check, $objectID, $metaKey, $metaValue, $unique);
    }

    /**
     * Updates a user meta field based on the given user ID.
     *
     * @param null|bool $check      Whether to allow updating metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param mixed     $prevValue  Optional. Previous value to check before updating.
     *                              If specified, only update existing metadata entries with
     *                              this value. Otherwise, update all entries.
     * 
     * @return int|bool The new meta field ID if a field with the given key didn't exist
     *                  and was therefore added, true on successful update,
     *                  false on failure or if the value passed to the function
     *                  is the same as the one that is already in the database.
     */
    function updateUserMeta($check, $objectID, $metaKey, $metaValue, $prevValue)
    {
        return $this->updateMeta('user', $check, $objectID, $metaKey, $metaValue, $prevValue);
    }

    /**
     * Removes metadata matching criteria from a user.
     * Fires after WP meta removed
     *
     * @param string[] $metaIDs    An array of metadata entry IDs to delete.
     * @param int      $objectID   ID of the object metadata is for.
     * @param string   $metaKey    Metadata key.
     * @param mixed    $metaValue Metadata value.
     * 
     * @return void
     * 
     * @todo Check $metaValue for remove specefic value
     */
    function deleteUserMeta($metaIDs, $objectID, $metaKey, $metaValue)
    {
        $this->deleteMeta('user', $objectID, $metaKey);
    }

    /**
     * Retrieves raw metadata value for the specified object.
     *
     * @param mixed  $value     The value to return, either a single metadata value or an array
     *                          of values depending on the value of `$single`. Default null.
     * @param int    $objectID  ID of the object metadata is for.
     * @param string $metaKey   Metadata key.
     * @param bool   $single    Whether to return only the first value of the specified `$metaKey`.
     * @param string $metaType Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user',
     *                          or any other object type with an associated meta table.
     * 
     * @return mixed An array of values if `$single` is false.
     *               The value of the meta field if `$single` is true.
     *               False for an invalid `$objectID` (non-numeric, zero, or negative value),
     *               or if `$metaType` is not specified.
     *               Null if the value does not exist.
     */
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

    /**
     * Adds metadata for the specified object. 
     *
     * @param string    $metaType     Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user',
     *                              or any other object type with an associated meta table.
     * @param null|bool $check      Whether to allow adding metadata for the given type.
     * @param int       $objectID   ID of the object metadata is for.
     * @param string    $metaKey    Metadata key.
     * @param mixed     $metaValue  Metadata value. Must be serializable if non-scalar.
     * @param bool      $unique     Whether the specified meta key should be unique for the object.
     * 
     * @return int|false The meta ID on success, false on failure.
     */
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

    /**
     * Updates metadata for the specified object. If no value already exists for the specified object
     * ID and metadata key, the metadata will be added.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @param string $metaType  Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user',
     *                           or any other object type with an associated meta table.
     * @param null|bool $check      Whether to allow updating metadata for the given type.
     * @param int    $objectID  ID of the object metadata is for.
     * @param string $metaKey   Metadata key.
     * @param mixed  $metaValue Metadata value. Must be serializable if non-scalar.
     * @param mixed  $prevValue Optional. Previous value to check before updating.
     *                           If specified, only update existing metadata entries with
     *                           this value. Otherwise, update all entries. Default empty.
     * 
     * @return int|bool The new meta field ID if a field with the given key didn't exist
     *                  and was therefore added, true on successful update,
     *                  false on failure or if the value passed to the function
     *                  is the same as the one that is already in the database.
     */
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

    /**
     * Deletes metadata for the specified object.
     *
     * @param string   $metaType   Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user',
     *                             or any other object type with an associated meta table.
     * @param int      $objectID   ID of the object metadata is for.
     * @param string   $metaKey    Metadata key.
     * 
     * @return boolean|int     
     * 
     * @todo Check delete_metadata parameter in WP method    
     */
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
