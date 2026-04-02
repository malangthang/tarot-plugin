<?php

add_action('admin_menu', function () {
    add_menu_page('Tarot Cards', 'Tarot Cards', 'manage_options', 'tarot-cards', 'tarot_admin_page');
    add_submenu_page('tarot-cards', 'All Cards', 'All Cards', 'manage_options', 'tarot-cards', 'tarot_admin_page');
    add_submenu_page('tarot-cards', 'Add Card', 'Add Card', 'manage_options', 'tarot-add', 'tarot_add_page');
    add_submenu_page('tarot-cards', 'Import', 'Import', 'manage_options', 'tarot-import', 'tarot_import_page');
});

function tarot_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';
    
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete' && isset($_REQUEST['id'])) {
        check_admin_referer('tarot_delete');
        $wpdb->delete($table, ['id' => intval($_REQUEST['id'])]);
        echo '<div class="updated"><p>Card deleted.</p></div>';
    }
    
    $cards = $wpdb->get_results("SELECT * FROM $table ORDER BY number ASC");

    echo '<div class="wrap"><h1>Tarot Cards</h1>';
    echo '<a href="?page=tarot-add" class="button button-primary">Add New Card</a>';
    echo '<table class="widefat" style="margin-top: 20px;"><thead><tr><th>Name</th><th>Arcana</th><th>#</th><th>Custom</th><th>Actions</th></tr></thead><tbody>';

    foreach ($cards as $c) {
        $delete_url = wp_nonce_url("?page=tarot-cards&action=delete&id={$c->id}", 'tarot_delete');
        echo "<tr>
            <td><strong>{$c->name}</strong></td>
            <td>{$c->arcana}</td>
            <td>{$c->number}</td>
            <td>" . ($c->custom_content ? '✅' : '❌') . "</td>
            <td>
                <a href='?page=tarot-edit&id={$c->id}' class='button button-small'>Edit</a>
                <a href='{$delete_url}' class='button button-small button-link-delete' onclick=\"return confirm('Delete this card?');\">Delete</a>
            </td>
        </tr>";
    }
    
    echo '</tbody></table></div>';
}

add_action('admin_menu', function () {
    add_submenu_page(null, 'Edit', 'Edit', 'manage_options', 'tarot-edit', 'tarot_edit');
});

function tarot_add_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('tarot_add');
        
        $wpdb->insert($table, [
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug']),
            'arcana' => sanitize_text_field($_POST['arcana']),
            'number' => intval($_POST['number']),
            'deck' => sanitize_text_field($_POST['deck']),
            'image' => sanitize_text_field($_POST['image']),
            'custom_image' => sanitize_text_field($_POST['custom_image']),
            'description' => sanitize_textarea_field($_POST['description']),
            'meaning_upright' => sanitize_textarea_field($_POST['meaning_upright']),
            'meaning_reversed' => sanitize_textarea_field($_POST['meaning_reversed']),
            'health_upright' => sanitize_textarea_field($_POST['health_upright']),
            'health_reversed' => sanitize_textarea_field($_POST['health_reversed']),
            'custom_title' => sanitize_text_field($_POST['custom_title']),
            'custom_content' => wp_kses_post($_POST['custom_content']),
            'custom_excerpt' => sanitize_textarea_field($_POST['custom_excerpt'])
        ]);
        
        echo '<div class="updated"><p>Card added successfully. <a href="?page=tarot-cards">Back to list</a></p></div>';
        return;
    }
    
    ?>
    <div class="wrap">
        <h1>Add New Card</h1>
        <form method="post" style="max-width: 800px;">
            <?php wp_nonce_field('tarot_add'); ?>
            
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
                    <th><label for="number">Number:</label></th>
                    <td><input type="number" id="number" name="number" min="0" style="width: 100px;"></td>
                </tr>
                <tr>
                    <th><label for="deck">Deck:</label></th>
                    <td><input type="text" id="deck" name="deck" placeholder="rider-waite" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="image">Image:</label></th>
                    <td><input type="text" id="image" name="image" placeholder="image.jpg" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="custom_image">Custom Image:</label></th>
                    <td><input type="text" id="custom_image" name="custom_image" placeholder="custom-image.jpg" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="description">Description:</label></th>
                    <td><textarea id="description" name="description" rows="2" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="meaning_upright">Meaning Upright:</label></th>
                    <td><textarea id="meaning_upright" name="meaning_upright" rows="3" style="width: 100%;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="meaning_reversed">Meaning Reversed:</label></th>
                    <td><textarea id="meaning_reversed" name="meaning_reversed" rows="3" style="width: 100%;"></textarea></td>
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
    <?php
}

