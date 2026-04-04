<?php

// REST API Endpoints
add_action('rest_api_init', 'tarot_register_api_routes');

function tarot_register_api_routes() {
    register_rest_route('tarot/v1', '/draw', [
        'methods' => 'GET',
        'callback' => 'tarot_api_draw_card',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('tarot/v1', '/spread', [
        'methods' => 'GET',
        'callback' => 'tarot_api_get_spread',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('tarot/v1', '/reading', [
        'methods' => 'POST',
        'callback' => 'tarot_api_create_reading',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('tarot/v1', '/reading/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'tarot_api_get_reading',
        'permission_callback' => '__return_true'
    ]);
}

// AJAX Endpoints for proper flow
add_action('wp_ajax_tarot_draw', 'tarot_ajax_draw_cards');
add_action('wp_ajax_nopriv_tarot_draw', 'tarot_ajax_draw_cards');

add_action('wp_ajax_tarot_interpret', 'tarot_ajax_interpret_reading');
add_action('wp_ajax_nopriv_tarot_interpret', 'tarot_ajax_interpret_reading');


// Draw single card
function tarot_api_draw_card($request) {
    global $wpdb;

    $cards = $wpdb->get_results("
        SELECT id, name, image 
        FROM tarot_cards 
        ORDER BY RAND() 
        LIMIT 3
    ");

    $result = [];

    foreach ($cards as $card) {
        $result[] = [
            'id' => $card->id,
            'name' => $card->name,
            'image' => $card->image,
            'orientation' => rand(0,1) ? 'reversed' : 'upright'
        ];
    }

    return $result;
}

// Get spread with positions
function tarot_api_get_spread($request) {
    global $wpdb;
    $spreads_table = $wpdb->prefix . 'tarot_spreads';
    $positions_table = $wpdb->prefix . 'tarot_spread_positions';

    $type = $request->get_param('type');

    // Default spreads
    $default_spreads = [
        '1card' => ['name' => 'Single Card', 'total_cards' => 1],
        '3card' => ['name' => 'Past, Present, Future', 'total_cards' => 3],
        'celtic-cross' => ['name' => 'Celtic Cross', 'total_cards' => 10],
        'horseshoe' => ['name' => 'Horseshoe', 'total_cards' => 7]
    ];

    if (!isset($default_spreads[$type])) {
        $type = '3card'; // default
    }

    $spread_data = $default_spreads[$type];

    // Check if spread exists in DB, if not create it
    $spread = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $spreads_table WHERE slug = %s",
        $type
    ));

    if (!$spread) {
        $wpdb->insert($spreads_table, [
            'name' => $spread_data['name'],
            'slug' => $type,
            'total_cards' => $spread_data['total_cards'],
            'description' => $spread_data['name'] . ' spread'
        ]);
        $spread_id = $wpdb->insert_id;

        // Create positions
        $positions = get_default_positions($type);
        foreach ($positions as $pos) {
            $wpdb->insert($positions_table, [
                'spread_id' => $spread_id,
                'position_order' => $pos['order'],
                'name' => $pos['name'],
                'description' => $pos['description']
            ]);
        }
    } else {
        $spread_id = $spread->id;
    }

    // Get positions
    $positions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $positions_table WHERE spread_id = %d ORDER BY position_order",
        $spread_id
    ), ARRAY_A);

    return [
        'spread' => [
            'id' => $spread_id,
            'name' => $spread_data['name'],
            'slug' => $type,
            'total_cards' => $spread_data['total_cards']
        ],
        'positions' => $positions
    ];
}

// Create reading
function tarot_api_create_reading($request) {
    global $wpdb;
    $readings_table = $wpdb->prefix . 'tarot_readings';
    $cards_table = $wpdb->prefix . 'tarot_cards';
    $meanings_table = $wpdb->prefix . 'tarot_card_meanings';

    require_once TAROT_PATH . 'includes/interpreter.php';

    $params = $request->get_json_params();
    $question = sanitize_text_field($params['question'] ?? '');
    $spread_type = sanitize_text_field($params['spread_type'] ?? '3card');

    // Get spread
    $spread_response = tarot_api_get_spread(new WP_REST_Request('GET', "/tarot/v1/spread?type=$spread_type"));
    if (is_wp_error($spread_response)) {
        return $spread_response;
    }
    $spread = $spread_response['spread'];
    $positions = $spread_response['positions'];

    // Draw cards without duplicates
    $total_cards = $spread['total_cards'];
    $drawn_cards = [];
    $used_card_ids = [];

    for ($i = 0; $i < $total_cards; $i++) {
        do {
            $card = $wpdb->get_row("SELECT * FROM $cards_table ORDER BY RAND() LIMIT 1", ARRAY_A);
        } while (in_array($card['id'], $used_card_ids));

        $used_card_ids[] = $card['id'];

        // Get meanings
        $meanings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $meanings_table WHERE card_id = %d",
            $card['id']
        ), ARRAY_A);

        $card_meanings = [];
        foreach ($meanings as $meaning) {
            $card_meanings[$meaning['type']][$meaning['context']] = $meaning;
        }

        $is_reversed = rand(0, 1);

        $drawn_cards[] = [
            'card' => $card,
            'meanings' => $card_meanings,
            'is_reversed' => $is_reversed,
            'orientation' => $is_reversed ? 'reversed' : 'upright'
        ];
    }

    // Generate interpretation
    $interpretation = TarotInterpreter::interpret($drawn_cards, $spread_type, $question);

    // Save reading
    $reading_data = [
        'user_id' => get_current_user_id(),
        'question' => $question,
        'spread_id' => $spread['id'],
        'cards_json' => wp_json_encode(array_map(function($card_data) {
            return [
                'card_id' => $card_data['card']['id'],
                'card_name' => $card_data['card']['name'],
                'position' => $card_data['position'],
                'is_reversed' => $card_data['is_reversed']
            ];
        }, $drawn_cards)),
        'result_json' => wp_json_encode($interpretation),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'status' => 'completed'
    ];

    $wpdb->insert($readings_table, $reading_data);
    $reading_id = $wpdb->insert_id;

    return [
        'reading_id' => $reading_id,
        'spread_type' => $spread_type,
        'spread' => $spread,
        'question' => $question,
        'cards' => array_map(function($card_data) {
            return [
                'card' => $card_data['card']['name'],
                'is_reversed' => $card_data['is_reversed']
            ];
        }, $drawn_cards),
        'interpretation' => $interpretation
    ];
}

