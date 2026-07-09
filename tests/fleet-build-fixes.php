<?php
/**
 * Static checks for fleet build / nav / SEO hardening (0.1.222).
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

$root = __DIR__ . '/..';

$setup = file_get_contents($root . '/inc/niches/setup-runner.php') ?: '';
expect(strpos($setup, 'lf_fleet_dedupe_alias_pages') !== false, 'setup dedupes alias pages');
expect(strpos($setup, 'lf_fleet_dedupe_duplicate_core_pages') !== false, 'setup dedupes duplicate core pages');
expect(strpos($setup, "'post_name' => ''") !== false, 'home front page clears slug');
expect(strpos($setup, 'lf_wizard_published_page_id') !== false, 'menus gate draft pages');

$fleet = file_get_contents($root . '/inc/fleet-pages.php') ?: '';
expect(strpos($fleet, 'function lf_fleet_dedupe_duplicate_core_pages') !== false, 'duplicate core dedupe helper');

$footer = file_get_contents($root . '/templates/parts/footer.php') ?: '';
expect(strpos($footer, "post_status === 'publish'") !== false, 'footer links require publish');

$seo = file_get_contents($root . '/inc/seo/seo-meta-box.php') ?: '';
expect(strpos($seo, "' in '") === false || strpos($seo, 'trim($disp . \' in \'') === false, 'meta title avoids duplicate in-city glue');

$seo_quality = file_get_contents($root . '/inc/seo/seo-quality.php') ?: '';
expect(strpos($seo_quality, 'function lf_seo_service_areas_hub_keyword') !== false, 'service areas hub keyword helper');

$entity = file_get_contents($root . '/inc/business-entity.php') ?: '';
expect(strpos($entity, 'function lf_business_entity_resolve_place_id') !== false, 'place id resolver');

$sections = file_get_contents($root . '/inc/sections.php') ?: '';
expect(strpos($sections, 'phone_display') !== false, 'CTA syncs phone display');

$css = file_get_contents($root . '/assets/css/design-system.css') ?: '';
expect(strpos($css, 'lf-hero-conversion__title') !== false, 'hero title rule exists');
expect(strpos($css, 'text-transform: uppercase') === false || strpos($css, 'lf-hero-conversion__title') === false
	|| !preg_match('/lf-hero-conversion__title[^}]*text-transform:\s*uppercase/s', $css), 'hero title no forced uppercase');

fwrite(STDOUT, "PASS: fleet-build-fixes\n");
