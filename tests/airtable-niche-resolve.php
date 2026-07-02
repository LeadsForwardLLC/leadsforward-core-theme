<?php
/**
 * Airtable niche resolution helpers.
 */

require __DIR__ . '/../inc/niches/registry.php';
require __DIR__ . '/../inc/manifester/ai-studio-airtable.php';
require __DIR__ . '/../inc/manifester/ai-studio.php';

function expect_niche($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect_niche(
	lf_ai_studio_airtable_value_looks_like_record_id('recrlwWhrQASLnJnj') === true,
	'record id detection'
);
expect_niche(
	lf_ai_studio_airtable_sanitize_niche_token('recrlwWhrQASLnJnj') === '',
	'record id stripped from niche token'
);
expect_niche(
	lf_ai_studio_airtable_sanitize_niche_token('Foundation Repair') === 'Foundation Repair',
	'human niche label preserved'
);

$bundle = lf_ai_studio_airtable_resolve_niche_from_fields(
	[
		'Niche' => ['recrlwWhrQASLnJnj'],
		'Niche Slug' => '',
		'Project Type' => 'Foundation Repair',
		'Primary KWs' => 'Foundation Repair Schaumburg IL',
		'Project' => 'AccuLevel - Schaumburg IL #21',
	],
	[
		'niche' => 'Niche',
		'niche_slug' => 'Niche Slug',
		'project_type' => 'Project Type',
		'primary_keyword' => 'Primary KWs',
		'project' => 'Project',
		'business_category' => 'Business Category',
	],
	[]
);
expect_niche($bundle['niche'] === 'Foundation Repair', 'project type used when niche is linked-record id');
expect_niche($bundle['resolved_slug'] === 'foundation-repair', 'foundation repair slug resolved from hints');

$manifest = lf_ai_studio_manifest_resolve_business_niche([
	'business' => [
		'name' => 'AccuLevel',
		'niche' => 'recrlwWhrQASLnJnj',
		'niche_slug' => '',
		'project_type' => 'Foundation Repair',
	],
	'homepage' => [
		'primary_keyword' => 'Foundation Repair Schaumburg IL',
	],
	'services' => [],
]);
expect_niche(
	(string) ($manifest['business']['niche_slug'] ?? '') === 'foundation-repair',
	'manifest resolver writes registry slug'
);

$keys = lf_ai_studio_manifest_niche_match_keys($manifest);
expect_niche(in_array('foundation-repair', $keys, true), 'match keys include slug');

echo "PASS\n";