function tarot_edit() {
    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

    $id = intval($_GET['id']);
    $card = $wpdb->get_row("SELECT * FROM $table WHERE id=$id");

    if (!$card) {
        wp_die('Card not found');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('tarot_edit');

        $wpdb->update($table, [
            'custom_title' => sanitize_text_field($_POST['custom_title']),
            'custom_content' => wp_kses_post($_POST['custom_content']),
            'custom_excerpt' => sanitize_textarea_field($_POST['custom_excerpt']),
            'custom_image' => sanitize_text_field($_POST['custom_image'])
        ], ['id' => $id]);

        echo '<div class="updated"><p>Card saved.</p></div>';
        $card = $wpdb->get_row("SELECT * FROM $table WHERE id=$id");
    }

    ?>

    <div class="wrap">
        <h1>Edit: <?php echo esc_html($card->name); ?></h1>

        <form method="post" style="max-width: 800px;">
            <?php wp_nonce_field('tarot_edit'); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <table class="form-table">
                <tr>
                    <th colspan="2"><h2>Card Info (Read-only)</h2></th>
                </tr>
                <tr>
                    <th>Name:</th>
                    <td><?php echo esc_html($card->name); ?></td>
                </tr>                <tr>
                    <th><label for="custom_image">Custom Image:</label></th>
                    <td><input type="text" id="custom_image" name="custom_image" value="<?php echo esc_attr($card->custom_image); ?>" style="width: 100%;"></td>
                </tr>                <tr>
                    <th>Slug:</th>
                    <td><?php echo esc_html($card->slug); ?></td>
                </tr>
                <tr>
                    <th>Arcana:</th>
                    <td><?php echo esc_html($card->arcana); ?></td>
                </tr>
                <tr>
                    <th>Number:</th>
                    <td><?php echo esc_html($card->number); ?></td>
                </tr>
                <tr>
                    <th>Meaning (Upright):</th>
                    <td><pre><?php echo esc_html($card->meaning_upright); ?></pre></td>
                </tr>
                <tr>
                    <th>Meaning (Reversed):</th>
                    <td><pre><?php echo esc_html($card->meaning_reversed); ?></pre></td>
                </tr>

                <tr>
                    <th colspan="2"><h2>Custom Content</h2></th>
                </tr>
                <tr>
                    <th><label for="custom_title">Custom Title:</label></th>
                    <td><input type="text" id="custom_title" name="custom_title" value="<?php echo esc_attr($card->custom_title); ?>" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th><label for="custom_excerpt">Custom Excerpt:</label></th>
                    <td><textarea id="custom_excerpt" name="custom_excerpt" rows="3" style="width: 100%;"><?php echo esc_textarea($card->custom_excerpt); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Custom Content:</label></th>
                    <td><?php wp_editor($card->custom_content, 'custom_content'); ?></td>
                </tr>
            </table>

            <button type="submit" class="button button-primary">Save Changes</button>
            <a href="?page=tarot-cards" class="button">Back to List</a>
        </form>
    </div>

    <?php
}

add_action('admin_init', function () {
    if (!isset($_POST['id'])) return;

    check_admin_referer('tarot_edit');

    global $wpdb;
    $table = $wpdb->prefix . 'tarot_cards';

    $wpdb->update($table, [
        'custom_title' => sanitize_text_field($_POST['custom_title']),
        'custom_content' => wp_kses_post($_POST['custom_content']),
        'custom_excerpt' => sanitize_textarea_field($_POST['custom_excerpt'])
    ], ['id' => intval($_POST['id'])]);
});