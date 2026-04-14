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
	$logo_html = wp_get_attachment_image($logo_id, 'large', false, ['class' => 'site-header__logo-img', 'loading' => 'lazy']);
}
$logo_text = function_exists('lf_get_option') ? (string) lf_get_option('lf_business_name', 'option') : '';
if ($logo_text === '') {
	$logo_text = get_bloginfo('name');
}
$layout = function_exists('lf_header_layout') ? lf_header_layout() : 'modern';
$topbar_enabled = function_exists('lf_header_topbar_enabled') && lf_header_topbar_enabled();
$topbar_text = function_exists('lf_header_topbar_text') ? lf_header_topbar_text() : '';
$show_topbar = ($layout === 'topbar' && $topbar_enabled && $topbar_text !== '');
$header_class = 'site-header site-header--modern site-header--' . $layout;
if ($show_topbar) {
	$header_class .= ' site-header--has-topbar';
}
?>
<header class="<?php echo esc_attr($header_class); ?>" role="banner">
	<?php if ($show_topbar) : ?>
		<div class="site-header__topbar"><div class="site-header__topbar-inner"><?php echo esc_html($topbar_text); ?></div></div>
	<?php endif; ?>
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
							<?php if (function_exists('lf_icon')) : ?>
								<span class="site-header__phone-icon" aria-hidden="true"><?php echo lf_icon('phone', ['class' => 'lf-icon lf-icon--sm lf-icon--inherit']); ?></span>
							<?php endif; ?>
							<span class="site-header__phone-text"><?php esc_html_e('Call Now', 'leadsforward-core'); ?></span>
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
<div class="site-header__spacer" aria-hidden="true"></div>
<script>
	(function () {
		var header = document.querySelector('.site-header');
		if (!header) return;
		var frozenHeaderHeight = 0;
		function applyFrozenHeaderHeight() {
			if (!frozenHeaderHeight) return;
			header.style.setProperty('--lf-header-height', frozenHeaderHeight + 'px');
		}
		function captureHeaderHeightForLayout() {
			window.requestAnimationFrame(function () {
				if (header.classList.contains('site-header--sticky') && !header.classList.contains('site-header--open')) {
					return;
				}
				var h = Math.ceil(header.getBoundingClientRect().height);
				if (header.classList.contains('site-header--open')) {
					frozenHeaderHeight = Math.max(frozenHeaderHeight || 0, h);
				} else {
					frozenHeaderHeight = h;
				}
				applyFrozenHeaderHeight();
			});
		}
		captureHeaderHeightForLayout();
		window.addEventListener('load', captureHeaderHeightForLayout, { passive: true });
		window.addEventListener('resize', captureHeaderHeightForLayout, { passive: true });
		if (typeof ResizeObserver !== 'undefined') {
			new ResizeObserver(function () {
				if (header.classList.contains('site-header--sticky') && !header.classList.contains('site-header--open')) {
					return;
				}
				captureHeaderHeightForLayout();
			}).observe(header);
		}
		var toggle = header.querySelector('.site-header__toggle');
		var panel = header.querySelector('.site-header__panel');
		var closeBtn = header.querySelector('.site-header__close');
		var submenuToggles = [];
		function closeSubmenus() {
			if (!submenuToggles.length) return;
			submenuToggles.forEach(function (entry) {
				entry.item.classList.remove('is-open');
				entry.toggle.setAttribute('aria-expanded', 'false');
				var entryMore = entry.item.querySelector(':scope > .site-header__more-toggle');
				if (entryMore) {
					entryMore.setAttribute('aria-expanded', 'false');
				}
			});
		}
		function setOpen(open) {
			header.classList.toggle('site-header--open', open);
			if (toggle) {
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
			if (panel) {
				panel.setAttribute('aria-hidden', open ? 'false' : 'true');
			}
			document.body.classList.toggle('site-header--menu-open', open);
			if (!open) {
				closeSubmenus();
			}
			captureHeaderHeightForLayout();
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
				if (!target) return;
				var link = target.closest('a');
				if (link && link.tagName === 'A') {
					var parentItem = link.closest('.menu-item-has-children, .lf-menu-more');
					var isTopLevelParent = parentItem && parentItem.parentElement && parentItem.parentElement.classList.contains('site-header__menu');
					var isDirectLink = link.closest('.sub-menu') === null;
					if (isTopLevelParent && isDirectLink) {
						event.preventDefault();
						var toggleBtn = parentItem.querySelector(':scope > .site-header__submenu-toggle, :scope > .site-header__more-toggle');
						if (toggleBtn) toggleBtn.click();
						return;
					}
					closeSubmenus();
					setOpen(false);
				}
			});
		}
		/* Remove any submenu toggles incorrectly added to nested items (keeps chevron on parent only) */
		header.querySelectorAll('.site-header__menu .sub-menu .menu-item .site-header__submenu-toggle').forEach(function (btn) {
			btn.remove();
		});
		var submenuItems = header.querySelectorAll('.site-header__menu > .menu-item-has-children, .site-header__menu > .lf-menu-more');
		if (submenuItems.length) {
			submenuItems.forEach(function (item) {
				var link = item.querySelector(':scope > a');
				var moreToggle = item.querySelector(':scope > .site-header__more-toggle');
				var toggleBtn = item.querySelector(':scope > .site-header__submenu-toggle') || moreToggle;
				if (!toggleBtn && link) {
					toggleBtn = document.createElement('button');
					toggleBtn.type = 'button';
					toggleBtn.className = 'site-header__submenu-toggle';
					toggleBtn.setAttribute('aria-expanded', 'false');
					toggleBtn.setAttribute('aria-label', 'Toggle submenu');
					toggleBtn.innerHTML = '<span aria-hidden="true">▾</span>';
					link.insertAdjacentElement('afterend', toggleBtn);
				}
				if (!toggleBtn) return;
				submenuToggles.push({ item: item, toggle: toggleBtn });
				var handleToggle = function (event) {
					event.preventDefault();
					var wasOpen = item.classList.contains('is-open');
					closeSubmenus();
					var open = !wasOpen;
					item.classList.toggle('is-open', open);
					toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
					if (moreToggle) {
						moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
					}
				};
				toggleBtn.addEventListener('click', handleToggle);
				if (moreToggle && moreToggle !== toggleBtn) {
					moreToggle.addEventListener('click', handleToggle);
				}
			});
		}
		document.addEventListener('click', function (event) {
			if (!event.target || !header.contains(event.target)) {
				closeSubmenus();
				setOpen(false);
			}
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				setOpen(false);
			}
		});
		var stickyOn = false;
		function updateStickyFromScroll() {
			var y = window.scrollY || window.pageYOffset;
			if (!stickyOn && y > 8) {
				stickyOn = true;
				header.classList.add('site-header--sticky');
			} else if (stickyOn && y < 2) {
				stickyOn = false;
				header.classList.remove('site-header--sticky');
			}
		}
		window.addEventListener('scroll', updateStickyFromScroll, { passive: true });
		updateStickyFromScroll();
	})();
</script>
