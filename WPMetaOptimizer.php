<?php

/**
 * Plugin Name: WP Meta Optimizer
 * Version: 1.0
 * Plugin URI: https://parsa.ws
 * Description: Optimize Post Meta Table
 * Author: Parsa Kafi
 * Author URI: https://parsa.ws
 */


// CREATE TABLE `wp_fa`.`wp_postmeta_optimze` ( `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT , `post_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0' , PRIMARY KEY (`meta_id`)) ENGINE = InnoDB;

class WPMetaOptimizer
{
    protected $option_key = 'MyPlugin-Key';
    protected $now, $pluginPostTable;

    protected $intTypes =  ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'],
        $floatTypes = ['FLOAT', 'DOUBLE', 'DECIMAL'],
        $charTypes = ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'],
        $dateTypes = ['DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR'];

    function __construct()
    {
        global $wpdb;
        $this->pluginPostTable = $wpdb->postmeta . '_optimize';
        $this->now = current_time('mysql');
        $actionPriority = 99999999;

        add_action('add_post_meta', [$this, 'addPostMeta'], $actionPriority, 3);
        add_action('update_post_meta', [$this, 'updatePostMeta'], $actionPriority, 4);
        add_filter('get_post_metadata', [$this, 'getPostMeta'], $actionPriority, 5);

        add_action('admin_menu', array($this, 'menu'));
    }

    function getPostMeta($value, $objectID, $metaKey, $single, $metaType)
    {
        global $wpdb;
        $row = $wpdb->get_row("SELECT $metaKey FROM $this->pluginPostTable WHERE post_id = $objectID", ARRAY_A);

        if (isset($row[$metaKey])) {
            $fieldType = $this->getTableColumnType($this->pluginPostTable, $metaKey);
            if (in_array($fieldType, $this->intTypes))
                $row[$metaKey] = intval($row[$metaKey]);
        }

        return isset($row[$metaKey]) ? $row[$metaKey] : $value;
    }

    function updatePostMeta($metaID, $objectID, $metaKey, $metaValue)
    {
        $addTableColumn = $this->addTableColumn($this->pluginPostTable, $metaKey, $metaValue);
        
        if ($addTableColumn) {
            $this->insertPostMeta($objectID, $metaKey, $metaValue);
        }
    }

    function addPostMeta($objectID, $metaKey, $metaValue)
    {
        $addTableColumn = $this->addTableColumn($this->pluginPostTable, $metaKey, $metaValue);
        
        if ($addTableColumn) {
            $this->insertPostMeta($objectID, $metaKey, $metaValue);
        }
    }

    private function insertPostMeta($objectID, $metaKey, $metaValue)
    {
        global $wpdb;

        $checkInserted = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->pluginPostTable} WHERE post_id = %d",
                $objectID
            )
        );

        if (is_bool($metaValue))
            $metaValue = intval($metaValue);

        $metaValue = maybe_serialize($metaValue);

        if ($checkInserted) {
            $wpdb->update(
                $this->pluginPostTable,
                [$metaKey => $metaValue, 'updated_at' => $this->now],
                ['post_id' => $objectID,]
            );
        } else {
            $wpdb->insert(
                $this->pluginPostTable,
                [
                    'post_id' => $objectID,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    $metaKey => $metaValue
                ]
            );
        }
    }

    private function addTableColumn($table, $field, $metaValue)
    {
        global $wpdb;
        $addTableColumn  = true;
        $collate  = '';

        $value = maybe_serialize($metaValue);
        $columnType = $this->getFieldType($value);
        $valueLength = mb_strlen($value);

        if (in_array($columnType, $this->charTypes))
            $collate = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';

        if ($this->checkColumnExists($table, $field)) {
            $currentFieldMaxLengthValue = intval($wpdb->get_var("SELECT MAX(LENGTH({$field})) as length FROM {$table}"));

            $currentColumnType = $this->getTableColumnType($table, $field);
            $newColumnType = $this->getNewColumnType($currentColumnType, $columnType);

            if ($newColumnType == 'VARCHAR')
                if ($currentFieldMaxLengthValue >= $valueLength  && $currentColumnType === 'VARCHAR')
                    return $addTableColumn;
                else
                    $newColumnType = 'VARCHAR(' . ($valueLength > $currentFieldMaxLengthValue ? $valueLength : $currentFieldMaxLengthValue) . ')';
            elseif ($newColumnType == $currentColumnType)
                return $addTableColumn;

            $sql = "ALTER TABLE `{$table}` CHANGE `{$field}` `{$field}` {$newColumnType} {$collate} NULL DEFAULT NULL";
        } else {
            if ($columnType == 'VARCHAR')
                $columnType = 'VARCHAR(' . $valueLength . ')';

            $sql = "ALTER TABLE `{$table}` ADD COLUMN {$field} {$columnType} {$collate} NULL AFTER `post_id`";
        }

        $addTableColumn = $wpdb->query($sql);

        return $addTableColumn;
    }

    private function checkColumnExists($table, $field)
    {
        global $wpdb;

        $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$field}'";
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
        add_options_page('WP Meta Optimizer', 'WP Meta Optimizer', 'manage_options', 'wp-meta-optimizer', array($this, 'settings_page'));
    }

    public function settings_page()
    {
        $update_message = '';
        if (isset($_POST['MyPlugin']) && wp_verify_nonce($_POST['MyPlugin'], 'settings_submit')) {
            unset($_POST['MyPlugin']);

            update_option($this->option_key, $_POST);
            $update_message = '<div class="notice notice-success is-dismissible" ><p>' . __('Settings saved.') . '</p></div> ';
        }

        $option = $this->get_option();
?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Settings</h1>

            <?php
            // update_post_meta(1, 'goal', 888);
            update_post_meta(1, 'goal', 85);
            var_dump(get_post_meta(1, 'goal', true));
            ?>
        </div>
<?php
    }

    public function get_option($key = null, $default = null)
    {
        $option = get_option($this->option_key);
        if ($key != null)
            $option = $option[$key] ?? $default;

        return $option;
    }

    public static function install()
    {
        global $wpdb;

        $postMetaOpimizeTable =  $wpdb->postmeta . '_optimize';

        if ($wpdb->get_var("show tables like '$postMetaOpimizeTable'") != $postMetaOpimizeTable) {
            $sql = "CREATE TABLE `{$postMetaOpimizeTable}` (
                  `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `post_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                   PRIMARY KEY (`meta_id`),
                   UNIQUE KEY `post_id` (`post_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            require_once(ABSPATH .
                str_replace('/', DIRECTORY_SEPARATOR, '/wp-admin/includes/upgrade.php'));

            dbDelta($sql);
        }
    }
}


new WPMetaOptimizer();
register_activation_hook(__FILE__, array('WPMetaOptimizer', 'install'));
