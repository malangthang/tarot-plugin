<?php

add_action('admin_menu', function () {
    add_menu_page('Tarot Cards', 'Tarot Cards', 'manage_options', 'tarot-cards', 'tarot_admin_page');
    add_submenu_page('tarot-cards', 'All Cards', 'All Cards', 'manage_options', 'tarot-cards', 'tarot_admin_page');
    add_submenu_page('tarot-cards', 'Add Card', 'Add Card', 'manage_options', 'tarot-add', 'tarot_add_page');
    add_submenu_page('tarot-cards', 'Import', 'Import', 'manage_options', 'tarot-import', 'tarot_import_page');
    add_submenu_page('tarot-cards', 'Meanings', 'Meanings', 'manage_options', 'tarot-meanings', 'tarot_meanings_page');
    add_submenu_page('tarot-cards', 'Spreads', 'Spreads', 'manage_options', 'tarot-spreads', 'tarot_spreads_page');
    add_submenu_page('tarot-cards', 'Readings', 'Readings', 'manage_options', 'tarot-readings', 'tarot_readings_page');
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
            'meaning_upright' => ['upright', 'general'],
            'meaning_reversed' => ['reversed', 'general'],
            'love_upright' => ['upright', 'love'],
            'love_reversed' => ['reversed', 'love'],
            'career_upright' => ['upright', 'career'],
            'career_reversed' => ['reversed', 'career'],
            'finance_upright' => ['upright', 'finance'],
            'finance_reversed' => ['reversed', 'finance'],
            'health_upright' => ['upright', 'health'],
            'health_reversed' => ['reversed', 'health']
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
            'keywords_upright' => ['upright', 'general', 'keywords'],
            'keywords_reversed' => ['reversed', 'general', 'keywords'],
            'advice_upright' => ['upright', 'general', 'advice'],
            'advice_reversed' => ['reversed', 'general', 'advice'],
            'yes_no_upright' => ['upright', 'general', 'yes_no'],
            'yes_no_reversed' => ['reversed', 'general', 'yes_no'],
            'advice_message' => ['upright', 'general', 'message']
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
    
    ?>
    <div class="wrap">
        <h1>Add New Card</h1>
        <form method="post" style="max-width: 800px;">
            <?php wp_nonce_field('tarot_add'); ?>
            
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px;">Meta Information</h2>
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
            </table>
            
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Description</h2>
            <table class="form-table">
                <tr>
                    <th><label for="description">Description:</label></th>
                    <td><textarea id="description" name="description" rows="3" style="width: 100%;"></textarea></td>
                </tr>
            </table>
            
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Meanings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="meaning_upright">General Upright:</label></th>
                    <td><textarea id="meaning_upright" name="meaning_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="meaning_reversed">General Reversed:</label></th>
                    <td><textarea id="meaning_reversed" name="meaning_reversed" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="love_upright">Love Upright:</label></th>
                    <td><textarea id="love_upright" name="love_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="love_reversed">Love Reversed:</label></th>
                    <td><textarea id="love_reversed" name="love_reversed" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="career_upright">Career Upright:</label></th>
                    <td><textarea id="career_upright" name="career_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="career_reversed">Career Reversed:</label></th>
                    <td><textarea id="career_reversed" name="career_reversed" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="finance_upright">Finance Upright:</label></th>
                    <td><textarea id="finance_upright" name="finance_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="finance_reversed">Finance Reversed:</label></th>
                    <td><textarea id="finance_reversed" name="finance_reversed" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="health_upright">Health Upright:</label></th>
                    <td><textarea id="health_upright" name="health_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="health_reversed">Health Reversed:</label></th>
                    <td><textarea id="health_reversed" name="health_reversed" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="keywords_upright">Keywords Upright:</label></th>
                    <td><input type="text" id="keywords_upright" name="keywords_upright" placeholder="new beginnings, freedom, adventure" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="keywords_reversed">Keywords Reversed:</label></th>
                    <td><input type="text" id="keywords_reversed" name="keywords_reversed" placeholder="recklessness, fear, naive" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="yes_no_upright">Yes/No Upright:</label></th>
                    <td><input type="text" id="yes_no_upright" name="yes_no_upright" placeholder="Yes" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="yes_no_reversed">Yes/No Reversed:</label></th>
                    <td><input type="text" id="yes_no_reversed" name="yes_no_reversed" placeholder="No" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="advice_upright">Advice Upright:</label></th>
                    <td><textarea id="advice_upright" name="advice_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="advice_reversed">Advice Reversed:</label></th>
                    <td><textarea id="advice_reversed" name="advice_reversed" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="advice_message">Advice Message:</label></th>
                    <td><textarea id="advice_message" name="advice_message" rows="3" style="width: 100%;"></textarea></td>
                </tr>
            </table>
            
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Content</h2>
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
            
            <button type="submit" class="button button-primary">Add Card</button>
            <a href="?page=tarot-cards" class="button">Cancel</a>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
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
            'meaning_upright' => ['upright', 'general'],
            'meaning_reversed' => ['reversed', 'general'],
            'love_upright' => ['upright', 'love'],
            'love_reversed' => ['reversed', 'love'],
            'career_upright' => ['upright', 'career'],
            'career_reversed' => ['reversed', 'career'],
            'finance_upright' => ['upright', 'finance'],
            'finance_reversed' => ['reversed', 'finance'],
            'health_upright' => ['upright', 'health'],
            'health_reversed' => ['reversed', 'health']
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

        // Update keywords, advice, message, yes_no for general meanings
        $general_fields = [
            'keywords_upright' => ['upright', 'general', 'keywords'],
            'keywords_reversed' => ['reversed', 'general', 'keywords'],
            'advice_upright' => ['upright', 'general', 'advice'],
            'advice_reversed' => ['reversed', 'general', 'advice'],
            'yes_no_upright' => ['upright', 'general', 'yes_no'],
            'yes_no_reversed' => ['reversed', 'general', 'yes_no'],
            'advice_message' => ['upright', 'general', 'message']
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
    
    ?>

    <div class="wrap">
        <h1>Edit: <?php echo esc_html($card->name); ?></h1>

        <form method="post" style="max-width: 800px;">
            <?php wp_nonce_field('tarot_edit'); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">

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
            </table>
            
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Description</h2>
            <table class="form-table">
                <tr>
                    <th><label for="description">Description:</label></th>
                    <td><textarea id="description" name="description" rows="3" style="width: 100%;"><?php echo esc_textarea($card->description); ?></textarea></td>
                </tr>
            </table>
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Meanings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="meaning_upright">General Upright:</label></th>
                    <td><textarea id="meaning_upright" name="meaning_upright" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['general']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="meaning_reversed">General Reversed:</label></th>
                    <td><textarea id="meaning_reversed" name="meaning_reversed" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['general']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="love_upright">Love Upright:</label></th>
                    <td><textarea id="love_upright" name="love_upright" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['love']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="love_reversed">Love Reversed:</label></th>
                    <td><textarea id="love_reversed" name="love_reversed" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['love']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="career_upright">Career Upright:</label></th>
                    <td><textarea id="career_upright" name="career_upright" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['career']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="career_reversed">Career Reversed:</label></th>
                    <td><textarea id="career_reversed" name="career_reversed" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['career']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="finance_upright">Finance Upright:</label></th>
                    <td><textarea id="finance_upright" name="finance_upright" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['finance']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="finance_reversed">Finance Reversed:</label></th>
                    <td><textarea id="finance_reversed" name="finance_reversed" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['finance']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="health_upright">Health Upright:</label></th>
                    <td><textarea id="health_upright" name="health_upright" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['health']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="health_reversed">Health Reversed:</label></th>
                    <td><textarea id="health_reversed" name="health_reversed" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['health']->meaning ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="keywords_upright">Keywords Upright:</label></th>
                    <td><input type="text" id="keywords_upright" name="keywords_upright" value="<?php echo esc_attr($card_meanings['upright']['general']->keywords ?? ''); ?>" placeholder="new beginnings, freedom, adventure" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="keywords_reversed">Keywords Reversed:</label></th>
                    <td><input type="text" id="keywords_reversed" name="keywords_reversed" value="<?php echo esc_attr($card_meanings['reversed']['general']->keywords ?? ''); ?>" placeholder="recklessness, fear, naive" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="yes_no_upright">Yes/No Upright:</label></th>
                    <td><input type="text" id="yes_no_upright" name="yes_no_upright" value="<?php echo esc_attr($card_meanings['upright']['general']->yes_no ?? ''); ?>" placeholder="Yes" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="yes_no_reversed">Yes/No Reversed:</label></th>
                    <td><input type="text" id="yes_no_reversed" name="yes_no_reversed" value="<?php echo esc_attr($card_meanings['reversed']['general']->yes_no ?? ''); ?>" placeholder="No" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="advice_upright">Advice Upright:</label></th>
                    <td><textarea id="advice_upright" name="advice_upright" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['general']->advice ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="advice_reversed">Advice Reversed:</label></th>
                    <td><textarea id="advice_reversed" name="advice_reversed" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['reversed']['general']->advice ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="advice_message">Advice Message:</label></th>
                    <td><textarea id="advice_message" name="advice_message" rows="3" style="width: 100%;"><?php echo esc_textarea($card_meanings['upright']['general']->message ?? ''); ?></textarea></td>
                </tr>
            </table>
            <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">Content</h2>
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
            
            <button type="submit" class="button button-primary">Update Card</button>
            <a href="?page=tarot-cards" class="button">Cancel</a>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
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