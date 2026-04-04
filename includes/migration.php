<?php

/**
 * Database Migration for Tarot Pro Plugin
 * Run this after updating to add new database features
 */

function tarot_run_migration() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Check if we need to add foreign keys and new columns
    $tables_to_check = [
        'tarot_card_meanings',
        'tarot_card_contents',
        'tarot_spreads',
        'tarot_readings'
    ];

    foreach ($tables_to_check as $table) {
        $table_name = $wpdb->prefix . $table;

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if (!$table_exists) {
            continue; // Skip if table doesn't exist
        }

        // Add missing columns and constraints
        switch ($table) {
            case 'tarot_card_meanings':
                // Add NOT NULL constraints and unique key
                $wpdb->query("ALTER TABLE $table_name
                    MODIFY COLUMN card_id INT NOT NULL,
                    MODIFY COLUMN type ENUM('upright','reversed') NOT NULL,
                    MODIFY COLUMN context VARCHAR(50) NOT NULL");

                // Add unique constraint if not exists
                $constraint_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_TYPE = 'UNIQUE'",
                    $table
                ));

                if (!$constraint_exists) {
                    $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY unique_meaning (card_id, type, context)");
                }

                // Add foreign key if not exists
                $fk_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = 'wp_tarot_cards'",
                    $table
                ));

                if (!$fk_exists) {
                    $wpdb->query("ALTER TABLE $table_name
                        ADD CONSTRAINT fk_meanings_card FOREIGN KEY (card_id)
                        REFERENCES {$wpdb->prefix}tarot_cards(id) ON DELETE CASCADE");
                }
                break;

            case 'tarot_card_contents':
                // Add NOT NULL constraint and foreign key
                $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN card_id INT NOT NULL");

                // Add foreign key if not exists
                $fk_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = 'wp_tarot_cards'",
                    $table
                ));

                if (!$fk_exists) {
                    $wpdb->query("ALTER TABLE $table_name
                        ADD CONSTRAINT fk_contents_card FOREIGN KEY (card_id)
                        REFERENCES {$wpdb->prefix}tarot_cards(id) ON DELETE CASCADE");
                }
                break;

            case 'tarot_spreads':
                // Add layout_json column if not exists
                $column_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW COLUMNS FROM $table_name WHERE Field = 'layout_json'"
                ));

                if (!$column_exists) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN layout_json LONGTEXT AFTER description");
                }

                // Add NOT NULL constraints
                $wpdb->query("ALTER TABLE $table_name
                    MODIFY COLUMN name VARCHAR(255) NOT NULL,
                    MODIFY COLUMN slug VARCHAR(255) NOT NULL,
                    MODIFY COLUMN total_cards INT NOT NULL");
                break;

            case 'tarot_readings':
                // Add new tracking columns if not exist
                $columns_to_add = [
                    'ip_address' => "VARCHAR(100)",
                    'user_agent' => "TEXT",
                    'status' => "VARCHAR(50) DEFAULT 'completed'"
                ];

                foreach ($columns_to_add as $column => $type) {
                    $column_exists = $wpdb->get_var($wpdb->prepare(
                        "SHOW COLUMNS FROM $table_name WHERE Field = %s",
                        $column
                    ));

                    if (!$column_exists) {
                        $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column $type");
                    }
                }

                // Add new indexes if not exist
                $indexes_to_add = ['created_at', 'status'];
                foreach ($indexes_to_add as $index) {
                    $index_exists = $wpdb->get_var($wpdb->prepare(
                        "SHOW INDEX FROM $table_name WHERE Key_name = %s",
                        $index
                    ));

                    if (!$index_exists) {
                        $wpdb->query("ALTER TABLE $table_name ADD KEY $index ($index)");
                    }
                }

                // Add foreign key for spread_id if not exists
                $fk_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND REFERENCED_TABLE_NAME = 'wp_tarot_spreads'",
                    $table
                ));

                if (!$fk_exists) {
                    $wpdb->query("ALTER TABLE $table_name
                        ADD CONSTRAINT fk_readings_spread FOREIGN KEY (spread_id)
                        REFERENCES {$wpdb->prefix}tarot_spreads(id) ON DELETE SET NULL");
                }
                break;
        }
    }

    // Create new tables if they don't exist
    // Settings table
    $settings_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $wpdb->prefix . 'tarot_settings'
    ));

    if (!$settings_exists) {
        dbDelta("CREATE TABLE {$wpdb->prefix}tarot_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY setting_key (setting_key)
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
            $wpdb->insert(
                $wpdb->prefix . 'tarot_settings',
                ['setting_key' => $setting[0], 'setting_value' => $setting[1]]
            );
        }
    }

    // AI Cache table
    $ai_cache_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $wpdb->prefix . 'tarot_ai_cache'
    ));

    if (!$ai_cache_exists) {
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
    }

    // Add deck index to cards table if not exists
    $cards_table = $wpdb->prefix . 'tarot_cards';
    $deck_index_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW INDEX FROM $cards_table WHERE Key_name = 'deck'"
    ));

    if (!$deck_index_exists) {
        $wpdb->query("ALTER TABLE $cards_table ADD KEY deck (deck)");
    }

    // Update existing readings to have status if they don't
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}tarot_readings SET status = 'completed' WHERE status IS NULL OR status = ''"
    ));

    error_log('Tarot Pro database migration completed successfully');
}

// Hook to run migration on plugin update
add_action('upgrader_process_complete', function($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        foreach ($options['plugins'] as $plugin) {
            if (strpos($plugin, 'tarot-pro.php') !== false) {
                tarot_run_migration();
                break;
            }
        }
    }
}, 10, 2);

// Manual migration function for admin use
function tarot_migrate_database() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    tarot_run_migration();

    wp_redirect(admin_url('admin.php?page=tarot-admin&migration=success'));
    exit;
}
add_action('admin_post_tarot_migrate', 'tarot_migrate_database');