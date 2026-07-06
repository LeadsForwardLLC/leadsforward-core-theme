<?php
$sitemaps = file_get_contents(__DIR__ . '/../inc/airtable/sitemaps.php');
$reconcile = file_get_contents(__DIR__ . '/../inc/sitemap-sync/reconcile.php');
$schemas = file_get_contents(__DIR__ . '/../inc/page-content-importer-schemas.php');
$seo = file_get_contents(__DIR__ . '/../inc/seo/seo-settings.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($sitemaps, "'secondary_keywords'") !== false, 'sitemap specs include secondary_keywords');
expect(strpos($sitemaps, 'lf_airtable_sitemaps_normalize_keyword_list') !== false, 'keyword list normalizer exists');
expect(strpos($sitemaps, 'KW-Top 10') !== false, 'sitemap reads Airtable secondary keyword fields');
expect(strpos($reconcile, 'lf_sitemap_sync_store_seo_keywords') !== false, 'reconcile stores secondary keywords');
expect(strpos($schemas, "'service-area'") !== false, 'PCI registry includes service-area template');
expect(strpos($seo, 'lf_seo_enable_faq') !== false, 'SEO settings expose FAQ schema toggle');
