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

    // Get meanings
    $meanings = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarot_card_meanings WHERE card_id=%d", $card['id']),
        ARRAY_A
    );

    // Get content
    $content = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tarot_card_contents WHERE card_id=%d", $card['id']),
        ARRAY_A
    );

    // Prevent output during plugin activation or admin requests
    if (defined('WP_INSTALLING') || is_admin() || wp_doing_ajax()) {
        return;
    }

    // Schema validation: kiểm tra trường trong DB
    $expected_fields = [
        'id', 'name', 'slug', 'arcana', 'suit', 'number', 'deck',
        'image', 'description', 'meta_data', 'created_at', 'updated_at'
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

    $title = $content['title'] ?: $card['name'];
    $image = $card['image'];
    $description = $card['description'] ?: '';

    // Organize meanings by type and context
    $card_meanings = [];
    foreach ($meanings as $meaning) {
        $card_meanings[$meaning['type']][$meaning['context']] = $meaning;
    }

    $upright_general = $card_meanings['upright']['general']['meaning'] ?? '';
    $reversed_general = $card_meanings['reversed']['general']['meaning'] ?? '';
    $love_upright = $card_meanings['upright']['love']['meaning'] ?? '';
    $love_reversed = $card_meanings['reversed']['love']['meaning'] ?? '';
    $career_upright = $card_meanings['upright']['career']['meaning'] ?? '';
    $career_reversed = $card_meanings['reversed']['career']['meaning'] ?? '';
    $finance_upright = $card_meanings['upright']['finance']['meaning'] ?? '';
    $finance_reversed = $card_meanings['reversed']['finance']['meaning'] ?? '';
    $health_upright = $card_meanings['upright']['health']['meaning'] ?? '';
    $health_reversed = $card_meanings['reversed']['health']['meaning'] ?? '';

    get_header();

    echo "<div class='tarot-page'>";
    echo $schema_notice;
    echo "<div class='tarot-card-single'>";
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
    echo "<p>" . nl2br(esc_html($upright_general)) . "</p>";
    echo "<h3>Love</h3><p>" . nl2br(esc_html($love_upright)) . "</p>";
    echo "<h3>Career</h3><p>" . nl2br(esc_html($career_upright)) . "</p>";
    echo "<h3>Finance</h3><p>" . nl2br(esc_html($finance_upright)) . "</p>";
    echo "<h3>Health</h3><p>" . nl2br(esc_html($health_upright)) . "</p>";
    echo "</div>";

    echo "<div class='tarot-card-meanings'><h2>Reversed</h2>";
    echo "<p>" . nl2br(esc_html($reversed_general)) . "</p>";
    echo "<h3>Love</h3><p>" . nl2br(esc_html($love_reversed)) . "</p>";
    echo "<h3>Career</h3><p>" . nl2br(esc_html($career_reversed)) . "</p>";
    echo "<h3>Finance</h3><p>" . nl2br(esc_html($finance_reversed)) . "</p>";
    echo "<h3>Health</h3><p>" . nl2br(esc_html($health_reversed)) . "</p>";
    echo "</div>";

    echo "</div>";

    get_footer();
    exit;
});

add_shortcode('tarot_reader', function () {
    ob_start(); ?>

<div id="tarotApp" class="tarot-app">

    <!-- STEP 1 -->
    <div class="tarot-topic">
        <h2>Chọn chủ đề</h2>
        <div class="topic-list">
            <div class="topic" data-topic="love">Tình cảm</div>
            <div class="topic" data-topic="work">Công việc</div>
            <div class="topic" data-topic="finance">Tài chính</div>
            <div class="topic" data-topic="health">Sức khỏe</div>
        </div>
    </div>

    <!-- STEP 2 -->
    <div class="tarot-stage hidden">

        <div id="countdown">3</div>

        <div id="cardDeck" class="card-deck"></div>

    </div>

    <!-- BUTTON -->
    <div class="tarot-actions hidden">
        <button id="btnResult">Xem kết quả</button>
    </div>

    <!-- RESULT -->
    <div id="tarotResult" class="hidden"></div>

</div>

<?php return ob_get_clean();
});

add_shortcode('tarot_cards', function ($atts) {
    global $wpdb;

    $atts = shortcode_atts([
        'arcana' => '', // major, minor, or empty for all
        'suit' => '', // cups, swords, wands, pentacles, or empty for all
        'limit' => 0, // 0 for all
        'columns' => 6, // number of columns in grid
        'show_description' => 'false', // show card description
        'order_by' => 'arcana DESC, number ASC' // order
    ], $atts);

    $table = $wpdb->prefix . 'tarot_cards';
    $where = [];

    if (!empty($atts['arcana'])) {
        $where[] = $wpdb->prepare("arcana = %s", $atts['arcana']);
    }

    if (!empty($atts['suit'])) {
        $where[] = $wpdb->prepare("suit = %s", $atts['suit']);
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit_clause = $atts['limit'] > 0 ? $wpdb->prepare("LIMIT %d", $atts['limit']) : '';
    $order_clause = "ORDER BY " . sanitize_sql_orderby($atts['order_by']);

    $cards = $wpdb->get_results("SELECT * FROM $table $where_clause $order_clause $limit_clause", ARRAY_A);

    if (empty($cards)) {
        return '<p>No cards found.</p>';
    }

    $columns = intval($atts['columns']);
    $show_description = $atts['show_description'] === 'true';

    ob_start(); ?>

    <div class="tarot-cards-grid" style="display: grid; grid-template-columns: repeat(<?php echo $columns; ?>, 1fr); gap: 20px; margin: 20px 0;">
        <?php foreach ($cards as $card): ?>
        <div class="tarot-card-item">
            <a href="<?php echo esc_url(home_url('/tarot/' . $card['slug'])); ?>">
                <?php if ($card['image']): ?>
                    <div class="tarot-card-image">
                        <img src="<?php echo esc_url($card['image']); ?>"
                             alt="<?php echo esc_attr($card['name']); ?>">
                    </div>
                <?php else: ?>
                    <div class="tarot-card-placeholder">
                        No Image
                    </div>
                <?php endif; ?>

            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php return ob_get_clean();
});
