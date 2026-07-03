# CF7 Spam Blocker

A WordPress plugin that blocks spam in **Contact Form 7 submissions and WordPress comments** — with per-form rules, a full spam log, and no third-party services or API fees.

![CF7 Spam Blocker settings](screenshot.png)

## The problem

Contact Form 7 ships with no spam protection. Sites running it get flooded with keyword spam, bot submissions, and link-stuffed messages — and WordPress comments suffer the same. External anti-spam services solve this with monthly fees and by sending your visitors' data to third parties. This plugin does it locally, inside WordPress.

## Features

**Contact Form 7 protection**
- **Per-form-group rules** — organize your forms into groups (e.g. quote forms vs. contact forms) and give each group its own blocking rules
- **Text field filtering** — block keywords/phrases in name and subject fields (comma-separated, case-insensitive)
- **Email blocking** — block specific addresses or entire domains; `.ru` domains are blocked automatically
- **Message body filtering** — whole-word keyword matching in textarea fields (regex `\b` boundaries, so blocking "loan" won't false-positive on "download")
- **Link limiting** — cap the number of links allowed per message, or set 0 to block all links; catches `http://`, `www.`, and bare domains

**WordPress comment protection**
- Same keyword, email/domain, and link-limit rules applied to comments
- **English-only mode** — optionally reject comments containing non-Latin characters (Cyrillic, CJK, emoji spam)
- Flagged comments are routed to the Spam queue (not deleted), with the block reason saved as comment meta
- A **"Spam Reason" column** in the Comments admin screen shows why each comment was flagged

**Spam log**
- Every blocked submission is recorded in a custom database table (`wp_cf7_spam_log`): type, reason, IP, user agent, timestamp, form ID
- Log viewer under **Contact → Spam Log** with per-entry delete
- Lets you audit what's being blocked and tune your rules with real data

## How it works

The plugin hooks into CF7's validation pipeline (`wpcf7_validate_text`, `wpcf7_validate_email`, `wpcf7_validate_textarea` and their required-field variants) and inspects each field against the rules configured for that form's group. Spam is rejected at validation time — the email is never sent, so junk never reaches the inbox or any connected CRM.

For comments, it hooks `preprocess_comment` to flag spam and `pre_comment_approved` to force flagged comments into the spam queue, preserving them for review instead of silently discarding them.

Rules are stored as WordPress options per form group; the log table is created on activation via `dbDelta()`.

## Installation

1. Download this repository as a ZIP (**Code → Download ZIP**)
2. In WordPress admin: **Plugins → Add New → Upload Plugin** → choose the ZIP
3. Activate **CF7 Spam Blocker** (this creates the spam log table)
4. Edit the form groups in `includes/spam-checks.php` to match your Contact Form 7 form IDs
5. Configure blocking rules under **Contact → Spam Rules**
6. Review blocked submissions under **Contact → Spam Log**

## Requirements

- WordPress 5.8+
- PHP 8.0+ (uses `str_contains`)
- Contact Form 7 (active) for the form-protection features; comment protection works standalone

## Roadmap

- Configurable form groups from the settings UI (no code editing)
- Honeypot field and submission-timing checks
- Export spam log to CSV

## Author

**Umal Baneen** — Full-Stack WordPress Developer
Portfolio: https://umalbaneen023.github.io/umalbaneen.github.io/
LinkedIn: https://www.linkedin.com/in/umal-baneen-89774a378/

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
