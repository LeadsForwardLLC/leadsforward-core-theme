<?php
/**
 * Site header. Semantic markup; menus via wp_nav_menu. Outputs nothing if no menus.
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
if (!$has_header_menu && !$has_utility_menu) {
	return;
}
?>
<header class="site-header" role="banner">
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
</header>
