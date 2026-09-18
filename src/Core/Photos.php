<?php

namespace EventCo\Core;

/**
 * Real photography for every image slot. Every ID below is an Unsplash photo
 * that was fetched and confirmed to resolve (HTTP 200) and to show the subject
 * it is filed under. Vendors' own portfolio uploads (vendor_listings
 * .portfolio_urls) always win; the per-category sets are only the fallback for
 * vendors who haven't uploaded a portfolio yet, so a listing never renders an
 * empty box.
 */
final class Photos
{
    /** Canonical categories, in the order the directory shows them. */
    public const CATEGORIES = [
        'venue' => 'Venues',
        'caterer' => 'Catering',
        'photographer' => 'Photography',
        'decor' => 'Decor',
        'entertainment' => 'Entertainment',
        'other' => 'Supplies & rentals',
    ];

    /** Spec wording (prd.md: "photography", "catering") mapped onto the stored slugs. */
    private const ALIASES = [
        'photography' => 'photographer',
        'catering' => 'caterer',
        'venues' => 'venue',
        'music' => 'entertainment',
        'dj' => 'entertainment',
        'rentals' => 'other',
        'supplies' => 'other',
    ];

    private const SETS = [
        'venue' => [
            '1712314947761-a8d718bd8c32', // banquet hall with chandeliers
            '1665607437981-973dcd6a22bb', // room set with tables for a wedding
            '1670529776286-f426fb7ba42c', // reception room, tables and chairs
            '1696204868903-91d809b4df09', // outdoor ceremony, white chairs
            '1670529776180-60e4132ab90c', // floral archway over gold chairs
        ],
        'caterer' => [
            '1555244162-803834f70033', // buffet with silver chafing dishes
            '1576842546422-60562b9242ae', // spread of food on a table
            '1672826979217-7156a305acf5', // sandwiches and pastries
            '1633424411431-5eb8d0e96488', // mini sandwiches on a platter
            '1637059395523-d5a35541d544', // plated dishes on a banquet table
            '1687369595840-e96a912586f1', // server plating food
        ],
        'photographer' => [
            '1519741497674-611481863552', // groom and bride with bouquet
            '1629756048377-09540f52caa1', // photographer with DSLR
            '1611550287705-7ff8b459c8eb', // photographer with camera
            '1622277430358-f4d134452e2e', // bride by a window
            '1600164913117-2125c1f60b01', // couple portrait
            '1722805740177-04256b6517f2', // couple in a garden
        ],
        'decor' => [
            '1737682599438-319b61711b5f', // long table under chandeliers
            '1738225734899-30852be7e396', // ceremony aisle with white flowers
            '1524777313293-86d2ab467344', // flower decor and candle holders
            '1676734627786-a3662ff6a243', // formal dinner table setting
            '1637534371564-458a3a29972f', // long table with candles
            '1747115276395-607f2e5dc269', // floral arrangement, outdoor event
        ],
        'entertainment' => [
            '1499364615650-ec38552f4f34', // band on stage
            '1565035010268-a3816f98589a', // crowd watching a band
            '1605340406960-f5b496c38b3d', // guitarist on stage
            '1526478806334-5fd488fcaabc', // trio playing instruments
            '1620577610365-86c411bad78d', // singer performing
            '1521547418549-6a31aad7c177', // audience at a live show
        ],
        'other' => [
            '1758426637769-a00c1edbbcf8', // white tents for an outdoor event
            '1768179123386-a86a85f1c35c', // large white marquee on grass
            '1530103862676-de8c9debad1d', // assorted balloons
            '1774557937666-74b54d3170cd', // guests at an outdoor event with tents
            '1764449320916-ccfdb8aaef62', // rows of colourful tents
            '1550850395-c17a8e90ad0a', // white, blue and purple balloons
        ],
    ];

    /** Homepage hero. */
    private const HERO = '1712314947761-a8d718bd8c32';

    public static function normalizeCategory(?string $category): string
    {
        $slug = strtolower(trim((string) $category));

        return self::ALIASES[$slug] ?? (isset(self::SETS[$slug]) ? $slug : 'other');
    }

    public static function categoryLabel(?string $category): string
    {
        return self::CATEGORIES[self::normalizeCategory($category)];
    }

    public static function hero(int $width = 1600): string
    {
        return self::unsplash(self::HERO, $width);
    }

    /** A representative photo for a whole category (homepage category tiles). */
    public static function categoryCover(string $category, int $width = 640): string
    {
        return self::unsplash(self::SETS[self::normalizeCategory($category)][0], $width);
    }

    /**
     * The vendor's own portfolio (decoded JSON list or a raw JSON string),
     * resized to $width. Empty when the vendor hasn't uploaded any.
     *
     * @return list<string>
     */
    public static function portfolio(mixed $portfolio, int $width = 800): array
    {
        if (is_string($portfolio)) {
            $portfolio = json_decode($portfolio, true);
        }
        if (!is_array($portfolio)) {
            return [];
        }

        $urls = [];
        foreach ($portfolio as $url) {
            // Only plain http(s) URLs may reach an <img src> — vendors supply these.
            if (is_string($url) && preg_match('#^https?://#i', $url)) {
                $urls[] = self::resize($url, $width);
            }
        }

        return $urls;
    }

    /**
     * Photos to show for a vendor: their own portfolio, else a per-category
     * fallback set rotated by vendor id so neighbouring cards differ.
     *
     * @return list<string>
     */
    public static function forVendor(mixed $portfolio, string $category, int $vendorId, int $width = 800): array
    {
        $own = self::portfolio($portfolio, $width);
        if ($own) {
            return $own;
        }

        $set = self::SETS[self::normalizeCategory($category)];
        $offset = $vendorId % count($set);
        $rotated = array_merge(array_slice($set, $offset), array_slice($set, 0, $offset));

        return array_map(fn (string $id): string => self::unsplash($id, $width), $rotated);
    }

    public static function hasOwnPortfolio(mixed $portfolio): bool
    {
        return self::portfolio($portfolio) !== [];
    }

    public static function unsplash(string $id, int $width): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w={$width}&q=80";
    }

    /** Re-size an Unsplash CDN URL; leave any other host's URL untouched. */
    private static function resize(string $url, int $width): string
    {
        if (!str_starts_with($url, 'https://images.unsplash.com/')) {
            return $url;
        }
        $base = explode('?', $url, 2)[0];

        return $base . "?auto=format&fit=crop&w={$width}&q=80";
    }
}
