<?php
$fleet = file_get_contents(__DIR__ . '/../inc/fleet-pages.php');
$leadgen = file_get_contents(__DIR__ . '/../inc/niches/leadgen-pages.php');
$reconcile = file_get_contents(__DIR__ . '/../inc/sitemap-sync/reconcile.php');

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(strpos($fleet, 'lf_fleet_always_publish_utility_page_slugs') !== false, 'utility slug list');
expect(strpos($fleet, "'sitemap'") !== false && strpos($fleet, "'privacy-policy'") !== false, 'utility slugs include sitemap and legal');
expect(strpos($leadgen, 'lf_fleet_always_publish_utility_page_slugs') !== false, 'wizard publish list merges utility slugs');
expect(strpos($reconcile, 'lf_fleet_is_always_publish_utility_slug') !== false, 'sitemap sync forces utility publish');
expect(strpos($reconcile, "'/sitemap/'") !== false, 'sitemap is core hub');
