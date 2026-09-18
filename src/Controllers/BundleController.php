<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Auth;
use EventCo\Core\Request;
use EventCo\Core\Response;

/**
 * Multi-vendor event bundles — this platform's defining feature and its
 * defining risk (see planning/05-event-co-ke/prd.md Core Features 2-4).
 *
 * The replacement-vendor matching algorithm, the last-minute
 * price-differential policy, and the Event Coordinator's unilateral-action
 * authority for time-critical cancellations are all UNRESOLVED (see
 * open-questions.md #1-#2) and intentionally NOT implemented here — this
 * controller only stores/reports state, it does not make those judgment
 * calls automatically.
 */
final class BundleController
{
    public function create(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO event_bundle
                (customer_id, event_type, event_date, event_location_address, event_location_lat, event_location_lng,
                 total_bundle_amount, combined_deposit_amount, status, created_at, updated_at)
             VALUES (:customer_id, :event_type, :event_date, :address, :lat, :lng, 0, 0, \'assembling\', NOW(), NOW())'
        );
        $stmt->execute([
            'customer_id' => $user['id'],
            'event_type' => $request->input('event_type'),
            'event_date' => $request->input('event_date'),
            'address' => $request->input('event_location_address'),
            'lat' => $request->input('event_location_lat'),
            'lng' => $request->input('event_location_lng'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId(), 'status' => 'assembling'], 201);
    }

    public function addVendor(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO event_bookings
                (customer_id, vendor_id, bundle_id, category, event_date, status, pricing_model,
                 head_count, hours_booked, total_amount, deposit_amount, created_at, updated_at)
             VALUES (:customer_id, :vendor_id, :bundle_id, :category,
                (SELECT event_date FROM event_bundle WHERE id = :bundle_id), \'requested\', :pricing_model,
                 :head_count, :hours_booked, :total_amount, :deposit_amount, NOW(), NOW())'
        );
        $stmt->execute([
            'customer_id' => $user['id'],
            'vendor_id' => $request->input('vendor_id'),
            'bundle_id' => $request->params['id'],
            'category' => $request->input('category'),
            'pricing_model' => $request->input('pricing_model'),
            'head_count' => $request->input('head_count'),
            'hours_booked' => $request->input('hours_booked'),
            'total_amount' => $request->input('total_amount', 0),
            'deposit_amount' => $request->input('deposit_amount', 0),
        ]);

        Response::json(['booking_id' => (int) $db->lastInsertId()], 201);
    }

    public function removeVendor(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'DELETE FROM event_bookings WHERE id = :booking_id AND bundle_id = :bundle_id AND status = \'requested\''
        );
        $stmt->execute(['booking_id' => $request->params['bookingId'], 'bundle_id' => $request->params['id']]);

        Response::json(['status' => 'removed']);
    }

    public function confirm(Request $request): void
    {
        $db = Database::connection();

        // TODO: lock vendor_availability for every included booking's
        // vendor/date simultaneously (see database/schema.sql's
        // vendor_availability table) — this must be a single atomic
        // operation so a bundle is never confirmed with an unavailable
        // vendor. Not yet implemented.
        $stmt = $db->prepare(
            'UPDATE event_bundle SET status = \'confirmed\',
                total_bundle_amount = (SELECT COALESCE(SUM(total_amount), 0) FROM event_bookings WHERE bundle_id = :id),
                combined_deposit_amount = (SELECT COALESCE(SUM(deposit_amount), 0) FROM event_bookings WHERE bundle_id = :id),
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $request->params['id']]);

        $stmt = $db->prepare('UPDATE event_bookings SET status = \'confirmed\' WHERE bundle_id = :id');
        $stmt->execute(['id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'confirmed']);
    }

    public function show(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM event_bundle WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $bundle = $stmt->fetch();

        if (!$bundle) {
            Response::notFound('Bundle not found');
            return;
        }

        $stmt = $db->prepare('SELECT * FROM event_bookings WHERE bundle_id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $bundle['bookings'] = $stmt->fetchAll();

        Response::json($bundle);
    }

    public function cancel(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE event_bundle SET status = \'cancelled\', updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);

        $stmt = $db->prepare('UPDATE event_bookings SET status = \'cancelled\' WHERE bundle_id = :id');
        $stmt->execute(['id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'cancelled']);
    }

    public function reportVendorCancellation(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();

        $stmt = $db->prepare('SELECT bundle_id, event_date FROM event_bookings WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $booking = $stmt->fetch();

        if (!$booking || !$booking['bundle_id']) {
            Response::error('Booking is not part of a bundle', 422);
            return;
        }

        $daysUntilEvent = (int) ((strtotime($booking['event_date']) - time()) / 86400);

        $stmt = $db->prepare(
            'INSERT INTO bundle_cancellation_incidents
                (bundle_id, original_booking_id, cancelling_vendor_id, reported_at, days_until_event, resolution_status, coordinator_id)
             VALUES (:bundle_id, :booking_id, :vendor_id, NOW(), :days_until_event, \'seeking_replacement\', NULL)'
        );
        $stmt->execute([
            'bundle_id' => $booking['bundle_id'],
            'booking_id' => $request->params['id'],
            'vendor_id' => $user['id'],
            'days_until_event' => $daysUntilEvent,
        ]);

        $stmt = $db->prepare('UPDATE event_bookings SET status = \'at_risk\' WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $stmt = $db->prepare('UPDATE event_bundle SET status = \'at_risk\' WHERE id = :id');
        $stmt->execute(['id' => $booking['bundle_id']]);

        // TODO: alert the Event Coordinator queue with urgency scaled by
        // days_until_event — see user-flows.md's Multi-Vendor Cancellation
        // Flow step 2.
        Response::json(['id' => (int) $db->lastInsertId(), 'days_until_event' => $daysUntilEvent], 201);
    }

    public function listCancellationIncidents(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM bundle_cancellation_incidents WHERE bundle_id = :bundle_id ORDER BY reported_at DESC'
        );
        $stmt->execute(['bundle_id' => $request->params['id']]);

        Response::json($stmt->fetchAll());
    }

    public function attachReplacementOptions(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        // TODO: replacement-vendor matching (same category, area, date
        // availability) is unimplemented — see open-questions.md and
        // user-flows.md step 3-4. This endpoint currently just assigns a
        // coordinator to the case.
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE bundle_cancellation_incidents SET coordinator_id = :coordinator_id WHERE id = :id');
        $stmt->execute(['coordinator_id' => $user['id'], 'id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'coordinator_assigned']);
    }

    public function confirmReplacement(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE bundle_cancellation_incidents
             SET replacement_booking_id = :replacement_booking_id,
                 replacement_price_differential = :price_differential,
                 resolution_status = \'replacement_confirmed\',
                 resolved_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'replacement_booking_id' => $request->input('replacement_booking_id'),
            'price_differential' => $request->input('replacement_price_differential', 0),
            'id' => $request->params['id'],
        ]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'replacement_confirmed']);
    }

    public function unresolvedRefund(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE bundle_cancellation_incidents SET resolution_status = \'unresolved_refunded\', resolved_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $request->params['id']]);

        // TODO: trigger the actual refund via the shared Escrow Engine.
        Response::json(['id' => (int) $request->params['id'], 'status' => 'unresolved_refunded']);
    }
}
