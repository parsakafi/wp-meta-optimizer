<?php

namespace WPMetaOptimizer;

class Options extends Base
{
    function __construct()
    {
        parent::__construct();
        add_action('admin_menu', array($this, 'menu'));
    }

    public function menu()
    {
        add_options_page(WPMETAOPTIMIZER_PLUGIN_NAME, WPMETAOPTIMIZER_PLUGIN_NAME, 'manage_options', WPMETAOPTIMIZER_PLUGIN_KEY, array($this, 'settingsPage'));
    }

    public function settingsPage()
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
                                    <textarea name="white_list" id="white-list" cols="60" rows="10" class="ltr" placeholder="custom_field_name"><?php echo $this->getOption('white_list', '', false) ?></textarea>
                                    <p class="description"><?php _e('Write each item on a new line', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="black-list"><?php _e('Black List', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label></th>
                                <td>
                                    <textarea name="black_list" id="black-list" cols="60" rows="10" class="ltr" placeholder="custom_field_name"><?php echo $this->getOption('black_list', '', false) ?></textarea>
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

    public function getOption($key = null, $default = null, $useCache = true)
    {
        $options = wp_cache_get('options', WPMETAOPTIMIZER_PLUGIN_KEY);

        if (!$useCache || !$options) {
            $options = get_option($this->optionKey);
            wp_cache_set('options', $options, WPMETAOPTIMIZER_PLUGIN_KEY);
        }

        if ($key != null)
            return $options[$key] ?? $default;

        return $options;
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

    private function getNoticeMessageHTML($message, $status = 'success')
    {
        return '<div class="notice notice-' . $status . ' is-dismissible" ><p>' . $message . '</p></div> ';
    }
}
