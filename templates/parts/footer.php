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

$has_footer_menu = has_nav_menu('footer_menu');
$nap = function_exists('lf_nap_data') ? lf_nap_data() : ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
$social = is_array($entity['social'] ?? null) ? $entity['social'] : [];
$social_map = [
	'facebook' => ['label' => __('Facebook', 'leadsforward-core'), 'icon' => 'social-facebook'],
	'instagram' => ['label' => __('Instagram', 'leadsforward-core'), 'icon' => 'social-instagram'],
	'youtube' => ['label' => __('YouTube', 'leadsforward-core'), 'icon' => 'social-youtube'],
	'linkedin' => ['label' => __('LinkedIn', 'leadsforward-core'), 'icon' => 'social-linkedin'],
	'tiktok' => ['label' => __('TikTok', 'leadsforward-core'), 'icon' => 'social-tiktok'],
	'x' => ['label' => __('X', 'leadsforward-core'), 'icon' => 'social-x'],
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

if (!$has_footer_menu && !$has_nap) {
	return;
}
?>
<footer class="site-footer" role="contentinfo">
	<div class="lf-container">
		<?php if ($has_nap) : ?>
			<address class="lf-footer-nap">
				<?php if (!empty($nap['name'])) : ?>
					<span class="lf-footer-nap__name"><?php echo esc_html($nap['name']); ?></span>
				<?php endif; ?>
				<?php if (!empty($nap['address'])) : ?>
					<span class="lf-footer-nap__address"><?php echo nl2br(esc_html($nap['address'])); ?></span>
				<?php endif; ?>
				<?php if (!empty($nap['phone'])) : ?>
					<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $nap['phone'])); ?>" class="lf-footer-nap__phone"><?php echo esc_html($nap['phone']); ?></a>
				<?php endif; ?>
				<?php if (!empty($nap['email'])) : ?>
					<a href="mailto:<?php echo esc_attr($nap['email']); ?>" class="lf-footer-nap__email"><?php echo esc_html($nap['email']); ?></a>
				<?php endif; ?>
			</address>
		<?php endif; ?>
		<?php if ($has_footer_menu) : ?>
			<nav class="footer-nav" aria-label="<?php esc_attr_e('Footer', 'leadsforward-core'); ?>">
				<?php
				wp_nav_menu([
					'theme_location' => 'footer_menu',
					'container'     => false,
					'menu_class'    => 'footer-menu',
				]);
				?>
			</nav>
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
	</div>
</footer>
