<?php

/**
 * Vendor directory checks — plain assertion script (no test runner is wired up
 * yet, see tests/README.md).
 *
 *   php database/seed_demo.php && php tests/vendor_directory_test.php
 *
 * Runs against the configured database (.env / PG* vars) and needs the demo
 * seed. Blocks and then removes one calendar date to exercise date filtering.
 * Exits non-zero on the first failed assertion.
 */

require __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

use EventCo\Config\Database;
use EventCo\Core\Photos;
use EventCo\Models\VendorDirectory;

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? "  ok    " : "  FAIL  ") . $name . "\n";
    $failures += $ok ? 0 : 1;
}
$names = static fn (array $r): array => array_column($r['vendors'], 'business_name');

$db = Database::connection();
$vendorId = (int) $db->query("SELECT id FROM users WHERE phone_number = '+254700000203'")->fetchColumn();
if ($vendorId === 0) {
    fwrite(STDERR, "Demo seed missing — run: php database/seed_demo.php\n");
    exit(2);
}

echo "Directory search\n";
$all = VendorDirectory::search([]);
check('lists every category', count(array_unique(array_column($all['vendors'], 'category'))) >= 6);
check('every vendor has a real photo URL', !array_filter($all['vendors'], fn ($v) => !str_starts_with($v['cover_photo'], 'https://images.unsplash.com/photo-')));
check('vendors without a portfolio still get photos', (bool) array_filter($all['vendors'], fn ($v) => !$v['has_own_portfolio'] && $v['photos']));
check('category filter (with "catering" alias)', array_unique(array_column(VendorDirectory::search(['category' => 'catering'])['vendors'], 'category')) === ['caterer']);
check('name search', $names(VendorDirectory::search(['q' => 'mama rosa'])) === ['Mama Rosa Catering']);
check('LIKE wildcards are literal', VendorDirectory::search(['q' => '%'])['vendors'] === []);
check('area filter', array_unique(array_column(VendorDirectory::search(['area' => 'mombasa'])['vendors'], 'service_area')) === ['Mombasa']);
check('max_price filter', !array_filter(VendorDirectory::search(['max_price' => 2000])['vendors'], fn ($v) => $v['base_price'] > 2000));
check('min_rating filter', !array_filter(VendorDirectory::search(['min_rating' => 4.5])['vendors'], fn ($v) => $v['average_rating'] < 4.5));
$asc = array_column(VendorDirectory::search(['sort' => 'price_asc'])['vendors'], 'base_price');
$sorted = $asc;
sort($sorted);
check('price_asc sort', $asc === $sorted);
check('unknown sort/garbage input falls back safely', count(VendorDirectory::search(['sort' => ';drop', 'page' => -4, 'min_price' => 'abc'])['vendors']) === count($all['vendors']));

echo "Pagination\n";
$p1 = VendorDirectory::search([], 5);
$p2 = VendorDirectory::search(['page' => 2], 5);
check('page 1 is full and has_more', count($p1['vendors']) === 5 && $p1['has_more']);
check('page 2 does not repeat page 1', array_intersect(array_column($p1['vendors'], 'id'), array_column($p2['vendors'], 'id')) === []);

echo "Date availability\n";
$date = date('Y-m-d', strtotime('+200 days'));
$db->prepare('DELETE FROM vendor_availability WHERE vendor_id = :v AND date = :d')->execute(['v' => $vendorId, 'd' => $date]);
check('free before blocking', in_array('Mama Rosa Catering', $names(VendorDirectory::search(['category' => 'caterer', 'date' => $date])), true));
$db->prepare('INSERT INTO vendor_availability (vendor_id, date, is_booked) VALUES (:v, :d, TRUE)')->execute(['v' => $vendorId, 'd' => $date]);
check('hidden once blocked', !in_array('Mama Rosa Catering', $names(VendorDirectory::search(['category' => 'caterer', 'date' => $date])), true));
check('availabilityOn reports blocked', VendorDirectory::availabilityOn($vendorId, $date)['available'] === false);
$db->prepare('DELETE FROM vendor_availability WHERE vendor_id = :v AND date = :d')->execute(['v' => $vendorId, 'd' => $date]);
check('availabilityOn reports free again', VendorDirectory::availabilityOn($vendorId, $date)['available'] === true);
check('rejects impossible dates', !VendorDirectory::isValidDate('2027-02-30') && VendorDirectory::isValidDate('2027-02-28'));

echo "Profile\n";
$profile = VendorDirectory::find($vendorId);
check('profile has rating, reviews, photos', $profile && $profile['review_count'] > 0 && count(VendorDirectory::recentReviews($vendorId)) > 0 && count($profile['photos']) >= 3);
check('unknown vendor is null', VendorDirectory::find(99999999) === null);
check('non-http portfolio URLs are dropped', Photos::portfolio(['javascript:alert(1)', 'https://images.unsplash.com/photo-1?w=9']) === ['https://images.unsplash.com/photo-1?auto=format&fit=crop&w=800&q=80']);

echo $failures === 0 ? "\nAll checks passed.\n" : "\n{$failures} check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
