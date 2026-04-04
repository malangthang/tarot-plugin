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
    
    $json_file = TAROT_PATH . 'data/tarot.json';
    
    if (!file_exists($json_file)) {
        return new WP_Error('no_file', 'JSON file not found: ' . $json_file);
    }

    $json_content = file_get_contents($json_file);
    $data = json_decode($json_content, true);

    if (is_array($data) && isset($data['slug'])) {
        $data = [$data]; // If single object, wrap in array
    }

    if (!is_array($data)) {
        return new WP_Error('invalid_json', 'Invalid JSON format');
    }

    $imported = 0;

    foreach ($data as $card) {
        // Import card
        $card_data = [
            'name' => $card['meta']['name'] ?? '',
            'slug' => $card['slug'] ?? '',
            'arcana' => $card['meta']['arcana'] ?? '',
            'suit' => $card['meta']['suit'] ?: null,
            'number' => $card['meta']['number'] ?? 0,
            'deck' => $card['meta']['deck'] ?? '',
            'image' => $card['meta']['image'] ?? '',
            'description' => $card['content']['intro'] ?? ''
        ];

        // Check if card exists
        $existing_card = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tarot_cards WHERE slug=%s",
            $card_data['slug']
        ));

        if ($existing_card) {
            $wpdb->update("{$wpdb->prefix}tarot_cards", $card_data, ['id' => $existing_card->id]);
            $card_id = $existing_card->id;
        } else {
            $wpdb->insert("{$wpdb->prefix}tarot_cards", $card_data);
            $card_id = $wpdb->insert_id;
        }

        // Import meanings
        if (isset($card['meaning'])) {
            // Import upright meanings
            if (isset($card['meaning']['upright'])) {
                $upright = $card['meaning']['upright'];
                
                // General meaning
                if (isset($upright['general'])) {
                    tarot_import_meaning($card_id, 'upright', 'general', $upright['general'], 
                                       isset($upright['keywords']) ? implode(', ', $upright['keywords']) : '',
                                       $upright['advice'] ?? '', $upright['message'] ?? '', $upright['yes_no'] ?? '');
                }

                // Context meanings
                if (isset($upright['contexts'])) {
                    foreach ($upright['contexts'] as $context) {
                        tarot_import_meaning($card_id, 'upright', $context['type'], $context['text'], '', '', '', '');
                    }
                }
            }

            // Import reversed meanings
            if (isset($card['meaning']['reversed'])) {
                $reversed = $card['meaning']['reversed'];
                
                // General meaning
                if (isset($reversed['general'])) {
                    tarot_import_meaning($card_id, 'reversed', 'general', $reversed['general'], 
                                       isset($reversed['keywords']) ? implode(', ', $reversed['keywords']) : '',
                                       $reversed['advice'] ?? '', $reversed['message'] ?? '', $reversed['yes_no'] ?? '');
                }

                // Context meanings
                if (isset($reversed['contexts'])) {
                    foreach ($reversed['contexts'] as $context) {
                        tarot_import_meaning($card_id, 'reversed', $context['type'], $context['text'], '', '', '', '');
                    }
                }
            }
        }

        // Import content
        if (isset($card['content'])) {
            $content_data = [
                'card_id' => $card_id,
                'title' => $card['content']['seo']['title'] ?? '',
                'content' => $card['content']['body'] ?? '',
                'excerpt' => $card['content']['seo']['description'] ?? '',
                'sections' => isset($card['content']['sections']) ? json_encode($card['content']['sections']) : '',
                'extra' => isset($card['extra']) ? json_encode($card['extra']) : ''
            ];

            // Check if content exists
            $existing_content = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tarot_card_contents WHERE card_id=%d",
                $card_id
            ));

            if ($existing_content) {
                $wpdb->update("{$wpdb->prefix}tarot_card_contents", $content_data, ['id' => $existing_content->id]);
            } else {
                $wpdb->insert("{$wpdb->prefix}tarot_card_contents", $content_data);
            }
        }

        $imported++;
    }

    return $imported;
}

function tarot_import_meaning($card_id, $type, $context, $meaning, $keywords, $advice, $message, $yes_no) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'tarot_card_meanings';
    
    // Check if meaning exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE card_id=%d AND type=%s AND context=%s",
        $card_id, $type, $context
    ));

    $data = [
        'card_id' => $card_id,
        'type' => $type,
        'context' => $context,
        'meaning' => $meaning,
        'keywords' => $keywords,
        'advice' => $advice,
        'message' => $message,
        'yes_no' => $yes_no
    ];

    if ($existing) {
        $wpdb->update($table, $data, ['id' => $existing->id]);
    } else {
        $wpdb->insert($table, $data);
    }
}