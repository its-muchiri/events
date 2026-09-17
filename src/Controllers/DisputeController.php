<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Request;
use EventCo\Core\Response;

/**
 * Category taxonomy (planning/05-event-co-ke/database-schema.md):
 * vendor_cancellation, vendor_no_show, service_quality, price_disagreement, other.
 * vendor_cancellation within a bundle is primarily handled via
 * BundleController::reportVendorCancellation()'s dedicated coordination
 * workflow (bundle_cancellation_incidents); this controller covers the
 * general dispute categories and the formal penalty record once a
 * cancellation incident resolves.
 */
final class DisputeController
{
    public function store(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO disputes (booking_id, raised_by, category, description, evidence_urls, status, created_at)
             VALUES (:booking_id, :raised_by, :category, :description, :evidence_urls, \'open\', NOW())'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'raised_by' => $request->user['id'] ?? null,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'evidence_urls' => json_encode($request->input('evidence_urls', [])),
        ]);

        Response::json(['id' => (int) $db->lastInsertId(), 'status' => 'open'], 201);
    }

    public function index(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->query('SELECT * FROM disputes WHERE status IN (\'open\', \'under_review\') ORDER BY created_at ASC');

        Response::json($stmt->fetchAll());
    }

    public function resolve(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE disputes SET status = :status, resolved_by = :resolved_by, resolution_notes = :notes, resolved_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $request->input('status'),
            'resolved_by' => $request->user['id'] ?? null,
            'notes' => $request->input('resolution_notes'),
            'id' => $request->params['id'],
        ]);

        Response::json(['id' => (int) $request->params['id'], 'status' => $request->input('status')]);
    }
}
