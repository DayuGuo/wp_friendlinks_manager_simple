<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Friends_Plugin
 * @subpackage Friends_Plugin/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Friends_Plugin
 * @subpackage Friends_Plugin/public
 * @author     Your Name <email@example.com>
 */
class Friends_Plugin_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        wp_enqueue_style( $this->plugin_name, FRIENDS_PLUGIN_URL . 'public/css/friends-plugin-public.css', array(), $this->version, 'all' );
        
        // 获取主题宽度设置并添加自定义CSS
        $this->add_custom_css_for_width();
        
        // 添加配色模式相关CSS
        $this->add_color_mode_css();

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        wp_enqueue_script( $this->plugin_name, FRIENDS_PLUGIN_URL . 'public/js/friends-plugin-public.js', array( 'jquery' ), $this->version, false );
        
        // 传递宽度设置到前端
        $width_settings = $this->get_theme_width_settings();
        wp_localize_script( $this->plugin_name, 'friendsPluginWidth', $width_settings );

    }

    /**
     * Display the friends page using the [friends_page] shortcode.
     *
     * @since    1.0.0
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the friends page.
     */
    public function display_friends_page( $atts ) {
        ob_start();
        include_once FRIENDS_PLUGIN_PATH . 'public/partials/friends-plugin-public-display.php';
        return ob_get_clean();
    }

    /**
     * 获取WordPress主题的宽度设置
     *
     * @since    1.0.0
     * @return   array    包含宽度设置的数组
     */
    private function get_theme_width_settings() {
        $settings = array(
            'content_width' => 1200, // 默认值
            'site_width' => 1200,
            'container_width' => 1200
        );

        // 尝试获取当前主题的内容宽度设置
        global $content_width;
        if (isset($content_width) && $content_width > 0) {
            $settings['content_width'] = $content_width;
        }

        // 尝试获取主题自定义器中的宽度设置
        $theme_mods = get_theme_mods();
        
        // 检查常见的宽度设置选项
        $width_options = array(
            'site_width',
            'container_width', 
            'content_width',
            'site_max_width',
            'container_max_width',
            'content_max_width',
            'layout_content_width',
            'global_content_width',
            'site_container_width'
        );

        foreach ($width_options as $option) {
            if (isset($theme_mods[$option]) && is_numeric($theme_mods[$option]) && $theme_mods[$option] > 0) {
                $settings['site_width'] = intval($theme_mods[$option]);
                break;
            }
        }

        // 检查主题选项
        $theme_options = get_option(get_template() . '_options', array());
        foreach ($width_options as $option) {
            if (isset($theme_options[$option]) && is_numeric($theme_options[$option]) && $theme_options[$option] > 0) {
                $settings['site_width'] = intval($theme_options[$option]);
                break;
            }
        }

        // 尝试从CSS自定义属性中获取（WordPress 5.9+）
        if (function_exists('wp_get_global_styles')) {
            $global_styles = wp_get_global_styles();
            if (isset($global_styles['layout']['contentSize'])) {
                $content_size = $global_styles['layout']['contentSize'];
                $numeric_value = intval(preg_replace('/[^0-9]/', '', $content_size));
                if ($numeric_value > 0) {
                    $settings['content_width'] = $numeric_value;
                }
            }
            if (isset($global_styles['layout']['wideSize'])) {
                $wide_size = $global_styles['layout']['wideSize'];
                $numeric_value = intval(preg_replace('/[^0-9]/', '', $wide_size));
                if ($numeric_value > 0) {
                    $settings['site_width'] = $numeric_value;
                }
            }
        }

        // 尝试从常见主题框架获取宽度设置
        // Genesis Framework
        if (function_exists('genesis_get_option')) {
            $genesis_width = genesis_get_option('site_layout');
            if ($genesis_width && is_numeric($genesis_width)) {
                $settings['site_width'] = intval($genesis_width);
            }
        }

        // Astra主题
        if (function_exists('astra_get_option')) {
            $astra_width = astra_get_option('site-content-width', 1200);
            if ($astra_width && is_numeric($astra_width)) {
                $settings['site_width'] = intval($astra_width);
            }
        }

        // GeneratePress主题
        if (function_exists('generate_get_option')) {
            $gp_width = generate_get_option('container_width');
            if ($gp_width && is_numeric($gp_width)) {
                $settings['site_width'] = intval($gp_width);
            }
        }

        // OceanWP主题
        if (function_exists('oceanwp_get_option')) {
            $ocean_width = oceanwp_get_option('main_container_width', 1200);
            if ($ocean_width && is_numeric($ocean_width)) {
                $settings['site_width'] = intval($ocean_width);
            }
        }

        // 尝试从CSS变量中读取（适用于现代主题）
        $css_vars = array(
            '--wp--style--global--content-size',
            '--wp--style--global--wide-size',
            '--global-content-width',
            '--content-width',
            '--container-width'
        );
        
        // 这里可以通过检查主题的style.css或其他方式获取CSS变量值
        // 但由于CSS变量在服务器端难以获取，我们主要依赖上述方法

        // 使用内容宽度作为最终宽度
        $settings['container_width'] = min($settings['content_width'], $settings['site_width']);

        return $settings;
    }

    /**
     * 添加基于主题宽度的自定义CSS
     *
     * @since    1.0.0
     */
    private function add_custom_css_for_width() {
        $settings = $this->get_theme_width_settings();
        $max_width = $settings['container_width'];

        $custom_css = "
        .friends-plugin-container {
            max-width: {$max_width}px;
            margin: 0 auto;
            padding: 15px;
        }
        
        @media (max-width: " . ($max_width + 40) . "px) {
            .friends-plugin-container {
                margin: 0 20px;
            }
        }
        
        @media (max-width: 768px) {
            .friends-plugin-container {
                margin: 0 15px;
            }
        }
        
        @media (max-width: 480px) {
            .friends-plugin-container {
                margin: 0 10px;
            }
        }
        ";

        wp_add_inline_style($this->plugin_name, $custom_css);
    }

    /**
     * 添加配色模式相关的自定义CSS
     *
     * @since    1.0.0
     */
    private function add_color_mode_css() {
        $color_mode = get_option( 'friends_plugin_color_mode', 'auto' );
        $custom_css = '';

        switch ( $color_mode ) {
            case 'light':
                // 强制浅色模式 - 覆盖暗色模式样式
                $custom_css = "
                .friends-plugin-container .friend-card {
                    background: #fff !important;
                    border-color: #eee !important;
                }

                .friends-plugin-container .friend-name a {
                    color: #333 !important;
                }

                .friends-plugin-container .friend-description {
                    color: #666 !important;
                }

                .friends-plugin-container .friend-latest-post {
                    border-top-color: #f0f0f0 !important;
                }

                .friends-plugin-container .latest-post-title {
                    color: #333 !important;
                }

                .friends-plugin-container .latest-post-date,
                .friends-plugin-container .no-latest-post {
                    color: #999 !important;
                }
                ";
                break;

            case 'dark':
                // 强制暗色模式
                $custom_css = "
                .friends-plugin-container .friend-card {
                    background: #222 !important;
                    border-color: #333 !important;
                }

                .friends-plugin-container .friend-name a {
                    color: #e0e0e0 !important;
                }

                .friends-plugin-container .friend-description {
                    color: #999 !important;
                }

                .friends-plugin-container .friend-latest-post {
                    border-top-color: #333 !important;
                }

                .friends-plugin-container .latest-post-title {
                    color: #e0e0e0 !important;
                }

                .friends-plugin-container .latest-post-date,
                .friends-plugin-container .no-latest-post {
                    color: #666 !important;
                }
                ";
                break;

            case 'auto':
            default:
                // 保持原有的自动适配逻辑，不添加额外CSS
                break;
        }

        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( $this->plugin_name, $custom_css );
        }
    }

}