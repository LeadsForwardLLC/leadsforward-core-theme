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
$services = [];
if (!empty($service_ids) && is_array($service_ids)) {
	$services = array_filter(array_map('get_post', $service_ids));
}
if (empty($services)) {
	$services = get_posts([
		'post_type'      => 'lf_service',
		'posts_per_page' => 8,
		'post_status'    => 'publish',
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	]);
}
if (empty($services)) {
	return;
}
$services = array_slice($services, 0, 8);
?>
<ul class="lf-related-links" role="list" aria-label="<?php esc_attr_e('Related services', 'leadsforward-core'); ?>">
	<?php
	$origin_id = get_the_ID();
	foreach ($services as $service) :
		if (!$service || $service->post_status !== 'publish') {
			continue;
		}
		$label = function_exists('lf_internal_link_label') ? lf_internal_link_label('service', $service, $origin_id) : $service->post_title;
	?>
		<li><a href="<?php echo esc_url(get_permalink($service)); ?>"><?php echo esc_html($label); ?></a></li>
	<?php endforeach; ?>
</ul>
