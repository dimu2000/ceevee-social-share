/**
 * Plugin Name: CV Social Share Pro
 * Plugin URI: https://ceeveeglobal.com
 * Description: Professional social share buttons with analytics tracking
 * Version: 1.0.0
 * Author: Dimuthu Harshana
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CVSOCIALSHARE_VERSION', '1.0.0');
define('CVSOCIALSHARE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CVSOCIALSHARE_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Plugin Class
 */
class CVsocialshare_Plugin {
    
    public function __construct() {
        add_action('init', array($this, 'CVsocialshare_init'));
        add_action('wp_enqueue_scripts', array($this, 'CVsocialshare_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'CVsocialshare_admin_scripts'));
        add_action('admin_menu', array($this, 'CVsocialshare_admin_menu'));
        add_filter('the_content', array($this, 'CVsocialshare_add_buttons'));
        add_action('wp_ajax_CVsocialshare_track_share', array($this, 'CVsocialshare_track_share'));
        add_action('wp_ajax_nopriv_CVsocialshare_track_share', array($this, 'CVsocialshare_track_share'));
        register_activation_hook(__FILE__, array($this, 'CVsocialshare_activate'));
    }
    
    public function CVsocialshare_init() {
        // Plugin initialization
    }
    
    public function CVsocialshare_activate() {
        $this->CVsocialshare_create_tables();
        $this->CVsocialshare_set_default_options();
    }
    
    public function CVsocialshare_create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cvsocial_shares';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            share_count int(11) DEFAULT 0,
            last_shared datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_platform (post_id, platform)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function CVsocialshare_set_default_options() {
        $default_options = array(
            'enabled_platforms' => array('facebook', 'twitter', 'linkedin', 'pinterest', 'whatsapp'),
            'button_position' => 'left',
            'show_counts' => true,
            'button_style' => 'rounded'
        );
        
        if (!get_option('CVsocialshare_options')) {
            add_option('CVsocialshare_options', $default_options);
        }
    }
    
    public function CVsocialshare_enqueue_scripts() {
        if (is_single()) {
            // Only enqueue jQuery and localize script for AJAX
            wp_enqueue_script('jquery');
            
            // Localize script for AJAX (will be used in inline script)
            wp_localize_script('jquery', 'CVsocialshare_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('CVsocialshare_nonce')
            ));
        }
    }
    
    public function CVsocialshare_admin_scripts($hook) {
        if ($hook === 'toplevel_page_cv-social-share') {
            // Only enqueue basic admin styles - no external files
            wp_enqueue_script('jquery');
        }
    }
    
    public function CVsocialshare_admin_menu() {
        add_menu_page(
            'CV Social Share',
            'Social Share',
            'manage_options',
            'cv-social-share',
            array($this, 'CVsocialshare_admin_page'),
            'dashicons-share',
            30
        );
        
        add_submenu_page(
            'cv-social-share',
            'Analytics',
            'Analytics',
            'manage_options',
            'cv-social-analytics',
            array($this, 'CVsocialshare_analytics_page')
        );
    }
    
    public function CVsocialshare_admin_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['CVsocialshare_nonce'], 'CVsocialshare_save_settings')) {
            $options = array(
                'enabled_platforms' => isset($_POST['enabled_platforms']) ? array_map('sanitize_text_field', $_POST['enabled_platforms']) : array(),
                'button_position' => sanitize_text_field($_POST['button_position']),
                'show_counts' => isset($_POST['show_counts']),
                'button_style' => sanitize_text_field($_POST['button_style'])
            );
            
            update_option('CVsocialshare_options', $options);
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $options = get_option('CVsocialshare_options');
        
        // Ensure options exist and have default values
        if (!$options || !is_array($options)) {
            $options = array(
                'enabled_platforms' => array('facebook', 'twitter', 'linkedin', 'pinterest', 'whatsapp'),
                'button_position' => 'left',
                'show_counts' => true,
                'button_style' => 'rounded'
            );
        }
        
        // Ensure enabled_platforms is always an array
        if (!isset($options['enabled_platforms']) || !is_array($options['enabled_platforms'])) {
            $options['enabled_platforms'] = array('facebook', 'twitter', 'linkedin', 'pinterest', 'whatsapp');
        }
        ?>
        <div class="wrap">
            <h1>CV Social Share Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('CVsocialshare_save_settings', 'CVsocialshare_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enabled Platforms</th>
                        <td>
                            <?php
                            $platforms = array(
                                'facebook' => 'Facebook',
                                'twitter' => 'Twitter/X',
                                'linkedin' => 'LinkedIn',
                                'pinterest' => 'Pinterest',
                                'whatsapp' => 'WhatsApp',
                                'telegram' => 'Telegram',
                                'reddit' => 'Reddit'
                            );
                            
                            foreach ($platforms as $key => $label) {
                                $checked = in_array($key, $options['enabled_platforms']) ? 'checked' : '';
                                echo "<label><input type='checkbox' name='enabled_platforms[]' value='$key' $checked> $label</label><br>";
                            }
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Button Position</th>
                        <td>
                            <select name="button_position">
                                <option value="left" <?php selected(isset($options['button_position']) ? $options['button_position'] : 'left', 'left'); ?>>Left Side (Floating)</option>
                                <option value="top" <?php selected(isset($options['button_position']) ? $options['button_position'] : 'left', 'top'); ?>>Above Content</option>
                                <option value="bottom" <?php selected(isset($options['button_position']) ? $options['button_position'] : 'left', 'bottom'); ?>>Below Content</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Show Share Counts</th>
                        <td>
                            <input type="checkbox" name="show_counts" value="1" <?php checked(isset($options['show_counts']) ? $options['show_counts'] : true); ?>>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Button Style</th>
                        <td>
                            <select name="button_style">
                                <option value="rounded" <?php selected(isset($options['button_style']) ? $options['button_style'] : 'rounded', 'rounded'); ?>>Rounded</option>
                                <option value="square" <?php selected(isset($options['button_style']) ? $options['button_style'] : 'rounded', 'square'); ?>>Square</option>
                                <option value="circle" <?php selected(isset($options['button_style']) ? $options['button_style'] : 'rounded', 'circle'); ?>>Circle</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function CVsocialshare_analytics_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cvsocial_shares';
        
        // Get total shares by platform
        $platform_stats = $wpdb->get_results("
            SELECT platform, SUM(share_count) as total_shares 
            FROM $table_name 
            GROUP BY platform 
            ORDER BY total_shares DESC
        ");
        
        // Get top shared posts
        $top_posts = $wpdb->get_results("
            SELECT s.post_id, p.post_title, SUM(s.share_count) as total_shares
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} p ON s.post_id = p.ID
            GROUP BY s.post_id
            ORDER BY total_shares DESC
            LIMIT 10
        ");
        
        // Get recent shares
        $recent_shares = $wpdb->get_results("
            SELECT s.*, p.post_title
            FROM $table_name s
            LEFT JOIN {$wpdb->posts} p ON s.post_id = p.ID
            ORDER BY s.last_shared DESC
            LIMIT 20
        ");
        
        ?>
        <div class="wrap">
            <h1>Social Share Analytics</h1>
            
            <div class="cvsocial-analytics-grid">
                <div class="cvsocial-analytics-card">
                    <h3>Shares by Platform</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Total Shares</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($platform_stats): ?>
                                <?php foreach ($platform_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo esc_html(ucfirst($stat->platform)); ?></td>
                                        <td><?php echo esc_html($stat->total_shares); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="cvsocial-analytics-card">
                    <h3>Top Shared Posts</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Post Title</th>
                                <th>Total Shares</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_posts): ?>
                                <?php foreach ($top_posts as $post): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($post->post_id); ?>">
                                                <?php echo esc_html($post->post_title ?: 'Untitled'); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($post->total_shares); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="cvsocial-analytics-card">
                <h3>Recent Activity</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Platform</th>
                            <th>Share Count</th>
                            <th>Last Shared</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_shares): ?>
                            <?php foreach ($recent_shares as $share): ?>
                                <tr>
                                    <td><?php echo esc_html($share->post_title ?: 'Untitled'); ?></td>
                                    <td><?php echo esc_html(ucfirst($share->platform)); ?></td>
                                    <td><?php echo esc_html($share->share_count); ?></td>
                                    <td><?php echo esc_html($share->last_shared); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .cvsocial-analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .cvsocial-analytics-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .cvsocial-analytics-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .cvsocial-analytics-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    public function CVsocialshare_add_buttons($content) {
        // Only add buttons on single posts of type 'post'
        if (!is_single() || get_post_type() !== 'post' || is_admin() || is_feed()) {
            return $content;
        }
        
        // Prevent infinite loops
        if (doing_filter('get_the_excerpt')) {
            return $content;
        }
        
        // Don't add buttons if content is empty
        if (empty($content)) {
            return $content;
        }
        
        try {
            $buttons = $this->CVsocialshare_generate_buttons();
            
            $options = get_option('CVsocialshare_options');
            $position = isset($options['button_position']) ? $options['button_position'] : 'left';
            
            if ($position === 'left') {
                // Floating left side buttons - don't modify content, just add buttons
                return $content . $buttons;
            } elseif ($position === 'top') {
                return $buttons . $content;
            } else {
                return $content . $buttons;
            }
        } catch (Exception $e) {
            // If there's any error, just return original content
            return $content;
        }
    }
    
    public function CVsocialshare_generate_buttons() {
        global $post;
        
        // Safety check
        if (!$post || !is_object($post)) {
            return '';
        }
        
        $options = get_option('CVsocialshare_options');
        
        // Ensure options exist and have default values
        if (!$options || !is_array($options)) {
            $options = array(
                'enabled_platforms' => array('facebook', 'twitter', 'linkedin', 'pinterest', 'whatsapp'),
                'button_position' => 'left',
                'show_counts' => true,
                'button_style' => 'rounded'
            );
        }
        
        // Ensure each option has a default value
        $enabled_platforms = isset($options['enabled_platforms']) && is_array($options['enabled_platforms']) ? $options['enabled_platforms'] : array('facebook', 'twitter', 'linkedin', 'pinterest', 'whatsapp');
        $show_counts = isset($options['show_counts']) ? $options['show_counts'] : true;
        $button_style = isset($options['button_style']) ? $options['button_style'] : 'rounded';
        $position = isset($options['button_position']) ? $options['button_position'] : 'left';
        
        // Don't generate buttons if no platforms enabled
        if (empty($enabled_platforms)) {
            return '';
        }
        
        $post_url = get_permalink($post->ID);
        $post_title = get_the_title($post->ID);
        $post_excerpt = wp_trim_words(get_the_excerpt($post->ID), 20);
        
        // Safety checks
        if (!$post_url || !$post_title) {
            return '';
        }
        
        $container_class = $position === 'left' ? 'cvsocial-floating-left' : 'cvsocial-inline';
        
        $html = '<div class="cvsocial-share-container ' . esc_attr($container_class) . '">';
        
        if ($position !== 'left') {
            $html .= '<h4 class="cvsocial-share-title">Share this post</h4>';
        }
        
        $html .= '<div class="cvsocial-share-buttons cvsocial-' . esc_attr($button_style) . '">';
        
        foreach ($enabled_platforms as $platform) {
            $share_url = $this->CVsocialshare_get_share_url($platform, $post_url, $post_title, $post_excerpt);
            $icon = $this->CVsocialshare_get_platform_icon($platform);
            $count = $show_counts ? $this->CVsocialshare_get_share_count($post->ID, $platform) : 0;
            
            // Skip if no share URL or icon
            if (!$share_url || $share_url === '#' || !$icon) {
                continue;
            }
            
            $html .= '<a href="' . esc_url($share_url) . '" 
                        class="cvsocial-share-btn cvsocial-' . esc_attr($platform) . '" 
                        data-platform="' . esc_attr($platform) . '" 
                        data-post-id="' . esc_attr($post->ID) . '" 
                        target="_blank" 
                        rel="noopener noreferrer">';
            
            $html .= '<span class="cvsocial-icon">' . $icon . '</span>';
            
            if ($position !== 'left') {
                $html .= '<span class="cvsocial-label">' . esc_html(ucfirst($platform)) . '</span>';
            }
            
            if ($show_counts && $count > 0) {
                $html .= '<span class="cvsocial-count">' . esc_html($count) . '</span>';
            }
            
            $html .= '</a>';
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    public function CVsocialshare_get_share_url($platform, $url, $title, $excerpt) {
        $encoded_url = urlencode($url);
        $encoded_title = urlencode($title);
        $encoded_excerpt = urlencode($excerpt);
        
        switch ($platform) {
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}";
            case 'twitter':
                return "https://twitter.com/intent/tweet?url={$encoded_url}&text={$encoded_title}";
            case 'linkedin':
                return "https://www.linkedin.com/sharing/share-offsite/?url={$encoded_url}";
            case 'pinterest':
                $image = get_the_post_thumbnail_url(get_the_ID(), 'large');
                $encoded_image = urlencode($image);
                return "https://pinterest.com/pin/create/button/?url={$encoded_url}&media={$encoded_image}&description={$encoded_title}";
            case 'whatsapp':
                return "https://wa.me/?text={$encoded_title}%20{$encoded_url}";
            case 'telegram':
                return "https://t.me/share/url?url={$encoded_url}&text={$encoded_title}";
            case 'reddit':
                return "https://reddit.com/submit?url={$encoded_url}&title={$encoded_title}";
            default:
                return '#';
        }
    }
    
    public function CVsocialshare_get_platform_icon($platform) {
        $icons = array(
            'facebook' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'twitter' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>',
            'linkedin' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'pinterest' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.093.112.105.21.078.323-.085.353-.402 1.292-.402 1.292-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.746-1.378l-.744 2.849c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.624 0 11.99-5.367 11.99-11.99C24.007 5.367 18.641.001 12.017.001z"/></svg>',
            'whatsapp' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.484 3.488"/></svg>',
            'telegram' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            'reddit' => '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>'
        );
        
        return isset($icons[$platform]) ? $icons[$platform] : '';
    }
    
    public function CVsocialshare_get_share_count($post_id, $platform) {
        global $wpdb;
        
        // Safety check
        if (!$post_id || !$platform) {
            return 0;
        }
        
        $table_name = $wpdb->prefix . 'cvsocial_shares';
        
        try {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT share_count FROM $table_name WHERE post_id = %d AND platform = %s",
                $post_id,
                $platform
            ));
            
            return $count ? intval($count) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public function CVsocialshare_track_share() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'CVsocialshare_nonce')) {
            wp_die('Security check failed');
        }
        
        $post_id = intval($_POST['post_id']);
        $platform = sanitize_text_field($_POST['platform']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cvsocial_shares';
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d AND platform = %s",
            $post_id,
            $platform
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array(
                    'share_count' => $existing->share_count + 1,
                    'last_shared' => current_time('mysql')
                ),
                array(
                    'post_id' => $post_id,
                    'platform' => $platform
                )
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'platform' => $platform,
                    'share_count' => 1,
                    'last_shared' => current_time('mysql')
                )
            );
        }
        
        wp_die(); // Important for AJAX requests
    }
}

