<?php

add_action('admin_menu', function () {
    add_menu_page('Tarot Cards', 'Tarot Cards', 'manage_options', 'tarot-cards', 'tarot_admin_page');
    add_submenu_page('tarot-cards', 'All Cards', 'All Cards', 'manage_options', 'tarot-cards', 'tarot_admin_page');
    add_submenu_page('tarot-cards', 'Add Card', 'Add Card', 'manage_options', 'tarot-add', 'tarot_add_page');
    add_submenu_page('tarot-cards', 'Import', 'Import', 'manage_options', 'tarot-import', 'tarot_import_page');
    add_submenu_page('tarot-cards', 'Meanings', 'Meanings', 'manage_options', 'tarot-meanings', 'tarot_meanings_page');
    add_submenu_page('tarot-cards', 'Spreads', 'Spreads', 'manage_options', 'tarot-spreads', 'tarot_spreads_page');
    add_submenu_page('tarot-cards', 'Readings', 'Readings', 'manage_options', 'tarot-readings', 'tarot_readings_page');
    add_submenu_page('tarot-cards', 'Settings', 'Settings', 'manage_options', 'tarot-settings', 'tarot_settings_page');
    add_submenu_page('tarot-cards', 'Migration', 'Migration', 'manage_options', 'tarot-migration', 'tarot_migration_page');
    add_submenu_page(null, 'Edit', 'Edit', 'manage_options', 'tarot-edit', 'tarot_edit');
});

