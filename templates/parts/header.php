<?php
/**
 * Site header. Layout variants by variation profile; logo slot, nav, CTA, phone.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$has_header_menu = has_nav_menu('header_menu');
$has_utility_menu = has_nav_menu('utility_menu');
$profile = function_exists('lf_get_variation_profile') ? lf_get_variation_profile() : 'a';
$layout = in_array($profile, ['b', 'e'], true) ? 'center' : 'left';
$cta_text = function_exists('lf_get_option') ? lf_get_option('lf_cta_primary_text', 'option') : '';
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$show_cta = $cta_text !== '' && $profile !== 'a';
$show_phone = $cta_phone !== '' && in_array($profile, ['b', 'c', 'e'], true);

if (!$has_header_menu && !$has_utility_menu && !$show_cta && !$show_phone) {
	return;
}
?>
<header class="site-header" role="banner" data-layout="<?php echo esc_attr($layout); ?>">
	<div class="lf-container lf-container--full">
		<?php if ($has_utility_menu) : ?>
			<nav class="utility-nav" aria-label="<?php esc_attr_e('Utility', 'leadsforward-core'); ?>">
				<?php
				wp_nav_menu([
					'theme_location' => 'utility_menu',
					'container'     => false,
					'menu_class'    => 'utility-menu',
				]);
				?>
			</nav>
		<?php endif; ?>
		<?php if ($has_header_menu) : ?>
			<nav class="header-nav" aria-label="<?php esc_attr_e('Primary', 'leadsforward-core'); ?>">
				<?php
				wp_nav_menu([
					'theme_location' => 'header_menu',
					'container'     => false,
					'menu_class'    => 'header-menu',
				]);
				?>
			</nav>
		<?php endif; ?>
		<?php if ($show_phone) : ?>
			<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $cta_phone)); ?>" class="lf-header-phone"><?php echo esc_html($cta_phone); ?></a>
		<?php endif; ?>
		<?php if ($show_cta && $cta_phone && in_array($profile, ['b', 'e'], true)) : ?>
			<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $cta_phone)); ?>" class="lf-header-cta"><?php echo esc_html($cta_text); ?></a>
		<?php elseif ($show_cta) : ?>
			<span class="lf-header-cta"><?php echo esc_html($cta_text); ?></span>
		<?php endif; ?>
	</div>
</header>
