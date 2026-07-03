<?php
/**
 * Plugin Name: CF7 Spam Blocker
 * Description: Blocks spam keywords, bots, and unsolicited marketing submissions in Contact Form 7.
 * Version: 1.0.0
 * Author: Umal Baneen
 * Author URI: https://umalbaneen023.github.io/umalbaneen.github.io/
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CF7_SPAM_BLOCK_PATH', plugin_dir_path( __FILE__ ) );

// Include modules
require_once CF7_SPAM_BLOCK_PATH . 'includes/admin-settings.php';
require_once CF7_SPAM_BLOCK_PATH . 'includes/spam-checks.php';

require_once CF7_SPAM_BLOCK_PATH . 'includes/comment-spam-checks.php';


register_activation_hook(__FILE__, 'cf7_spam_create_log_table');
function cf7_spam_create_log_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'cf7_spam_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` BIGINT(20) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `reason` TEXT NOT NULL,
    `ip` VARCHAR(100) NULL,
    `user_agent` TEXT NULL,
    `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `form_id` (`form_id`),
    KEY `submitted_at` (`submitted_at`)
) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
