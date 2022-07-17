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

        add_action('deleted_post', [$this, 'deletePostMetas']);
        add_action('deleted_comment', [$this, 'deleteCommentMetas']);
        add_action('deleted_user', [$this, 'deleteUserMetas']);
        add_action('delete_term', [$this, 'deleteTermMetas']);

        add_filter('cron_schedules', [$this, 'addIntervalToCron']);
        add_action('init', [$this, 'initScheduler']);
        add_action('import_metas_wpmo', [$this, 'importMetas']);
        // add_action('init', [$this, 'importMetas']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    function deletePostMetas($postID)
    {
        $this->Helpers->deleteMetaRow($postID, 'post');
    }

    function deleteCommentMetas($commentID)
    {
        $this->Helpers->deleteMetaRow($commentID, 'comment');
    }

    function deleteUserMetas($commentID)
    {
        $this->Helpers->deleteMetaRow($commentID, 'user');
    }

    function deleteTermMetas($commentID)
    {
        $this->Helpers->deleteMetaRow($commentID, 'term');
    }

    function renameTableColumn()
    {
        global $wpdb;
        if (current_user_can('manage_options') && check_admin_referer('wpmo_ajax_nonce', 'nonce')) {
            $type = $_POST['type'];
            $column = sanitize_text_field($_POST['column']);
            $newColumnName = sanitize_text_field($_POST['newColumnName']);
            $collate = '';

            $table = $this->Helpers->getTableName($type);

            if ($table && $this->Helpers->checkColumnExists($table, $column) && !$this->Helpers->checkColumnExists($table, $newColumnName)) {
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
                    wp_send_json_success();
            }

            wp_send_json_error();
        }
    }

    function deleteTableColumn()
    {
        global $wpdb;
        if (current_user_can('manage_options') && check_admin_referer('wpmo_ajax_nonce', 'nonce')) {
            $type = $_POST['type'];
            $column = sanitize_text_field($_POST['column']);

            $table = $this->Helpers->getTableName($type);
            if ($table) {
                $result = $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
                if ($result)
                    wp_send_json_success();
            }

            wp_send_json_error();
        }
    }

    function importMetas()
    {
        define('IMPORT_PROCESS_WPMO', true);

        $importTables = $this->Options->getOption('import', []);
        if (is_array($importTables) && count($importTables))
            $importTables = array_keys($importTables);

        foreach ($importTables as $type) {
            if (!$this->Helpers->checkMetaType($type))
                continue;

            $latestObjectID = $this->Options->getOption('import_' . $type . '_latest_id', null);

            if ($latestObjectID === 'finished')
                continue;

            $latestObjectID = $this->Helpers->getLatestObjectID($type, $latestObjectID);

            if (!is_null($latestObjectID)) {
                $latestObjectID = intval($latestObjectID);

                $objectMetas = get_metadata($type, $latestObjectID);

                foreach ($objectMetas as $metaKey => $metaValue) {
                    if ($this->Helpers->checkInBlackWhiteList($type, $metaKey, 'black_list') === true || $this->Helpers->checkInBlackWhiteList($type, $metaKey, 'white_list') === false)
                        continue;

                    if (is_array($metaValue) && count($metaValue) === 1)
                        $metaValue = current($metaValue);

                    $this->Helpers->insertMeta(
                        [
                            'metaType' => $type,
                            'objectID' => $latestObjectID,
                            'metaKey' => $metaKey,
                            'metaValue' => $metaValue,
                            'checkCurrentValue' => false
                        ]
                    );
                }

                $this->Options->setOption('import_' . $type . '_latest_id', $latestObjectID);
            } else {
                $this->Options->setOption('import_' . $type . '_latest_id', 'finished');
            }
        }

        exit;
    }

    function initScheduler()
    {
        if (!wp_next_scheduled('import_metas_wpmo'))
            wp_schedule_event(time(), 'every_1_minutes', 'import_metas_wpmo');
    }

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

    function enqueueScripts()
    {
        wp_enqueue_style(WPMETAOPTIMIZER_PLUGIN_KEY, plugin_dir_url(dirname(__FILE__)) . 'assets/style.css', array(), '1.0', false);
        wp_enqueue_script(
            WPMETAOPTIMIZER_PLUGIN_KEY,
            plugin_dir_url(dirname(__FILE__)) . 'assets/plugin.js',
            array('jquery'),
            '1.0',
            true
        );
        wp_localize_script(WPMETAOPTIMIZER_PLUGIN_KEY, 'wpmoObject', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmo_ajax_nonce'),
            'deleteColumnMessage' => __('Are you sure you want to delete this column?', WPMETAOPTIMIZER_PLUGIN_KEY),
            'renamePromptColumnMessage' => __('Enter new column name', WPMETAOPTIMIZER_PLUGIN_KEY),
            'renameConfirmColumnMessage' => __('Are you sure you want to rename this column?', WPMETAOPTIMIZER_PLUGIN_KEY),
            'oldName' => __('Old name', WPMETAOPTIMIZER_PLUGIN_KEY),
            'newName' => __('New name', WPMETAOPTIMIZER_PLUGIN_KEY)
        ));
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
