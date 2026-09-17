<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Request;
use EventCo\Core\Response;

/**
 * Vendor onboarding, directory browsing, public profile, favorites, and
 * business accounts (see planning/05-event-co-ke/api-endpoints.md's
 * "Platform-Specific Resources" group).
 *
 * KYC depth varies by vendor category (a venue needs more verification
 * than an individual photographer) — this is an assumed, unconfirmed
 * platform-specific configuration of the shared trust-tiering model (see
 * open-questions.md #4), so this controller only requires the universal
 * minimum (national ID) rather than guessing a category-specific matrix.
 */
final class VendorController
{
    public function onboard(Request $request): void
    {
        $db = Database::connection();
        $vendorId = $request->user['id'] ?? null;

        $documents = $request->input('documents', []);
        if (empty($documents)) {
            Response::error('At least one KYC document is required to onboard', 422);
            return;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO kyc_documents (user_id, document_type, file_reference, verification_status)
                 VALUES (:user_id, :document_type, :file_reference, "pending")'
            );
            foreach ($documents as $document) {
                $stmt->execute([
                    'user_id' => $vendorId,
                    'document_type' => $document['document_type'],
                    'file_reference' => $document['file_reference'],
                ]);
            }

            $stmt = $db->prepare(
                'INSERT INTO vendor_listings (vendor_id, category, business_name, portfolio_urls, pricing_model, base_price, service_area, status)
                 VALUES (:vendor_id, :category, :business_name, :portfolio_urls, :pricing_model, :base_price, :service_area, "active")'
            );
            $stmt->execute([
                'vendor_id' => $vendorId,
                'category' => $request->input('category'),
                'business_name' => $request->input('business_name'),
                'portfolio_urls' => json_encode($request->input('portfolio_urls', [])),
                'pricing_model' => $request->input('pricing_model'),
                'base_price' => $request->input('base_price', 0),
                'service_area' => $request->input('service_area'),
            ]);

            $stmt = $db->prepare('UPDATE users SET status = "pending_verification" WHERE id = :id');
            $stmt->execute(['id' => $vendorId]);

            $db->commit();
            Response::json(['status' => 'pending_verification'], 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error('Onboarding submission failed', 500, ['reason' => $e->getMessage()]);
        }
    }

    public function browse(Request $request): void
    {
        $db = Database::connection();
        $category = $request->query['category'] ?? null;

        if ($category) {
            $stmt = $db->prepare('SELECT * FROM vendor_listings WHERE status = "active" AND category = :category');
            $stmt->execute(['category' => $category]);
        } else {
            $stmt = $db->query('SELECT * FROM vendor_listings WHERE status = "active"');
        }

        Response::json($stmt->fetchAll());
    }

    public function profile(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT u.id, u.full_name, u.status, vl.category, vl.business_name, vl.portfolio_urls, vl.pricing_model, vl.base_price, vl.service_area
             FROM users u JOIN vendor_listings vl ON vl.vendor_id = u.id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $request->params['id']]);
        $vendor = $stmt->fetch();

        if (!$vendor) {
            Response::notFound('Vendor not found');
            return;
        }

        $standingStmt = $db->prepare('SELECT * FROM vendor_standing WHERE vendor_id = :id');
        $standingStmt->execute(['id' => $request->params['id']]);
        $vendor['standing'] = $standingStmt->fetch() ?: null;

        Response::json($vendor);
    }

    public function save(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO saved_vendors (customer_id, vendor_id, created_at) VALUES (:customer_id, :vendor_id, NOW())
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        $stmt->execute(['customer_id' => $request->user['id'] ?? null, 'vendor_id' => $request->params['id']]);

        Response::json(['status' => 'saved']);
    }

    public function createBusinessAccount(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO business_accounts (primary_user_id, organization_name, created_at) VALUES (:user_id, :org_name, NOW())'
        );
        $stmt->execute([
            'user_id' => $request->user['id'] ?? null,
            'org_name' => $request->input('organization_name'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }
}
