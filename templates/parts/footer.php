<?php
/**
 * Site footer. Semantic markup; menu via wp_nav_menu. Outputs nothing if no footer menu.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

if (!has_nav_menu('footer_menu')) {
	return;
}
?>
<footer class="site-footer" role="contentinfo">
	<nav class="footer-nav" aria-label="<?php esc_attr_e('Footer', 'leadsforward-core'); ?>">
		<?php
		wp_nav_menu([
			'theme_location' => 'footer_menu',
			'container'     => false,
			'menu_class'    => 'footer-menu',
		]);
		?>
	</nav>
</footer>
