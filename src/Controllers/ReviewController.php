<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Auth;
use EventCo\Core\Request;
use EventCo\Core\Response;

/**
 * Per planning/05-event-co-ke/user-flows.md step 9: reviews are per-vendor,
 * even within a bundle — not one review for the whole bundle.
 */
final class ReviewController
{
    public function store(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO reviews (booking_id, reviewer_id, reviewee_id, rating, comment, created_at)
             VALUES (:booking_id, :reviewer_id, :reviewee_id, :rating, :comment, NOW())'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'reviewer_id' => $user['id'],
            'reviewee_id' => $request->input('reviewee_id'),
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }

    public function forVendor(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT r.* FROM reviews r
             JOIN event_bookings b ON b.id = r.booking_id
             WHERE b.vendor_id = :vendor_id
             ORDER BY r.created_at DESC'
        );
        $stmt->execute(['vendor_id' => $request->params['id']]);

        Response::json($stmt->fetchAll());
    }
}
