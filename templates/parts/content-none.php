<?php
/**
 * Content part when no posts found (e.g. empty archive or search).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<section class="no-results">
	<p><?php esc_html_e('No content found.', 'leadsforward-core'); ?></p>
</section>
