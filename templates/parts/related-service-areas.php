<?php
/**
 * Related service areas for a service. Outputs only if relationship has items.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$area_ids = function_exists('get_field') ? get_field('lf_service_related_areas') : null;
if (empty($area_ids) || !is_array($area_ids)) {
	return;
}
$areas = array_filter(array_map('get_post', $area_ids));
if (empty($areas)) {
	return;
}
?>
<ul class="lf-related-links" role="list" aria-label="<?php esc_attr_e('Related service areas', 'leadsforward-core'); ?>">
	<?php foreach ($areas as $area) : if (!$area || $area->post_status !== 'publish') continue; ?>
		<li><a href="<?php echo esc_url(get_permalink($area)); ?>"><?php echo esc_html($area->post_title); ?></a></li>
	<?php endforeach; ?>
</ul>
