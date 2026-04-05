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
$areas = [];
if (!empty($area_ids) && is_array($area_ids)) {
	$areas = array_filter(array_map('get_post', $area_ids));
}
if (empty($areas)) {
	$areas = get_posts([
		'post_type'      => 'lf_service_area',
		'posts_per_page' => 8,
		'post_status'    => 'publish',
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	]);
}
if (empty($areas)) {
	return;
}
$areas = array_slice($areas, 0, 8);
$icon = isset($args['icon']) && is_string($args['icon']) ? $args['icon'] : '';
?>
<ul class="lf-related-links lf-cpt-driven-links" role="list" aria-label="<?php esc_attr_e('Related service areas', 'leadsforward-core'); ?>">
	<?php
	$origin_id = get_the_ID();
	foreach ($areas as $area) :
		if (!$area || $area->post_status !== 'publish') {
			continue;
		}
		$label = function_exists('lf_internal_link_label') ? lf_internal_link_label('area', $area, $origin_id) : $area->post_title;
	?>
		<li>
			<a href="<?php echo esc_url(get_permalink($area)); ?>">
				<?php if ($icon) : ?><span class="lf-related-links__icon"><?php echo $icon; ?></span><?php endif; ?>
				<span class="lf-related-links__label"><?php echo esc_html($label); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
