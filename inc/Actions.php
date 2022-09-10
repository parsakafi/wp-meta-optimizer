<?php

namespace WPMetaOptimizer;

class Actions extends Base
{
    public static $instance = null;
    protected $Helpers, $Options;

    function __construct()
    {
        $this->Helpers = Helpers::getInstance();
        $this->Options = Options::getInstance();

        add_action('wp_ajax_wpmo_delete_table_column', [$this, 'deleteTableColumn']);
        add_action('wp_ajax_wpmo_rename_table_column', [$this, 'renameTableColumn']);
        add_action('wp_ajax_wpmo_add_remove_black_list', [$this, 'addRemoveBlackList']);

        add_action('deleted_post', [$this, 'deletePostMetas']);
        add_action('deleted_comment', [$this, 'deleteCommentMetas']);
        add_action('deleted_user', [$this, 'deleteUserMetas']);
        add_action('delete_term', [$this, 'deleteTermMetas']);

        add_filter('cron_schedules', [$this, 'addIntervalToCron']);
        add_action('init', [$this, 'initScheduler']);
        add_action('import_metas_wpmo', [$this, 'importMetas']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_filter('plugin_action_links_' . plugin_basename(WPMETAOPTIMIZER_PLUGIN_FILE_PATH), array($this, 'addPluginActionLinks'), 10, 4);
    }

    /**  
     * Delete post metas after delete post
     * 
     * @param int $postID   Post ID
     */
    function deletePostMetas($postID)
    {
        $this->Helpers->deleteMetaRow($postID, 'post');
    }

    /**  
     * Delete comment metas after delete comment
     * 
     * @param int $commentID   Comment ID
     */
    function deleteCommentMetas($commentID)
    {
        $this->Helpers->deleteMetaRow($commentID, 'comment');
    }

    /**  
     * Delete user metas after delete user
     * 
     * @param int $userID   User ID
     */
    function deleteUserMetas($userID)
    {
        $this->Helpers->deleteMetaRow($userID, 'user');
    }

    /**  
     * Delete term metas after delete term
     * 
     * @param int $termID   Term ID
     */
    function deleteTermMetas($termID)
    {
        $this->Helpers->deleteMetaRow($termID, 'term');
    }

    /**  
     * Add or remove meta key from black list
     */
    function addRemoveBlackList()
    {
        if (current_user_can('manage_options') && check_admin_referer('wpmo_ajax_nonce', 'nonce')) {
            $type = sanitize_text_field($_POST['type']);
            $column = sanitize_text_field($_POST['column']);
            $listAction = sanitize_text_field($_POST['list_action']);

            $table = $this->Helpers->getMetaTableName($type);
            if ($table && in_array($listAction, ['insert', 'remove'])) {
                $list = $this->Options->getOption($type . '_black_list', '');
                $list = explode("\n", $list);
                $list = str_replace(["\n", "\r"], '', $list);
                $list = array_map('trim', $list);
                $listCount = count($list);
                $listItemInex = array_search($column, $list);
                $newAction = $listAction === 'insert' ? 'remove' : 'insert';

                if ($listAction === 'insert' && $listItemInex === false)
                    $list[] = $column;
                elseif ($listAction === 'remove' && $listItemInex !== false)
                    unset($list[$listItemInex]);

                if (count($list) !== $listCount) {
                    $list = implode("\n", $list);
                    $list = trim($list, "\n");
                    $this->Options->setOption($type . '_black_list', $list);
                    wp_send_json_success(['newAction' => $newAction, 'list' => $list]);
                }
            }

            wp_send_json_error();
        }
    }

    /**  
     * Rename meta key and table column
     */
    function renameTableColumn()
    {
        global $wpdb;
        if (current_user_can('manage_options') && check_admin_referer('wpmo_ajax_nonce', 'nonce')) {
            $type          = sanitize_text_field($_POST['type']);
            $column        = $_column = wp_unslash(sanitize_text_field($_POST['column']));
            $newColumnName = $_newColumnName = wp_unslash(sanitize_text_field($_POST['newColumnName']));
            $metaTable     = sanitize_text_field($_POST['meta_table']);
            $collate = '';

            $renameOriginMetaKey = false;
            if ($metaTable == 'origin' && $this->Helpers->checkCanChangeWPMetaKey($type, $column)) {
                $checkMetaKeyExists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '%s';", $newColumnName));

                if ($checkMetaKeyExists)
                    wp_send_json_error();
                else
                    $renameOriginMetaKey = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_key = '%s' WHERE meta_key = '%s';", $newColumnName, $column));
            }

            $table = $this->Helpers->getMetaTableName($type);
            $column = $this->Helpers->translateColumnName($type, $column);
            $newColumnName = $this->Helpers->translateColumnName($type, $newColumnName);

            if (($metaTable == 'origin' && $renameOriginMetaKey || $metaTable == 'plugin') && $table && $this->Helpers->checkColumnExists($table, $type, $column) && !$this->Helpers->checkColumnExists($table, $type, $newColumnName)) {
                $currentColumnType = $this->Helpers->getTableColumnType($table, $column);

                if (in_array($currentColumnType, $this->charTypes))
                    $collate = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';

                if ($currentColumnType == 'VARCHAR') {
                    $currentFieldMaxLengthValue = intval($wpdb->get_var("SELECT MAX(LENGTH({$column})) as length FROM {$table}"));
                    $currentColumnType = 'VARCHAR(' . $currentFieldMaxLengthValue . ')';
                }

                $sql = "ALTER TABLE `{$table}` CHANGE `{$column}` `{$newColumnName}` {$currentColumnType} {$collate} NULL DEFAULT NULL";
                $result = $wpdb->query($sql);

                if ($result)
                    wp_send_json_success(['blackListAction' => $this->Helpers->checkInBlackWhiteList($type, $_newColumnName) ? 'insert' : 'remove']);
            }

            wp_send_json_error();
        }
    }

    /**  
     * Delete meta key and table column
     */
    function deleteTableColumn()
    {
        global $wpdb;
        if (current_user_can('manage_options') && check_admin_referer('wpmo_ajax_nonce', 'nonce')) {
            $type      = sanitize_text_field($_POST['type']);
            $column    = wp_unslash(sanitize_text_field($_POST['column']));
            $metaTable = sanitize_text_field($_POST['meta_table']);

            $deleteOriginMetaKey = false;
            if ($metaTable == 'origin' && $this->Helpers->checkCanChangeWPMetaKey($type, $column))
                $deleteOriginMetaKey = delete_metadata($type, null, $column, '', true);

            $table = $this->Helpers->getMetaTableName($type);
            $column = $this->Helpers->translateColumnName($type, $column);

            if (($metaTable == 'origin' && $deleteOriginMetaKey || $metaTable == 'plugin') && $table && $this->Helpers->checkColumnExists($table, $type, $column)) {
                $result = $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
                if ($result)
                    wp_send_json_success();
            }

            wp_send_json_error();
        }
    }

    /**  
     * Import meta scheduler
     */
    function importMetas()
    {
        define('IMPORT_PROCESS_WPMO', true);

        $importTables = $this->Options->getOption('import', []);
        if (isset($importTables['hidden']))
            unset($importTables['hidden']);
        if (is_array($importTables) && count($importTables))
            $importTables = array_keys($importTables);

        $importItemsNumber = intval($this->Options->getOption('import_items_number', 1));
        if (!$importItemsNumber)
            $importItemsNumber = 1;

        foreach ($importTables as $type) {
            if (!$this->Helpers->checkMetaType($type))
                continue;

            $latestObjectID = $this->Options->getOption('import_' . $type . '_latest_id', null, false);

            if ($latestObjectID === 'finished')
                continue;

            $objectID_ = $objectID = false;
            for ($c = 1; $c <= $importItemsNumber; $c++) {
                $objectID = $this->Helpers->getLatestObjectID($type, $c == 1 ? $latestObjectID : $objectID);

                if (!is_null($objectID)) {
                    $objectID = $objectID_ = intval($objectID);

                    $objectMetas = get_metadata($type, $objectID);

                    foreach ($objectMetas as $metaKey => $metaValue) {
                        if ($this->Helpers->checkInBlackWhiteList($type, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($type, $metaKey, 'white_list') === false)
                            continue;

                        $metaKey = $this->Helpers->translateColumnName($type, $metaKey);

                        if (is_array($metaValue) && count($metaValue) === 1)
                            $metaValue = current($metaValue);

                        $this->Helpers->insertMeta(
                            [
                                'metaType' => $type,
                                'objectID' => $objectID,
                                'metaKey' => $metaKey,
                                'metaValue' => $metaValue,
                                'checkCurrentValue' => false
                            ]
                        );
                    }
                } else {
                    $this->Options->setOption('import_' . $type . '_latest_id', 'finished');
                    break;
                }
            }

            if ($objectID_ && !is_null($objectID))
                $this->Options->setOption('import_' . $type . '_latest_id', $objectID_);

            $this->Options->setOption('import_' . $type . '_checked_date', date('Y-m-d H:i:s'));
        }

        $this->Helpers->activeAutomaticallySupportWPQuery();
    }

    /**  
     * Add schedule event
     */
    function initScheduler()
    {
        if (!wp_next_scheduled('import_metas_wpmo'))
            wp_schedule_event(time(), 'every_1_minutes', 'import_metas_wpmo');
    }

    /**  
     * Add custom interval to WP cron
     */
    function addIntervalToCron($schedules)
    {
        $i = 1;
        if (!isset($schedules['every_' . $i . '_minutes'])) {
            $title                                   = "Every %d Minutes";
            $schedules['every_' . $i . '_minutes'] = array(
                'interval' => $i * 60,
                'display'  => sprintf($title, $i)
            );
        }

        return $schedules;
    }

    /**  
     * Register plugin js/css
     */
    function enqueueScripts()
    {
        if (!function_exists('get_plugin_data'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $pluginData = get_plugin_data(WPMETAOPTIMIZER_PLUGIN_FILE_PATH);
        $pluginVersion = $pluginData['Version'];

        wp_enqueue_style(WPMETAOPTIMIZER_PLUGIN_KEY, plugin_dir_url(dirname(__FILE__)) . 'assets/style.css', array(), $pluginVersion, false);
        wp_enqueue_script(
            WPMETAOPTIMIZER_PLUGIN_KEY,
            plugin_dir_url(dirname(__FILE__)) . 'assets/plugin.js',
            array('jquery'),
            $pluginVersion,
            true
        );
        wp_localize_script(WPMETAOPTIMIZER_PLUGIN_KEY, 'wpmoObject', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmo_ajax_nonce'),
            'deleteColumnMessage' => __('Are you sure you want to delete this column?', WPMETAOPTIMIZER_PLUGIN_KEY),
            'deleteOriginMetaMessage' => __('Second confirmation, Are you sure you want to delete this meta?', WPMETAOPTIMIZER_PLUGIN_KEY),
            'renamePromptColumnMessage' => __('Enter new column name', WPMETAOPTIMIZER_PLUGIN_KEY),
            'renameConfirmColumnMessage' => __('Are you sure you want to rename this column?', WPMETAOPTIMIZER_PLUGIN_KEY),
            'renameConfirmOriginMetaMessage' => __('Second confirmation, Are you sure you want to rename this meta?', WPMETAOPTIMIZER_PLUGIN_KEY),
            'oldName' => __('Old name', WPMETAOPTIMIZER_PLUGIN_KEY),
            'newName' => __('New name', WPMETAOPTIMIZER_PLUGIN_KEY),
            'removeFromBlackList' => __('Remove from black list', WPMETAOPTIMIZER_PLUGIN_KEY),
            'addToBlackList' => __('Add to black list', WPMETAOPTIMIZER_PLUGIN_KEY)
        ));
    }

    /**  
     * Add action links to plugin section in WP plugins admin page 
     */
    function addPluginActionLinks($actions)
    {
        $actions[] = '<a href="' . admin_url('options-general.php?page=' . WPMETAOPTIMIZER_PLUGIN_KEY) . '">' . __('Settings', WPMETAOPTIMIZER_PLUGIN_KEY) . '</a>';
        return $actions;
    }

    /**
     * Returns an instance of class
     * @return Actions
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new Actions();

        return self::$instance;
    }
}
