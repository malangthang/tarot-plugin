<?php
/*
Plugin Name: Tarot AI Reader
*/

if (!defined('ABSPATH')) exit;

define('TAROT_PATH', plugin_dir_path(__FILE__));
define('TAROT_URL', plugin_dir_url(__FILE__));
define('TAROT_FILE', __FILE__);

register_activation_hook(__FILE__, 'tarot_install');
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// Run migration on admin init to ensure database is up to date
add_action('admin_init', function() {
    if (function_exists('tarot_run_migration')) {
        tarot_run_migration();
    }
});

require_once TAROT_PATH . 'includes/install.php';
require_once TAROT_PATH . 'includes/import.php';
require_once TAROT_PATH . 'includes/interpreter.php';
require_once TAROT_PATH . 'includes/api.php';
require_once TAROT_PATH . 'includes/admin.php';
require_once TAROT_PATH . 'includes/frontend.php';
require_once TAROT_PATH . 'includes/migration.php';

// assets
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tarot-reader-style', TAROT_URL . 'assets/css/tarot-reader.css');
    wp_enqueue_script('tarot-reader-js', TAROT_URL . 'assets/js/tarot-reader.js', ['jquery'], null, true);

    wp_localize_script('tarot-reader-js', 'tarot_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tarot_nonce'),
        'rest_url' => rest_url('tarot/v1/')
    ]);
});

