<?php

namespace EventCo\Models;

use EventCo\Config\Database;
use EventCo\Core\Photos;

/**
 * Vendor directory search/compare data — backs GET /api/v1/vendors and the
 * /vendors, / and /vendors/{id} pages (planning/05-event-co-ke/api-endpoints.md,
 * "Platform-Specific Resources"; prd.md Core Feature 1: browse by category and
 * compare pricing/ratings/portfolio).
 *
 * Assumptions (see MVP_STATUS.md):
 *  - Only suspended/banned vendors are hidden. Unverified vendors stay listed,
 *    with `is_verified` false, because no admin KYC-approval flow exists yet and
 *    hiding them would make every newly onboarded vendor invisible.
 *  - A date is unavailable for a vendor if their calendar marks it booked, or
 *    they already hold a confirmed/at-risk/fulfilled booking on it. Dates
 *    absent from vendor_availability are open by default.
 */
final class VendorDirectory
{
    public const PAGE_SIZE = 24;

    /** Statuses that hold a vendor's date (a `requested` booking is not yet a commitment). */
    private const DATE_HOLDING_STATUSES = "'confirmed', 'at_risk', 'fulfilled'";

    private const SORTS = [
        'rating' => 'average_rating DESC, review_count DESC, vl.id DESC',
        'popular' => 'completed_events_count DESC, average_rating DESC, vl.id DESC',
        'price_asc' => 'vl.base_price ASC, vl.id DESC',
        'price_desc' => 'vl.base_price DESC, vl.id DESC',
        'newest' => 'vl.id DESC',
    ];

