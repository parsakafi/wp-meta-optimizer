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

    /**
     * Add admin menu
     *
     * @return void
     */
    public function menu()
    {
        add_options_page(__('Meta Optimizer', WPMETAOPTIMIZER_PLUGIN_KEY), __('Meta Optimizer', WPMETAOPTIMIZER_PLUGIN_KEY), 'manage_options', WPMETAOPTIMIZER_PLUGIN_KEY, array($this, 'settingsPage'));
    }

    /**
     * Add settings page
     *
     * @return void
     */
    public function settingsPage()
    {
        $Helpers = Helpers::getInstance();
        $update_message = '';
        $currentTab = 'tables';
        if (isset($_POST[WPMETAOPTIMIZER_PLUGIN_KEY])) {
            if (wp_verify_nonce($_POST[WPMETAOPTIMIZER_PLUGIN_KEY], 'settings_submit')) {
                $currentTab = sanitize_text_field($_POST['current_tab']);
                $checkBoxList = [];
                unset($_POST[WPMETAOPTIMIZER_PLUGIN_KEY]);
                unset($_POST['current_tab']);

                $options = $this->getOption(null, [], false);
                foreach ($_POST as $key => $value)
                    $options[sanitize_key($key)] = sanitize_text_field($value);

                if ($currentTab == 'settings')
                    $checkBoxList = ['support_wp_query', 'support_wp_query_active_automatically', 'support_wp_query_deactive_while_import', 'original_meta_actions'];

                foreach ($checkBoxList as $checkbox)
                    $options[$checkbox] = isset($_POST[$checkbox]) ? sanitize_text_field($_POST[$checkbox]) : 0;

                update_option(WPMETAOPTIMIZER_OPTION_KEY, $options);
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
            'show_ui' => true
        ], "objects");

        $metaSaveTypes = $this->getOption('meta_save_types', []);
?>
        <div class="wrap wpmo-wrap">
            <h1 class="wp-heading-inline"><span class="dashicons dashicons-editor-table"></span> <?php _e('Meta Optimizer', WPMETAOPTIMIZER_PLUGIN_KEY) ?></h1>
            <?php echo $update_message; ?>

            <div class="nav-tab-wrapper">
                <a id="tables-tab" class="wpmo-tab nav-tab <?php echo $currentTab == 'tables' ? 'nav-tab-active' : '' ?>"><?php _e('Tables', WPMETAOPTIMIZER_PLUGIN_KEY) ?></a>
                <a id="settings-tab" class="wpmo-tab nav-tab <?php echo $currentTab == 'settings' ? 'nav-tab-active' : '' ?>"><?php _e('Settings') ?></a>
                <a id="import-tab" class="wpmo-tab nav-tab <?php echo $currentTab == 'import' ? 'nav-tab-active' : '' ?>"><?php _e('Import', WPMETAOPTIMIZER_PLUGIN_KEY) ?></a>
            </div>

            <div id="tables-tab-content" class="wpmo-tab-content <?php echo $currentTab != 'tables' ? 'hidden' : '' ?>">
                <?php
                foreach ($this->tables as $type => $table) {
                    $columns = $Helpers->getTableColumns($table['table'], $type);
                    sort($columns);
                ?>
                    <h2><?php echo $table['title'] ?></h2>
                    <p>
                        <?php
                        _e('Number of Columns:', WPMETAOPTIMIZER_PLUGIN_KEY);
                        echo ' ' . (is_array($columns) ? count($columns) : 0);
                        echo ' - ';
                        _e('Number of rows:', WPMETAOPTIMIZER_PLUGIN_KEY);
                        echo ' ' . $this->getTableRowsCount($table['table']);
                        ?>
                    </p>

                    <table class="wp-list-table widefat fixed striped table-view-list table-sticky-head">
                        <thead>
                            <tr>
                                <th style="width:30px">#</th>
                                <th><?php _e('Field Name', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                                <th><?php _e('Change') ?></th>
                                <?php if ($this->getOption('original_meta_actions', false) == 1) { ?>
                                    <th class="color-red"><span class="dashicons dashicons-info"></span> <abbr title="<?php echo sprintf(__("These actions directly affect the %s WordPress table and %s plugin table", WPMETAOPTIMIZER_PLUGIN_KEY), $Helpers->getWPMetaTableName($type), $Helpers->getMetaTableName($type)); ?>" class="tooltip-title"><?php _e('Change the original meta') ?></abbr></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $c = 1;
                            if (is_array($columns) && count($columns))
                                foreach ($columns as $column) {
                                    $_column = $column;
                                    $column = $Helpers->translateColumnName($type, $column);

                                    $checkInBlackList = Helpers::getInstance()->checkInBlackWhiteList($type, $column);
                                    if ($checkInBlackList) {
                                        $listActionTitle = __('Remove from black list', WPMETAOPTIMIZER_PLUGIN_KEY);
                                        $listAction = 'remove';
                                    } else {
                                        $listActionTitle = __('Add to black list', WPMETAOPTIMIZER_PLUGIN_KEY);
                                        $listAction = 'insert';
                                    }

                                    if ($_column === $column)
                                        $_column = '';

                                    echo "<tr class='" . ($checkInBlackList ? 'black-list-column' : '') . "'><td>{$c}</td><td class='column-name'><span>{$column}</span>" . ($_column ? " <abbr class='translated-column-name tooltip-title' title='" . __('The meta key was renamed because it equals the name of a reserved column.', WPMETAOPTIMIZER_PLUGIN_KEY) . "'>({$_column})</abbr>" : '') . "</td>";

                                    echo "<td class='change-icons'>";
                                    echo "<span class='dashicons dashicons-edit rename-table-column tooltip-title' title='" . __('Rename', WPMETAOPTIMIZER_PLUGIN_KEY) . "' data-type='{$type}' data-meta-table='plugin' data-column='{$column}'></span>";
                                    echo "<span class='dashicons dashicons-trash delete-table-column tooltip-title' title='" . __('Delete') . "' data-type='{$type}' data-meta-table='plugin' data-column='{$column}'></span>";
                                    echo "<span span class='dashicons dashicons-{$listAction} add-remove-black-list tooltip-title' title='{$listActionTitle}' data-action='{$listAction}' data-type='{$type}' data-meta-table='plugin' data-column='{$column}'></span>";
                                    echo "</td>";

                                    if ($this->getOption('original_meta_actions', false) == 1) {
                                        echo "<td class='change-icons'>";
                                        if ($Helpers->checkCanChangeWPMetaKey($type, $column)) {
                                            echo "<span class='dashicons dashicons-edit rename-table-column tooltip-title' title='" . __('Rename', WPMETAOPTIMIZER_PLUGIN_KEY) . "' data-type='{$type}' data-meta-table='origin' data-column='{$column}'></span>";
                                            echo "<span class='dashicons dashicons-trash delete-table-column tooltip-title' title='" . __('Delete') . "' data-type='{$type}' data-meta-table='origin' data-column='{$column}'></span>";
                                        } else {
                                            echo '---';
                                        }
                                        echo "</td>";
                                    }

                                    echo "</tr>";
                                    $c++;
                                }
                            else
                                echo "<tr><td colspan='" . ($this->getOption('original_meta_actions', false) == 1 ? 4 : 3) . "'>" . __('Without custom field column', WPMETAOPTIMIZER_PLUGIN_KEY) . "</td></tr>";
                            ?>
                        </tbody>
                    </table>
                    <br>
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
                                <th><?php _e('Support WordPress Query', WPMETAOPTIMIZER_PLUGIN_KEY) ?></th>
                                <td>
                                    <label><input type="checkbox" name="support_wp_query" id="support_wp_query" value="1" <?php checked($this->getOption('support_wp_query', false) == 1); ?> <?php disabled(!$Helpers->checkImportFinished()) ?>><?php _e('Active', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label>
                                    <label><input type="checkbox" name="support_wp_query_active_automatically" id="support_wp_query_active_automatically" value="1" <?php checked($this->getOption('support_wp_query_active_automatically', false) == 1) ?>><?php _e('Active automatically after import completed', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label>
                                    <label><input type="checkbox" name="support_wp_query_deactive_while_import" id="support_wp_query_deactive_while_import" value="1" <?php checked($this->getOption('support_wp_query_deactive_while_import', false) == 1) ?>><?php _e('Deactive while import process is run', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label>
                                    <p class="description"><span class="description-notice"><?php _e('Apply a filter to the WordPress query. You can disable this option if you experience any problems with the results of your display posts.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></span></p>
                                </td>
                            </tr>
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
                                    <input type="hidden" name="dont_save_wpmeta[hidden]" value="1">
                                    <?php
                                    $defaultMetaSave = $this->getOption('dont_save_wpmeta', []);
                                    foreach ($this->tables as $type => $table) {
                                    ?>
                                        <label><input type="checkbox" name="dont_save_wpmeta[<?php echo $type ?>]" value="1" <?php checked(isset($defaultMetaSave[$type])) ?>> <?php echo $table['name'] ?></label>
                                    <?php
                                    }
                                    ?>
                                    <p class="description">
                                        <?php _e('You can choose the Meta types if you do not want Meta saved in the default tables.', WPMETAOPTIMIZER_PLUGIN_KEY) ?>
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
                                    foreach ($postTypes as $postType) {
                                        if (!in_array($postType->name, $this->ignorePostTypes))
                                            echo '<label><input type="checkbox" name="post_types[' . $postType->name . ']" value="1" ' .
                                                checked($postTypesOption[$postType->name] ?? 0, 1, false) .  (isset($metaSaveTypes['post']) ? '' : ' disabled') . '/>' . $postType->label . '</label> &nbsp;';
                                    }
                                    ?>
                                    <br>
                                    <p class="description"><?php _e('You can save meta fields for specific post types.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="original_meta_actions"><?php _e('Actions for original meta', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label></td>
                                <td>
                                    <label><input type="checkbox" name="original_meta_actions" id="original_meta_actions" value="1" <?php checked($this->getOption('original_meta_actions', false) == 1) ?>><?php _e('Active', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label>
                                    <p class="description"><?php _e('In the plugin tables tab, display actions for original meta keys.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
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
                                <td><label for="import_items_number"><?php _e('Import items per run', WPMETAOPTIMIZER_PLUGIN_KEY) ?></label></td>
                                <td>
                                    <input type="number" name="import_items_number" id="import_items_number" class="small-text" step="1" min="1" max="10" value="<?php echo $this->getOption('import_items_number', 1) ?>" placeholder="1">
                                    <p class="description"><?php _e('The import scheduler runs every minute, and you can set the number of items to import.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
                                </td>
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
                                                                                                                    echo $metaTypeCanSaved ? '' : ' disabled' ?>> <?php echo $table['name'] . ' (' . $Helpers->getWPMetaTableName($type) . ')' ?></label> <br>
                                        <?php
                                        if ($metaTypeCanSaved && $latestObjectID) {
                                            $checkedDate = $this->getOption('import_' . $type . '_checked_date', false);
                                            $checkedDate_ = '';
                                            if ($checkedDate)
                                                $checkedDate_ = ' (' . wp_date('Y-m-d H:i:s', strtotime($checkedDate)) . ') ';

                                            echo '<p>';

                                            if ($latestObjectID === 'finished') {
                                                echo __('Finished', WPMETAOPTIMIZER_PLUGIN_KEY) . $checkedDate_ . ', ';
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
                                                    echo  __('The last item checked:', WPMETAOPTIMIZER_PLUGIN_KEY) . " <a href='{$objectLink}' target='_blank'>{$objectTitle}</a> {$checkedDate_}, ";
                                                else
                                                    echo __('Unknown item', WPMETAOPTIMIZER_PLUGIN_KEY) . " {$checkedDate_}, ";

                                                echo __('Left Items: ', WPMETAOPTIMIZER_PLUGIN_KEY) . $Helpers->getObjectLeftItemsCount($type) . ", ";
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
                                    <p class="description"><?php _e('Importing runs in the background without requiring a website to be open.', WPMETAOPTIMIZER_PLUGIN_KEY) ?></p>
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

    /**
     * Get option value
     *
     * @param string $key           Option key
     * @param mixed $default        Default value
     * @param boolean $useCache     Use cache
     * @return mixed
     */
    public function getOption($key = null, $default = null, $useCache = true)
    {
        $options = wp_cache_get('options', WPMETAOPTIMIZER_PLUGIN_KEY);

        if (!$useCache || $options === false) {
            $options = get_option(WPMETAOPTIMIZER_OPTION_KEY);
            wp_cache_set('options', $options, WPMETAOPTIMIZER_PLUGIN_KEY, WPMETAOPTIMIZER_CACHE_EXPIRE);
        }

        if ($key != null)
            return $options[$key] ?? $default;

        return $options ? $options : $default;
    }

    /**
     * Set plugin option
     *
     * @param string $key       Option key 
     * @param mixed $value      Option value
     * @return boolean
     */
    public function setOption($key, $value)
    {
        $options = $this->getOption(null, [], false);
        $options[$key] = $value;
        return update_option(WPMETAOPTIMIZER_OPTION_KEY, $options);
    }

    /**
     * Get table rows count
     *
     * @param string $table         Table name
     * @return int
     */
    private function getTableRowsCount($table)
    {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get notice message HTML
     *
     * @param string $message       Message text
     * @param string $status        Message status text
     * @return string
     */
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
