<?php
/*
Plugin Name: Tarot AI Reader
*/

if (!defined('ABSPATH')) exit;

define('TAROT_PATH', plugin_dir_path(__FILE__));
define('TAROT_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'tarot_install');
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

require_once TAROT_PATH . 'includes/install.php';
require_once TAROT_PATH . 'includes/import.php';
require_once TAROT_PATH . 'includes/api.php';
require_once TAROT_PATH . 'includes/admin.php';
require_once TAROT_PATH . 'includes/frontend.php';

// assets
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('tarot-style', TAROT_URL . 'assets/style.css');
    wp_enqueue_script('tarot-js', TAROT_URL . 'assets/app.js', ['jquery'], null, true);

    wp_localize_script('tarot-js', 'tarot_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});

