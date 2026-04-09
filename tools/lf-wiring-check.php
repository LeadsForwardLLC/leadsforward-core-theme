<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	require_once dirname(__DIR__, 4) . '/wp-load.php';
}

if (!function_exists('lf_ai_studio_wiring_report')) {
	fwrite(STDERR, "Missing lf_ai_studio_wiring_report()\n");
	exit(1);
}

$report = lf_ai_studio_wiring_report();
$issues = array_merge(
	$report['registry_issues'] ?? [],
	$report['blueprint_issues'] ?? []
);

$summary = $report['payload_summary'] ?? [];
$page_types = $summary['page_types'] ?? [];
$scope = $summary['generation_scope'] ?? [];
$required = [
	'homepage'      => ['homepage'],
	'services'      => ['service', 'services_overview'],
	'service_areas' => ['service_area', 'service_areas_overview'],
	'core_pages'    => ['about', 'contact', 'reviews', 'blog', 'why_choose_us', 'sitemap', 'privacy_policy', 'terms_of_service', 'thank_you'],
	'blog_posts'    => ['post'],
	'projects'      => ['project'],
];
foreach ($required as $scope_key => $types) {
	if (empty($scope[ $scope_key ])) {
		continue;
	}
	foreach ($types as $type) {
		if (empty($page_types[ $type ])) {
			fwrite(STDERR, "Missing {$type} blueprints (scope {$scope_key} enabled)\n");
			exit(1);
		}
	}
}

echo wp_json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
exit(empty($issues) ? 0 : 1);
