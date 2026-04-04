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
        'image', 'description', 'created_at', 'updated_at'
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

add_shortcode('tarot_reader', function ($atts) {
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-tabs');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_style('tarot-reader-css', plugins_url('assets/css/tarot-reader.css', TAROT_FILE));
    wp_enqueue_script('tarot-reader-js', plugins_url('assets/js/tarot-reader.js', TAROT_FILE), ['jquery'], '1.0', true);

    wp_localize_script('tarot-reader-js', 'tarot_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'rest_url' => rest_url('tarot/v1/'),
        'nonce' => wp_create_nonce('tarot_reader_nonce')
    ]);

    ob_start(); ?>

    <div id="tarot-reader" class="tarot-reader-container">
        <!-- Spread Selection -->
        <div id="spread-selection" class="spread-selection">
            <h3>Choose Your Spread</h3>
            <div class="spread-options">
                <div class="spread-option" data-spread="1card">
                    <h4>Single Card</h4>
                    <p>Quick insight for simple questions</p>
                    <span class="card-count">1 card</span>
                </div>
                <div class="spread-option active" data-spread="3card">
                    <h4>Past, Present, Future</h4>
                    <p>Classic 3-card spread for timeline reading</p>
                    <span class="card-count">3 cards</span>
                </div>
                <div class="spread-option" data-spread="celtic-cross">
                    <h4>Celtic Cross</h4>
                    <p>Comprehensive 10-card spread</p>
                    <span class="card-count">10 cards</span>
                </div>
                <div class="spread-option" data-spread="horseshoe">
                    <h4>Horseshoe</h4>
                    <p>7-card spread for detailed guidance</p>
                    <span class="card-count">7 cards</span>
                </div>
            </div>
        </div>

        <!-- Question Input -->
        <div id="question-section" class="question-section">
            <h3>What is your question?</h3>
            <textarea id="tarot-question" placeholder="Enter your question here..." rows="3"></textarea>
            <button id="start-reading" class="start-reading-btn">Begin Reading</button>
        </div>

        <!-- Shuffle Animation -->
        <div id="shuffle-section" class="shuffle-section" style="display: none;">
            <h3>Shuffling the cards...</h3>
            <div class="card-deck">
                <div class="card back" id="deck-card-1"></div>
                <div class="card back" id="deck-card-2"></div>
                <div class="card back" id="deck-card-3"></div>
                <div class="card back" id="deck-card-4"></div>
                <div class="card back" id="deck-card-5"></div>
            </div>
            <div class="shuffle-controls">
                <button id="shuffle-btn" class="shuffle-btn">Shuffle</button>
                <button id="draw-cards-btn" class="draw-cards-btn" style="display: none;">Draw Cards</button>
            </div>
        </div>

        <!-- Reading Results -->
        <div id="reading-results" class="reading-results" style="display: none;">
            <!-- Content will be populated by JavaScript -->
            <div class="reading-actions" style="margin-top: 40px;">
                <button id="new-reading" class="new-reading-btn">New Reading</button>
                <button id="save-reading" class="save-reading-btn">Save Reading</button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="loading-overlay" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Consulting the cards...</p>
        </div>
    </div>

    <?php return ob_get_clean();
});