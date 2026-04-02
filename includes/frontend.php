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

    // Schema validation: kiểm tra trường trong DB
    $expected_fields = [
        'id', 'name', 'slug', 'arcana', 'number', 'deck',
        'image', 'custom_image', 'description',
        'meaning_upright', 'meaning_reversed',
        'love_upright', 'love_reversed',
        'career_upright', 'career_reversed',
        'finance_upright', 'finance_reversed',
        'health_upright', 'health_reversed',
        'keywords_upright', 'keywords_reversed',
        'yes_no_upright', 'yes_no_reversed',
        'advice_upright', 'advice_reversed', 'advice_message',
        'custom_title', 'custom_content', 'custom_excerpt',
        'created_at', 'updated_at'
    ];

    $actual_fields = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);
    $missing_fields = array_diff($expected_fields, $actual_fields);
    $extra_fields = array_diff($actual_fields, $expected_fields);

    $schema_notice = '';
    if (!empty($missing_fields) || !empty($extra_fields)) {
        $schema_notice = "<div class='tarot-schema-warning' style='padding: 12px; border: 2px solid #ff0000; background: #fff0f0; margin-bottom: 20px;'>";
        $schema_notice .= "<h3>Database schema mismatch</h3>";
        if (!empty($missing_fields)) {
            $schema_notice .= "<p><strong>Missing fields:</strong> " . esc_html(implode(', ', $missing_fields)) . "</p>";
        }
        if (!empty($extra_fields)) {
            $schema_notice .= "<p><strong>Extra fields:</strong> " . esc_html(implode(', ', $extra_fields)) . "</p>";
        }
        $schema_notice .= "</div>";
    }

    $title = $card['custom_title'] ?: $card['name'];
    $image = $card['custom_image'] ?: $card['image'];
    $description = $card['description'] ?: '';

    $upright = $card['meaning_upright'];
    $reversed = $card['meaning_reversed'];
    $love_upright = $card['love_upright'];
    $love_reversed = $card['love_reversed'];
    $career_upright = $card['career_upright'];
    $career_reversed = $card['career_reversed'];
    $finance_upright = $card['finance_upright'];
    $finance_reversed = $card['finance_reversed'];
    $health_upright = $card['health_upright'];
    $health_reversed = $card['health_reversed'];

    get_header();

    echo "<div class='tarot-page'>";
    echo $schema_notice;
    echo "<div class='tarot-card-single'>";
    echo "<a href='" . esc_url(home_url('/tarot')) . "' class='button'>Back to Tarot</a>";
    echo "<h1>" . esc_html($title) . "</h1>";
    echo "<div class='tarot-card-meta'>";
    echo "<p><strong>Arcana:</strong> " . esc_html($card['arcana']) . "</p>";
    echo "<p><strong>Number:</strong> " . esc_html($card['number']) . "</p>";
    echo "<p><strong>Deck:</strong> " . esc_html($card['deck']) . "</p>";
    echo "</div>";

    if ($image) {
        echo "<div class='tarot-card-image'><img src='" . esc_url($image) . "' alt='" . esc_attr($title) . "'></div>";
    }

    if ($description) {
        echo "<div class='tarot-card-description'>" . wp_kses_post($description) . "</div>";
    }

    echo "<div class='tarot-card-meanings'><h2>Upright</h2>";
    echo "<p>" . nl2br(esc_html($upright)) . "</p>";
    echo "<h3>Love</h3><p>" . nl2br(esc_html($love_upright)) . "</p>";
    echo "<h3>Career</h3><p>" . nl2br(esc_html($career_upright)) . "</p>";
    echo "<h3>Finance</h3><p>" . nl2br(esc_html($finance_upright)) . "</p>";
    echo "<h3>Health</h3><p>" . nl2br(esc_html($health_upright)) . "</p>";
    echo "</div>";

    echo "<div class='tarot-card-meanings'><h2>Reversed</h2>";
    echo "<p>" . nl2br(esc_html($reversed)) . "</p>";
    echo "<h3>Love</h3><p>" . nl2br(esc_html($love_reversed)) . "</p>";
    echo "<h3>Career</h3><p>" . nl2br(esc_html($career_reversed)) . "</p>";
    echo "<h3>Finance</h3><p>" . nl2br(esc_html($finance_reversed)) . "</p>";
    echo "<h3>Health</h3><p>" . nl2br(esc_html($health_reversed)) . "</p>";
    echo "</div>";

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