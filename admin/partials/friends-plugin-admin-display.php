<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Friends_Plugin
 * @subpackage Friends_Plugin/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <?php wp_nonce_field( 'friends_plugin_ajax_nonce', '_ajax_nonce', false ); ?>

    <h2 class="nav-tab-wrapper">
        <a href="#tab-1" class="nav-tab nav-tab-active">Manage Links</a>
        <a href="#tab-2" class="nav-tab">Settings</a>
    </h2>

    <div id="tab-1" class="tab-content active">
        <form method="post" action="options.php">
            <?php
            // settings_fields( $this->plugin_name . '_links_options_group' );
            // do_settings_sections( $this->plugin_name . '-admin' );
            ?>
            <h3>Add New Friend Link</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Site Name *</th>
                    <td><input type="text" name="friend_name" value="" class="regular-text" required/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Site URL *</th>
                    <td><input type="url" name="friend_url" value="" class="regular-text" required/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Site Description</th>
                    <td><textarea name="friend_description" rows="3" cols="50" class="large-text"></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row">RSS Feed URL</th>
                    <td><input type="url" name="friend_rss_url" value="" class="regular-text"/></td>
                </tr>
            </table>
            <?php submit_button(__('Add Friend Link', 'friends-plugin'), 'primary', 'submit_add_friend_link'); ?>
        </form>

        <h3>Current Friend Links</h3>
        <p>Drag and drop to reorder links.</p>
        <table class="wp-list-table widefat fixed striped posts" id="friends-links-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name">Site Name</th>
                    <th scope="col" class="manage-column column-url">Site URL</th>
                    <th scope="col" class="manage-column column-description">Description</th>
                    <th scope="col" class="manage-column column-rss">RSS URL</th>
                    <th scope="col" class="manage-column column-latest-post">Latest Post</th>
                    <th scope="col" class="manage-column column-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="the-list" data-wp-lists="list:friend_link">
                <?php // Friend links will be loaded here by PHP or JavaScript ?>
                <tr id="no-items" class="no-items">
                    <td class="colspanchange" colspan="6"><?php _e('No friend links found.', 'friends-plugin'); ?></td>
                </tr>
            </tbody>
        </table>
        <p><button class="button" id="save-links-order"><?php _e('Save Links Order', 'friends-plugin'); ?></button></p>

    </div>

    <!-- Edit Link Modal -->
    <div id="edit-link-modal" class="friends-modal" style="display: none;">
        <div class="friends-modal-content">
            <div class="friends-modal-header">
                <h3>Edit Friend Link</h3>
                <span class="friends-modal-close">&times;</span>
            </div>
            <div class="friends-modal-body">
                <form id="edit-link-form">
                    <input type="hidden" id="edit_link_id" name="edit_link_id" value="" />
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Site Name *</th>
                            <td><input type="text" id="edit_friend_name" name="edit_friend_name" value="" class="regular-text" required/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Site URL *</th>
                            <td><input type="url" id="edit_friend_url" name="edit_friend_url" value="" class="regular-text" required/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Site Description</th>
                            <td><textarea id="edit_friend_description" name="edit_friend_description" rows="3" cols="50" class="large-text"></textarea></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">RSS Feed URL</th>
                            <td><input type="url" id="edit_friend_rss_url" name="edit_friend_rss_url" value="" class="regular-text"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div class="friends-modal-footer">
                <button type="button" class="button button-primary" id="save-edit-link"><?php _e('Update Link', 'friends-plugin'); ?></button>
                <button type="button" class="button" id="cancel-edit-link"><?php _e('Cancel', 'friends-plugin'); ?></button>
            </div>
        </div>
    </div>

    <div id="tab-2" class="tab-content">
        <h2>设置</h2>
        <form method="post" action="">
            <?php wp_nonce_field('friends_plugin_settings', 'friends_plugin_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">RSS 更新间隔（小时）</th>
                    <td>
                        <input type="number" name="friends_plugin_rss_update_interval" value="<?php echo esc_attr(get_option('friends_plugin_rss_update_interval', 24)); ?>" min="1" />
                        <p class="description">RSS订阅更新的时间间隔，单位为小时。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">配色模式</th>
                    <td>
                        <?php $color_mode = get_option('friends_plugin_color_mode', 'auto'); ?>
                        <fieldset>
                            <label>
                                <input type="radio" name="friends_plugin_color_mode" value="auto" <?php checked($color_mode, 'auto'); ?> />
                                自动适配系统配色
                            </label><br>
                            <label>
                                <input type="radio" name="friends_plugin_color_mode" value="light" <?php checked($color_mode, 'light'); ?> />
                                始终使用浅色模式
                            </label><br>
                            <label>
                                <input type="radio" name="friends_plugin_color_mode" value="dark" <?php checked($color_mode, 'dark'); ?> />
                                始终使用暗色模式
                            </label>
                        </fieldset>
                        <p class="description">选择友链页面的配色方案。自动适配将根据用户系统设置显示相应配色。</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit_save_settings" class="button-primary" value="保存设置" />
            </p>
        </form>

        <h2>RSS更新</h2>
        <p>手动触发所有友链的RSS订阅更新：</p>
        <p class="submit">
            <button type="button" id="fetch-rss-now" class="button">立即更新RSS</button>
        </p>
        <div id="rss-update-feedback"></div>

        <h2>导入/导出</h2>
        <div class="import-export-section">
            <div class="import-section">
                <h3>导入友链</h3>
                <p>请上传 JSON 格式的友链数据文件。</p>
                <form id="import-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('friends_plugin_import', 'friends_plugin_import_nonce'); ?>
                    <input type="file" name="import_file" accept=".json" required />
                    <p class="submit">
                        <input type="submit" name="submit_import" class="button" value="导入友链" />
                    </p>
                </form>
                <div id="import-feedback"></div>
            </div>

            <div class="export-section">
                <h3>导出友链</h3>
                <p>导出所有友链数据为 JSON 文件。</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="friends_plugin_export_links">
                    <?php wp_nonce_field('friends_plugin_export_links_nonce', 'friends_plugin_export_nonce'); ?>
                    <p class="submit">
                        <input type="submit" name="submit_export" class="button" value="导出友链" />
                    </p>
                </form>
            </div>
        </div>
    </div>

</div>