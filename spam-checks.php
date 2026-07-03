<?php

function cf7_log_spam($form_id, $type, $reason) {
    global $wpdb;
    $table = $wpdb->prefix . 'cf7_spam_log';

    $wpdb->insert($table, [
        'form_id'    => intval($form_id),
        'type'       => sanitize_text_field($type),
        'reason'     => sanitize_textarea_field($reason),
        'ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => sanitize_textarea_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ]);
}


// Define groups
$cf7_spam_groups = [
    'Full Quote Form, Burning man Form, Landing Page Form' => [ 9275, 25124, 1290],
    'Contact Form' => [1349],
    'Driver Job Application Form' => [4370],
];

function cf7_get_group_rules($form_id) {
    global $cf7_spam_groups;

    foreach ($cf7_spam_groups as $group_key => $ids) {
        if (in_array($form_id, $ids)) {
            $option_key = '_cf7_spam_rules_' . sanitize_title($group_key);
            return get_option($option_key, []);
        }
    }
    return [];
}

// ------------------- TEXT -------------------
add_filter('wpcf7_validate_text', 'cf7_spam_validate_text', 10, 2);
add_filter('wpcf7_validate_text*', 'cf7_spam_validate_text', 10, 2);

function cf7_spam_validate_text($result, $tag) {
    $form  = WPCF7_ContactForm::get_current();
    $rules = cf7_get_group_rules($form->id());

    $name  = $tag->name;
    $value = isset($_POST[$name]) ? sanitize_text_field($_POST[$name]) : '';

    if (!empty($rules['text'])) {
        foreach (array_map('trim', explode(',', $rules['text'])) as $word) {
        if ($word && stripos($value, $word) !== false) {
            cf7_log_spam($form->id(), 'Text', "field value includes: {$word}");
            $result->invalidate($tag, 'Your input is invalid.');
            break;
        }
}

    }
    return $result;
}

// ------------------- EMAIL -------------------
add_filter('wpcf7_validate_email', 'cf7_spam_validate_email', 10, 2);
add_filter('wpcf7_validate_email*', 'cf7_spam_validate_email', 10, 2);

function cf7_spam_validate_email($result, $tag) {
    $form  = WPCF7_ContactForm::get_current();
    $rules = cf7_get_group_rules($form->id());

    $name  = $tag->name;
    $value = isset($_POST[$name]) ? sanitize_email($_POST[$name]) : '';

    $blocked = false;

    // --- 1. Always block .ru domains ---
    if ($value && preg_match('/\.ru$/i', $value)) {
        $blocked = true;
        cf7_log_spam($form->id(), 'Email', "Blocked .ru domain: {$value}");
    }

    // --- 2. Block per-form configured rules ---
    if (!$blocked && !empty($rules['email'])) {
        foreach (array_map('trim', explode(',', $rules['email'])) as $bad) {
            if ($bad && stripos($value, $bad) !== false) {
                $blocked = true;
                cf7_log_spam($form->id(), 'Email', "Blocked email/domain: {$bad}");
                break;
            }
        }
    }

    // --- 3. Apply single unified error ---
    if ($blocked) {
        $result->invalidate($tag, 'Your email is invalid.');
    }

    return $result;
}



// ------------------- TEXTAREA -------------------
add_filter('wpcf7_validate_textarea', 'cf7_spam_validate_textarea', 10, 2);
add_filter('wpcf7_validate_textarea*', 'cf7_spam_validate_textarea', 10, 2);

function cf7_spam_validate_textarea($result, $tag) {
    $form  = WPCF7_ContactForm::get_current();
    $rules = cf7_get_group_rules($form->id());

    $name  = $tag->name;
    $value = isset($_POST[$name]) ? sanitize_textarea_field($_POST[$name]) : '';

    $is_spam = false;

    // 1. Check blocked keywords (whole words / phrases, case-insensitive)
    if (!empty($rules['textarea'])) {
        foreach (array_map('trim', explode(',', $rules['textarea'])) as $word) {
            if ($word) {
                // Use regex with \b for whole words
                $pattern = '/\b' . preg_quote($word, '/') . '\b/i';

                if (preg_match($pattern, $value)) {
                    $is_spam = true;
                    cf7_log_spam($form->id(), 'Textarea', "Blocked keyword/phrase: {$word}");
                    break;
                }
            }
        }
    }

    // 2. Check link limits
    if (
        !$is_spam &&
        !empty($rules['limit_links_enabled']) &&
        isset($rules['max_links'])
    ) {
        $max_links = intval($rules['max_links']);
        if ($max_links >= 0) {
            preg_match_all(
                '/(?:https?:\/\/|www\.)[^\s]+|[a-z0-9.-]+\.[a-z]{2,}(?:\/[^\s]*)?/i',
                $value,
                $matches
            );
            $link_count = count($matches[0]);

            if ($link_count > $max_links) {
                $is_spam = true;
                cf7_log_spam($form->id(), 'Textarea', "Too many links: {$link_count} > {$max_links}");
            }
        }
    }

    // Single unified error
    if ($is_spam) {
        $result->invalidate($tag, 'Your message contains a spam.');
    }

    return $result;
}
