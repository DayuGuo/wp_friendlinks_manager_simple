(function( $ ) {
    'use strict';

    $(function() {
        
        // 通用通知函数
        function showNotice(message, type) {
            type = type || 'info';
            var noticeClass = 'notice notice-' + type;
            var $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
            
            // 移除旧通知
            $('.notice.friends-plugin-notice').remove();
            
            // 添加新通知
            $notice.addClass('friends-plugin-notice');
            $('.wrap h1').after($notice);
            
            // 添加关闭按钮功能
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.remove();
            });
            
            // 3秒后自动消失（除了错误消息）
            if (type !== 'error') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        }

        // Tabs functionality
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();
            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');

            $(this).addClass('nav-tab-active');
            $($(this).attr('href')).addClass('active');
        });

        // Make table sortable
        $('#friends-links-table tbody').sortable({
            items: 'tr:not(.no-items)',
            cursor: 'move',
            axis: 'y',
            handle: '.column-name',
            update: function(event, ui) {
                // console.log('Order updated, ready to save');
            }
        }).disableSelection();

        // Image uploader
        $(document).on('click', '.upload_image_button', function(e) {
            e.preventDefault();
            var button = $(this);
            var image_input = $(this).prev('input[type="url"]');

            var custom_uploader = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $(image_input).val(attachment.url);
            }).open();
        });

        // Load links on page load (Placeholder - will be implemented with AJAX)
        load_friend_links(); 

        // Add friend link (Placeholder - will be implemented with AJAX)
        $('input[name="submit_add_friend_link"]').on('click', function(e){
            e.preventDefault();
            // Basic validation
            var name = $('input[name="friend_name"]').val();
            var url = $('input[name="friend_url"]').val();

            if(!name || !url){
                showNotice('Site Name and Site URL are required.', 'error');
                return;
            }
            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'add_link',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce,
                    name: name,
                    url: url,
                    description: $('textarea[name="friend_description"]').val(),
                    rss_url: $('input[name="friend_rss_url"]').val()
                },
                success: function(response) {
                    if(response.success){
                        showNotice('Link added successfully!', 'success');
                        $('input[name="friend_name"]').val('');
                        $('input[name="friend_url"]').val('');
                        $('textarea[name="friend_description"]').val('');
                        $('input[name="friend_rss_url"]').val('');
                        load_friend_links();
                    } else {
                        var errorMessage = 'Unknown error';
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        showNotice('Error: ' + errorMessage, 'error');
                    }
                },
                error: function(errorThrown){
                    showNotice('AJAX error: ' + errorThrown, 'error');
                }
            });
        });

        // Save links order (Placeholder - will be implemented with AJAX)
        $('#save-links-order').on('click', function(){
            var new_order = [];
            $('#friends-links-table tbody tr:not(.no-items)').each(function(index, elem){
                new_order.push($(elem).data('id')); // Assuming each tr has data-id attribute
            });
            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_order',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce,
                    order: new_order
                },
                success: function(response) {
                    if(response.success){
                        showNotice('Order saved successfully!', 'success');
                        load_friend_links(); // Reload to reflect new order if backend sorts by it
                    } else {
                        var errorMessage = 'Unknown error';
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        showNotice('Error: ' + errorMessage, 'error');
                    }
                },
                error: function(errorThrown){
                    showNotice('AJAX error: ' + errorThrown, 'error');
                }
            });
        });

        // Delete link (Placeholder - will be implemented with AJAX)
        $(document).on('click', '.delete-link', function(e){
            e.preventDefault();
            if(confirm('Are you sure you want to delete this link?')){
                var link_id = $(this).data('id');
                $.ajax({
                    url: friends_plugin_ajax_obj.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'delete_link',
                        _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce,
                        id: link_id
                    },
                    success: function(response) {
                        if(response.success){
                            showNotice('Link deleted successfully!', 'success');
                            load_friend_links();
                        } else {
                            showNotice('Error: ' + (response.data.message || response.data), 'error');
                        }
                    },
                    error: function(errorThrown){
                        showNotice('AJAX error: ' + errorThrown, 'error');
                    }
                });
            }
        });

        // Edit link - Open modal with current data
        $(document).on('click', '.edit-link', function(e){
            e.preventDefault();
            var link_id = $(this).data('id');
            var $row = $(this).closest('tr');
            
            // Get current data from the table row
            var name = $row.find('.column-name').text().trim();
            var url = $row.find('.column-url a').attr('href');
            var description = $row.find('.column-description').text().trim();
            var rss_url = $row.find('.column-rss').text().trim();
            if (rss_url === '-') rss_url = '';
            
            // We need to get the icon URL via AJAX since it's not displayed in the table
            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_link_data',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce,
                    id: link_id
                },
                success: function(response) {
                    if(response.success){
                        var link_data = response.data;
                        // Populate the edit form
                        $('#edit_link_id').val(link_data.id);
                        $('#edit_friend_name').val(link_data.name);
                        $('#edit_friend_url').val(link_data.url);
                        $('#edit_friend_icon_url').val(link_data.icon_url);
                        $('#edit_friend_description').val(link_data.description);
                        $('#edit_friend_rss_url').val(link_data.rss_url);
                        
                        // Show the modal
                        $('#edit-link-modal').show();
                    } else {
                        showNotice('Error loading link data: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(errorThrown){
                    showNotice('AJAX error: ' + errorThrown, 'error');
                }
            });
        });
        
        // Close modal
        $(document).on('click', '.friends-modal-close, #cancel-edit-link', function(){
            $('#edit-link-modal').hide();
        });
        
        // Close modal when clicking outside
        $(document).on('click', '.friends-modal', function(e){
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Save edited link
        $('#save-edit-link').on('click', function(){
            var link_id = $('#edit_link_id').val();
            var name = $('#edit_friend_name').val();
            var url = $('#edit_friend_url').val();
            var description = $('#edit_friend_description').val();
            var rss_url = $('#edit_friend_rss_url').val();
            
            // Basic validation
            if(!name || !url){
                showNotice('Site Name and Site URL are required.', 'error');
                return;
            }
            
            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_link',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce,
                    id: link_id,
                    name: name,
                    url: url,
                    description: description,
                    rss_url: rss_url
                },
                success: function(response) {
                    if(response.success){
                        showNotice('Link updated successfully!', 'success');
                        $('#edit-link-modal').hide();
                        load_friend_links();
                    } else {
                        var errorMessage = 'Unknown error';
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        showNotice('Error: ' + errorMessage, 'error');
                    }
                },
                error: function(errorThrown){
                    showNotice('AJAX error: ' + errorThrown, 'error');
                }
            });
        });

        // Save settings (Placeholder - will be implemented with AJAX)
         $('input[name="submit_save_settings"]').on('click', function(e){
            e.preventDefault();
            var interval = $('input[name="friends_plugin_rss_update_interval"]').val();
            var colorMode = $('input[name="friends_plugin_color_mode"]:checked').val();
            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_settings',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce,
                    interval: interval,
                    color_mode: colorMode
                },
                success: function(response) {
                    if(response.success){
                        showNotice('Settings saved successfully!', 'success');
                    } else {
                        var errorMessage = 'Unknown error';
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        showNotice('Error: ' + errorMessage, 'error');
                    }
                },
                error: function(errorThrown){
                    showNotice('AJAX error: ' + errorThrown, 'error');
                }
            });
        });

        // Fetch RSS Now
        $('#fetch-rss-now').on('click', function(){
            var $feedbackDiv = $('#rss-update-feedback');
            $feedbackDiv.html(friendsPluginAjax.processingText || 'Processing...').removeClass('success error').show();

            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'fetch_rss',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message;
                        if (response.data.errors && response.data.errors.length > 0) {
                            message += '<br/><strong>Detailed Errors:</strong><ul>';
                            $.each(response.data.errors, function(index, error_info) {
                                message += '<li>Link ID ' + error_info.id + ' (RSS: ' + error_info.rss_url + '): ' + error_info.error + '</li>';
                            });
                            message += '</ul>';
                            $feedbackDiv.html(message).addClass('error'); // Still show as error if there are any failures
                        } else {
                            $feedbackDiv.html(message).addClass('success');
                        }
                    } else {
                        $feedbackDiv.html('Error: ' + (response.data || 'Unknown error occurred.')).addClass('error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $feedbackDiv.html('AJAX Error: ' + textStatus + ' - ' + errorThrown).addClass('error');
                }
            });
        });

        // Function to load friend links (Placeholder - will be implemented with AJAX)
        function load_friend_links(){
            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_links',
                    _ajax_nonce: friends_plugin_ajax_obj._ajax_nonce
                },
                success: function(response){
                    var tbody = $('#friends-links-table tbody');
                    tbody.empty(); // Clear existing rows
                    if(response.success && response.data.length > 0){
                        $.each(response.data, function(index, link) {
                            // Format latest post information
                            var latestPostHtml = '-';
                            if (link.latest_post_title && link.latest_post_url) {
                                var postTitle = $('<div>').text(link.latest_post_title).html();
                                var postDate = link.latest_post_date ? new Date(link.latest_post_date).toLocaleDateString() : '';
                                latestPostHtml = '<a href="' + link.latest_post_url + '" target="_blank" title="' + postTitle + '">' + 
                                                postTitle.substring(0, 30) + (postTitle.length > 30 ? '...' : '') + '</a>' +
                                                (postDate ? '<br><small>' + postDate + '</small>' : '');
                            }
                            
                            var row = '<tr data-id="' + link.id + '">' +
                                        '<td class="column-name">' + $('<div>').text(link.name).html() + 
                                        (link.latest_post_date && isInactive(link.latest_post_date) ? ' 🕊️' : '') + 
                                        '</td>' +
                                        '<td class="column-url"><a href="' + link.url + '" target="_blank">' + $('<div>').text(link.url).html() + '</a></td>' +
                                        '<td class="column-description">' + $('<div>').text(link.description).html() + '</td>' +
                                        '<td class="column-rss">' + (link.rss_url ? $('<div>').text(link.rss_url).html() : '-') + '</td>' +
                                        '<td class="column-latest-post">' + latestPostHtml + '</td>' +
                                        '<td class="column-actions">' +
                                            '<a href="#" class="edit-link" data-id="' + link.id + '">Edit</a> | ' +
                                            '<a href="#" class="delete-link" data-id="' + link.id + '">Delete</a>' +
                                        '</td>' +
                                      '</tr>';
                            tbody.append(row);
                        });
                    } else if (response.success && response.data.length === 0) {
                         tbody.append('<tr id="no-items" class="no-items"><td class="colspanchange" colspan="6">' + (friendsPluginAjax.noLinksText || 'No friend links found.') + '</td></tr>');
                    } else {
                        tbody.append('<tr id="no-items" class="no-items"><td class="colspanchange" colspan="6">' + (friendsPluginAjax.errorLoadingText || 'Error loading links:') + ' '+ (response.data || (friendsPluginAjax.unknownErrorText || 'Unknown error')) +'</td></tr>');
                    }
                },
                error: function(errorThrown){
                     $('#friends-links-table tbody').empty().append('<tr id="no-items" class="no-items"><td class="colspanchange" colspan="6">' + (friendsPluginAjax.ajaxErrorText || 'AJAX error loading links:') + ' '+errorThrown+'</td></tr>');
                }
            });
            // Example of populating:
            /*
            var tbody = $('#friends-links-table tbody');
            tbody.empty(); // Clear existing rows
            if (links.length === 0) {
                tbody.append('<tr id="no-items" class="no-items"><td class="colspanchange" colspan="5">No friend links found.</td></tr>');
            } else {
                $.each(links, function(index, link) {
                    var row = '<tr data-id="' + link.id + '">' +
                                '<td class="column-name">' + link.name + '</td>' +
                                '<td class="column-url"><a href="' + link.url + '" target="_blank">' + link.url + '</a></td>' +
                                '<td class="column-description">' + link.description + '</td>' +
                                '<td class="column-rss">' + (link.rss_url ? link.rss_url : '-') + '</td>' +
                                '<td class="column-actions">' +
                                    '<a href="#" class="edit-link" data-id="' + link.id + '">Edit</a> | ' +
                                    '<a href="#" class="delete-link" data-id="' + link.id + '">Delete</a>' +
                                '</td>' +
                              '</tr>';
                    tbody.append(row);
                });
            }
            */
        }

        // Function to check if a feed is inactive (no updates for 6 months)
        function isInactive(dateString) {
            if (!dateString) return false;
            var lastUpdate = new Date(dateString);
            var sixMonthsAgo = new Date();
            sixMonthsAgo.setMonth(sixMonthsAgo.getMonth() - 6);
            return lastUpdate < sixMonthsAgo;
        }

        // 处理导入功能
        $('#import-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'import_links');
            formData.append('_ajax_nonce', friends_plugin_ajax_obj._ajax_nonce);

            $.ajax({
                url: friends_plugin_ajax_obj.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var results = response.data;
                        var message = '导入完成：成功导入 ' + results.success + ' 个友链';
                        if (results.failed > 0) {
                            message += '，失败 ' + results.failed + ' 个';
                            if (results.errors.length > 0) {
                                message += '<br>错误信息：<br>' + results.errors.join('<br>');
                            }
                        }
                        $('#import-feedback').html('<div class="notice notice-success"><p>' + message + '</p></div>');
                        // 刷新友链列表
                        location.reload();
                    } else {
                        $('#import-feedback').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $('#import-feedback').html('<div class="notice notice-error"><p>导入失败，请重试</p></div>');
                }
            });
        });

        // 导出功能使用标准表单提交，不需要JavaScript处理
    });
})( jQuery );