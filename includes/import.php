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

    tarot_import_from_json();
    
    echo '<div class="wrap">';
    echo '<div class="updated"><p><strong>Import completed!</strong> <a href="?page=tarot-cards">View all cards</a></p></div>';
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

    if (is_array($data) && isset($data['name'])) {
        $data = [$data]; // If single object, wrap in array
    }

    if (!is_array($data)) {
        wp_die('Invalid JSON format');
    }

    $imported = 0;

    foreach ($data as $card) {
        // Fallback for older data schema
        $health_upright = $card['health_upright'] ?? $card['spiritual_upright'] ?? '';
        $health_reversed = $card['health_reversed'] ?? $card['spiritual_reversed'] ?? '';

        // Check if card already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE slug=%s",
            $card['slug']
        ));

        if ($existing) {
            // Update existing
            $wpdb->update($table, [
                'name' => $card['name'],
                'arcana' => $card['arcana'] ?? '',
                'number' => $card['number'] ?? 0,
                'deck' => $card['deck'] ?? '',
                'image' => $card['image'] ?? '',
                'description' => $card['description'] ?? '',
                'meaning_upright' => $card['meaning_upright'] ?? '',
                'meaning_reversed' => $card['meaning_reversed'] ?? '',
                'love_upright' => $card['love_upright'] ?? '',
                'love_reversed' => $card['love_reversed'] ?? '',
                'career_upright' => $card['career_upright'] ?? '',
                'career_reversed' => $card['career_reversed'] ?? '',
                'finance_upright' => $card['finance_upright'] ?? '',
                'finance_reversed' => $card['finance_reversed'] ?? '',
                'health_upright' => $health_upright,
                'health_reversed' => $health_reversed,
                'spiritual_upright' => $card['spiritual_upright'] ?? $health_upright,
                'spiritual_reversed' => $card['spiritual_reversed'] ?? $health_reversed,
                'keywords_upright' => is_array($card['keywords_upright']) ? implode(', ', $card['keywords_upright']) : '',
                'keywords_reversed' => is_array($card['keywords_reversed']) ? implode(', ', $card['keywords_reversed']) : '',
                'yes_no_upright' => $card['yes_no_upright'] ?? '',
                'yes_no_reversed' => $card['yes_no_reversed'] ?? '',
                'advice_upright' => $card['advice_upright'] ?? '',
                'advice_reversed' => $card['advice_reversed'] ?? '',
                'advice_message' => $card['advice_message'] ?? ''
            ], ['slug' => $card['slug']]);
        } else {
            // Insert new
            $wpdb->insert($table, [
                'name' => $card['name'],
                'slug' => $card['slug'],
                'arcana' => $card['arcana'] ?? '',
                'number' => $card['number'] ?? 0,
                'deck' => $card['deck'] ?? '',
                'image' => $card['image'] ?? '',
                'description' => $card['description'] ?? '',
                'meaning_upright' => $card['meaning_upright'] ?? '',
                'meaning_reversed' => $card['meaning_reversed'] ?? '',
                'love_upright' => $card['love_upright'] ?? '',
                'love_reversed' => $card['love_reversed'] ?? '',
                'career_upright' => $card['career_upright'] ?? '',
                'career_reversed' => $card['career_reversed'] ?? '',
                'finance_upright' => $card['finance_upright'] ?? '',
                'finance_reversed' => $card['finance_reversed'] ?? '',
                'health_upright' => $health_upright,
                'health_reversed' => $health_reversed,
                'spiritual_upright' => $card['spiritual_upright'] ?? $health_upright,
                'spiritual_reversed' => $card['spiritual_reversed'] ?? $health_reversed,
                'keywords_upright' => is_array($card['keywords_upright']) ? implode(', ', $card['keywords_upright']) : '',
                'keywords_reversed' => is_array($card['keywords_reversed']) ? implode(', ', $card['keywords_reversed']) : '',
                'yes_no_upright' => $card['yes_no_upright'] ?? '',
                'yes_no_reversed' => $card['yes_no_reversed'] ?? '',
                'advice_upright' => $card['advice_upright'] ?? '',
                'advice_reversed' => $card['advice_reversed'] ?? '',
                'advice_message' => $card['advice_message'] ?? ''
            ]);
        }

        $imported++;
    }

    return $imported;
}