<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WordPress Comment Spam Protection
 * Uses option key: _cf7_spam_rules_wordpress-comments
 */

/* ---------------------------------------------------------
 * STEP 1: Preprocess comment and flag spam
 * --------------------------------------------------------- */
add_filter('preprocess_comment', 'cf7_block_comment_spam');

function cf7_block_comment_spam($commentdata) {

    $raw_content = $commentdata['comment_content'] ?? '';
    $content     = strtolower($raw_content);
    $email       = strtolower($commentdata['comment_author_email'] ?? '');

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Load rules
    $rules = get_option('_cf7_spam_rules_wordpress-comments', [
        'textarea' => '',
        'email' => '',
        'limit_links_enabled' => 0,
        'max_links' => -1,
        'english_only' => 0,
    ]);

    /* -------- English-only -------- */
    if (!empty($rules['english_only'])) {
        if (preg_match('/[^\x00-\x7F]/u', $raw_content)) {

            cf7_log_spam(
                'comment',
                'Non-English characters detected',
                $ip,
                $ua,
                0
            );

            $commentdata['_cf7_force_spam']   = true;
            $commentdata['_cf7_spam_reason'] = 'Non-English characters detected';
            return $commentdata;
        }
    }

    /* -------- Keyword blocking -------- */
    if (!empty($rules['textarea'])) {
        foreach (explode(',', $rules['textarea']) as $kw) {
            $kw = trim(strtolower($kw));
            if ($kw !== '' && str_contains($content, $kw)) {

                cf7_log_spam(
                    'comment',
                    'Keyword match: ' . $kw,
                    $ip,
                    $ua,
                    0
                );

                $commentdata['_cf7_force_spam']   = true;
                $commentdata['_cf7_spam_reason'] = 'Keyword match: ' . $kw;
                return $commentdata;
            }
        }
    }

    /* -------- Email / domain blocking -------- */
    if (!empty($rules['email'])) {
        foreach (explode(',', $rules['email']) as $blocked) {
            $blocked = trim(strtolower($blocked));
            if ($blocked !== '' && str_contains($email, $blocked)) {

                cf7_log_spam(
                    'comment',
                    'Blocked email/domain: ' . $blocked,
                    $ip,
                    $ua,
                    0
                );

                $commentdata['_cf7_force_spam']   = true;
                $commentdata['_cf7_spam_reason'] = 'Blocked email/domain: ' . $blocked;
                return $commentdata;
            }
        }
    }

    /* -------- Link limit -------- */
    if (!empty($rules['limit_links_enabled'])) {
        preg_match_all('/https?:\/\//i', $content, $matches);
        $link_count = count($matches[0]);

        $max = isset($rules['max_links']) ? (int)$rules['max_links'] : -1;

        if ($max >= 0 && $link_count > $max) {

            cf7_log_spam(
                'comment',
                'Too many links: ' . $link_count,
                $ip,
                $ua,
                0
            );

            $commentdata['_cf7_force_spam']   = true;
            $commentdata['_cf7_spam_reason'] = 'Too many links: ' . $link_count;
            return $commentdata;
        }
    }

    return $commentdata;
}

/* ---------------------------------------------------------
 * STEP 2: Force flagged comments into Spam
 * --------------------------------------------------------- */
add_filter('pre_comment_approved', 'cf7_force_comment_spam', 10, 2);

function cf7_force_comment_spam($approved, $commentdata) {
    if (!empty($commentdata['_cf7_force_spam'])) {
        return 'spam';
    }
    return $approved;
}

/* ---------------------------------------------------------
 * STEP 3: Save spam reason as comment meta
 * --------------------------------------------------------- */
add_action('comment_post', 'cf7_save_comment_spam_reason', 10, 3);

function cf7_save_comment_spam_reason($comment_id, $approved, $commentdata) {
    if (!empty($commentdata['_cf7_spam_reason'])) {
        add_comment_meta(
            $comment_id,
            '_cf7_spam_reason',
            sanitize_text_field($commentdata['_cf7_spam_reason']),
            true
        );
    }
}

/* ---------------------------------------------------------
 * STEP 4: Show Spam Reason column in admin
 * --------------------------------------------------------- */
add_filter('manage_edit-comments_columns', 'cf7_add_spam_reason_column');
function cf7_add_spam_reason_column($columns) {
    $columns['cf7_spam_reason'] = 'Spam Reason';
    return $columns;
}

add_action('manage_comments_custom_column', 'cf7_render_spam_reason_column', 10, 2);
function cf7_render_spam_reason_column($column, $comment_id) {
    if ($column !== 'cf7_spam_reason') {
        return;
    }

    $comment = get_comment($comment_id);
    if (!$comment || $comment->comment_approved !== 'spam') {
        echo '—';
        return;
    }

    $reason = get_comment_meta($comment_id, '_cf7_spam_reason', true);
    echo $reason ? esc_html($reason) : '—';
}
