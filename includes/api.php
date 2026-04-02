<?php

add_action('wp_ajax_tarot_draw_3', 'tarot_draw_3');
add_action('wp_ajax_nopriv_tarot_draw_3', 'tarot_draw_3');

function tarot_draw_3() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

    $cards = $wpdb->get_results("SELECT * FROM $table ORDER BY RAND() LIMIT 3", ARRAY_A);

    $result = [];

    foreach ($cards as $c) {
        $result[] = [
            'card' => $c,
            'reversed' => rand(0,1)
        ];
    }

    wp_send_json($result);
}

// AI
add_action('wp_ajax_tarot_ai', 'tarot_ai');
add_action('wp_ajax_nopriv_tarot_ai', 'tarot_ai');

function tarot_ai() {

    $question = $_POST['question'];
    $cards = $_POST['cards'];

    $prompt = "Câu hỏi: $question\n";

    foreach ($cards as $i => $c) {
        $name = $c['card']['name'];
        $meaning = $c['reversed'] ? $c['card']['meaning_reversed'] : $c['card']['meaning_upright'];
        $pos = ['Past','Present','Future'][$i];

        $prompt .= "$pos: $name - $meaning\n";
    }

    $res = wp_remote_post("https://api.openai.com/v1/chat/completions", [
        'headers' => [
            'Authorization' => 'Bearer YOUR_KEY',
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role'=>'user','content'=>$prompt]
            ]
        ])
    ]);

    $body = json_decode(wp_remote_retrieve_body($res), true);

    wp_send_json([
        'text' => $body['choices'][0]['message']['content']
    ]);
}