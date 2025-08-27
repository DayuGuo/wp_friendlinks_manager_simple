(function( $ ) {
    'use strict';

    /**
     * All of the code for your public-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here,
     * $ examples.
     *
     * This file is loaded in `includes/class-friends-plugin-public.php`.
     */

    $(document).ready(function() {
        // 获取PHP传递的宽度设置
        var widthSettings = (typeof friendsPluginWidth !== 'undefined') ? friendsPluginWidth : {
            container_width: 1200,
            content_width: 1200,
            site_width: 1200
        };
        
        // 检测容器宽度并添加相应的CSS类
        function checkContainerWidth() {
            $('.friends-plugin-container').each(function() {
                var $container = $(this);
                var containerWidth = $container.width();
                var maxWidth = widthSettings.container_width;
                
                // 移除之前的类
                $container.removeClass('narrow-container very-narrow-container wide-container');
                
                // 根据WordPress设置的最大宽度和实际宽度来分类
                if (containerWidth < 480 || maxWidth < 600) {
                    $container.addClass('very-narrow-container');
                } else if (containerWidth < 720 || maxWidth < 900) {
                    $container.addClass('narrow-container');
                } else {
                    $container.addClass('wide-container');
                }
                
                // 确保容器不超过WordPress设置的最大宽度
                if (!$container.closest('.widget, .sidebar').length) {
                    var currentMaxWidth = $container.css('max-width');
                    if (currentMaxWidth === 'none' || parseInt(currentMaxWidth) > maxWidth) {
                        $container.css('max-width', maxWidth + 'px');
                    }
                }
            });
        }
        
        // 初始检查
        checkContainerWidth();
        
        // 窗口大小改变时重新检查
        $(window).on('resize', function() {
            setTimeout(checkContainerWidth, 100); // 延迟一点确保重新计算完成
        });
        
        // 监听内容变化
        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                setTimeout(checkContainerWidth, 100);
            });
            observer.observe(document.body, { 
                childList: true, 
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }
    });

})( jQuery );