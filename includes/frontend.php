<?php

add_action('init', function () {
    add_rewrite_rule('^tarot/([^/]+)/?$', 'index.php?tarot_card=$matches[1]', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'tarot_card';
    return $vars;
});

add_action('template_redirect', function () {

    $slug = get_query_var('tarot_card');
    if (!$slug) return;

    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

    $card = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE slug=%s", $slug),
        ARRAY_A
    );

    if (!$card) {
        wp_die('Card not found');
        return;
    }

    // Prevent output during plugin activation or admin requests
    if (defined('WP_INSTALLING') || is_admin() || wp_doing_ajax()) {
        return;
    }

    $title = $card['custom_title'] ?: $card['name'];
    $content = $card['custom_content'] ?: $card['meaning_upright'];

    // Use proper WordPress template loading
    get_header();
    echo "<div class='tarot-card-single'>";
    echo "<h1>" . esc_html($title) . "</h1>";
    echo "<div class='card-content'>" . wp_kses_post($content) . "</div>";
    echo "</div>";
    get_footer();
    exit;
});

add_shortcode('tarot_3card', function () {
    ob_start(); ?>

    <input id="q" placeholder="Nhập câu hỏi">
    <button id="start">Xem bài</button>

    <div id="result"></div>
    <div id="ai"></div>

    <?php return ob_get_clean();
});