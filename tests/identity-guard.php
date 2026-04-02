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

echo "PASS\n";
