<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Request;
use EventCo\Core\Response;

/**
 * Single-vendor bookings — maps to the "Bookings (Single-Vendor)" group in
 * planning/05-event-co-ke/api-endpoints.md. Multi-vendor bundling lives in
 * BundleController.
 */
final class BookingController
{
    public function create(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO event_bookings
                (customer_id, vendor_id, bundle_id, category, event_date, status, pricing_model,
                 head_count, hours_booked, total_amount, deposit_amount, created_at, updated_at)
             VALUES (:customer_id, :vendor_id, :bundle_id, :category, :event_date, \'requested\', :pricing_model,
                 :head_count, :hours_booked, :total_amount, :deposit_amount, NOW(), NOW())'
        );
        $stmt->execute([
            'customer_id' => $request->user['id'] ?? null,
            'vendor_id' => $request->input('vendor_id'),
            'bundle_id' => $request->input('bundle_id'),
            'category' => $request->input('category'),
            'event_date' => $request->input('event_date'),
            'pricing_model' => $request->input('pricing_model'),
            'head_count' => $request->input('head_count'),
            'hours_booked' => $request->input('hours_booked'),
            'total_amount' => $request->input('total_amount', 0),
            'deposit_amount' => $request->input('deposit_amount', 0),
        ]);

        Response::json(['id' => (int) $db->lastInsertId(), 'status' => 'requested'], 201);
    }

    public function show(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM event_bookings WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $booking = $stmt->fetch();

        if (!$booking) {
            Response::notFound('Booking not found');
            return;
        }

        Response::json($booking);
    }

    public function updateStatus(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE event_bookings SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $request->input('status'), 'id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => $request->input('status')]);
    }

    public function cancel(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE event_bookings SET status = \'cancelled\', updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'cancelled']);
    }

    public function vendorAvailability(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM vendor_availability WHERE vendor_id = :vendor_id AND date BETWEEN :from AND :to'
        );
        $stmt->execute([
            'vendor_id' => $request->params['id'],
            'from' => $request->query['from'] ?? date('Y-m-d'),
            'to' => $request->query['to'] ?? date('Y-m-d', strtotime('+90 days')),
        ]);

        Response::json($stmt->fetchAll());
    }

    public function updateMyAvailability(Request $request): void
    {
        $db = Database::connection();
        // See Database::driver() and planning/00-portfolio/ui-implementation-plan.md
        // for why this branches (Vercel's Marketplace has no MySQL-compatible database).
        $sql = Database::driver() === 'pgsql'
            ? 'INSERT INTO vendor_availability (vendor_id, date, is_booked) VALUES (:vendor_id, :date, false)
               ON CONFLICT (vendor_id, date) DO NOTHING'
            : 'INSERT INTO vendor_availability (vendor_id, date, is_booked) VALUES (:vendor_id, :date, false)
               ON DUPLICATE KEY UPDATE is_booked = is_booked';
        $stmt = $db->prepare($sql);
        $stmt->execute(['vendor_id' => $request->user['id'] ?? null, 'date' => $request->input('date')]);

        Response::json(['status' => 'updated']);
    }
}
