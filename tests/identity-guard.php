<?php
require __DIR__ . '/../inc/ai-studio-identity.php';

function expect($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
        exit(1);
    }
}

// 1) mismatch should fail
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => 'Fort Collins Roofing', 'city_region' => 'Fort Collins', 'niche' => 'roofing'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === false, 'mismatch should fail');

// 2) missing incoming name with expected name should fail
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => '', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === false, 'missing incoming name should fail');

// 3) no comparable fields should pass with warning reason
$expected = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'no comparable fields should pass');
expect($result['reason'] === 'no_comparable_fields', 'no comparable reason');

// 4) match should pass (exact slug)
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'expected match should pass');

// 5) normalization should collapse spaces and replace &
expect(
    lf_ai_studio_identity_normalize_text('  Piano  &   Tuning ') === 'piano and tuning',
    'normalize spaces and &'
);

// 6) normalization should strip punctuation
expect(
    lf_ai_studio_identity_normalize_text('Bethesda, MD') === 'bethesda md',
    'normalize punctuation'
);

// 7) helper should honor explicit niche_slug even when label differs
$expected = [
    'business_name' => 'Bethesda Piano Tuning',
    'city_region' => 'Bethesda',
    'niche' => 'Concert Piano Service',
    'niche_slug' => 'piano-tuning',
];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug/label match should pass');

// 8) helper should allow incoming to match either slug or label
$expected = [
    'business_name' => 'Bethesda Piano Tuning',
    'city_region' => 'Bethesda',
    'niche' => 'Piano Tuning Service',
    'niche_slug' => 'piano-tuning-service',
];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano Tuning Service'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug OR label match should pass');

// 9) build_expected should honor per-field precedence
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => 'Job Name', 'city_region' => 'Job City', 'niche' => 'job-niche'],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche_slug' => 'manifest-niche']],
    [
        'lf_business_name' => 'Opt Name',
        'lf_city_region' => 'Opt City',
        'lf_homepage_city' => 'Opt City 2',
        'lf_homepage_niche_slug' => 'opt-niche',
    ]
);
expect($expected['business_name'] === 'Job Name', 'job name precedence');
expect($expected['city_region'] === 'Job City', 'job city precedence');
expect($expected['niche'] === 'job-niche', 'job niche precedence');

// 10) build_incoming should prefer apply payload over top-level
$incoming = lf_ai_studio_identity_build_incoming(
    ['business_name' => 'Apply Name', 'meta' => ['city_region' => 'Apply City', 'niche' => 'apply-niche']],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Apply Name', 'apply business_name precedence');
expect($incoming['city_region'] === 'Apply City', 'apply city precedence');
expect($incoming['niche'] === 'apply-niche', 'apply niche precedence');

// 11) build_expected should mix sources per field when job is missing values
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => 'Job Name', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche_slug' => 'manifest-niche']],
    [
        'lf_business_name' => 'Opt Name',
        'lf_city_region' => 'Opt City',
        'lf_homepage_city' => 'Opt City 2',
        'lf_homepage_niche_slug' => 'opt-niche',
    ]
);
expect($expected['business_name'] === 'Job Name', 'job name still wins');
expect($expected['city_region'] === 'Manifest City', 'manifest city fallback');
expect($expected['niche'] === 'manifest-niche', 'manifest niche fallback');

// 12) build_incoming should honor meta fallbacks
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => ['business_name' => 'Meta Name', 'city_region' => 'Meta City', 'niche' => 'meta-niche']],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Meta Name', 'meta business_name fallback');
expect($incoming['city_region'] === 'Meta City', 'meta city fallback');
expect($incoming['niche'] === 'meta-niche', 'meta niche fallback');

// 13) empty expected should still return no comparable fields
$expected = lf_ai_studio_identity_build_expected([], [], []);
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'empty expected should pass');
expect($result['reason'] === 'no_comparable_fields', 'empty expected no comparable');

// 14) manifest should fall back to business.niche when niche_slug missing
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche' => 'Piano Tuning']],
    []
);
expect($expected['niche'] === 'Piano Tuning', 'manifest niche label fallback');

// 15) incoming should fall back to payload when apply is missing
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => []],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Payload Name', 'payload business_name fallback');
expect($incoming['city_region'] === 'Payload City', 'payload city fallback');
expect($incoming['niche'] === 'payload-niche', 'payload niche fallback');

// 16) guard decision should return mismatch response shape
$decision = lf_ai_studio_identity_guard_decision(
    ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'],
    ['business_name' => 'Fort Collins Roofing', 'city_region' => 'Fort Collins', 'niche' => 'roofing'],
    42
);
expect($decision['allow'] === false, 'guard should block mismatch');
expect($decision['response']['success'] === false, 'response success false');
expect($decision['response']['error'][0] === 'business_identity_mismatch', 'response error code');
expect($decision['response']['job_id'] === 42, 'response job_id');
expect($decision['response']['acknowledged'] === true, 'response acknowledged');

// 17) incoming should fall back to payload if apply is missing fields
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => ['city_region' => 'Apply City']],
    ['business_name' => 'Payload Name', 'meta' => ['niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Payload Name', 'payload name fallback when apply missing');
expect($incoming['city_region'] === 'Apply City', 'apply city still wins');
expect($incoming['niche'] === 'payload-niche', 'payload niche fallback when apply missing');

// 18) guard decision should allow when identity matches
$decision = lf_ai_studio_identity_guard_decision(
    ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'],
    ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'],
    7
);
expect($decision['allow'] === true, 'guard should allow matching identity');

// 19) options city precedence should prefer lf_city_region
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    [],
    ['lf_city_region' => 'City A', 'lf_homepage_city' => 'City B']
);
expect($expected['city_region'] === 'City A', 'options city precedence');

// 20) build_expected should fall back to manifest business.name
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => '', 'niche_slug' => '']],
    ['lf_business_name' => 'Opt Name']
);
expect($expected['business_name'] === 'Manifest Name', 'manifest name fallback');

// 21) build_expected should fall back to options business name
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => '', 'primary_city' => '', 'niche_slug' => '']],
    ['lf_business_name' => 'Opt Name']
);
expect($expected['business_name'] === 'Opt Name', 'options name fallback');

// 22) build_expected should fall back to business.address.city
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['primary_city' => '', 'address' => ['city' => 'Address City']]],
    []
);
expect($expected['city_region'] === 'Address City', 'address city fallback');

// 23) partial expected identity should ignore missing fields
$expected = ['business_name' => '', 'city_region' => 'Bethesda', 'niche' => ''];
$incoming = ['business_name' => 'Other Name', 'city_region' => 'Bethesda', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'partial expected identity ignores missing name');

// 24) guard decision should allow when no comparable fields
$decision = lf_ai_studio_identity_guard_decision(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    9
);
expect($decision['allow'] === true, 'guard allows no comparable fields');

// 25) build_expected should use lf_homepage_niche_slug when other sources empty
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    [],
    ['lf_homepage_niche_slug' => 'opt-niche']
);
expect($expected['niche'] === 'opt-niche', 'options niche_slug fallback');

// 26) niche label should compare via slug (sanitize_title) not raw normalize
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano & Tuning'];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano Tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'label slug comparison should pass');

echo "PASS\n";