function tarot_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';
    
    // Handle delete
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        check_admin_referer('tarot_delete_' . $_GET['id']);
        $wpdb->delete($table, ['id' => intval($_GET['id'])]);
        echo '<div class="updated"><p>Card deleted successfully.</p></div>';
    }
    
    $cards = $wpdb->get_results("SELECT * FROM $table ORDER BY arcana DESC, number ASC", ARRAY_A);
    
    ?>
    <div class="wrap">
        <h1>Tarot Cards</h1>
        <a href="?page=tarot-add" class="button button-primary">Add New Card</a>
        <a href="?page=tarot-import" class="button">Import Cards</a>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Arcana</th>
                    <th>Suit</th>
                    <th>Number</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cards as $card): ?>
                <tr>
                    <td><?php echo esc_html($card['id']); ?></td>
                    <td><?php echo esc_html($card['name']); ?></td>
                    <td><?php echo esc_html($card['arcana']); ?></td>
                    <td><?php echo esc_html($card['suit'] ?: '-'); ?></td>
                    <td><?php echo esc_html($card['number']); ?></td>
                    <td>
                        <a href="<?php echo esc_url(home_url('/tarot/' . $card['slug'])); ?>" class="button" target="_blank">View</a>
                        <a href="?page=tarot-edit&id=<?php echo $card['id']; ?>" class="button">Edit</a>
                        <a href="<?php echo wp_nonce_url('?page=tarot-cards&action=delete&id=' . $card['id'], 'tarot_delete_' . $card['id']); ?>" 
                           class="button" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function tarot_add_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';
    $meanings_table = $wpdb->prefix . 'tarot_card_meanings';
    $contents_table = $wpdb->prefix . 'tarot_card_contents';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('tarot_add');
        
        // Insert card
        $card_data = [
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug']),
            'arcana' => sanitize_text_field($_POST['arcana']),
            'suit' => sanitize_text_field($_POST['suit']),
            'number' => intval($_POST['number']),
            'deck' => sanitize_text_field($_POST['deck']),
            'image' => sanitize_text_field($_POST['image']),
            'description' => sanitize_textarea_field($_POST['description'])
        ];
        
        $card_id = $wpdb->insert($table, $card_data);
        $card_id = $wpdb->insert_id;
        
        // Insert meanings
        $meaning_fields = [
            'upright_general' => ['upright', 'general'],
            'upright_love' => ['upright', 'love'],
            'upright_career' => ['upright', 'career'],
            'upright_finance' => ['upright', 'finance'],
            'upright_health' => ['upright', 'health'],
            'reversed_general' => ['reversed', 'general'],
            'reversed_love' => ['reversed', 'love'],
            'reversed_career' => ['reversed', 'career'],
            'reversed_finance' => ['reversed', 'finance'],
            'reversed_health' => ['reversed', 'health']
        ];

        foreach ($meaning_fields as $field => $keys) {
            list($type, $context) = $keys;
            $meaning_text = sanitize_textarea_field($_POST[$field] ?? '');
            
            if (!empty($meaning_text)) {
                $wpdb->insert($meanings_table, [
                    'card_id' => $card_id,
                    'type' => $type,
                    'context' => $context,
                    'meaning' => $meaning_text
                ]);
            }
        }

        // Insert general meaning fields
        $general_fields = [
            'upright_keywords' => ['upright', 'general', 'keywords'],
            'upright_yes_no' => ['upright', 'general', 'yes_no'],
            'upright_advice' => ['upright', 'general', 'advice'],
            'upright_message' => ['upright', 'general', 'message'],
            'reversed_keywords' => ['reversed', 'general', 'keywords'],
            'reversed_yes_no' => ['reversed', 'general', 'yes_no'],
            'reversed_advice' => ['reversed', 'general', 'advice'],
            'reversed_message' => ['reversed', 'general', 'message']
        ];

        foreach ($general_fields as $field => $keys) {
            list($type, $context, $column) = $keys;
            $value = sanitize_text_field($_POST[$field] ?? '');
            
            if (!empty($value)) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $meanings_table WHERE card_id=%d AND type=%s AND context=%s",
                    $card_id, $type, $context
                ));
                
                if ($existing) {
                    $wpdb->update($meanings_table, [$column => $value], ['id' => $existing->id]);
                } else {
                    $wpdb->insert($meanings_table, [
                        'card_id' => $card_id,
                        'type' => $type,
                        'context' => $context,
                        $column => $value
                    ]);
                }
            }
        }

        // Insert content
        $content_data = [
            'card_id' => $card_id,
            'title' => sanitize_text_field($_POST['custom_title'] ?? ''),
            'excerpt' => sanitize_textarea_field($_POST['custom_excerpt'] ?? ''),
            'content' => wp_kses_post($_POST['custom_content'] ?? '')
        ];

        if (!empty($content_data['title']) || !empty($content_data['content'])) {
            $wpdb->insert($contents_table, $content_data);
        }
        
        echo '<div class="updated"><p>Card added successfully. <a href="?page=tarot-cards">Back to list</a></p></div>';
        return;
    }
    
    // Enqueue media scripts
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-tabs');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    
    ?>
    <div class="wrap">
        <h1>Add New Card</h1>
        
        <form method="post" style="max-width: 100%;">
            <?php wp_nonce_field('tarot_add'); ?>
            
            <div id="tarot-tabs">
                <ul>
                    <li><a href="#tab-general">General</a></li>
                    <li><a href="#tab-upright">Upright</a></li>
                    <li><a href="#tab-reversed">Reversed</a></li>
                    <li><a href="#tab-content">Content</a></li>
                </ul>
                
                <div id="tab-general">
                    <table class="form-table">
                        <tr>
                            <th><label for="name">Name:</label></th>
                            <td><input type="text" id="name" name="name" required style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="slug">Slug:</label></th>
                            <td><input type="text" id="slug" name="slug" required style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="arcana">Arcana:</label></th>
                            <td>
                                <select id="arcana" name="arcana">
                                    <option value="major">Major</option>
                                    <option value="minor">Minor</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="suit">Suit:</label></th>
                            <td>
                                <select id="suit" name="suit">
                                    <option value="">None (Major Arcana)</option>
                                    <option value="cups">Cups</option>
                                    <option value="swords">Swords</option>
                                    <option value="wands">Wands</option>
                                    <option value="pentacles">Pentacles</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="number">Number:</label></th>
                            <td><input type="number" id="number" name="number" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="deck">Deck:</label></th>
                            <td><input type="text" id="deck" name="deck" placeholder="rider-waite" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="image">Image:</label></th>
                            <td>
                                <input type="text" id="image" name="image" placeholder="the-fool.jpg" style="width: 70%;">
                                <button type="button" id="upload_image_button" class="button">Choose Image</button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="description">Description:</label></th>
                            <td><textarea id="description" name="description" rows="3" style="width: 100%;"></textarea></td>
                        </tr>
                    </table>
                </div>
                
                <div id="tab-upright">
                    <table class="form-table">
                        <tr>
                            <th><label for="upright_general">General:</label></th>
                            <td><textarea id="upright_general" name="upright_general" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_love">Love:</label></th>
                            <td><textarea id="upright_love" name="upright_love" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_career">Career:</label></th>
                            <td><textarea id="upright_career" name="upright_career" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_finance">Finance:</label></th>
                            <td><textarea id="upright_finance" name="upright_finance" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_health">Health:</label></th>
                            <td><textarea id="upright_health" name="upright_health" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_keywords">Keywords:</label></th>
                            <td><input type="text" id="upright_keywords" name="upright_keywords" placeholder="new beginnings, freedom, adventure" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="upright_yes_no">Yes/No:</label></th>
                            <td><input type="text" id="upright_yes_no" name="upright_yes_no" placeholder="Yes" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="upright_advice">Advice:</label></th>
                            <td><textarea id="upright_advice" name="upright_advice" rows="3" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_message">Message:</label></th>
                            <td><textarea id="upright_message" name="upright_message" rows="3" style="width: 100%;"></textarea></td>
                        </tr>
                    </table>
                </div>
                
                <div id="tab-reversed">
                    <table class="form-table">
                        <tr>
                            <th><label for="reversed_general">General:</label></th>
                            <td><textarea id="reversed_general" name="reversed_general" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_love">Love:</label></th>
                            <td><textarea id="reversed_love" name="reversed_love" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_career">Career:</label></th>
                            <td><textarea id="reversed_career" name="reversed_career" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_finance">Finance:</label></th>
                            <td><textarea id="reversed_finance" name="reversed_finance" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_health">Health:</label></th>
                            <td><textarea id="reversed_health" name="reversed_health" rows="4" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_keywords">Keywords:</label></th>
                            <td><input type="text" id="reversed_keywords" name="reversed_keywords" placeholder="recklessness, fear, naive" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_yes_no">Yes/No:</label></th>
                            <td><input type="text" id="reversed_yes_no" name="reversed_yes_no" placeholder="No" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_advice">Advice:</label></th>
                            <td><textarea id="reversed_advice" name="reversed_advice" rows="3" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_message">Message:</label></th>
                            <td><textarea id="reversed_message" name="reversed_message" rows="3" style="width: 100%;"></textarea></td>
                        </tr>
                    </table>
                </div>
                
                <div id="tab-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_title">Custom Title:</label></th>
                            <td><input type="text" id="custom_title" name="custom_title" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_excerpt">Custom Excerpt:</label></th>
                            <td><textarea id="custom_excerpt" name="custom_excerpt" rows="3" style="width: 100%;"></textarea></td>
                        </tr>
                        <tr>
                            <th><label>Custom Content:</label></th>
                            <td><?php wp_editor('', 'custom_content'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large">Add Card</button>
                <a href="?page=tarot-cards" class="button">Cancel</a>
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#tarot-tabs').tabs();
        
        $('#upload_image_button').click(function(e) {
            e.preventDefault();
            
            var custom_uploader = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Choose Image'
                },
                multiple: false
            });
            
            custom_uploader.on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#image').val(attachment.url);
            });
            
            custom_uploader.open();
        });
    });
    </script>
    <?php
}

