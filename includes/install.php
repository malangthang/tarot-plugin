<?php

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

function tarot_install() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Cards
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        arcana ENUM('major','minor') NOT NULL,
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
        KEY deck (deck),
        KEY slug_arcana (slug, arcana)
    ) $charset_collate;");

    // 2. Meanings
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_card_meanings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        type ENUM('upright','reversed') NOT NULL,
        context VARCHAR(50) NOT NULL,
        meaning LONGTEXT,
        keywords TEXT,
        advice TEXT,
        message TEXT,
        yes_no VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY card_id (card_id),
        KEY type (type),
        KEY context (context),
        KEY card_type_context (card_id, type, context),
        UNIQUE KEY unique_meaning (card_id, type, context),
        FOREIGN KEY (card_id) REFERENCES {$wpdb->prefix}tarot_cards(id) ON DELETE CASCADE
    ) $charset_collate;");

    // 3. Contents
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_card_contents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_id INT NOT NULL,
        title VARCHAR(255),
        content LONGTEXT,
        excerpt TEXT,
        sections LONGTEXT,
        extra LONGTEXT,
        KEY card_id (card_id),
        FOREIGN KEY (card_id) REFERENCES {$wpdb->prefix}tarot_cards(id) ON DELETE CASCADE
    ) $charset_collate;");

    // 4. Spreads
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_spreads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        total_cards INT NOT NULL,
        description TEXT,
        layout_json LONGTEXT,
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
        ip_address VARCHAR(100),
        user_agent TEXT,
        status VARCHAR(50) DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY spread_id (spread_id),
        KEY created_at (created_at),
        KEY status (status),
        FOREIGN KEY (spread_id) REFERENCES {$wpdb->prefix}tarot_spreads(id) ON DELETE SET NULL
    ) $charset_collate;");

    // 7. Settings
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL,
        setting_value LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY setting_key (setting_key)
    ) $charset_collate;");

    // 8. AI Cache
    dbDelta("CREATE TABLE {$wpdb->prefix}tarot_ai_cache (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        hash_key VARCHAR(64) NOT NULL,
        question TEXT,
        cards_data LONGTEXT,
        spread_type VARCHAR(50),
        result LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY hash_key (hash_key),
        KEY created_at (created_at)
    ) $charset_collate;");

    // Insert default settings
    $default_settings = [
        ['openai_api_key', ''],
        ['openai_model', 'gpt-3.5-turbo'],
        ['ai_enabled', '0'],
        ['default_spread', '3card'],
        ['cache_enabled', '1'],
        ['cache_ttl', '3600'],
        ['max_readings_per_hour', '10'],
        ['max_readings_per_day', '50']
    ];

    foreach ($default_settings as $setting) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}tarot_settings WHERE setting_key = %s",
            $setting[0]
        ));

        if (!$existing) {
            $wpdb->insert(
                $wpdb->prefix . 'tarot_settings',
                ['setting_key' => $setting[0], 'setting_value' => $setting[1]]
            );
        }
    }

    // flush_rewrite_rules();
}