// Get reading by ID
function tarot_api_get_reading($request) {
    global $wpdb;
    $readings_table = $wpdb->prefix . 'tarot_readings';

    $reading_id = $request->get_param('id');

    $reading = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $readings_table WHERE id = %d",
        $reading_id
    ), ARRAY_A);

    if (!$reading) {
        return new WP_Error('reading_not_found', 'Reading not found', ['status' => 404]);
    }

    return [
        'reading' => $reading,
        'cards' => json_decode($reading['cards_json'], true),
        'result' => json_decode($reading['result_json'], true),
        'ai_response' => $reading['ai_response']
    ];
}

// Helper function for default positions
function get_default_positions($type) {
    $positions = [];

    switch ($type) {
        case '1card':
            $positions[] = ['order' => 1, 'name' => 'Focus', 'description' => 'The main focus of your question'];
            break;

        case '3card':
            $positions[] = ['order' => 1, 'name' => 'Past', 'description' => 'What has led to the current situation'];
            $positions[] = ['order' => 2, 'name' => 'Present', 'description' => 'The current situation'];
            $positions[] = ['order' => 3, 'name' => 'Future', 'description' => 'What will come to pass'];
            break;

        case 'celtic-cross':
            $positions = [
                ['order' => 1, 'name' => 'Present', 'description' => 'The present situation'],
                ['order' => 2, 'name' => 'Challenge', 'description' => 'The challenge or obstacle'],
                ['order' => 3, 'name' => 'Distant Past', 'description' => 'Events from the distant past'],
                ['order' => 4, 'name' => 'Possible Outcome', 'description' => 'The possible outcome'],
                ['order' => 5, 'name' => 'Recent Past', 'description' => 'Events from the recent past'],
                ['order' => 6, 'name' => 'Near Future', 'description' => 'Events in the near future'],
                ['order' => 7, 'name' => 'Approach', 'description' => 'Your approach to the situation'],
                ['order' => 8, 'name' => 'External Influences', 'description' => 'External influences'],
                ['order' => 9, 'name' => 'Hopes and Fears', 'description' => 'Your hopes and fears'],
                ['order' => 10, 'name' => 'Final Outcome', 'description' => 'The final outcome']
            ];
            break;

        case 'horseshoe':
            $positions = [
                ['order' => 1, 'name' => 'Past', 'description' => 'The past influencing the situation'],
                ['order' => 2, 'name' => 'Present', 'description' => 'The current situation'],
                ['order' => 3, 'name' => 'Future', 'description' => 'The future outcome'],
                ['order' => 4, 'name' => 'Obstacles', 'description' => 'Obstacles to overcome'],
                ['order' => 5, 'name' => 'External Influences', 'description' => 'External influences'],
                ['order' => 6, 'name' => 'Hopes and Fears', 'description' => 'Your hopes and fears'],
                ['order' => 7, 'name' => 'Advice', 'description' => 'Advice for the situation']
            ];
            break;
    }

    return $positions;
}

