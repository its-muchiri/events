<?php

/**
 * DEV/DEMO ONLY — seeds a browsable vendor directory (vendors across every
 * category, their portfolios, completed bookings and reviews) so the directory,
 * comparison and profile pages can be exercised against realistic data.
 * Never run this against production.
 *
 *   php database/seed_demo.php
 *
 * Idempotent: a vendor whose demo phone number already exists is skipped, so
 * re-running adds nothing. Works against MySQL (local .env) and Postgres
 * (PGHOST set, as on the Vercel/Neon deployment). Demo accounts all share the
 * password printed at the end; vendors' phone numbers are +2547000002NN.
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

const DEMO_PASSWORD = 'demo-password-1';

$db = Database::connection();
$isPg = Database::driver() === 'pgsql';

function insertId(PDO $db, bool $isPg, string $sql, array $params): int
{
    $stmt = $db->prepare($isPg ? $sql . ' RETURNING id' : $sql);
    $stmt->execute($params);

    return $isPg ? (int) $stmt->fetchColumn() : (int) $db->lastInsertId();
}

function userExists(PDO $db, string $phone): ?int
{
    $stmt = $db->prepare('SELECT id FROM users WHERE phone_number = :p');
    $stmt->execute(['p' => $phone]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

// --- Demo customers (they "wrote" the reviews below) ---
$customerIds = [];
foreach ([['+254700000101', 'Amina Otieno'], ['+254700000102', 'Brian Kamau'], ['+254700000103', 'Grace Wambui']] as [$phone, $name]) {
    $customerIds[] = userExists($db, $phone) ?? insertId(
        $db,
        $isPg,
        "INSERT INTO users (phone_number, password_hash, full_name, account_type, status) VALUES (:phone, :hash, :name, 'customer', 'active')",
        ['phone' => $phone, 'hash' => $hash, 'name' => $name]
    );
}

// [phone, business, category, pricing, base price, area, ratings of past events, portfolio photo IDs (own portfolio) or null (sample fallback)]
$vendors = [
    ['+254700000201', 'Karura Gardens Events Hall', 'venue', 'flat_fee', 180000, 'Nairobi', [5, 5, 4], ['1712314947761-a8d718bd8c32', '1665607437981-973dcd6a22bb', '1670529776286-f426fb7ba42c']],
    ['+254700000202', 'Nyali Beachfront Pavilion', 'venue', 'flat_fee', 250000, 'Mombasa', [5, 4], ['1696204868903-91d809b4df09', '1670529776180-60e4132ab90c', '1712314947761-a8d718bd8c32']],
    ['+254700000203', 'Mama Rosa Catering', 'caterer', 'per_head', 1200, 'Nairobi', [5, 5, 5, 4], ['1555244162-803834f70033', '1576842546422-60562b9242ae', '1672826979217-7156a305acf5']],
    ['+254700000204', 'Coastal Swahili Kitchen', 'caterer', 'per_head', 1800, 'Mombasa', [4, 4], ['1637059395523-d5a35541d544', '1633424411431-5eb8d0e96488', '1687369595840-e96a912586f1']],
    ['+254700000205', 'Lensmark Weddings', 'photographer', 'flat_fee', 65000, 'Nairobi', [5, 5, 4], ['1519741497674-611481863552', '1600164913117-2125c1f60b01', '1622277430358-f4d134452e2e']],
    ['+254700000206', 'Rift Valley Frames', 'photographer', 'per_hour', 6000, 'Nakuru', [4, 3], ['1629756048377-09540f52caa1', '1722805740177-04256b6517f2', '1611550287705-7ff8b459c8eb']],
    ['+254700000207', 'Petal & Pine Decor', 'decor', 'flat_fee', 90000, 'Nairobi', [5, 4, 5], ['1737682599438-319b61711b5f', '1738225734899-30852be7e396', '1637534371564-458a3a29972f']],
    ['+254700000208', 'Lakeside Florals', 'decor', 'flat_fee', 55000, 'Kisumu', [4], null],
    ['+254700000209', 'Kilimanjaro Live Band', 'entertainment', 'per_hour', 25000, 'Nairobi', [5, 5, 4, 4], ['1499364615650-ec38552f4f34', '1605340406960-f5b496c38b3d', '1526478806334-5fd488fcaabc']],
    ['+254700000210', 'DJ Sokoni Sounds', 'entertainment', 'per_hour', 8000, 'Nairobi', [4, 3, 4], null],
    ['+254700000211', 'Tent & Chair Hire Ltd', 'other', 'flat_fee', 35000, 'Kiambu', [4, 5], ['1758426637769-a00c1edbbcf8', '1768179123386-a86a85f1c35c', '1764449320916-ccfdb8aaef62']],
    ['+254700000212', 'Sherehe Sound & Lights', 'other', 'per_hour', 12000, 'Nakuru', [], null],
];

$comments = [
    5 => ['Everything ran exactly on time — our guests are still talking about it.', 'Professional from the first call to the last hour. Highly recommended.', 'Went above and beyond on the day. Worth every shilling.'],
    4 => ['Very good overall; a couple of small hiccups but handled quickly.', 'Great work and fair pricing. Would book again.'],
    3 => ['Decent, but communication before the event could have been better.'],
];

$added = 0;
foreach ($vendors as $i => [$phone, $business, $category, $pricing, $price, $area, $ratings, $portfolioIds]) {
    if (userExists($db, $phone) !== null) {
        continue;
    }

    $db->beginTransaction();
    try {
        $vendorId = insertId(
            $db,
            $isPg,
            "INSERT INTO users (phone_number, password_hash, full_name, account_type, status) VALUES (:phone, :hash, :name, 'provider', 'active')",
            ['phone' => $phone, 'hash' => $hash, 'name' => $business]
        );

        $portfolio = $portfolioIds
            ? array_map(fn (string $id): string => Photos::unsplash($id, 1200), $portfolioIds)
            : [];
        $db->prepare(
            "INSERT INTO vendor_listings (vendor_id, category, business_name, portfolio_urls, pricing_model, base_price, service_area, status)
             VALUES (:v, :c, :b, :p, :m, :price, :area, 'active')"
        )->execute(['v' => $vendorId, 'c' => $category, 'b' => $business, 'p' => json_encode($portfolio), 'm' => $pricing, 'price' => $price, 'area' => $area]);

        foreach ($ratings as $n => $rating) {
            $customerId = $customerIds[$n % count($customerIds)];
            $bookingId = insertId(
                $db,
                $isPg,
                "INSERT INTO event_bookings (customer_id, vendor_id, category, event_date, status, pricing_model, total_amount, deposit_amount)
                 VALUES (:c, :v, :cat, :d, 'completed', :m, :total, :deposit)",
                [
                    'c' => $customerId, 'v' => $vendorId, 'cat' => $category, 'm' => $pricing,
                    'd' => date('Y-m-d', strtotime('-' . (30 + $n * 45) . ' days')),
                    'total' => $price, 'deposit' => round($price * 0.3, 2),
                ]
            );
            $pool = $comments[$rating] ?? $comments[4];
            $db->prepare(
                'INSERT INTO reviews (booking_id, reviewer_id, reviewee_id, rating, comment, created_at)
                 VALUES (:b, :r, :v, :rating, :comment, :at)'
            )->execute([
                'b' => $bookingId, 'r' => $customerId, 'v' => $vendorId, 'rating' => $rating,
                'comment' => $pool[($i + $n) % count($pool)],
                'at' => date('Y-m-d H:i:s', strtotime('-' . (25 + $n * 45) . ' days')),
            ]);
        }

        $average = $ratings ? round(array_sum($ratings) / count($ratings), 2) : 0;
        $db->prepare(
            "INSERT INTO vendor_standing (vendor_id, average_rating, completed_events_count, cancellation_count_12mo, standing_status)
             VALUES (:v, :avg, :n, 0, 'good_standing')"
        )->execute(['v' => $vendorId, 'avg' => $average, 'n' => count($ratings)]);

        $db->commit();
        $added++;
    } catch (Throwable $e) {
        $db->rollBack();
        fwrite(STDERR, "Failed seeding {$business}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Seeded {$added} demo vendor(s) (" . (count($vendors) - $added) . " already present).\n";
echo 'Demo login password for all demo accounts: ' . DEMO_PASSWORD . "\n";
