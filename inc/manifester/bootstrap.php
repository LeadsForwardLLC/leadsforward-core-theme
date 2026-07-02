<?php
/**
 * AI Manifester package (future WordPress plugin).
 *
 * Orchestrator client, Airtable import, n8n webhook + REST callbacks.
 * The theme loads this bootstrap; when extracted to a plugin, require this file from the plugin entrypoint.
 *
 * @package LeadsForward_Manifester
 * @since 0.1.170
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

if (!defined('LF_MANIFESTER_DIR')) {
	define('LF_MANIFESTER_DIR', __DIR__);
}

if (!defined('LF_MANIFESTER_URI')) {
	define('LF_MANIFESTER_URI', LF_THEME_URI . '/inc/manifester');
}

require_once LF_MANIFESTER_DIR . '/ai-studio.php';
require_once LF_MANIFESTER_DIR . '/ai-studio-wiring.php';
require_once LF_MANIFESTER_DIR . '/ai-studio-rest.php';
require_once LF_MANIFESTER_DIR . '/ai-studio-airtable.php';
require_once LF_MANIFESTER_DIR . '/admin-menu.php';
