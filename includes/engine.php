<?php

function tarot_get_all_cards() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';
    return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
}

function tarot_shuffle_deck($cards) {
    shuffle($cards);
    return $cards;
}

function tarot_get_spread($type = '1card') {
    switch ($type) {
        case '3card':
            return ['past','present','future'];
        case '5card':
            return ['past','present','future','advice','outcome'];
        default:
            return ['general'];
    }
}

function tarot_draw_cards($type = '1card') {
    $cards = tarot_get_all_cards();
    $cards = tarot_shuffle_deck($cards);

    $positions = tarot_get_spread($type);
    $drawn = array_slice($cards, 0, count($positions));

    foreach ($drawn as $i => &$card) {
        $card['position'] = $positions[$i];
        $card['reversed'] = rand(0,1) === 1;
    }

    return $drawn;
}

function tarot_generate_basic_reading($cards) {
    $result = [];

    foreach ($cards as $card) {
        $meaning = $card['reversed']
            ? json_decode($card['meaning_reversed'], true)
            : json_decode($card['meaning_upright'], true);

        $result[] = [
            'name' => $card['name'],
            'position' => $card['position'],
            'reversed' => $card['reversed'],
            'meaning' => $meaning['general'] ?? ''
        ];
    }

    return $result;
}