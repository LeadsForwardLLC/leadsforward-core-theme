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
$cta_text = function_exists('lf_get_option') ? lf_get_option('lf_cta_primary_text', 'option') : '';
$cta_phone = function_exists('lf_get_cta_phone') ? lf_get_cta_phone() : '';
$cta_label = function_exists('lf_get_global_option') ? lf_get_global_option('lf_header_cta_label', '') : '';
$cta_url = function_exists('lf_get_global_option') ? lf_get_global_option('lf_header_cta_url', '') : '';
$show_cta = ($cta_label !== '' || $cta_text !== '');
$show_actions = !$has_header_menu;
$logo_id = function_exists('lf_get_global_option') ? lf_get_global_option('lf_global_logo', 0) : 0;
$logo_id = is_numeric($logo_id) ? (int) $logo_id : 0;
$logo_html = '';
if ($logo_id) {
	$logo_html = wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'site-header__logo-img', 'loading' => 'lazy']);
}
$logo_text = function_exists('lf_get_option') ? (string) lf_get_option('lf_business_name', 'option') : '';
if ($logo_text === '') {
	$logo_text = get_bloginfo('name');
}
?>
<header class="site-header site-header--modern" role="banner">
	<div class="site-header__inner">
		<a class="site-header__logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($logo_text ?: __('Home', 'leadsforward-core')); ?>">
			<?php if ($logo_html) : ?>
				<?php echo $logo_html; ?>
			<?php else : ?>
				<span class="site-header__logo-text"><?php echo esc_html($logo_text ?: __('LeadsForward', 'leadsforward-core')); ?></span>
			<?php endif; ?>
		</a>
		<button class="site-header__toggle" type="button" aria-expanded="false" aria-controls="site-header-panel">
			<span class="site-header__toggle-icon" aria-hidden="true">☰</span>
			<span class="site-header__toggle-label"><?php esc_html_e('Menu', 'leadsforward-core'); ?></span>
		</button>
		<div class="site-header__panel" id="site-header-panel" aria-hidden="true">
			<div class="site-header__panel-header">
				<span class="site-header__panel-title"><?php esc_html_e('Menu', 'leadsforward-core'); ?></span>
				<button class="site-header__close" type="button" aria-label="<?php esc_attr_e('Close menu', 'leadsforward-core'); ?>">✕</button>
			</div>
			<?php if ($has_header_menu) : ?>
				<nav class="site-header__nav" aria-label="<?php esc_attr_e('Primary', 'leadsforward-core'); ?>">
					<?php
					wp_nav_menu([
						'theme_location' => 'header_menu',
						'container'     => false,
						'menu_class'    => 'site-header__menu',
					]);
					?>
				</nav>
			<?php endif; ?>
			<?php if ($show_actions) : ?>
				<div class="site-header__actions">
					<?php if ($cta_phone) : ?>
						<a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $cta_phone)); ?>" class="site-header__phone">
							<?php
							if (function_exists('lf_icon')) {
								echo lf_icon('phone', ['class' => 'site-header__phone-icon lf-icon lf-icon--sm lf-icon--inherit', 'aria-hidden' => 'true']);
							}
							?>
							<span><?php esc_html_e('Call Now', 'leadsforward-core'); ?></span>
						</a>
					<?php endif; ?>
					<?php if ($show_cta) : ?>
						<?php
					$label = $cta_label !== '' ? $cta_label : ($cta_text ?: __('Free Estimate', 'leadsforward-core'));
						?>
					<?php if ($cta_url !== '') : ?>
						<a class="site-header__cta lf-btn lf-btn--primary" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($label); ?></a>
					<?php else : ?>
						<button type="button" class="site-header__cta lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="header"><?php echo esc_html($label); ?></button>
					<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ($has_utility_menu) : ?>
				<nav class="site-header__utility" aria-label="<?php esc_attr_e('Utility', 'leadsforward-core'); ?>">
					<?php
					wp_nav_menu([
						'theme_location' => 'utility_menu',
						'container'     => false,
						'menu_class'    => 'site-header__utility-menu',
					]);
					?>
				</nav>
			<?php endif; ?>
		</div>
	</div>
</header>
<script>
	(function () {
		var header = document.querySelector('.site-header');
		if (!header) return;
		var toggle = header.querySelector('.site-header__toggle');
		var panel = header.querySelector('.site-header__panel');
		var closeBtn = header.querySelector('.site-header__close');
		function setOpen(open) {
			header.classList.toggle('site-header--open', open);
			if (toggle) {
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
			if (panel) {
				panel.setAttribute('aria-hidden', open ? 'false' : 'true');
			}
			document.body.classList.toggle('site-header--menu-open', open);
		}
		if (toggle && panel) {
			toggle.addEventListener('click', function () {
				setOpen(!header.classList.contains('site-header--open'));
			});
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				setOpen(false);
			});
		}
		if (panel) {
			panel.addEventListener('click', function (event) {
				var target = event.target;
				if (target && target.tagName === 'A') {
					setOpen(false);
				}
			});
		}
		var moreToggles = header.querySelectorAll('.site-header__more-toggle');
		if (moreToggles.length) {
			moreToggles.forEach(function (toggleBtn) {
				var parentItem = toggleBtn.closest('.menu-item');
				if (!parentItem) return;
				function setMoreOpen(open) {
					parentItem.classList.toggle('is-open', open);
					toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
				}
				toggleBtn.addEventListener('click', function (event) {
					event.preventDefault();
					setMoreOpen(!parentItem.classList.contains('is-open'));
				});
				parentItem.addEventListener('mouseleave', function () {
					setMoreOpen(false);
				});
				parentItem.addEventListener('focusout', function (event) {
					if (!parentItem.contains(event.relatedTarget)) {
						setMoreOpen(false);
					}
				});
				toggleBtn.addEventListener('focus', function () {
					setMoreOpen(true);
				});
			});
		}
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				setOpen(false);
			}
		});
		var last = 0;
		window.addEventListener('scroll', function () {
			var y = window.scrollY || window.pageYOffset;
			if (Math.abs(y - last) < 6) return;
			header.classList.toggle('site-header--scrolled', y > 12);
			last = y;
		}, { passive: true });
	})();
</script>
