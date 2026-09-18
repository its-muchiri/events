<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Auth;
use EventCo\Core\Escrow;
use EventCo\Core\Request;
use EventCo\Core\Response;
use Throwable;

/**
 * Single-vendor bookings — maps to the "Bookings (Single-Vendor)" group in
 * planning/05-event-co-ke/api-endpoints.md. Multi-vendor bundling lives in
 * BundleController.
 */
final class BookingController
{
    public function create(Request $request): void
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
             VALUES (:customer_id, :vendor_id, :bundle_id, :category, :event_date, \'requested\', :pricing_model,
                 :head_count, :hours_booked, :total_amount, :deposit_amount, NOW(), NOW())'
        );
        $stmt->execute([
            'customer_id' => $user['id'],
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

    private const VALID_STATUSES = [
        'requested', 'confirmed', 'at_risk', 'replaced', 'fulfilled', 'completed', 'cancelled', 'disputed',
    ];

    /**
     * Per api-endpoints.md, only the assigned vendor or an admin may update
     * a booking's status. Moving to 'completed' releases the held escrow
     * (Core\Escrow) to the vendor minus platform commission — the trigger
     * point for the deposit's lifecycle, since event.co.ke's MVP has no
     * separate customer confirm-receipt endpoint.
     */
    public function updateStatus(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            Response::error('Invalid status', 422);
            return;
        }

        $bookingId = (int) $request->params['id'];
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM event_bookings WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $bookingId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                $db->rollBack();
                Response::notFound('Booking not found');
                return;
            }

            $isAssignedVendor = (int) $booking['vendor_id'] === (int) $user['id'];
            $isAdmin = ($user['account_type'] ?? null) === 'admin';
            if (!$isAssignedVendor && !$isAdmin) {
                $db->rollBack();
                Response::forbidden('Only the assigned vendor or an admin can update this booking\'s status');
                return;
            }

            $update = $db->prepare('UPDATE event_bookings SET status = :status, updated_at = NOW() WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $bookingId]);

            $escrowResult = null;
            if ($status === 'completed' && $booking['status'] !== 'completed') {
                $escrowResult = Escrow::release($db, $bookingId, (int) $booking['vendor_id']);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        Response::json([
            'id' => $bookingId,
            'status' => $status,
            'escrow' => $escrowResult,
        ]);
    }

    /**
     * Per api-endpoints.md, the booking's customer, its assigned vendor, or
     * an admin may cancel it (subject to cancellation policy — refund
     * handling on a forfeited/held deposit is not built this pass; see
     * MVP_STATUS.md's Known issues).
     */
    public function cancel(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $bookingId = (int) $request->params['id'];
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM event_bookings WHERE id = :id');
        $stmt->execute(['id' => $bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            Response::notFound('Booking not found');
            return;
        }

        $isCustomer = (int) $booking['customer_id'] === (int) $user['id'];
        $isAssignedVendor = (int) $booking['vendor_id'] === (int) $user['id'];
        $isAdmin = ($user['account_type'] ?? null) === 'admin';
        if (!$isCustomer && !$isAssignedVendor && !$isAdmin) {
            Response::forbidden('You do not have access to this booking');
            return;
        }

        $update = $db->prepare('UPDATE event_bookings SET status = \'cancelled\', updated_at = NOW() WHERE id = :id');
        $update->execute(['id' => $bookingId]);

        Response::json(['id' => $bookingId, 'status' => 'cancelled']);
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
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        // See Database::driver() and planning/00-portfolio/ui-implementation-plan.md
        // for why this branches (Vercel's Marketplace has no MySQL-compatible database).
        $sql = Database::driver() === 'pgsql'
            ? 'INSERT INTO vendor_availability (vendor_id, date, is_booked) VALUES (:vendor_id, :date, false)
               ON CONFLICT (vendor_id, date) DO NOTHING'
            : 'INSERT INTO vendor_availability (vendor_id, date, is_booked) VALUES (:vendor_id, :date, false)
               ON DUPLICATE KEY UPDATE is_booked = is_booked';
        $stmt = $db->prepare($sql);
        $stmt->execute(['vendor_id' => $user['id'], 'date' => $request->input('date')]);

        Response::json(['status' => 'updated']);
    }
}