// AJAX Functions for proper flow
function tarot_ajax_draw_cards() {
    // Security check
    check_ajax_referer('tarot_nonce', 'nonce');

    $spread_type = sanitize_text_field($_POST['spread_type'] ?? '3card');

    // Draw cards without duplicates
    $drawn_cards = tarot_draw_random_cards($spread_type);

    wp_send_json_success([
        'cards' => $drawn_cards,
        'spread_type' => $spread_type
    ]);
}

function tarot_ajax_interpret_reading() {
    // Security check
    check_ajax_referer('tarot_nonce', 'nonce');

    $cards_data = $_POST['cards'] ?? [];
    $spread_type = sanitize_text_field($_POST['spread_type'] ?? '3card');
    $question = sanitize_text_field($_POST['question'] ?? '');

    if (empty($cards_data)) {
        wp_send_json_error(['message' => 'No cards provided']);
        return;
    }

    // Generate interpretation
    require_once TAROT_PATH . 'includes/interpreter.php';
    $interpretation = TarotInterpreter::interpret($cards_data, $spread_type, $question);

    // Cache result for 1 hour
    $cache_key = 'tarot_' . md5($question . $spread_type . json_encode($cards_data));
    set_transient($cache_key, $interpretation, 3600);

    wp_send_json_success([
        'interpretation' => $interpretation,
        'cache_key' => $cache_key
    ]);
}

// Helper function for drawing cards (reusable)
function tarot_draw_random_cards($spread_type = '3card') {
    global $wpdb;
    $cards_table = $wpdb->prefix . 'tarot_cards';
    $meanings_table = $wpdb->prefix . 'tarot_card_meanings';

    // Get spread info
    $spread_response = tarot_api_get_spread(new WP_REST_Request('GET', "/tarot/v1/spread?type=$spread_type"));
    if (is_wp_error($spread_response)) {
        return [];
    }
    $spread = $spread_response['spread'];
    $positions = $spread_response['positions'];

    $total_cards = $spread['total_cards'];
    $drawn_cards = [];
    $used_card_ids = [];

    for ($i = 0; $i < $total_cards; $i++) {
        do {
            $card = $wpdb->get_row("SELECT * FROM $cards_table ORDER BY RAND() LIMIT 1", ARRAY_A);
        } while (in_array($card['id'], $used_card_ids));

        $used_card_ids[] = $card['id'];

        // Get meanings
        $meanings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $meanings_table WHERE card_id = %d",
            $card['id']
        ), ARRAY_A);

        $card_meanings = [];
        foreach ($meanings as $meaning) {
            $card_meanings[$meaning['type']][$meaning['context']] = $meaning;
        }

        $is_reversed = rand(0, 1);

        $drawn_cards[] = [
            'card' => $card,
            'meanings' => $card_meanings,
            'is_reversed' => $is_reversed,
            'orientation' => $is_reversed ? 'reversed' : 'upright',
            'position' => $positions[$i]['name'] ?? 'Position ' . ($i + 1)
        ];
    }

    return $drawn_cards;
}

// Settings helper functions
function tarot_get_setting($key, $default = '') {
    global $wpdb;
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM {$wpdb->prefix}tarot_settings WHERE setting_key = %s",
        $key
    ));
    return $value !== null ? $value : $default;
}

function tarot_update_setting($key, $value) {
    global $wpdb;
    return $wpdb->replace(
        $wpdb->prefix . 'tarot_settings',
        ['setting_key' => $key, 'setting_value' => $value]
    );
}