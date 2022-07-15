<?php

namespace WPMetaOptimizer;

use DateTime;

class Helpers extends Base
{
    protected $Options;

    function __construct()
    {
        parent::__construct();

        $this->Options = Options::getInstance();
    }

    public function insertMeta($args)
    {
        global $wpdb;

        $args = wp_parse_args($args, [
            'metaType' => '',
            'objectID' => 0,
            'metaKey' => '',
            'metaValue' => '',
            'unique' => false,
            'prevValue' => '',
            'checkCurrentValue' => true
        ]);

        extract($args);

        if (!$objectID || empty($metaType) || empty($metaKey) || empty($metaValue))
            return false;

        $tableName = $this->getTableName($metaType);
        if (!$tableName)
            return false;

        $addTableColumn = $this->addTableColumn($tableName, $metaType, $metaKey, $metaValue);

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



        if ($checkInserted) {
            if ($checkCurrentValue) {
                $currentValue = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT {$metaKey} FROM {$tableName} WHERE {$column} = %d",
                        $objectID
                    )
                );

                if ($unique && $currentValue !== null)
                    return false;

                elseif (!$unique  && $currentValue !== null) {
                    $currentValue = maybe_unserialize($currentValue);

                    if (empty($prevValue)) {
                        if (is_array($currentValue))
                            $metaValue = array_merge($currentValue, [$metaValue]);
                        else
                            $metaValue = [$currentValue, $metaValue];
                    } else {
                        if (is_array($currentValue)) {
                            $indexValue = array_search($prevValue, $currentValue, true);
                            if ($indexValue === false)
                                return false;
                            else {
                                $currentValue[$indexValue] = $metaValue;
                                $metaValue = $currentValue;
                            }
                        } elseif ($prevValue !== $currentValue)
                            return false;
                    }
                }

                $addTableColumn = $this->addTableColumn($tableName, $metaType, $metaKey, $metaValue);
                if (!$addTableColumn)
                    return false;
            }

            $metaValue = maybe_serialize($metaValue);

            $result = $wpdb->update(
                $tableName,
                [$metaKey => $metaValue, 'updated_at' => $this->now],
                [$column => $objectID]
            );

            wp_cache_delete($objectID . '_' . $metaKey, WPMETAOPTIMIZER_PLUGIN_KEY . '_post_meta');

            return $result;
        } else {
            $metaValue = maybe_serialize($metaValue);

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

    public function deleteMetaRow($objectID, $type)
    {
        global $wpdb;
        $table = $this->getTableName($type);
        if ($table)
            return $wpdb->query("DELETE FROM {$table} WHERE {$type}_id = {$objectID}");

        return false;
    }

    public function addTableColumn($table, $type, $field, $metaValue)
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

    public function checkColumnExists($table, $field)
    {
        global $wpdb;

        // $sql = "SHOW COLUMNS FROM `{$table}` LIKE `{$field}`";
        $sql = "SHOW COLUMNS FROM `{$table}` WHERE field = '{$field}';";
        $checkColumnExists = $wpdb->query($sql);

        return $checkColumnExists;
    }

    public function getNewColumnType($currentColumnType, $valueType)
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

    public function getTableColumnType($table, $field)
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

    public function getFieldType($value)
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

    public function getTableName($type)
    {
        if (isset($this->tables[$type]))
            return $this->tables[$type]['table'];
        else
            return false;
    }

    public function checkPostType($postID)
    {
        $postType = wp_cache_get('post_type_value_' . $postID, WPMETAOPTIMIZER_PLUGIN_KEY);
        if (!$postType) {
            $postType = get_post_type($postID);
            wp_cache_set('post_type_value_' . $postID, $postType, WPMETAOPTIMIZER_PLUGIN_KEY);
        }
        $allowdPostTypes = $this->Options->getOption('post_types', []);
        $allowdPostTypes = array_keys($allowdPostTypes);
        return in_array($postType, $allowdPostTypes);
    }

    public function checkInBlackWhiteList($type, $metaKey, $listName = 'black_list')
    {
        if ($listName === 'black_list' && in_array($metaKey, $this->ignoreWPPostMetaKeys))
            return true;

        $list = $this->Options->getOption($type . '_' . $listName, '');
        if (empty($list))
            return '';

        $list = explode("\n", $list);
        $list = str_replace(["\n", "\r"], '', $list);
        return in_array($metaKey, $list);
    }

    public function getLatestObjectID($type, $latestObjectID = null)
    {
        global $wpdb;
        $primaryColumn = 'ID';
        $where = [];
        $wheres = "";

        $table = $wpdb->prefix . $type . 's';

        if (in_array($type, ['term', 'comment']))
            $primaryColumn = $type . '_ID';

        if ($latestObjectID !== null)
            $where[] = "{$primaryColumn} < {$latestObjectID}";

        if ($type === 'post') {
            $where[] = "post_status IN ('publish','future','draft','pending','private')";
            
            $allowdPostTypes = $this->Options->getOption('post_types', []);
            $allowdPostTypes = array_keys($allowdPostTypes);
            if (count($allowdPostTypes))
                $where[] = "post_type IN ('" . implode("','", $allowdPostTypes) . "')";
        }

        if (count($where))
            $wheres = "WHERE " . implode(' AND ', $where);

        $query = "SELECT {$primaryColumn} FROM {$table} {$wheres} ORDER BY {$primaryColumn} DESC LIMIT 1";
        return $wpdb->get_var($query);
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
}
