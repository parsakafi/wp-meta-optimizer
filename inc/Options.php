<?php

namespace WPMetaOptimizer;

class Options extends Base
{
    public static $instance = null;

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
        $currentTab = 'tables';
        if (isset($_POST[WPMETAOPTIMIZER_PLUGIN_KEY])) {
            if (wp_verify_nonce($_POST[WPMETAOPTIMIZER_PLUGIN_KEY], 'settings_submit')) {
                $currentTab = $_POST['current_tab'];
                unset($_POST[WPMETAOPTIMIZER_PLUGIN_KEY]);
                unset($_POST['current_tab']);

                $options = $this->getOption(null, [], false);
                foreach ($_POST as $key => $value) {
                    $options[$key] = $value;
                }

                update_option($this->optionKey, $options);
                $update_message = $this->getNoticeMessageHTML(__('Settings saved.'));

                // Reset Import
                foreach ($this->tables as $type => $table) {
                    if (isset($_POST['reset_import_' . $type]))
                        $this->setOption('import_' . $type . '_latest_id', null);
                }
            }
        }

        $options = $this->getOption(null, [], false);

        $postTypes = get_post_types([
            'public'              => true,
            'show_ui'             => true
        ], "objects");

        $metaSaveTypes = $this->getOption('meta_save_types', []);
?>
        <div class="wrap wpmo-wrap">
            <h1 class="wp-heading-inline"><?php echo WPMETAOPTIMIZER_PLUGIN_NAME ?></h1>
            <?php echo $update_message; ?>

            <div class="nav-tab-wrapper">
                <a id="tables-tab" class="wpmo-tab nav-tab <?php echo $currentTab == 'tables' ? 'nav-tab-active' : '' ?>"><?php _e('Tables', WPMETAOPTIMIZER_PLUGIN_KEY) ?></a>
                <a id="settings-tab" class="wpmo-tab nav-tab <?php echo $currentTab == 'settings' ? 'nav-tab-active' : '' ?>"><?php _e('Settings') ?></a>
                <a id="import-tab" class="wpmo-tab nav-tab <?php echo $currentTab == 'import' ? 'nav-tab-active' : '' ?>"><?php _e('Import', WPMETAOPTIMIZER_PLUGIN_KEY) ?></a>
            </div>

            <div id="tables-tab-content" class="wpmo-tab-content <?php echo $currentTab != 'tables' ? 'hidden' : '' ?>">
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
                                    $checkInBlackList = Helpers::getInstance()->checkInBlackWhiteList($type, $column);
                                    if ($checkInBlackList) {
                                        $listActionTitle = __('Remove from black list', WPMETAOPTIMIZER_PLUGIN_KEY);
                                        $listAction = 'remove';
                                    } else {
                                        $listActionTitle = __('Add to black list', WPMETAOPTIMIZER_PLUGIN_KEY);
                                        $listAction = 'insert';
                                    }
                                    echo "<tr class='" . ($checkInBlackList ? 'black-list-column' : '') . "'><td>{$c}</td><td class='column-name'><span>{$column}</span></td><td class='change-icons'>";
                                    echo "<span class='dashicons dashicons-edit rename-table-column tooltip-title' title='" . __('Rename', WPMETAOPTIMIZER_PLUGIN_KEY) . "' data-type='{$type}' data-column='{$column}'></span>";
                                    echo "<span class='dashicons dashicons-trash delete-table-column tooltip-title' title='" . __('Delete') . "' data-type='{$type}' data-column='{$column}'></span>";
                                    echo "<span span class='dashicons dashicons-{$listAction} add-remove-black-list tooltip-title' title='{$listActionTitle}' data-action='{$listAction}' data-type='{$type}' data-column='{$column}'></span>";
                                    echo "</td></tr>";
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

