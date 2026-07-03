<?php
add_action('admin_menu', function() {
    add_submenu_page(
        'wpcf7',
        'Spam Rules',
        'Spam Rules',
        'manage_options',
        'cf7-shared-spam-rules',
        'cf7_shared_spam_rules_page'
    );

    add_submenu_page(
        'wpcf7',
        'Spam Log',
        'Spam Log',
        'manage_options',
        'cf7-spam-log',
        'cf7_spam_log_page'
    );
});

// Spam Log Page
function cf7_spam_log_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'cf7_spam_log';

    // --- Handle deletion first ---
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        if ($delete_id > 0) {
            $wpdb->delete($table, ['id' => $delete_id]);
        }
    }

    // --- Now fetch logs AFTER any deletion ---
    $logs  = $wpdb->get_results("SELECT * FROM $table ORDER BY submitted_at DESC LIMIT 100");

    echo '<div class="wrap"><h1>CF7 Spam Log</h1>';
    echo '<table class="widefat striped">';
    echo '<thead><tr>
        <th>Type</th>
        <th>Reason</th>
        <th>IP</th>
        <th>User Agent</th>
        <th>Date</th>
        <th>Form ID</th>
        <th>Action</th>
    </tr></thead><tbody>';

    if ($logs) {
        foreach ($logs as $log) {
            $delete_url = esc_url(
                wp_nonce_url(
                    admin_url('admin.php?page=cf7-spam-log&delete=' . intval($log->id)),
                    'cf7_delete_log_' . intval($log->id)
                )
            );
            echo '<tr>';
            echo '<td>' . esc_html($log->type) . '</td>';
            echo '<td>' . esc_html($log->reason) . '</td>';
            echo '<td>' . esc_html($log->ip) . '</td>';
            echo '<td style="max-width:300px;word-break:break-word;">' . esc_html($log->user_agent) . '</td>';
            echo '<td>' . esc_html($log->submitted_at) . '</td>';
            echo '<td>' . intval($log->form_id) . '</td>';
            echo '<td><a href="' . $delete_url . '" class="button">Delete</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">No spam logs found.</td></tr>';
    }

    echo '</tbody></table></div>';
}


