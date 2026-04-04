<?php

function tarot_install() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

    $sql = "CREATE TABLE $table (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(255),
        slug VARCHAR(255),
        arcana VARCHAR(50),
        number INT,
        deck VARCHAR(100),

        image VARCHAR(255),
        custom_image VARCHAR(255),

        description TEXT,

        meaning_upright LONGTEXT,
        meaning_reversed LONGTEXT,

        love_upright TEXT,
        love_reversed TEXT,
        career_upright TEXT,
        career_reversed TEXT,
        finance_upright TEXT,
        finance_reversed TEXT,
        health_upright TEXT,
        health_reversed TEXT,

        keywords_upright TEXT,
        keywords_reversed TEXT,

        yes_no_upright VARCHAR(20),
        yes_no_reversed VARCHAR(20),

        advice_upright TEXT,
        advice_reversed TEXT,
        advice_message TEXT,

        custom_title VARCHAR(255),
        custom_content LONGTEXT,
        custom_excerpt TEXT,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)

    ) {$wpdb->get_charset_collate()};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Create tarot_card_meanings table
    $meanings_table = $wpdb->prefix . 'tarot_card_meanings';
    $meanings_sql = "CREATE TABLE $meanings_table (
        id INT NOT NULL AUTO_INCREMENT,
        card_id INT NOT NULL,
        type ENUM('upright','reversed'),
        context ENUM('general','love','career','finance','health'),
        meaning TEXT,
        keywords TEXT,
        advice TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (card_id) REFERENCES $table(id)
    ) {$wpdb->get_charset_collate()};";
    
    dbDelta($meanings_sql);

    // Create tarot_spreads table
    $spreads_table = $wpdb->prefix . 'tarot_spreads';
    $spreads_sql = "CREATE TABLE $spreads_table (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100),
        slug VARCHAR(100),
        total_cards INT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) {$wpdb->get_charset_collate()};";
    
    dbDelta($spreads_sql);

    // Create tarot_spread_positions table
    $positions_table = $wpdb->prefix . 'tarot_spread_positions';
    $positions_sql = "CREATE TABLE $positions_table (
        id INT NOT NULL AUTO_INCREMENT,
        spread_id INT NOT NULL,
        position_order INT,
        name VARCHAR(100),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (spread_id) REFERENCES $spreads_table(id)
    ) {$wpdb->get_charset_collate()};";
    
    dbDelta($positions_sql);

    // Create tarot_readings table
    $readings_table = $wpdb->prefix . 'tarot_readings';
    $readings_sql = "CREATE TABLE $readings_table (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NULL,
        question TEXT,
        spread_id INT NOT NULL,
        result_json JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (spread_id) REFERENCES $spreads_table(id)
    ) {$wpdb->get_charset_collate()};";
    
    dbDelta($readings_sql);

    // Flush rewrite rules to support /tarot/{slug} endpoints
    flush_rewrite_rules();
}