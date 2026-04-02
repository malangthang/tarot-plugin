<?php

register_activation_hook(__FILE__, 'tarot_install');

function tarot_install() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

        $sql = "CREATE TABLE $table (
        id INT AUTO_INCREMENT PRIMARY KEY,

        name VARCHAR(255),
        slug VARCHAR(255) UNIQUE,
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

    ) {$wpdb->get_charset_collate()};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}