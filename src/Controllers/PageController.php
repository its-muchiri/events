<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Request;
use EventCo\Core\View;
use EventCo\Models\EventBooking;
use Throwable;

/**
 * Server-rendered pages for the primary customer journey — browse vendors,
 * book a single vendor or assemble a multi-vendor bundle, track status —
 * per planning/05-event-co-ke/prd.md's Core Features 1-2. Admin/coordinator
 * consoles (cancellation-incident resolution) are not built here, matching
 * the scope discipline set by laundry.co.ke's page layer (see
 * planning/00-portfolio/ui-implementation-plan.md §3).
 *
 * Every DB-backed method fails soft: this environment (a fresh Vercel
 * deploy with no database wired yet) has no live connection, so a page
 * must still render a meaningful empty state rather than a fatal error,
 * per artcollect-design-system.md §8.
 */
final class PageController
{
    public function home(Request $request): void
    {
        $vendors = [];
        $dbError = null;

        try {
            $stmt = Database::connection()->query(
                'SELECT vl.vendor_id AS id, vl.category, vl.business_name, vl.base_price, vl.pricing_model, u.full_name
                 FROM vendor_listings vl
                 JOIN users u ON u.id = vl.vendor_id
                 WHERE vl.status = "active"
                 ORDER BY vl.id DESC
                 LIMIT 6'
            );
            $vendors = $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live vendor data is unavailable in this environment — no database is connected yet.';
        }

        View::render('home', [
            'title' => 'Book event vendors, one at a time or all at once',
            'vendors' => $vendors,
            'dbError' => $dbError,
        ]);
    }

    public function vendorIndex(Request $request): void
    {
        $category = $request->query['category'] ?? null;
        $vendors = [];
        $dbError = null;

        try {
            $db = Database::connection();
            if ($category) {
                $stmt = $db->prepare(
                    'SELECT vl.*, u.full_name FROM vendor_listings vl JOIN users u ON u.id = vl.vendor_id
                     WHERE vl.status = "active" AND vl.category = :category'
                );
                $stmt->execute(['category' => $category]);
            } else {
                $stmt = $db->query(
                    'SELECT vl.*, u.full_name FROM vendor_listings vl JOIN users u ON u.id = vl.vendor_id WHERE vl.status = "active"'
                );
            }
            $vendors = $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live vendor data is unavailable in this environment — no database is connected yet.';
        }

        View::render('vendor-index', [
            'title' => 'Browse vendors',
            'vendors' => $vendors,
            'category' => $category,
            'bundleId' => $request->query['bundle_id'] ?? null,
            'dbError' => $dbError,
        ]);
    }

    public function vendorProfile(Request $request): void
    {
        $vendorId = (int) $request->params['id'];
        $vendor = null;
        $dbError = null;

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT u.id, u.full_name, u.status, vl.category, vl.business_name, vl.portfolio_urls, vl.pricing_model, vl.base_price, vl.service_area
                 FROM users u JOIN vendor_listings vl ON vl.vendor_id = u.id
                 WHERE u.id = :id'
            );
            $stmt->execute(['id' => $vendorId]);
            $vendor = $stmt->fetch() ?: null;

            if ($vendor) {
                $standingStmt = $db->prepare('SELECT * FROM vendor_standing WHERE vendor_id = :id');
                $standingStmt->execute(['id' => $vendorId]);
                $vendor['standing'] = $standingStmt->fetch() ?: null;
            }
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live vendor data is unavailable in this environment — no database is connected yet.';
        }

        View::render('vendor-profile', [
            'title' => $vendor ? $vendor['business_name'] : 'Vendor #' . $vendorId,
            'vendorId' => $vendorId,
            'vendor' => $vendor,
            'bundleId' => $request->query['bundle_id'] ?? null,
            'dbError' => $dbError,
        ]);
    }

    public function bookingForm(Request $request): void
    {
        View::render('booking-new', [
            'title' => 'Book a vendor',
            'prefillVendorId' => $request->query['vendor_id'] ?? '',
            'prefillCategory' => $request->query['category'] ?? '',
            'prefillBundleId' => $request->query['bundle_id'] ?? '',
        ]);
    }

    public function bookingStatus(Request $request): void
    {
        $bookingId = (int) $request->params['id'];
        $booking = null;
        $dbError = null;

        try {
            $booking = EventBooking::find($bookingId);
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live booking data is unavailable in this environment — no database is connected yet.';
        }

        View::render('booking-status', [
            'title' => 'Booking #' . $bookingId,
            'bookingId' => $bookingId,
            'booking' => $booking,
            'dbError' => $dbError,
        ]);
    }

    public function bundleForm(Request $request): void
    {
        View::render('bundle-new', ['title' => 'Build an event bundle']);
    }

    public function bundleStatus(Request $request): void
    {
        $bundleId = (int) $request->params['id'];
        $bundle = null;
        $incidents = [];
        $dbError = null;

        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT * FROM event_bundle WHERE id = :id');
            $stmt->execute(['id' => $bundleId]);
            $bundle = $stmt->fetch() ?: null;

            if ($bundle) {
                $bookingsStmt = $db->prepare('SELECT * FROM event_bookings WHERE bundle_id = :id');
                $bookingsStmt->execute(['id' => $bundleId]);
                $bundle['bookings'] = $bookingsStmt->fetchAll();

                if ($bundle['status'] === 'at_risk') {
                    $incidentsStmt = $db->prepare(
                        'SELECT * FROM bundle_cancellation_incidents WHERE bundle_id = :id ORDER BY reported_at DESC'
                    );
                    $incidentsStmt->execute(['id' => $bundleId]);
                    $incidents = $incidentsStmt->fetchAll();
                }
            }
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live bundle data is unavailable in this environment — no database is connected yet.';
        }

        View::render('bundle-status', [
            'title' => 'Bundle #' . $bundleId,
            'bundleId' => $bundleId,
            'bundle' => $bundle,
            'incidents' => $incidents,
            'dbError' => $dbError,
        ]);
    }
}
