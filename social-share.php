<?php
/*
Plugin Name: Social Share Plugin
Plugin URI: https://lioneur.com/
Description: A simple plugin to share posts and pages on Facebook, X, WhatsApp, Lioneur, Pinterest, and Reddit.
Version: 1.4
Author: Lioneur
Author URI: https://lioneur.com/
License: GPL2
*/

// Enqueue necessary JavaScript and CSS
function social_share_enqueue_scripts() {
    wp_enqueue_script('social-share-script', plugin_dir_url(__FILE__) . 'social-share.js', [], null, true);
    wp_enqueue_style('social-share-style', plugin_dir_url(__FILE__) . 'social-share.css');
}
add_action('wp_enqueue_scripts', 'social_share_enqueue_scripts');

// Add settings page
function social_share_settings_menu() {
    add_options_page('Social Share Settings', 'Social Share', 'manage_options', 'social-share-settings', 'social_share_settings_page');
}
add_action('admin_menu', 'social_share_settings_menu');

// Render settings page
function social_share_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'social-share'));
    }

    // Check nonce for security
    if (isset($_POST['social_share_settings_nonce']) && 
        wp_verify_nonce($_POST['social_share_settings_nonce'], 'social_share_settings_action')) {
        
        // Sanitize and validate inputs
        $alignment = isset($_POST['social_share_alignment']) ? 
            sanitize_text_field($_POST['social_share_alignment']) : 'left';
        
        $auto_display = isset($_POST['social_share_auto_display']) ? 
            1 : 0;
        
        $post_types = isset($_POST['social_share_post_types']) && is_array($_POST['social_share_post_types']) ? 
            array_map('sanitize_text_field', $_POST['social_share_post_types']) : [];

        $include_excerpt = isset($_POST['social_share_include_excerpt']) ? 
            1 : 0;

        // Update options
        update_option('social_share_alignment', $alignment);
        update_option('social_share_auto_display', $auto_display);
        update_option('social_share_post_types', $post_types);
        update_option('social_share_include_excerpt', $include_excerpt);

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    // Retrieve current settings
    $alignment = get_option('social_share_alignment', 'left');
    $auto_display = get_option('social_share_auto_display', 0);
    $saved_post_types = get_option('social_share_post_types', []);
    $include_excerpt = get_option('social_share_include_excerpt', 0);

    // Get available post types
    $post_types = get_post_types(['public' => true], 'objects');
    
    ?>
    <div class="wrap">
        <h1>Social Share Settings</h1>
        <form method="post">
            <?php 
            // Add nonce field for security
            wp_nonce_field('social_share_settings_action', 'social_share_settings_nonce'); 
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="social_share_alignment">Button Alignment:</label></th>
                    <td>
                        <select id="social_share_alignment" name="social_share_alignment">
                            <option value="left"<?php selected($alignment, 'left'); ?>>Left</option>
                            <option value="center"<?php selected($alignment, 'center'); ?>>Center</option>
                            <option value="right"<?php selected($alignment, 'right'); ?>>Right</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="social_share_auto_display">Automatically Display Buttons</label></th>
                    <td>
                        <input type="checkbox" 
                               id="social_share_auto_display" 
                               name="social_share_auto_display" 
                               value="1" 
                               <?php checked(1, $auto_display); ?> />
                        <label for="social_share_auto_display">Enable automatic display of share buttons</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="social_share_include_excerpt">Include Excerpt</label></th>
                    <td>
                        <input type="checkbox" 
                               id="social_share_include_excerpt" 
                               name="social_share_include_excerpt" 
                               value="1" 
                               <?php checked(1, $include_excerpt); ?> />
                        <label for="social_share_include_excerpt">Include post excerpt in share text when available</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Post Types to Display:</label></th>
                    <td>
                        <?php foreach ($post_types as $post_type): 
                            // Exclude certain post types
                            if (in_array($post_type->name, ['attachment', 'nav_menu_item', 'custom_css', 'customize_changeset'])) continue;
                            ?>
                            <label style="margin-right: 15px;">
                                <input type="checkbox" 
                                       name="social_share_post_types[]" 
                                       value="<?php echo esc_attr($post_type->name); ?>"
                                       <?php checked(in_array($post_type->name, $saved_post_types), true); ?> />
                                <?php echo esc_html($post_type->label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <p>Use the shortcode <code>[social_share_buttons]</code> to display the buttons on your posts or pages.</p>
            <input type="submit" value="Save Changes" class="button button-primary">
        </form>
    </div>
    <?php
}

// Function to automatically add share buttons
function social_share_auto_display_buttons($content) {
    // Check if auto-display is enabled
    $auto_display = get_option('social_share_auto_display', 0);
    if (!$auto_display) {
        return $content;
    }

    // Check if we're on the home page or front page
    if (is_home() || is_front_page()) {
        return $content;
    }

    // Get allowed post types
    $allowed_post_types = get_option('social_share_post_types', []);

    // Check if current post type is allowed
    $current_post_type = get_post_type();
    if (!in_array($current_post_type, $allowed_post_types)) {
        return $content;
    }

    // Generate share buttons
    $share_buttons = do_shortcode('[social_share_buttons]');

    // Append buttons to content
    $content .= $share_buttons;

    return $content;
}
add_filter('the_content', 'social_share_auto_display_buttons');

// Add shortcode for share buttons
function social_share_buttons_shortcode($atts) {
    $atts = shortcode_atts([
        'url' => '',
        'title' => '',
        'text' => ''
    ], $atts);

    $url = $atts['url'] ? esc_url($atts['url']) : esc_url(get_permalink());
    $title = $atts['title'] ? esc_attr($atts['title']) : esc_attr(get_the_title());
    
    // Check if excerpt should be included
    $include_excerpt = get_option('social_share_include_excerpt', 0);
    $text = $atts['text'];
    
    if ($include_excerpt && empty($text)) {
        // Try to get the excerpt
        $post = get_post();
        if ($post) {
            // Get excerpt, fallback to trimmed content
            $text = !empty($post->post_excerpt) 
                ? esc_attr(wp_trim_words($post->post_excerpt, 30)) 
                : esc_attr(wp_trim_words($post->post_content, 30));
        }
    }

    $alignment = get_option('social_share_alignment', 'left');

    return '<div class="social-share-buttons" style="text-align:' . $alignment . ';">
        <button style="background-color: #3b5998; color: white;" onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=' . $url . '\', \'_blank\', \'height=600,width=800\')">Facebook</button>
        <button style="background-color: #000000; color: white;" onclick="window.open(\'https://twitter.com/intent/tweet?url=' . $url . '&text=' . urlencode($text) . '\', \'_blank\', \'height=600,width=800\')">X</button>
        <button style="background-color: #25d366; color: white;" onclick="window.open(\'https://wa.me/?text=' . urlencode($text . ' ' . $url) . '\', \'_blank\', \'height=600,width=800\')">WhatsApp</button>
        <button style="background-color: #141415; color: white;" onclick="SocialShare(\'' . $url . '\')">Lioneur</button>
        <button style="background-color: #bd081c; color: white;" onclick="window.open(\'https://www.pinterest.com/pin/create/button/?url=' . $url . '&description=' . urlencode($text) . '\', \'_blank\', \'height=600,width=800\')">Pinterest</button>
        <button style="background-color: #ff4500; color: white;" onclick="window.open(\'https://www.reddit.com/submit?url=' . $url . '&title=' . urlencode($text) . '\', \'_blank\', \'height=600,width=800\')">Reddit</button>
    </div>';
}
add_shortcode('social_share_buttons', 'social_share_buttons_shortcode');

// Add action hook for developers
function social_share_custom_hook($url, $title, $text) {
    do_action('social_share_custom_hook', $url, $title, $text);
}

// Include Lioneur sharing script
function social_share_add_lioneur_script() {
    echo '<script>
        function SocialShare(url) {
            window.open(\'https://lioneur.com/share?url=\' + url, \'\', \'height=600,width=800\');
        }
    </script>';
}
add_action('wp_head', 'social_share_add_lioneur_script');