function tarot_edit() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';
    $meanings_table = $wpdb->prefix . 'tarot_card_meanings';
    $contents_table = $wpdb->prefix . 'tarot_card_contents';

    $id = intval($_GET['id']);
    $card = $wpdb->get_row("SELECT * FROM $table WHERE id=$id");

    if (!$card) {
        wp_die('Card not found');
    }

    // Get meanings
    $meanings = $wpdb->get_results("SELECT * FROM $meanings_table WHERE card_id=$id", OBJECT_K);

    // Organize meanings by type and context
    $card_meanings = [];
    foreach ($meanings as $m) {
        $card_meanings[$m->type][$m->context] = $m;
    }

    // Get content
    $content = $wpdb->get_row("SELECT * FROM $contents_table WHERE card_id=$id");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('tarot_edit');

        // Update card
        $wpdb->update($table, [
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug']),
            'arcana' => sanitize_text_field($_POST['arcana']),
            'suit' => sanitize_text_field($_POST['suit']),
            'number' => intval($_POST['number']),
            'deck' => sanitize_text_field($_POST['deck']),
            'image' => sanitize_text_field($_POST['image']),
            'description' => sanitize_textarea_field($_POST['description'])
        ], ['id' => $id]);

        // Update meanings
        $meaning_fields = [
            'upright_general' => ['upright', 'general'],
            'upright_love' => ['upright', 'love'],
            'upright_career' => ['upright', 'career'],
            'upright_finance' => ['upright', 'finance'],
            'upright_health' => ['upright', 'health'],
            'reversed_general' => ['reversed', 'general'],
            'reversed_love' => ['reversed', 'love'],
            'reversed_career' => ['reversed', 'career'],
            'reversed_finance' => ['reversed', 'finance'],
            'reversed_health' => ['reversed', 'health']
        ];

        foreach ($meaning_fields as $field => $keys) {
            list($type, $context) = $keys;
            $meaning_text = sanitize_textarea_field($_POST[$field] ?? '');
            
            if (isset($card_meanings[$type][$context])) {
                $wpdb->update($meanings_table, ['meaning' => $meaning_text], ['id' => $card_meanings[$type][$context]->id]);
            } else {
                $wpdb->insert($meanings_table, [
                    'card_id' => $id,
                    'type' => $type,
                    'context' => $context,
                    'meaning' => $meaning_text
                ]);
            }
        }

        // Update general meaning fields
        $general_fields = [
            'upright_keywords' => ['upright', 'general', 'keywords'],
            'upright_yes_no' => ['upright', 'general', 'yes_no'],
            'upright_advice' => ['upright', 'general', 'advice'],
            'upright_message' => ['upright', 'general', 'message'],
            'reversed_keywords' => ['reversed', 'general', 'keywords'],
            'reversed_yes_no' => ['reversed', 'general', 'yes_no'],
            'reversed_advice' => ['reversed', 'general', 'advice'],
            'reversed_message' => ['reversed', 'general', 'message']
        ];

        foreach ($general_fields as $field => $keys) {
            list($type, $context, $column) = $keys;
            $value = sanitize_text_field($_POST[$field] ?? '');
            
            if (isset($card_meanings[$type][$context])) {
                $wpdb->update($meanings_table, [$column => $value], ['id' => $card_meanings[$type][$context]->id]);
            }
        }

        // Update content
        $content_data = [
            'title' => sanitize_text_field($_POST['custom_title'] ?? ''),
            'excerpt' => sanitize_textarea_field($_POST['custom_excerpt'] ?? ''),
            'content' => wp_kses_post($_POST['custom_content'] ?? '')
        ];

        if ($content) {
            $wpdb->update($contents_table, $content_data, ['card_id' => $id]);
        } else {
            $content_data['card_id'] = $id;
            $wpdb->insert($contents_table, $content_data);
        }

        echo '<div class="updated"><p>Card saved.</p></div>';
        $card = $wpdb->get_row("SELECT * FROM $table WHERE id=$id");
        $meanings = $wpdb->get_results("SELECT * FROM $meanings_table WHERE card_id=$id", OBJECT_K);
        foreach ($meanings as $m) {
            $card_meanings[$m->type][$m->context] = $m;
        }
        $content = $wpdb->get_row("SELECT * FROM $contents_table WHERE card_id=$id");
    }

    // Enqueue media scripts
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-tabs');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    
    ?>

    <div class="wrap">
        <h1>Edit: <?php echo esc_html($card->name); ?></h1>
        <a href="<?php echo esc_url(home_url('/tarot/' . $card->slug)); ?>" class="button button-secondary" target="_blank" style="margin-bottom: 10px;">View Card Page</a>

        <form method="post" style="max-width: 100%;">
            <?php wp_nonce_field('tarot_edit'); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <div id="tarot-tabs">
                <ul>
                    <li><a href="#tab-general">General</a></li>
                    <li><a href="#tab-upright">Upright</a></li>
                    <li><a href="#tab-reversed">Reversed</a></li>
                    <li><a href="#tab-content">Content</a></li>
                </ul>
                
                <div id="tab-general">
                    <table class="form-table">
                        <tr>
                            <th><label for="name">Name:</label></th>
                            <td><input type="text" id="name" name="name" value="<?php echo esc_attr($card->name); ?>" required style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="slug">Slug:</label></th>
                            <td><input type="text" id="slug" name="slug" value="<?php echo esc_attr($card->slug); ?>" required style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="arcana">Arcana:</label></th>
                            <td>
                                <select id="arcana" name="arcana">
                                    <option value="major" <?php selected($card->arcana, 'major'); ?>>Major</option>
                                    <option value="minor" <?php selected($card->arcana, 'minor'); ?>>Minor</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="suit">Suit:</label></th>
                            <td>
                                <select id="suit" name="suit">
                                    <option value="" <?php selected($card->suit, ''); ?>>None (Major Arcana)</option>
                                    <option value="cups" <?php selected($card->suit, 'cups'); ?>>Cups</option>
                                    <option value="swords" <?php selected($card->suit, 'swords'); ?>>Swords</option>
                                    <option value="wands" <?php selected($card->suit, 'wands'); ?>>Wands</option>
                                    <option value="pentacles" <?php selected($card->suit, 'pentacles'); ?>>Pentacles</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="number">Number:</label></th>
                            <td><input type="number" id="number" name="number" value="<?php echo esc_attr($card->number); ?>" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="deck">Deck:</label></th>
                            <td><input type="text" id="deck" name="deck" value="<?php echo esc_attr($card->deck); ?>" placeholder="rider-waite" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="image">Image:</label></th>
                            <td>
                                <input type="text" id="image" name="image" value="<?php echo esc_attr($card->image); ?>" placeholder="the-fool.jpg" style="width: 70%;">
                                <button type="button" id="upload_image_button" class="button">Choose Image</button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="description">Description:</label></th>
                            <td><textarea id="description" name="description" rows="3" style="width: 100%;"><?php echo esc_textarea($card->description); ?></textarea></td>
                        </tr>
                    </table>
                </div>
                
                <div id="tab-upright">
                    <table class="form-table">
                        <tr>
                            <th><label for="upright_general">General:</label></th>
                            <td><textarea id="upright_general" name="upright_general" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['general']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_love">Love:</label></th>
                            <td><textarea id="upright_love" name="upright_love" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['love']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_career">Career:</label></th>
                            <td><textarea id="upright_career" name="upright_career" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['career']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_finance">Finance:</label></th>
                            <td><textarea id="upright_finance" name="upright_finance" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['finance']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_health">Health:</label></th>
                            <td><textarea id="upright_health" name="upright_health" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['health']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_keywords">Keywords:</label></th>
                            <td><input type="text" id="upright_keywords" name="upright_keywords" value="<?php echo esc_attr($card_meanings['upright']['general']->keywords ?? ''); ?>" placeholder="new beginnings, freedom, adventure" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="upright_yes_no">Yes/No:</label></th>
                            <td><input type="text" id="upright_yes_no" name="upright_yes_no" value="<?php echo esc_attr($card_meanings['upright']['general']->yes_no ?? ''); ?>" placeholder="Yes" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="upright_advice">Advice:</label></th>
                            <td><textarea id="upright_advice" name="upright_advice" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['general']->advice ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="upright_message">Message:</label></th>
                            <td><textarea id="upright_message" name="upright_message" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['general']->message ?? ''); ?></textarea></td>
                        </tr>
                    </table>
                </div>
                
                <div id="tab-reversed">
                    <table class="form-table">
                        <tr>
                            <th><label for="reversed_general">General:</label></th>
                            <td><textarea id="reversed_general" name="reversed_general" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['general']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_love">Love:</label></th>
                            <td><textarea id="reversed_love" name="reversed_love" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['love']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_career">Career:</label></th>
                            <td><textarea id="reversed_career" name="reversed_career" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['career']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_finance">Finance:</label></th>
                            <td><textarea id="reversed_finance" name="reversed_finance" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['finance']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_health">Health:</label></th>
                            <td><textarea id="reversed_health" name="reversed_health" rows="4" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['health']->meaning ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_keywords">Keywords:</label></th>
                            <td><input type="text" id="reversed_keywords" name="reversed_keywords" value="<?php echo esc_attr($card_meanings['reversed']['general']->keywords ?? ''); ?>" placeholder="recklessness, fear, naive" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_yes_no">Yes/No:</label></th>
                            <td><input type="text" id="reversed_yes_no" name="reversed_yes_no" value="<?php echo esc_attr($card_meanings['reversed']['general']->yes_no ?? ''); ?>" placeholder="No" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_advice">Advice:</label></th>
                            <td><textarea id="reversed_advice" name="reversed_advice" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['general']->advice ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="reversed_message">Message:</label></th>
                            <td><textarea id="reversed_message" name="reversed_message" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['general']->message ?? ''); ?></textarea></td>
                        </tr>
                    </table>
                </div>
                
                <div id="tab-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_title">Custom Title:</label></th>
                            <td><input type="text" id="custom_title" name="custom_title" value="<?php echo esc_attr($content->title ?? ''); ?>" style="width: 100%;"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_excerpt">Custom Excerpt:</label></th>
                            <td><textarea id="custom_excerpt" name="custom_excerpt" rows="3" style="width: 100%;"><?php echo esc_textarea($content->excerpt ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label>Custom Content:</label></th>
                            <td><?php wp_editor($content->content ?? '', 'custom_content'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large">Update Card</button>
                <a href="?page=tarot-cards" class="button">Cancel</a>
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#tarot-tabs').tabs();
        
        $('#upload_image_button').click(function(e) {
            e.preventDefault();
            
            var custom_uploader = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Choose Image'
                },
                multiple: false
            });
            
            custom_uploader.on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#image').val(attachment.url);
            });
            
            custom_uploader.open();
        });
    });
    </script>

    <?php
}

