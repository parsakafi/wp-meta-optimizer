<?php

/**
 * Plugin Name: WP Meta Optimizer
 * Version: 1.0
 * Plugin URI: https://parsa.ws
 * Description: Optimize Meta Tables
 * Author: Parsa Kafi
 * Author URI: https://parsa.ws
 */


define('WPMETAOPTIMIZER_PLUGIN_KEY', 'wp-meta-optimizer');
define('WPMETAOPTIMIZER_PLUGIN_NAME', 'WP Meta Optimizer');

class WPMetaOptimizer
{
    protected $optionKey = 'wp_meta_optimizer';
    protected $now, $tables,
        $intTypes =  ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'],
        $floatTypes = ['FLOAT', 'DOUBLE', 'DECIMAL'],
        $charTypes = ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'],
        $dateTypes = ['DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR'],
        $ignoreTableColumns = ['meta_id', 'created_at', 'updated_at'],
        $ignoreNativeMetaKeys = []; //['_edit_lock', '_edit_last'];

    function __construct()
    {
        global $wpdb;

        $this->now = current_time('mysql');
        $actionPriority = 99999999;

        $this->tables = array(
            'post' => [
                'table' => $wpdb->postmeta . '_wpmo',
                'title' => __('Post Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'comment' => [
                'table' => $wpdb->commentmeta . '_wpmo',
                'title' => __('Comment Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'term' => [
                'table' => $wpdb->termmeta . '_wpmo',
                'title' => __('Term Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
            'user' => [
                'table' => $wpdb->usermeta . '_wpmo',
                'title' => __('User Meta', WPMETAOPTIMIZER_PLUGIN_KEY)
            ],
        );

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

        add_action('wp_ajax_wpmo_delete_table_column', [$this, 'deleteTableColumn']);
        add_action('wp_ajax_wpmo_rename_table_column', [$this, 'renameTableColumn']);

        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
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

        if ($this->checkInBlackWhiteList($metaKey, 'black_list') === true || $this->checkInBlackWhiteList($metaKey, 'white_list') === false)
            return $value;

        $metaCache = wp_cache_get($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . "_{$metaType}_meta");

        if ($metaCache !== false)
            return $metaCache;

        $tableName = $this->getTableName($metaType);

        $sql = "SELECT `{$metaKey}` FROM `{$tableName}` WHERE {$metaType}_id = {$objectID}";
        $row = $wpdb->get_row($sql, ARRAY_A);

        if ($row && isset($row[$metaKey])) {
            $row[$metaKey] = maybe_unserialize($row[$metaKey]);

            $fieldType = $this->getTableColumnType($tableName, $metaKey);
            if (in_array($fieldType, $this->intTypes))
                $row[$metaKey] = intval($row[$metaKey]);

            wp_cache_set($objectID . '_' . $metaKey, $row[$metaKey], WPMETAOPTIMIZER_PLUGIN_KEY . "_{$metaType}_meta");
        }

        return isset($row[$metaKey]) ? $row[$metaKey] : $value;
    }

    function addMeta($metaType, $objectID, $metaKey, $metaValue)
    {
        if ($this->checkInBlackWhiteList($metaKey, 'black_list') === true || $this->checkInBlackWhiteList($metaKey, 'white_list') === false)
            return;

        $addTableColumn = $this->addTableColumn($this->getTableName($metaType), $metaType, $metaKey, $metaValue);

        if ($addTableColumn)
            $this->insertMeta($metaType, $objectID, $metaKey, $metaValue);
    }

    function updateMeta($metaType, $metaID, $objectID, $metaKey, $metaValue)
    {
        $this->addMeta($metaType, $objectID, $metaKey, $metaValue);
    }

    private function insertMeta($metaType, $objectID, $metaKey, $metaValue)
    {
        global $wpdb;
        $tableName = $this->getTableName($metaType);

        $checkInserted = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE {$metaType}_id = %d",
                $objectID
            )
        );

        if (is_bool($metaValue))
            $metaValue = intval($metaValue);

        $metaValue = maybe_serialize($metaValue);

        if ($checkInserted) {
            $wpdb->update(
                $tableName,
                [$metaKey => $metaValue, 'updated_at' => $this->now],
                ["{$metaType}_id" => $objectID]
            );

            wp_cache_delete($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . '_post_meta');
        } else {
            $wpdb->insert(
                $tableName,
                [
                    $metaType . '_id' => $objectID,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    $metaKey => $metaValue
                ]
            );
        }
    }

    private function addTableColumn($table, $type, $field, $metaValue)
    {
        global $wpdb;
        $addTableColumn = true;
        $collate = '';

        $value = maybe_serialize($metaValue);
        $columnType = $this->getFieldType($value);
        $valueLength = mb_strlen($value);

        if (in_array($columnType, $this->charTypes))
            $collate = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';

        if ($this->checkColumnExists($table, $field)) {
            $currentColumnType = $this->getTableColumnType($table, $field);
            $newColumnType = $this->getNewColumnType($currentColumnType, $columnType);

            if ($newColumnType == 'VARCHAR') {
                $currentFieldMaxLengthValue = intval($wpdb->get_var("SELECT MAX(LENGTH({$field})) as length FROM {$table}"));

                if ($currentFieldMaxLengthValue >= $valueLength  && $currentColumnType === 'VARCHAR')
                    return $addTableColumn;
                else
                    $newColumnType = 'VARCHAR(' . ($valueLength > $currentFieldMaxLengthValue ? $valueLength : $currentFieldMaxLengthValue) . ')';
            } elseif ($newColumnType == $currentColumnType)
                return $addTableColumn;

            $sql = "ALTER TABLE `{$table}` CHANGE `{$field}` `{$field}` {$newColumnType} {$collate} NULL DEFAULT NULL";
        } else {
            if ($columnType == 'VARCHAR')
                $columnType = 'VARCHAR(' . $valueLength . ')';

            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$field}` {$columnType} {$collate} NULL AFTER `{$type}_id`";
        }

        $addTableColumn = $wpdb->query($sql);

        return $addTableColumn;
    }

    private function checkColumnExists($table, $field)
    {
        global $wpdb;

        $sql = "SHOW COLUMNS FROM `{$table}` LIKE `{$field}`";
        $checkColumnExists = $wpdb->query($sql);

        return $checkColumnExists;
    }

    private function getNewColumnType($currentColumnType, $valueType)
    {
        if ($currentColumnType === $valueType)
            return $currentColumnType;
        elseif (in_array($currentColumnType, $this->intTypes) && in_array($valueType, $this->floatTypes))
            return $valueType;
        elseif (in_array($currentColumnType, $this->intTypes) && in_array($valueType, $this->charTypes))
            return $valueType;
        elseif (in_array($currentColumnType, $this->dateTypes) && in_array($valueType, $this->charTypes))
            return $valueType;
        elseif (in_array($currentColumnType, $this->intTypes) && array_search($currentColumnType, $this->intTypes) < array_search($valueType, $this->intTypes))
            return $valueType;
        elseif (in_array($currentColumnType, $this->floatTypes) && array_search($currentColumnType, $this->floatTypes) < array_search($valueType, $this->floatTypes))
            return $valueType;

        return $currentColumnType;
    }

    private function getTableColumnType($table, $field)
    {
        global $wpdb;

        $wpdb->get_results("SELECT {$field} FROM {$table} LIMIT 1");
        $columnType = $wpdb->get_col_info('type', 0);

        if ($columnType === 252)
            return 'TEXT';
        else if ($columnType === 253)
            return 'VARCHAR';
        else if ($columnType === 1)
            return 'TINYINT';
        else if ($columnType === 2)
            return 'SMALLINT';
        else if ($columnType === 9)
            return 'MEDIUMINT';
        else if ($columnType === 3)
            return 'INT';
        else if ($columnType === 8)
            return 'BIGINT';
        else if ($columnType === 4)
            return 'FLOAT';
        else if ($columnType === 5)
            return 'DOUBLE';
        else if ($columnType === 10)
            return 'DATE';
        else if ($columnType === 12)
            return 'DATETIME';
        else
            return false;
    }

    private function getFieldType($value)
    {
        $valueLength = mb_strlen($value);

        if ($this->isDate($value))
            return 'DATE';
        elseif ($this->isDateTime($value))
            return 'DATETIME';
        // elseif ($this->isJson($value))
        //     return 'LONGTEXT';
        elseif (is_string($value) && $valueLength <= 65535 || is_null($value))
            return 'VARCHAR';
        elseif (is_bool($value))
            return 'TINYINT';
        elseif (is_float($value))
            return 'FLOAT';
        elseif (is_double($value))
            return 'DOUBLE';
        elseif (is_numeric($value) && intval($value) != 0 || is_int($value) || $value == 0) {
            $value = intval($value);
            if ($value >= -128 && $value <= 127)
                return 'TINYINT';
            if ($value >= -32768 && $value <= 32767)
                return 'SMALLINT';
            if ($value >= -8388608 && $value <= 8388607)
                return 'MEDIUMINT';
            if ($value >= -2147483648 && $value <= 2147483647)
                return 'INT';
            else
                return 'BIGINT';
        } else
            return 'TEXT';
    }

    private function isJson($string)
    {
        if (!is_string($string))
            return false;
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function isDate($string)
    {
        $time = strtotime($string);

        if ($time)
            $time = DateTime::createFromFormat('Y-m-d', $string) !== false;

        return $time;
    }

    private function isDateTime($string)
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $string) !== false;
    }

    /**
     * @param string $string
     * @return bool
     */
    private function isTimestamp($string)
    {
        try {
            new DateTime('@' . $string);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function menu()
    {
        add_options_page(WPMETAOPTIMIZER_PLUGIN_NAME, WPMETAOPTIMIZER_PLUGIN_NAME, 'manage_options', WPMETAOPTIMIZER_PLUGIN_KEY, array($this, 'settings_page'));
    }

    public function settings_page()
    {
        $update_message = '';
        if (isset($_POST[WPMETAOPTIMIZER_PLUGIN_KEY])) {
            if (wp_verify_nonce($_POST[WPMETAOPTIMIZER_PLUGIN_KEY], 'settings_submit')) {
                unset($_POST[WPMETAOPTIMIZER_PLUGIN_KEY]);

                update_option($this->optionKey, $_POST);
                $update_message = $this->getNoticeMessageHTML(__('Settings saved.'));
            }
        }
?>
        <div class="wrap wpmo-wrap">
            <h1 class="wp-heading-inline"><?php echo WPMETAOPTIMIZER_PLUGIN_NAME ?></h1>
            <?php echo $update_message; ?>

            <div class="nav-tab-wrapper">
                <a id="tables-tab" class="wpmo-tab nav-tab nav-tab-active"><?php _e('Tables', WPMETAOPTIMIZER_PLUGIN_KEY) ?></a>
                <a id="settings-tab" class="wpmo-tab nav-tab"><?php _e('Settings') ?></a>
            </div>

            <div id="tables-tab-content" class="wpmo-tab-content">
                <?php
                foreach ($this->tables as $type => $table) {
                    $columns = $this->getTableColumns($table['table'], $type);
                ?>
                    <h2><?php echo $table['title'] ?></h2>
                    <p><?php _e('Rows count:', WPMETAOPTIMIZER_PLUGIN_KEY);
                        echo ' ' . $this->getTableRowsCount($table['table']); ?></p>

                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th style="width:30px">#</th>
                                <th><?php _e('Field Name', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                                <th><?php _e('Change') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $c = 1;
                            if (is_array($columns) && count($columns))
                                foreach ($columns as $column) {
                                    echo "<tr><td>{$c}</td><td class='column-name'><span>{$column}</span></td><td class='change-icons'><span class='dashicons dashicons-edit rename-table-column' title='" . __('Rename', WPMETAOPTIMIZER_PLUGIN_KEY) . "' data-type='{$type}' data-column='{$column}'></span><span class='dashicons dashicons-trash delete-table-column' title='" . __('Delete') . "' data-type='{$type}' data-column='{$column}'></span></td></tr>";
                                    $c++;
                                }
                            else
                                echo "<tr><td colspan='3'>" . __('Without custom field column', WPMETAOPTIMIZER_PLUGIN_KEY) . "</td></tr>";
                            ?>
                        </tbody>
                    </table>
                <?php
                }
                ?>
            </div>

            <div id="settings-tab-content" class="wpmo-tab-content hidden">
                <form action="" method="post">
                    <?php wp_nonce_field('settings_submit', WPMETAOPTIMIZER_PLUGIN_KEY, false); ?>
                    <table>
                        <tbody>
                            <tr>
                                <th><label for="white-list"><?php _e('White List', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label></th>
                                <td>
                                    <textarea name="white_list" id="white-list" cols="60" rows="10" class="ltr" placeholder="custom_field_name"><?php echo $this->getOption('white_list', '') ?></textarea>
                                    <p class="description"><?php _e('Write each item on a new line', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="black-list"><?php _e('Black List', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label></th>
                                <td>
                                    <textarea name="black_list" id="black-list" cols="60" rows="10" class="ltr" placeholder="custom_field_name"><?php echo $this->getOption('black_list') ?></textarea>
                                    <p class="description"><?php _e('Write each item on a new line', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                    <p class="description"><?php _e('If the blacklist is filled, the white list will be excluded.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2"><input type="submit" class="button button-primary" value="<?php _e('Save') ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
<?php
    }

    function renameTableColumn()
    {
        global $wpdb;
        if (current_user_can('manage_options') && wp_verify_nonce($_POST['nonce'], 'wpmo_ajax_nonce')) {
            $type = $_POST['type'];
            $column = $_POST['column'];
            $newColumnName = $_POST['newColumnName'];
            $collate = '';

            $table = $this->getTableName($type);

            if ($table && $this->checkColumnExists($table, $column) && !$this->checkColumnExists($table, $newColumnName)) {
                $currentColumnType = $this->getTableColumnType($table, $column);

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
        if (current_user_can('manage_options') && wp_verify_nonce($_POST['nonce'], 'wpmo_ajax_nonce')) {
            $type = $_POST['type'];
            $column = $_POST['column'];

            $table = $this->getTableName($type);
            if ($table) {
                $result = $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
                if ($result)
                    wp_send_json_success();
            }

            wp_send_json_error();
        }
    }

    private function getTableColumns($table, $type)
    {
        global $wpdb;
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
        $columns = array_map(function ($column) {
            return $column['Field'];
        }, $columns);
        return array_diff($columns, array_merge($this->ignoreTableColumns, [$type . '_id']));
    }

    private function getTableRowsCount($table)
    {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    private function getTableName($type)
    {
        if (isset($this->tables[$type]))
            return $this->tables[$type]['table'];
        else
            return false;
    }

    private function checkInBlackWhiteList($metaKey, $listName = 'black_list')
    {
        if ($listName === 'black_list' && in_array($metaKey, $this->ignoreNativeMetaKeys))
            return false;

        $list = $this->getOption($listName, '');
        if (empty($list))
            return '';

        $list = explode("\n", $list);
        $list = str_replace(["\n", "\r"], '', $list);
        return in_array($metaKey, $list);
    }

    private function getNoticeMessageHTML($message, $status = 'success')
    {
        return '<div class="notice notice-' . $status . ' is-dismissible" ><p>' . $message . '</p></div> ';
    }

    function enqueueScripts()
    {
        wp_enqueue_style(WPMETAOPTIMIZER_PLUGIN_KEY, plugin_dir_url(__FILE__) . 'assets/style.css', array(), '1.0', false);
        wp_enqueue_script(
            WPMETAOPTIMIZER_PLUGIN_KEY,
            plugin_dir_url(__FILE__) . 'assets/plugin.js',
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

    public function getOption($key = null, $default = null)
    {
        $option = get_option($this->optionKey);
        if ($key != null)
            $option = $option[$key] ?? $default;

        return $option;
    }

    public static function install()
    {
        global $wpdb;

        if (!function_exists('dbDelta'))
            require_once(ABSPATH . str_replace('/', DIRECTORY_SEPARATOR, '/wp-admin/includes/upgrade.php'));

        $tables = array(
            'post' => $wpdb->postmeta . '_wpmo',
            'comment' => $wpdb->commentmeta . '_wpmo',
            'user' => $wpdb->usermeta . '_wpmo',
            'term' => $wpdb->termmeta . '_wpmo'
        );

        foreach ($tables as $type => $table) {
            if ($wpdb->get_var("show tables like '$table'") != $table) {
                $sql = "CREATE TABLE `{$table}` (
                  `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `{$type}_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                   PRIMARY KEY (`meta_id`),
                   UNIQUE KEY `{$type}_id` (`{$type}_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

                dbDelta($sql);
            }
        }
    }
}

new WPMetaOptimizer();
register_activation_hook(__FILE__, array('WPMetaOptimizer', 'install'));
