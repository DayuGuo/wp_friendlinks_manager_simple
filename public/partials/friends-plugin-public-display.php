<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Friends_Plugin
 * @subpackage Friends_Plugin/public/partials
 */

// Fetch links from the database - this is a placeholder
// In a real scenario, you would query the $wpdb->prefix . 'friends_links' table
$links = array(
    // Example Data Structure
    /*
    array(
        'name' => '博客名1',
        'url' => 'https://example1.com',
        'icon_url' => 'https://via.placeholder.com/80/007bff/ffffff?Text=Icon1',
        'description' => '一句话的描述, 一句话的描述',
        'latest_post_title' => '这里是最新的文章, 这里是最新...',
        'latest_post_url' => 'https://example1.com/latest-post',
        'latest_post_date' => '2025-01-01'
    ),
    array(
        'name' => '博客名2',
        'url' => 'https://example2.com',
        'icon_url' => 'https://via.placeholder.com/80/ffc107/000000?Text=Icon2',
        'description' => '另一句话的描述',
        'latest_post_title' => '这是第二篇最新的文章',
        'latest_post_url' => 'https://example2.com/another-post',
        'latest_post_date' => '2025-01-02'
    ),
    array(
        'name' => '博客名3 (无RSS)',
        'url' => 'https://example3.com',
        'icon_url' => 'https://via.placeholder.com/80/28a745/ffffff?Text=Icon3',
        'description' => '站点描述三',
        'latest_post_title' => null, // No RSS or failed to fetch
        'latest_post_url' => null,
        'latest_post_date' => null
    )
    */
);

// Simulate fetching from DB for now
global $wpdb;
$table_name = $wpdb->prefix . 'friends_links';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
    $db_links = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY sort_order ASC"), ARRAY_A);
    if ($db_links) {
        $links = $db_links;
    }
}

?>
<div class="friends-plugin-container">
    <?php if ( ! empty( $links ) ) : ?>
        <div class="friends-grid">
            <?php foreach ( $links as $link ) : ?>
                <div class="friend-card">
                    <h3 class="friend-name">
                        <a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php 
                            echo esc_html( $link['name'] );
                            // 检查是否半年未更新
                            if (!empty($link['latest_post_date'])) {
                                $last_update = strtotime($link['latest_post_date']);
                                $six_months_ago = strtotime('-6 months');
                                if ($last_update < $six_months_ago) {
                                    echo ' 🕊️';
                                }
                            }
                            ?>
                        </a>
                    </h3>
                    <p class="friend-description">
                        <?php echo esc_html( $link['description'] ); ?>
                    </p>
                    <div class="friend-latest-post">
                        <?php if ( ! empty( $link['latest_post_title'] ) && ! empty( $link['latest_post_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $link['latest_post_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="latest-post-title">
                                <?php echo esc_html( $link['latest_post_title'] ); ?>
                            </a>
                            <span class="latest-post-date">
                                <?php echo esc_html( date( 'Y-m-d', strtotime( $link['latest_post_date'] ) ) ); ?>
                            </span>
                        <?php else : ?>
                            <span class="no-latest-post">无法获取文章 (＞﹏＜)</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p>No friend links to display yet.</p>
    <?php endif; ?>
</div>