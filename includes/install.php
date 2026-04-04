<?php

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

function tarot_install() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Cards
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        slug VARCHAR(255),
        arcana ENUM('major','minor'),
        suit ENUM('cups','swords','wands','pentacles') NULL,
        number INT,
        deck VARCHAR(100),
        image VARCHAR(255),
        description TEXT,
        meta_data LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY slug (slug),
        KEY arcana (arcana),
        KEY suit (suit),
        KEY slug_arcana (slug, arcana)
    ) $charset_collate;");

    // 2. Meanings
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_card_meanings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT,
        type ENUM('upright','reversed'),
        context VARCHAR(50),
        meaning LONGTEXT,
        keywords TEXT,
        advice TEXT,
        message TEXT,
        yes_no VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY card_id (card_id),
        KEY type (type),
        KEY context (context)
    ) $charset_collate;");

    // 3. Contents
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_card_contents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT,
        title VARCHAR(255),
        content LONGTEXT,
        excerpt TEXT,
        sections LONGTEXT,
        extra LONGTEXT,
        KEY card_id (card_id)
    ) $charset_collate;");

    // 4. Spreads
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_spreads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        slug VARCHAR(255),
        total_cards INT,
        description TEXT,
        UNIQUE KEY slug (slug)
    ) $charset_collate;");

    // 5. Spread Positions
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_spread_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        spread_id INT,
        position_order INT,
        name VARCHAR(255),
        description TEXT,
        KEY spread_id (spread_id)
    ) $charset_collate;");

    // 6. Readings
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_readings (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NULL,
        question TEXT,
        spread_id INT,
        cards_json LONGTEXT,
        result_json LONGTEXT,
        ai_response LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY spread_id (spread_id)
    ) $charset_collate;");

    // flush_rewrite_rules();
}