function tarot_meanings_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_card_meanings';
    
    $meanings = $wpdb->get_results("
        SELECT m.*, c.name as card_name 
        FROM $table m 
        LEFT JOIN {$wpdb->prefix}tarot_cards c ON m.card_id = c.id 
        ORDER BY c.name ASC, m.type ASC, m.context ASC
    ");

    echo '<div class="wrap"><h1>Tarot Card Meanings</h1>';
    echo '<table class="widefat fixed striped" style="margin-top: 20px;"><thead><tr><th>Card</th><th>Type</th><th>Context</th><th>Meaning</th><th>Keywords</th><th>Message</th><th>Advice</th></tr></thead><tbody>';

    foreach ($meanings as $m) {
        echo "<tr>
            <td><strong>{$m->card_name}</strong></td>
            <td>{$m->type}</td>
            <td>{$m->context}</td>
            <td>" . wp_trim_words($m->meaning, 10) . "</td>
            <td>{$m->keywords}</td>
            <td>" . wp_trim_words($m->message, 5) . "</td>
            <td>" . wp_trim_words($m->advice, 5) . "</td>
        </tr>";
    }
    
    echo '</tbody></table></div>';
}

function tarot_spreads_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_spreads';
    
    $spreads = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

    echo '<div class="wrap"><h1>Tarot Spreads</h1>';
    echo '<table class="widefat fixed striped" style="margin-top: 20px;"><thead><tr><th>Name</th><th>Slug</th><th>Cards</th><th>Description</th></tr></thead><tbody>';

    foreach ($spreads as $s) {
        echo "<tr>
            <td><strong>{$s->name}</strong></td>
            <td>{$s->slug}</td>
            <td>{$s->total_cards}</td>
            <td>" . wp_trim_words($s->description, 10) . "</td>
        </tr>";
    }
    
    echo '</tbody></table></div>';
}

