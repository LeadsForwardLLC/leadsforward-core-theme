<?php
/**
 * Related services for a service area. Outputs only if relationship has items.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$service_ids = function_exists('get_field') ? get_field('lf_service_area_services') : null;
if (empty($service_ids) || !is_array($service_ids)) {
	return;
}
$services = array_filter(array_map('get_post', $service_ids));
if (empty($services)) {
	return;
}
?>
<ul class="lf-related-links" role="list" aria-label="<?php esc_attr_e('Related services', 'leadsforward-core'); ?>">
	<?php foreach ($services as $service) : if (!$service || $service->post_status !== 'publish') continue; ?>
		<li><a href="<?php echo esc_url(get_permalink($service)); ?>"><?php echo esc_html($service->post_title); ?></a></li>
	<?php endforeach; ?>
</ul>