// Spam Setting Page
function cf7_shared_spam_rules_page() {
    $cf7_spam_groups = [
        'Full Quote Form, Burning man Form, Landing Page Form' => [9275, 25124, 1290],
        'Contact Form' => [1349],
        'Driver Job Application Form' => [4370],
        'WordPress Comments' => ['comments'],
    ];

    if (isset($_POST['cf7_shared_save'])) {
    check_admin_referer('cf7_shared_spam_rules_save');
    foreach ($cf7_spam_groups as $group_key => $ids) {
        $option_key = '_cf7_spam_rules_' . sanitize_title($group_key);

        $rules = [
            'text'                => sanitize_text_field($_POST['cf7_group_rules'][$group_key]['text'] ?? ''),
            'email'               => sanitize_text_field($_POST['cf7_group_rules'][$group_key]['email'] ?? ''),
            'textarea'            => sanitize_text_field($_POST['cf7_group_rules'][$group_key]['textarea'] ?? ''),
            'limit_links_enabled' => !empty($_POST['cf7_group_rules'][$group_key]['limit_links_enabled']) ? 1 : 0,
            'max_links'           => intval($_POST['cf7_group_rules'][$group_key]['max_links'] ?? -1),
            'english_only'        => !empty($_POST['cf7_group_rules'][$group_key]['english_only']) ? 1 : 0,

        ];

        update_option($option_key, $rules);
    }

    echo '<div class="updated"><p>Spam rules updated.</p></div>';
}


    ?>
    <div class="wrap">

        <h1>Contact Form 7 Spam Rules</h1>
        <form method="post" novalidate>
            <?php wp_nonce_field('cf7_shared_spam_rules_save'); ?>

            <?php foreach ($cf7_spam_groups as $group_key => $ids): 
                $option_key = '_cf7_spam_rules_' . sanitize_title($group_key);
                $rules = get_option($option_key, ['text'=>'','email'=>'','textarea'=>'','limit_links_enabled' => 0,'max_links'=>-1,  'english_only' => 0,]);
            ?>
            <div class="cf7-group">
                <h2><?php echo esc_html($group_key); ?> <span style="opacity:.7;">(IDs: <?php echo implode(', ', $ids); ?>)</span></h2>

                <!-- Accordion blocks -->
                <div class="cf7-accordion">
    <button type="button" class="cf7-accordion-btn">Text Fields (Usually Name/Subject)</button>
    <div class="cf7-accordion-content">
        <input type="text" name="cf7_group_rules[<?php echo $group_key; ?>][text]" 
            value="<?php echo esc_attr($rules['text']); ?>" 
            placeholder="Comma separated keywords" class="regular-text">

        <ul style="font-size:13px; margin:10px 0 0 20px; list-style:disc;">
            <li><strong>Block keywords or phrases</strong> (comma-separated)</li>
            <li>Example: <code>marketing, free money, "win a prize"</code></li>
            <li>⚠️ Matching is case-insensitive and partial</li>
        </ul>
    </div>
</div>

<div class="cf7-accordion">
    <button type="button" class="cf7-accordion-btn">Email Fields</button>
    <div class="cf7-accordion-content">
        <input type="text" name="cf7_group_rules[<?php echo $group_key; ?>][email]" 
            value="<?php echo esc_attr($rules['email']); ?>" 
            placeholder="test@gmail.com, spam@example.com" class="regular-text">

        <ul style="font-size:13px; margin:10px 0 0 20px; list-style:disc;">
            <li><strong>Block by full email or domain</strong></li>
            <li>Example: <code>test@gmail.com, spam@example.com</code></li>
            <li>⚠️ Matching is case-insensitive</li>
        </ul>
    </div>
</div>

<div class="cf7-accordion">
    <button type="button" class="cf7-accordion-btn">Textarea Fields (Message/Long text)</button>
    <div class="cf7-accordion-content">
        <p>
            <input type="text" 
                   name="cf7_group_rules[<?php echo $group_key; ?>][textarea]" 
                   value="<?php echo esc_attr($rules['textarea']); ?>" 
                   placeholder="loan offer, click here" 
                   class="regular-text">
        </p>

        <ul style="font-size:13px; margin:10px 0 15px 20px; list-style:disc;">
            <li><strong>Block keywords/phrases inside message body</strong></li>
            <li>Example: <code>loan offer, click here</code></li>
            <li>⚠️ Case-insensitive partial matches</li>
        </ul>

        <div style="margin-top:15px; padding:10px; border-top:1px solid #eee;">
            <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
                <input type="checkbox" 
                       name="cf7_group_rules[<?php echo $group_key; ?>][limit_links_enabled]" 
                       value="1" <?php checked(!empty($rules['limit_links_enabled'])); ?> />
                Limit Links
                <span title="Set the maximum number of http:// or https:// links allowed inside textarea. Set 0 to block all links."
                      style="color:#f15c5c; cursor:help;">&#9432;</span>
            </label>
            <p style="margin:8px 0 0 24px;">
                <input type="number" min="0" 
                       name="cf7_group_rules[<?php echo $group_key; ?>][max_links]" 
                       value="<?php echo esc_attr($rules['max_links']); ?>" 
                       placeholder="0 = block all, 2 = allow 2 links" 
                       style="width:80px;"> 
                <span style="font-size:12px; opacity:.7;">Max links allowed (Set 0 for not even one link)</span>
            </p>
        </div>
        <?php if ($group_key === 'WordPress Comments'): ?>
<div style="margin-top:15px; padding:10px; border-top:1px solid #eee;">
    <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
        <input type="checkbox"
               name="cf7_group_rules[<?php echo $group_key; ?>][english_only]"
               value="1" <?php checked(!empty($rules['english_only'])); ?> />
        Allow English language only (comments)
    </label>
    <p style="margin:6px 0 0 24px; font-size:12px; opacity:.7;">
        Blocks Cyrillic, Arabic, Chinese, emoji spam, etc.
    </p>
</div>
<?php endif; ?>

    </div>
</div>


            <?php endforeach; ?>

            <p><input type="submit" name="cf7_shared_save" class="button button-primary" value="Save Changes"></p>
        </form>
    </div>

    <style>
        .cf7-group { margin-bottom:20px; padding:10px; border:1px solid #ddd; border-radius:6px; background:#fff; }
        .cf7-accordion { margin-top:8px; border:1px solid #eee; border-radius:4px; overflow:hidden; }
        .cf7-accordion-btn {
            background:#fff6ef; cursor:pointer; padding:12px; width:100%; text-align:left;
            border:none; outline:none; font-size:15px; font-weight:600; transition:.2s;
        }
        .cf7-accordion-btn:hover { background:#ffe9d6; }
        .cf7-accordion-content {
            display:none; padding:12px; background:#fff; border-top:1px solid #eee;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function(){
            document.querySelectorAll(".cf7-accordion-btn").forEach(function(btn){
                btn.addEventListener("click", function(){
                    this.classList.toggle("active");
                    let content = this.nextElementSibling;
                    content.style.display = content.style.display === "block" ? "none" : "block";
                });
            });
        });
    </script>
    <?php
}
