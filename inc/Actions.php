<?php

namespace WPMetaOptimizer;

class Actions extends Base
{
    protected $Helpers;

    function __construct($Helpers)
    {
        $this->Helpers = $Helpers;

        add_action('wp_ajax_wpmo_delete_table_column', [$this, 'deleteTableColumn']);
        add_action('wp_ajax_wpmo_rename_table_column', [$this, 'renameTableColumn']);

        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
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
}
