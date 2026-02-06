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
$has_nap = !empty(trim((string) ($nap['name'] ?? ''))) || !empty(trim((string) ($nap['phone'] ?? '')));

if (!$has_footer_menu && !$has_nap) {
	return;
}
?>
<footer class="site-footer" role="contentinfo">
	<div class="lf-container lf-container--full">
		<?php if ($has_nap) : ?>
			<address class="lf-footer-nap">
				<?php if (!empty($nap['name'])) : ?>
					<span class="lf-footer-nap__name"><?php echo esc_html($nap['name']); ?></span>
				<?php endif; ?>
				<?php if (!empty($nap['address'])) : ?>
					<span class="lf-footer-nap__address"><?php echo nl2br(esc_html($nap['address'])); ?></span>
				<?php endif; ?>
				<?php if (!empty($nap['phone'])) : ?>
					<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $nap['phone'])); ?>"><?php echo esc_html($nap['phone']); ?></a>
				<?php endif; ?>
				<?php if (!empty($nap['email'])) : ?>
					<a href="mailto:<?php echo esc_attr($nap['email']); ?>"><?php echo esc_html($nap['email']); ?></a>
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
	</div>
</footer>
