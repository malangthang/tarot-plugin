<?php

function tarot_import_page() {
    if (!isset($_POST['import_submit'])) {
        echo '<div class="wrap">';
        echo '<h1>Import Cards</h1>';
        echo '<p>Click the button below to import all 78 cards from the tarot.json file.</p>';
        echo '<form method="post" style="margin: 20px 0;">';
        wp_nonce_field('tarot_import');
        echo '<button type="submit" name="import_submit" class="button button-primary button-large">Import 78 Cards</button>';
        echo '</form>';
        echo '</div>';
        return;
    }

    check_admin_referer('tarot_import');

    $result = tarot_import_from_json();
    if (is_wp_error($result)) {
        echo '<div class="wrap">';
        echo '<div class="error"><p><strong>Import failed:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
        echo '</div>';
        return;
    }

    echo '<div class="wrap">';
    echo '<div class="updated"><p><strong>Import completed!</strong> ' . intval($result) . ' cards imported/updated. <a href="?page=tarot-cards">View all cards</a></p></div>';
    echo '</div>';
}

function tarot_import_from_json() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

    $json_file = TAROT_PATH . 'data/tarot.json';
    
    if (!file_exists($json_file)) {
        wp_die('JSON file not found: ' . $json_file);
    }

    $json_content = file_get_contents($json_file);
    $data = json_decode($json_content, true);

    if (is_array($data) && isset($data['slug'])) {
        $data = [$data]; // If single object, wrap in array
    }

    if (!is_array($data)) {
        wp_die('Invalid JSON format');
    }

    $imported = 0;

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($table_exists !== $table) {
        return new WP_Error('no_table', 'Database table "'.esc_html($table).'" does not exist. Activate plugin or re-install first.');
    }

    foreach ($data as $card) {
        // Map new JSON structure to database fields
        $name = $card['meta']['name'] ?? '';
        $slug = $card['slug'] ?? '';
        $arcana = $card['meta']['arcana'] ?? '';
        $number = $card['meta']['number'] ?? 0;
        $deck = $card['meta']['deck'] ?? '';
        $image = $card['meta']['image'] ?? '';
        $description = $card['content']['intro'] ?? '';

        $meaning_upright = $card['meaning']['upright']['general'] ?? '';
        $meaning_reversed = $card['meaning']['reversed']['general'] ?? '';

        $love_upright = $card['meaning']['upright']['contexts']['love'] ?? '';
        $love_reversed = $card['meaning']['reversed']['contexts']['love'] ?? '';
        $career_upright = $card['meaning']['upright']['contexts']['career'] ?? '';
        $career_reversed = $card['meaning']['reversed']['contexts']['career'] ?? '';
        $finance_upright = $card['meaning']['upright']['contexts']['finance'] ?? '';
        $finance_reversed = $card['meaning']['reversed']['contexts']['finance'] ?? '';
        $health_upright = $card['meaning']['upright']['contexts']['health'] ?? '';
        $health_reversed = $card['meaning']['reversed']['contexts']['health'] ?? '';

        $keywords_upright = is_array($card['meaning']['upright']['keywords'] ?? null) ? implode(', ', $card['meaning']['upright']['keywords']) : '';
        $keywords_reversed = is_array($card['meaning']['reversed']['keywords'] ?? null) ? implode(', ', $card['meaning']['reversed']['keywords']) : '';

        $yes_no_upright = $card['meaning']['upright']['yes_no'] ? 'Yes' : 'No';
        $yes_no_reversed = $card['meaning']['reversed']['yes_no'] ? 'Yes' : 'No';

        $advice_upright = $card['meaning']['upright']['advice'] ?? '';
        $advice_reversed = $card['meaning']['reversed']['advice'] ?? '';
        $advice_message = $card['meaning']['upright']['message'] ?? '';

        $custom_title = $card['content']['seo']['title'] ?? '';
        $custom_content = $card['content']['body'] ?? '';
        $custom_excerpt = $card['content']['seo']['description'] ?? '';

        // Check if card already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE slug=%s",
            $slug
        ));

        if ($existing) {
            // Update existing
            $wpdb->update($table, [
                'name' => $name,
                'arcana' => $arcana,
                'number' => $number,
                'deck' => $deck,
                'image' => $image,
                'description' => $description,
                'meaning_upright' => $meaning_upright,
                'meaning_reversed' => $meaning_reversed,
                'love_upright' => $love_upright,
                'love_reversed' => $love_reversed,
                'career_upright' => $career_upright,
                'career_reversed' => $career_reversed,
                'finance_upright' => $finance_upright,
                'finance_reversed' => $finance_reversed,
                'health_upright' => $health_upright,
                'health_reversed' => $health_reversed,
                'keywords_upright' => $keywords_upright,
                'keywords_reversed' => $keywords_reversed,
                'yes_no_upright' => $yes_no_upright,
                'yes_no_reversed' => $yes_no_reversed,
                'advice_upright' => $advice_upright,
                'advice_reversed' => $advice_reversed,
                'advice_message' => $advice_message,
                'custom_title' => $custom_title,
                'custom_content' => $custom_content,
                'custom_excerpt' => $custom_excerpt
            ], ['slug' => $slug]);
            if (!empty($wpdb->last_error)) {
                return new WP_Error('db_error', 'Update failed: '.esc_html($wpdb->last_error).
                    '. Query: '.esc_html($wpdb->last_query));
            }
        } else {
            // Insert new
            $wpdb->insert($table, [
                'name' => $name,
                'slug' => $slug,
                'arcana' => $arcana,
                'number' => $number,
                'deck' => $deck,
                'image' => $image,
                'description' => $description,
                'meaning_upright' => $meaning_upright,
                'meaning_reversed' => $meaning_reversed,
                'love_upright' => $love_upright,
                'love_reversed' => $love_reversed,
                'career_upright' => $career_upright,
                'career_reversed' => $career_reversed,
                'finance_upright' => $finance_upright,
                'finance_reversed' => $finance_reversed,
                'health_upright' => $health_upright,
                'health_reversed' => $health_reversed,
                'keywords_upright' => $keywords_upright,
                'keywords_reversed' => $keywords_reversed,
                'yes_no_upright' => $yes_no_upright,
                'yes_no_reversed' => $yes_no_reversed,
                'advice_upright' => $advice_upright,
                'advice_reversed' => $advice_reversed,
                'advice_message' => $advice_message,
                'custom_title' => $custom_title,
                'custom_content' => $custom_content,
                'custom_excerpt' => $custom_excerpt
            ]);
            if (!empty($wpdb->last_error)) {
                return new WP_Error('db_error', 'Insert failed: '.esc_html($wpdb->last_error).
                    '. Query: '.esc_html($wpdb->last_query));
            }
        }

        $imported++;
    }

    return $imported;
}