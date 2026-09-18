<?php

namespace EventCo\Core;

/**
 * Minimal PHP-template view renderer — server-rendered pages progressively
 * enhanced with JS, per planning/00-portfolio/shared-architecture.md's
 * frontend stack decision (no JS framework). Templates live in
 * src/Views/*.php and are plain PHP files using short echo tags; this
 * class only handles wrapping them in the shared layout. Ported unchanged
 * from laundry.co.ke's reference implementation (see
 * planning/00-portfolio/ui-implementation-plan.md §2).
 */
final class View
{
    public static function render(string $template, array $data = []): void
    {
        // Every page gets the current session user available as $currentUser
        // (for layout.php's nav) without every controller having to thread
        // it through explicitly — see src/Core/Auth.php.
        $data['currentUser'] = $data['currentUser'] ?? Auth::currentUser();

        extract($data);
        $viewFile = __DIR__ . '/../Views/' . $template . '.php';

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layout.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** "KES 1,500 per head" / "KES 45,000 flat fee" — how a vendor's base price reads everywhere. */
    public static function priceLabel(float $amount, string $pricingModel): string
    {
        return 'KES ' . number_format($amount) . ' ' . str_replace('_', ' ', $pricingModel);
    }

    /** Rating as shown on vendor cards; unrated vendors say so rather than showing a misleading 0.0. */
    public static function ratingHtml(float $average, int $reviewCount): string
    {
        if ($reviewCount === 0 && $average <= 0) {
            return '<span class="rating rating--none">New — no reviews yet</span>';
        }
        $count = $reviewCount > 0 ? ' <span class="card__meta" style="display:inline">(' . $reviewCount . ')</span>' : '';

        return '<span class="rating"><span class="rating__star" aria-hidden="true">★</span>'
            . '<span class="sr-only">Rated </span>' . number_format($average, 1) . $count . '</span>';
    }
}
