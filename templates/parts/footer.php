<?php
/**
 * Site footer. NAP block, service/area/legal links via menu. Layout by variation.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$nap = function_exists('lf_nap_data') ? lf_nap_data() : ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
$license = (string) ($entity['license_number'] ?? '');
$social = is_array($entity['social'] ?? null) ? $entity['social'] : [];
$social_map = [
	'facebook' => ['label' => __('Facebook', 'leadsforward-core'), 'icon' => 'facebook'],
	'instagram' => ['label' => __('Instagram', 'leadsforward-core'), 'icon' => 'instagram'],
	'youtube' => ['label' => __('YouTube', 'leadsforward-core'), 'icon' => 'youtube'],
	'linkedin' => ['label' => __('LinkedIn', 'leadsforward-core'), 'icon' => 'linkedin'],
	'tiktok' => ['label' => __('TikTok', 'leadsforward-core'), 'icon' => 'video'],
	'x' => ['label' => __('X', 'leadsforward-core'), 'icon' => 'twitter'],
];
$social_links = [];
foreach ($social_map as $key => $meta) {
	$url = isset($social[$key]) ? trim((string) $social[$key]) : '';
	if ($url !== '') {
		$social_links[] = [
			'url' => $url,
			'label' => $meta['label'],
			'icon' => $meta['icon'],
		];
	}
}
$has_nap = !empty(trim((string) ($nap['name'] ?? ''))) || !empty(trim((string) ($nap['phone'] ?? '')));

$services = get_posts([
	'post_type'      => 'lf_service',
	'post_status'    => 'publish',
	'posts_per_page' => 6,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
]);
$areas = get_posts([
	'post_type'      => 'lf_service_area',
	'post_status'    => 'publish',
	'posts_per_page' => 6,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
]);
$company_links = [];
foreach (['about-us', 'contact', 'reviews', 'blog'] as $slug) {
	$page = get_page_by_path($slug);
	if ($page instanceof \WP_Post) {
		$company_links[] = ['label' => get_the_title($page), 'url' => get_permalink($page)];
	}
}
$resource_links = [];
foreach (['sitemap', 'privacy-policy', 'terms-of-service'] as $slug) {
	$page = get_page_by_path($slug);
	if ($page instanceof \WP_Post) {
		$resource_links[] = ['label' => get_the_title($page), 'url' => get_permalink($page)];
	}
}
$privacy = get_page_by_path('privacy-policy');
$terms = get_page_by_path('terms-of-service');

if (!$has_nap && empty($services) && empty($areas) && empty($company_links) && empty($resource_links)) {
	return;
}
?>
<footer class="site-footer" role="contentinfo">
	<div class="lf-container">
		<div class="lf-footer-grid">
			<?php if ($has_nap) : ?>
				<address class="lf-footer-nap">
					<?php if (!empty($nap['name'])) : ?>
						<span class="lf-footer-nap__name"><?php echo esc_html($nap['name']); ?></span>
					<?php endif; ?>
					<?php if (!empty($nap['address'])) : ?>
						<span class="lf-footer-nap__item lf-footer-nap__address">
							<?php if (function_exists('lf_icon')) : ?>
								<span class="lf-footer-nap__icon" aria-hidden="true"><?php echo lf_icon('map-pin', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
							<?php endif; ?>
							<span class="lf-footer-nap__text"><?php echo nl2br(esc_html($nap['address'])); ?></span>
						</span>
					<?php endif; ?>
					<?php if (!empty($nap['phone'])) : ?>
						<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $nap['phone'])); ?>" class="lf-footer-nap__item lf-footer-nap__phone">
							<?php if (function_exists('lf_icon')) : ?>
								<span class="lf-footer-nap__icon" aria-hidden="true"><?php echo lf_icon('phone', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
							<?php endif; ?>
							<span class="lf-footer-nap__text"><?php echo esc_html($nap['phone']); ?></span>
						</a>
					<?php endif; ?>
					<?php if (!empty($nap['email'])) : ?>
						<a href="mailto:<?php echo esc_attr($nap['email']); ?>" class="lf-footer-nap__item lf-footer-nap__email">
							<?php if (function_exists('lf_icon')) : ?>
								<span class="lf-footer-nap__icon" aria-hidden="true"><?php echo lf_icon('mail', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
							<?php endif; ?>
							<span class="lf-footer-nap__text"><?php echo esc_html($nap['email']); ?></span>
						</a>
					<?php endif; ?>
					<?php if ($license !== '') : ?>
						<span class="lf-footer-nap__item lf-footer-nap__license">
							<?php if (function_exists('lf_icon')) : ?>
								<span class="lf-footer-nap__icon" aria-hidden="true"><?php echo lf_icon('shield', ['class' => 'lf-icon--sm lf-icon--inherit']); ?></span>
							<?php endif; ?>
							<span class="lf-footer-nap__text"><?php echo esc_html(sprintf(__('License: %s', 'leadsforward-core'), $license)); ?></span>
						</span>
					<?php endif; ?>
					<?php if (!empty($social_links)) : ?>
						<div class="lf-footer-social" aria-label="<?php esc_attr_e('Social media', 'leadsforward-core'); ?>">
							<?php foreach ($social_links as $item) : ?>
								<a class="lf-footer-social__link" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer">
									<?php
									if (function_exists('lf_icon')) {
										echo lf_icon($item['icon'], ['class' => 'lf-footer-social__icon lf-icon--sm lf-icon--inherit']);
									}
									?>
									<span class="screen-reader-text"><?php echo esc_html($item['label']); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</address>
			<?php endif; ?>
			<div class="lf-footer-columns">
				<?php if (!empty($services)) : ?>
					<div class="lf-footer-col">
						<h3 class="lf-footer-col__title"><?php esc_html_e('Services', 'leadsforward-core'); ?></h3>
						<ul class="lf-footer-links" role="list">
							<?php foreach ($services as $service) : ?>
								<li><a href="<?php echo esc_url(get_permalink($service)); ?>"><?php echo esc_html(get_the_title($service)); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if (!empty($areas)) : ?>
					<div class="lf-footer-col">
						<h3 class="lf-footer-col__title"><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></h3>
						<ul class="lf-footer-links" role="list">
							<?php foreach ($areas as $area) : ?>
								<li><a href="<?php echo esc_url(get_permalink($area)); ?>"><?php echo esc_html(get_the_title($area)); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if (!empty($company_links)) : ?>
					<div class="lf-footer-col">
						<h3 class="lf-footer-col__title"><?php esc_html_e('Company', 'leadsforward-core'); ?></h3>
						<ul class="lf-footer-links" role="list">
							<?php foreach ($company_links as $item) : ?>
								<li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if (!empty($resource_links)) : ?>
					<div class="lf-footer-col">
						<h3 class="lf-footer-col__title"><?php esc_html_e('Resources', 'leadsforward-core'); ?></h3>
						<ul class="lf-footer-links" role="list">
							<?php foreach ($resource_links as $item) : ?>
								<li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="lf-footer-bottom">
			<div class="lf-footer-legal">
				<?php if ($privacy instanceof \WP_Post) : ?>
					<a href="<?php echo esc_url(get_permalink($privacy)); ?>"><?php echo esc_html(get_the_title($privacy)); ?></a>
				<?php endif; ?>
				<?php if ($terms instanceof \WP_Post) : ?>
					<a href="<?php echo esc_url(get_permalink($terms)); ?>"><?php echo esc_html(get_the_title($terms)); ?></a>
				<?php endif; ?>
			</div>
			<div class="lf-footer-copy">
				<?php echo esc_html(sprintf(__('© %1$s %2$s. All rights reserved.', 'leadsforward-core'), date_i18n('Y'), $nap['name'] ?? get_bloginfo('name'))); ?>
			</div>
		</div>
	</div>
</footer>
