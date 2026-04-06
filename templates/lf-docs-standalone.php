<?php
/**
 * Standalone theme docs: full HTML document (no theme header/footer, no WP Page).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

header('Content-Type: text/html; charset=UTF-8');

$stylesheet = esc_url(LF_THEME_URI . '/assets/css/docs-page.css');
$ver = rawurlencode((string) LF_THEME_VERSION);
$home = esc_url(home_url('/'));
$logout = esc_url(wp_logout_url($home));
$name = esc_html(get_bloginfo('name'));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html(sprintf(__('Theme documentation — %s', 'leadsforward-core'), get_bloginfo('name'))); ?></title>
	<link rel="stylesheet" href="<?php echo $stylesheet; ?>?ver=<?php echo $ver; ?>">
	<style id="lf-docs-critical">
		/* Visible even if theme CSS URL is blocked */
		html { box-sizing: border-box; }
		*, *::before, *::after { box-sizing: inherit; }
		body.lf-docs-standalone { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #f1f5f9; line-height: 1.5; }
		.lf-docs-topbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 1.25rem; background: #fff; border-bottom: 1px solid #e2e8f0; }
		.lf-docs-topbar a { color: #0f172a; }
	</style>
</head>
<body class="lf-docs-standalone">
	<header class="lf-docs-topbar">
		<strong><?php echo $name; ?></strong>
		<nav aria-label="<?php esc_attr_e('Account', 'leadsforward-core'); ?>">
			<a href="<?php echo $home; ?>"><?php esc_html_e('← Site home', 'leadsforward-core'); ?></a>
			&nbsp;·&nbsp;
			<a href="<?php echo $logout; ?>"><?php esc_html_e('Log out', 'leadsforward-core'); ?></a>
		</nav>
	</header>
	<main id="main" class="site-main site-main--docs" role="main">
		<?php
		if (function_exists('lf_docs_render_main_sections')) {
			lf_docs_render_main_sections('public');
		}
		?>
	</main>
</body>
</html>