    /**
     * @param array<string,mixed> $filters q, category, area, date (Y-m-d), pricing_model,
     *        max_price, min_price, min_rating, sort, page
     * @return array{vendors: list<array<string,mixed>>, page: int, has_more: bool, filters: array<string,mixed>}
     */
    public static function search(array $filters, int $pageSize = self::PAGE_SIZE): array
    {
        $filters = self::cleanFilters($filters);
        $where = ["vl.status = 'active'", "u.status NOT IN ('suspended', 'banned')"];
        $params = [];

        if ($filters['category'] !== '') {
            $where[] = 'vl.category = :category';
            $params['category'] = $filters['category'];
        }
        if ($filters['q'] !== '') {
            // Real prepared statements can't reuse one named placeholder.
            $where[] = '(LOWER(vl.business_name) LIKE :q1 OR LOWER(vl.service_area) LIKE :q2 OR LOWER(vl.category) LIKE :q3)';
            $like = '%' . self::escapeLike(strtolower($filters['q'])) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if ($filters['area'] !== '') {
            $where[] = 'LOWER(vl.service_area) LIKE :area';
            $params['area'] = '%' . self::escapeLike(strtolower($filters['area'])) . '%';
        }
        if ($filters['pricing_model'] !== '') {
            $where[] = 'vl.pricing_model = :pricing_model';
            $params['pricing_model'] = $filters['pricing_model'];
        }
        if ($filters['min_price'] !== null) {
            $where[] = 'vl.base_price >= :min_price';
            $params['min_price'] = $filters['min_price'];
        }
        if ($filters['max_price'] !== null) {
            $where[] = 'vl.base_price <= :max_price';
            $params['max_price'] = $filters['max_price'];
        }
        if ($filters['min_rating'] !== null) {
            $where[] = 'COALESCE(rv.avg_rating, vs.average_rating, 0) >= :min_rating';
            $params['min_rating'] = $filters['min_rating'];
        }
        if ($filters['date'] !== '') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM vendor_availability va
                                     WHERE va.vendor_id = vl.vendor_id AND va.date = :avail_date AND va.is_booked = TRUE)';
            $where[] = 'NOT EXISTS (SELECT 1 FROM event_bookings eb
                                     WHERE eb.vendor_id = vl.vendor_id AND eb.event_date = :booked_date
                                       AND eb.status IN (' . self::DATE_HOLDING_STATUSES . '))';
            $params['avail_date'] = $filters['date'];
            $params['booked_date'] = $filters['date'];
        }

        $offset = ($filters['page'] - 1) * $pageSize;
        $sql = self::baseSelect()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . self::SORTS[$filters['sort']]
            // Fetch one extra row to know whether a next page exists.
            . ' LIMIT ' . ($pageSize + 1) . ' OFFSET ' . $offset;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > $pageSize;

        return [
            'vendors' => array_map([self::class, 'present'], array_slice($rows, 0, $pageSize)),
            'page' => $filters['page'],
            'has_more' => $hasMore,
            'filters' => $filters,
        ];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $vendorId): ?array
    {
        $stmt = Database::connection()->prepare(
            self::baseSelect() . " WHERE vl.vendor_id = :id AND vl.status = 'active' AND u.status NOT IN ('suspended', 'banned') LIMIT 1"
        );
        $stmt->execute(['id' => $vendorId]);
        $row = $stmt->fetch();

        return $row ? self::present($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function recentReviews(int $vendorId, int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.rating, r.comment, r.created_at, u.full_name
             FROM reviews r JOIN users u ON u.id = r.reviewer_id
             WHERE r.reviewee_id = :id
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['id' => $vendorId]);

        return array_map(static fn (array $r): array => [
            'rating' => (int) $r['rating'],
            'comment' => $r['comment'],
            'created_at' => $r['created_at'],
            // First name only: reviewer identity beyond that isn't needed on a public page.
            'reviewer' => explode(' ', trim((string) $r['full_name']))[0] ?: 'Customer',
        ], $stmt->fetchAll());
    }

    /**
     * Whether the vendor can take a booking on $date.
     *
     * @return array{available: bool, reason: string|null}
     */
    public static function availabilityOn(int $vendorId, string $date): array
    {
        $db = Database::connection();

        $stmt = $db->prepare('SELECT 1 FROM vendor_availability WHERE vendor_id = :v AND date = :d AND is_booked = TRUE LIMIT 1');
        $stmt->execute(['v' => $vendorId, 'd' => $date]);
        if ($stmt->fetchColumn()) {
            return ['available' => false, 'reason' => 'The vendor has blocked this date on their calendar.'];
        }

        $stmt = $db->prepare(
            'SELECT 1 FROM event_bookings WHERE vendor_id = :v AND event_date = :d
             AND status IN (' . self::DATE_HOLDING_STATUSES . ') LIMIT 1'
        );
        $stmt->execute(['v' => $vendorId, 'd' => $date]);
        if ($stmt->fetchColumn()) {
            return ['available' => false, 'reason' => 'The vendor is already booked for this date.'];
        }

        return ['available' => true, 'reason' => null];
    }

    /** True when $value is a real calendar date in Y-m-d form. */
    public static function isValidDate(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    private static function baseSelect(): string
    {
        return 'SELECT vl.id AS listing_id, vl.vendor_id, vl.category, vl.business_name, vl.portfolio_urls,
                       vl.pricing_model, vl.base_price, vl.service_area, u.full_name, u.status AS user_status,
                       COALESCE(rv.avg_rating, vs.average_rating, 0) AS average_rating,
                       COALESCE(rv.review_count, 0) AS review_count,
                       COALESCE(vs.completed_events_count, 0) AS completed_events_count,
                       vs.standing_status
                FROM vendor_listings vl
                JOIN users u ON u.id = vl.vendor_id
                LEFT JOIN vendor_standing vs ON vs.vendor_id = vl.vendor_id
                LEFT JOIN (SELECT reviewee_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
                           FROM reviews GROUP BY reviewee_id) rv ON rv.reviewee_id = vl.vendor_id';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function present(array $row): array
    {
        $vendorId = (int) $row['vendor_id'];
        $photos = Photos::forVendor($row['portfolio_urls'], $row['category'], $vendorId);

        return [
            'id' => $vendorId,
            'vendor_id' => $vendorId,
            'business_name' => $row['business_name'],
            'category' => $row['category'],
            'category_label' => Photos::categoryLabel($row['category']),
            'pricing_model' => $row['pricing_model'],
            'base_price' => (float) $row['base_price'],
            'service_area' => $row['service_area'],
            'average_rating' => round((float) $row['average_rating'], 1),
            'review_count' => (int) $row['review_count'],
            'completed_events_count' => (int) $row['completed_events_count'],
            'standing_status' => $row['standing_status'],
            'is_verified' => $row['user_status'] === 'active',
            'cover_photo' => $photos[0],
            'photos' => $photos,
            'has_own_portfolio' => Photos::hasOwnPortfolio($row['portfolio_urls']),
        ];
    }

    /**
     * @param array<string,mixed> $in
     * @return array{q:string,category:string,area:string,date:string,pricing_model:string,min_price:?float,max_price:?float,min_rating:?float,sort:string,page:int}
     */
    public static function cleanFilters(array $in): array
    {
        $text = static fn (string $key, int $max = 80): string => mb_substr(trim((string) ($in[$key] ?? '')), 0, $max);
        $number = static fn (string $key): ?float => isset($in[$key]) && is_numeric($in[$key]) && (float) $in[$key] >= 0
            ? (float) $in[$key] : null;

        $category = $text('category', 100);
        $date = $text('date', 10);
        $sort = $text('sort', 20);
        $model = $text('pricing_model', 20);

        return [
            'q' => $text('q'),
            'category' => $category === '' ? '' : Photos::normalizeCategory($category),
            'area' => $text('area'),
            'date' => self::isValidDate($date) ? $date : '',
            'pricing_model' => in_array($model, ['flat_fee', 'per_head', 'per_hour'], true) ? $model : '',
            'min_price' => $number('min_price'),
            'max_price' => $number('max_price'),
            'min_rating' => $number('min_rating'),
            'sort' => isset(self::SORTS[$sort]) ? $sort : 'rating',
            'page' => max(1, (int) ($in['page'] ?? 1)),
        ];
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