// Initialize the plugin
new CVsocialshare_Plugin();

// Include CSS and JS inline since we can't create separate files in Code Snippets
add_action('wp_head', 'CVsocialshare_inline_styles');
add_action('wp_footer', 'CVsocialshare_inline_scripts');

function CVsocialshare_inline_styles() {
    if (is_single()) {
        ?>
        <style>
        /* CV Social Share Styles */
        .cvsocial-share-container {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .cvsocial-floating-left {
            position: fixed;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 999;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 15px 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .cvsocial-floating-left .cvsocial-share-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .cvsocial-inline {
            margin: 30px 0;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #007cba;
        }
        
        .cvsocial-share-title {
            margin: 0 0 20px 0;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .cvsocial-inline .cvsocial-share-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .cvsocial-share-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            text-decoration: none;
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }
        
        .cvsocial-floating-left .cvsocial-share-btn {
            width: 48px;
            height: 48px;
            justify-content: center;
            padding: 0;
            border-radius: 50%;
        }
        
        .cvsocial-rounded .cvsocial-share-btn {
            border-radius: 25px;
        }
        
        .cvsocial-square .cvsocial-share-btn {
            border-radius: 4px;
        }
        
        .cvsocial-circle .cvsocial-share-btn {
            border-radius: 50%;
            width: 50px;
            height: 50px;
            justify-content: center;
            padding: 0;
        }
        
        .cvsocial-share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: white;
        }
        
        .cvsocial-share-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .cvsocial-share-btn:hover:before {
            left: 100%;
        }
        
        .cvsocial-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cvsocial-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            min-width: 20px;
            text-align: center;
        }
        
        /* Platform specific colors */
        .cvsocial-facebook { background: linear-gradient(135deg, #1877f2, #42a5f5); }
        .cvsocial-twitter { background: linear-gradient(135deg, #1da1f2, #64b5f6); }
        .cvsocial-linkedin { background: linear-gradient(135deg, #0077b5, #42a5f5); }
        .cvsocial-pinterest { background: linear-gradient(135deg, #bd081c, #e57373); }
        .cvsocial-whatsapp { background: linear-gradient(135deg, #25d366, #66bb6a); }
        .cvsocial-telegram { background: linear-gradient(135deg, #0088cc, #42a5f5); }
        .cvsocial-reddit { background: linear-gradient(135deg, #ff4500, #ff7043); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cvsocial-floating-left {
                position: relative;
                left: auto;
                top: auto;
                transform: none;
                margin: 20px 0;
                padding: 15px;
            }
            
            .cvsocial-floating-left .cvsocial-share-buttons {
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .cvsocial-inline .cvsocial-share-buttons {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .cvsocial-inline .cvsocial-share-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .cvsocial-share-btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }
        }
        </style>
        <?php
    }
}

function CVsocialshare_inline_scripts() {
    if (is_single()) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Handle social share button clicks
            $('.cvsocial-share-btn').on('click', function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var platform = $this.data('platform');
                var postId = $this.data('post-id');
                var shareUrl = $this.attr('href');
                
                // Track the share
                $.ajax({
                    url: CVsocialshare_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'CVsocialshare_track_share',
                        post_id: postId,
                        platform: platform,
                        nonce: CVsocialshare_ajax.nonce
                    },
                    success: function(response) {
                        // Update count if visible
                        var $count = $this.find('.cvsocial-count');
                        if ($count.length) {
                            var currentCount = parseInt($count.text()) || 0;
                            $count.text(currentCount + 1);
                        }
                    }
                });
                
                // Open share popup
                var width = 600;
                var height = 400;
                var left = (screen.width / 2) - (width / 2);
                var top = (screen.height / 2) - (height / 2);
                
                window.open(
                    shareUrl,
                    'social-share',
                    'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
                );
            });
            
            // Smooth floating button animation on scroll
            if ($('.cvsocial-floating-left').length) {
                var $floatingBtns = $('.cvsocial-floating-left');
                var lastScrollTop = 0;
                
                $(window).scroll(function() {
                    var scrollTop = $(this).scrollTop();
                    
                    if (scrollTop > lastScrollTop && scrollTop > 100) {
                        // Scrolling down
                        $floatingBtns.css('transform', 'translateY(-50%) translateX(-10px)');
                        $floatingBtns.css('opacity', '0.7');
                    } else {
                        // Scrolling up or at top
                        $floatingBtns.css('transform', 'translateY(-50%) translateX(0)');
                        $floatingBtns.css('opacity', '1');
                    }
                    
                    lastScrollTop = scrollTop;
                });
            }
        });
        </script>
        <?php
    }
}