            <div id="settings-tab-content" class="wpmo-tab-content <?php echo $currentTab != 'settings' ? 'hidden' : '' ?>">
                <form action="" method="post">
                    <input type="hidden" name="current_tab" value="settings">
                    <?php wp_nonce_field('settings_submit', WPMETAOPTIMIZER_PLUGIN_KEY, false); ?>
                    <table>
                        <tbody>
                            <tr>
                                <td><?php _e('Save meta for', WPMETAOPTIMIZER_PLUGIN_KEY) ?></td>
                                <td>
                                    <input type="hidden" name="meta_save_types[hidden]" value="1">
                                    <?php
                                    foreach ($this->tables as $type => $table) {
                                    ?>
                                        <label><input type="checkbox" name="meta_save_types[<?php echo $type ?>]" value="1" <?php checked(isset($metaSaveTypes[$type])) ?>> <?php echo $table['name'] ?></label>
                                    <?php
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><?php _e('Don\'t saving Meta in the default tables', WPMETAOPTIMIZER_PLUGIN_KEY) ?></td>
                                <td>
                                    <input type="hidden" name="default_meta_save[hidden]" value="1">
                                    <?php
                                    $defaultMetaSave = $this->getOption('default_meta_save', []);
                                    foreach ($this->tables as $type => $table) {
                                    ?>
                                        <label><input type="checkbox" name="default_meta_save[<?php echo $type ?>]" value="1" <?php checked(isset($defaultMetaSave[$type])) ?>> <?php echo $table['name'] ?></label>
                                    <?php
                                    }
                                    ?>
                                    <p class="description">
                                        <?php _e('If you want the Meta not to be saved in the default tables, you can select the Meta types.', WPMETAOPTIMIZER_PLUGIN_KEY) ?>
                                        <a href="https://developer.wordpress.org/plugins/metadata/" target="_blank">
                                            <?php _e('More information', WPMETAOPTIMIZER_PLUGIN_KEY) ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td><?php _e('Post Types', WPMETAOPTIMIZER_PLUGIN_KEY) ?></td>
                                <td>
                                    <input type="hidden" name="post_types[hidden]" value="1">
                                    <?php
                                    $postTypesOption = $this->getOption('post_types', []);
                                    foreach ($postTypes as $post_type) {
                                        echo '<label><input type="checkbox" name="post_types[' . $post_type->name . ']" value="1" ' .
                                            checked($postTypesOption[$post_type->name] ?? 0, 1, false) .  (isset($metaSaveTypes['post']) ? '' : ' disabled') . '/>' . $post_type->label . '</label> &nbsp;';
                                    }
                                    ?>
                                    <br>
                                    <p class="description"><?php _e('Select post types you want to save meta fields.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <?php _e('Black/White list', WPMETAOPTIMIZER_PLUGIN_KEY) ?>
                                </th>
                                <td colspan="2">
                                    <?php _e('Set White/Black list for custom meta fields', WPMETAOPTIMIZER_PLUGIN_KEY) ?>
                                    <p class="description"><?php _e('Write each item on a new line', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Type') ?></th>
                                <th><?php _e('White List', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                                <th><?php _e('Black List', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                            </tr>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($this->tables as $type => $table) {
                            ?>
                                <tr>
                                    <td><?php echo $table['title'] ?></td>
                                    <td>
                                        <textarea name="<?php echo $type ?>_white_list" id="<?php echo $type ?>_white_list" cols="40" rows="7" class="ltr" placeholder="custom_field_name" <?php echo isset($metaSaveTypes[$type]) ? '' : ' disabled' ?>><?php echo $this->getOption($type . '_white_list', '') ?></textarea>
                                    </td>
                                    <td>
                                        <textarea name="<?php echo $type ?>_black_list" id="<?php echo $type ?>_black_list" cols="40" rows="7" class="ltr" placeholder="custom_field_name" <?php echo isset($metaSaveTypes[$type]) ? '' : ' disabled' ?>><?php echo $this->getOption($type . '_black_list', '') ?></textarea>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                            <tr>
                                <td colspan="3"><input type="submit" class="button button-primary" value="<?php _e('Save') ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>

            <div id="import-tab-content" class="wpmo-tab-content <?php echo $currentTab != 'import' ? 'hidden' : '' ?>">
                <form action="" method="post">
                    <input type="hidden" name="current_tab" value="import">
                    <?php wp_nonce_field('settings_submit', WPMETAOPTIMIZER_PLUGIN_KEY, false); ?>
                    <table>
                        <tbody>
                            <tr>
                                <th colspan="2"><?php _e('Import Post/Comment/User/Term Metas from meta tables', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                            </tr>
                            <tr>
                                <th><?php _e('Meta Tables', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                                <td>
                                    <input type="hidden" name="import[hidden]" value="1">
                                    <?php
                                    $importTables = $this->getOption('import', []);
                                    foreach ($this->tables as $type => $table) {
                                        $latestObjectID = $this->getOption('import_' . $type . '_latest_id', false);
                                        $metaTypeCanSaved = isset($metaSaveTypes[$type]);
                                    ?>
                                        <label><input type="checkbox" name="import[<?php echo $type ?>]" value="1" <?php checked(isset($importTables[$type]));
                                                                                                                    echo $metaTypeCanSaved ? '' : ' disabled' ?>> <?php echo $table['name'] ?></label> <br>
                                        <?php
                                        if ($metaTypeCanSaved && $latestObjectID) {
                                            echo '<p>';

                                            if ($latestObjectID === 'finished') {
                                                echo __('Finished', WPMETAOPTIMIZER_PLUGIN_KEY) . ', ';
                                            } elseif (is_numeric($latestObjectID)) {
                                                $objectTitle = $objectLink = false;

                                                if ($type == 'post') {
                                                    $objectTitle = get_the_title($latestObjectID);
                                                    $objectLink = get_edit_post_link($latestObjectID);
                                                } elseif ($type == 'comment') {
                                                    $comment = get_comment($latestObjectID);
                                                    $objectTitle = $comment->comment_author . ' - ' . $comment->comment_author_email;
                                                    $objectLink = get_edit_comment_link($latestObjectID);
                                                } elseif ($type == 'user') {
                                                    $user = get_userdata($latestObjectID);
                                                    $objectTitle = $user->display_name;
                                                    $objectLink = get_edit_user_link($latestObjectID);
                                                } elseif ($type == 'term') {
                                                    $term = get_term($latestObjectID);
                                                    if ($term)
                                                        $objectTitle = $term->name;
                                                    $objectLink = get_edit_term_link($latestObjectID);
                                                }

                                                if ($objectTitle && $objectLink)
                                                    echo  __('The last item checked:', WPMETAOPTIMIZER_PLUGIN_KEY) . " <a href='{$objectLink}' target='_blank'>{$objectTitle}</a>, ";
                                            }

                                            echo "<label><input type='checkbox' name='reset_import_{$type}' value='1'> " . __('Reset', WPMETAOPTIMIZER_PLUGIN_KEY) . '</label>';
                                            echo '</p>';
                                        }
                                        ?>
                                    <?php
                                        echo '<br>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p class="description">Importing runs in the background without requiring a website to be open.</p>
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

        if (!$useCache || $options === false) {
            $options = get_option($this->optionKey);
            wp_cache_set('options', $options, WPMETAOPTIMIZER_PLUGIN_KEY);
        }

        if ($key != null)
            return $options[$key] ?? $default;

        return $options ? $options : $default;
    }

    public function setOption($key, $value)
    {
        $options = $this->getOption(null, [], false);
        $options[$key] = $value;
        update_option($this->optionKey, $options);
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

    /**
     * Returns an instance of class
     * @return Options
     */
    static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new Options();

        return self::$instance;
    }
}