function tarot_readings_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_readings';
    
    $readings = $wpdb->get_results("
        SELECT r.*, s.name as spread_name, u.display_name as user_name
        FROM $table r 
        LEFT JOIN {$wpdb->prefix}tarot_spreads s ON r.spread_id = s.id 
        LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
        ORDER BY r.created_at DESC LIMIT 50
    ");

    echo '<div class="wrap"><h1>Recent Readings</h1>';
    echo '<table class="widefat fixed striped" style="margin-top: 20px;"><thead><tr><th>User</th><th>Question</th><th>Spread</th><th>Date</th></tr></thead><tbody>';

    foreach ($readings as $r) {
        $user_name = $r->user_name ?: 'Anonymous';
        echo "<tr>
            <td>{$user_name}</td>
            <td>" . wp_trim_words($r->question, 8) . "</td>
            <td>{$r->spread_name}</td>
            <td>" . date('M j, Y H:i', strtotime($r->created_at)) . "</td>
        </tr>";
    }
    
    echo '</tbody></table></div>';
}

function tarot_settings_page() {
    global $wpdb;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tarot_settings_nonce'])) {
        if (!wp_verify_nonce($_POST['tarot_settings_nonce'], 'tarot_settings_save')) {
            wp_die('Security check failed');
        }

        $settings = [
            'openai_api_key' => sanitize_text_field($_POST['openai_api_key'] ?? ''),
            'openai_model' => sanitize_text_field($_POST['openai_model'] ?? 'gpt-3.5-turbo'),
            'ai_enabled' => isset($_POST['ai_enabled']) ? '1' : '0',
            'default_spread' => sanitize_text_field($_POST['default_spread'] ?? '3card'),
            'cache_enabled' => isset($_POST['cache_enabled']) ? '1' : '0',
            'cache_ttl' => intval($_POST['cache_ttl'] ?? 3600),
            'max_readings_per_hour' => intval($_POST['max_readings_per_hour'] ?? 10),
            'max_readings_per_day' => intval($_POST['max_readings_per_day'] ?? 50)
        ];

        foreach ($settings as $key => $value) {
            tarot_update_setting($key, $value);
        }

        echo '<div class="updated"><p>Settings saved successfully!</p></div>';
    }

    // Handle migration
    if (isset($_GET['migration']) && $_GET['migration'] === 'success') {
        echo '<div class="updated"><p>Database migration completed successfully!</p></div>';
    }

    // Get current settings
    $settings = [
        'openai_api_key' => tarot_get_setting('openai_api_key', ''),
        'openai_model' => tarot_get_setting('openai_model', 'gpt-3.5-turbo'),
        'ai_enabled' => tarot_get_setting('ai_enabled', '0'),
        'default_spread' => tarot_get_setting('default_spread', '3card'),
        'cache_enabled' => tarot_get_setting('cache_enabled', '1'),
        'cache_ttl' => tarot_get_setting('cache_ttl', '3600'),
        'max_readings_per_hour' => tarot_get_setting('max_readings_per_hour', '10'),
        'max_readings_per_day' => tarot_get_setting('max_readings_per_day', '50')
    ];

    // Get spreads for dropdown
    $spreads = $wpdb->get_results("SELECT slug, name FROM {$wpdb->prefix}tarot_spreads ORDER BY name ASC");

    ?>
    <div class="wrap">
        <h1>Tarot Pro Settings</h1>

        <div style="margin-bottom: 20px;">
            <h3>Database Migration</h3>
            <p>If you've updated the plugin and need to add new database features, run the migration below:</p>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="tarot_migrate">
                <?php wp_nonce_field('tarot_migrate'); ?>
                <button type="submit" class="button button-secondary" onclick="return confirm('This will modify your database. Make sure you have a backup. Continue?')">Run Database Migration</button>
            </form>
        </div>

        <form method="post">
            <?php wp_nonce_field('tarot_settings_save', 'tarot_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th colspan="2"><h3>OpenAI Settings</h3></th>
                </tr>
                <tr>
                    <th><label for="openai_api_key">OpenAI API Key:</label></th>
                    <td>
                        <input type="password" id="openai_api_key" name="openai_api_key"
                               value="<?php echo esc_attr($settings['openai_api_key']); ?>"
                               style="width: 400px;">
                        <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="openai_model">OpenAI Model:</label></th>
                    <td>
                        <select id="openai_model" name="openai_model">
                            <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            <option value="gpt-4" <?php selected($settings['openai_model'], 'gpt-4'); ?>>GPT-4</option>
                            <option value="gpt-4-turbo" <?php selected($settings['openai_model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ai_enabled">Enable AI Interpretations:</label></th>
                    <td>
                        <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1"
                               <?php checked($settings['ai_enabled'], '1'); ?>>
                        <label for="ai_enabled">Enable AI-powered card interpretations</label>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3>General Settings</h3></th>
                </tr>
                <tr>
                    <th><label for="default_spread">Default Spread:</label></th>
                    <td>
                        <select id="default_spread" name="default_spread">
                            <?php foreach ($spreads as $spread): ?>
                                <option value="<?php echo esc_attr($spread->slug); ?>"
                                        <?php selected($settings['default_spread'], $spread->slug); ?>>
                                    <?php echo esc_html($spread->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3>Cache Settings</h3></th>
                </tr>
                <tr>
                    <th><label for="cache_enabled">Enable Caching:</label></th>
                    <td>
                        <input type="checkbox" id="cache_enabled" name="cache_enabled" value="1"
                               <?php checked($settings['cache_enabled'], '1'); ?>>
                        <label for="cache_enabled">Cache AI interpretations for better performance</label>
                    </td>
                </tr>
                <tr>
                    <th><label for="cache_ttl">Cache TTL (seconds):</label></th>
                    <td>
                        <input type="number" id="cache_ttl" name="cache_ttl"
                               value="<?php echo esc_attr($settings['cache_ttl']); ?>" min="300" max="86400">
                        <p class="description">How long to keep cached interpretations (300 seconds to 24 hours)</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3>Rate Limiting</h3></th>
                </tr>
                <tr>
                    <th><label for="max_readings_per_hour">Max Readings per Hour:</label></th>
                    <td>
                        <input type="number" id="max_readings_per_hour" name="max_readings_per_hour"
                               value="<?php echo esc_attr($settings['max_readings_per_hour']); ?>" min="1" max="100">
                        <p class="description">Maximum readings allowed per IP address per hour</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="max_readings_per_day">Max Readings per Day:</label></th>
                    <td>
                        <input type="number" id="max_readings_per_day" name="max_readings_per_day"
                               value="<?php echo esc_attr($settings['max_readings_per_day']); ?>" min="1" max="500">
                        <p class="description">Maximum readings allowed per IP address per day</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary button-large">Save Settings</button>
            </p>
        </form>
    </div>
    <?php
}

function tarot_migration_page() {
    global $wpdb;

    $message = '';
    $error = '';

    // Handle migration run
    if (isset($_POST['run_migration']) && wp_verify_nonce($_POST['migration_nonce'], 'tarot_migration')) {
        try {
            // Include migration function
            if (function_exists('tarot_run_migration')) {
                tarot_run_migration();
                $message = 'Migration completed successfully!';
            } else {
                $error = 'Migration function not found. Please check migration.php file.';
            }
        } catch (Exception $e) {
            $error = 'Migration failed: ' . $e->getMessage();
        }
    }

    // Check current database status
    $tables_status = [];
    $tables = [
        'tarot_cards' => 'Cards',
        'tarot_card_meanings' => 'Card Meanings',
        'tarot_card_contents' => 'Card Contents',
        'tarot_spreads' => 'Spreads',
        'tarot_spread_positions' => 'Spread Positions',
        'tarot_readings' => 'Readings',
        'tarot_settings' => 'Settings',
        'tarot_ai_cache' => 'AI Cache'
    ];

    foreach ($tables as $table => $label) {
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table));
        $tables_status[$table] = [
            'exists' => $exists,
            'label' => $label
        ];
    }

    ?>
    <div class="wrap">
        <h1>Database Migration</h1>

        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Database Status</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables_status as $table => $status): ?>
                    <tr>
                        <td><code><?php echo esc_html($wpdb->prefix . $table); ?></code></td>
                        <td>
                            <span class="status-<?php echo $status['exists'] ? 'exists' : 'missing'; ?>">
                                <?php echo $status['exists'] ? '✓ Exists' : '✗ Missing'; ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($status['label']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Run Migration</h2>
            <p>This will update your database schema to the latest version, adding missing tables, columns, and constraints.</p>
            <p><strong>Warning:</strong> This operation cannot be undone. Make sure you have a backup of your database.</p>

            <form method="post">
                <?php wp_nonce_field('tarot_migration', 'migration_nonce'); ?>
                <p>
                    <button type="submit" name="run_migration" class="button button-primary button-large"
                            onclick="return confirm('Are you sure you want to run the migration? This will modify your database.')">
                        Run Migration
                    </button>
                </p>
            </form>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Schema Validation</h2>
            <p>Check if your database schema matches the expected structure:</p>

            <?php
            // Check tarot_cards table schema
            $cards_table = $wpdb->prefix . 'tarot_cards';
            $cards_fields = $wpdb->get_results("SHOW COLUMNS FROM $cards_table", ARRAY_A);
            $expected_cards_fields = ['id', 'name', 'slug', 'arcana', 'suit', 'number', 'deck', 'image', 'description', 'meta_data', 'created_at', 'updated_at'];

            $cards_field_names = array_column($cards_fields, 'Field');
            $missing_cards_fields = array_diff($expected_cards_fields, $cards_field_names);
            $extra_cards_fields = array_diff($cards_field_names, $expected_cards_fields);
            ?>

            <h3>Cards Table (<?php echo esc_html($cards_table); ?>)</h3>
            <ul>
                <?php if (empty($missing_cards_fields) && empty($extra_cards_fields)): ?>
                    <li style="color: green;">✓ Schema matches expected structure</li>
                <?php else: ?>
                    <?php if (!empty($missing_cards_fields)): ?>
                        <li style="color: red;">✗ Missing fields: <?php echo esc_html(implode(', ', $missing_cards_fields)); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($extra_cards_fields)): ?>
                        <li style="color: orange;">⚠ Extra fields: <?php echo esc_html(implode(', ', $extra_cards_fields)); ?></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <style>
        .status-exists { color: green; font-weight: bold; }
        .status-missing { color: red; font-weight: bold; }
        .card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
        .card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    </style>
    <?php
}