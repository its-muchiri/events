<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Auth;
use EventCo\Core\Request;
use EventCo\Core\Response;
use EventCo\Models\VendorDirectory;
use Throwable;

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
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        $vendorId = $user['id'];

        $documents = $request->input('documents', []);
        if (empty($documents)) {
            Response::error('At least one KYC document is required to onboard', 422);
            return;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO kyc_documents (user_id, document_type, file_reference, verification_status)
                 VALUES (:user_id, :document_type, :file_reference, \'pending\')'
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
                 VALUES (:vendor_id, :category, :business_name, :portfolio_urls, :pricing_model, :base_price, :service_area, \'active\')'
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

            $stmt = $db->prepare('UPDATE users SET status = \'pending_verification\' WHERE id = :id');
            $stmt->execute(['id' => $vendorId]);

            $db->commit();
            Response::json(['status' => 'pending_verification'], 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error('Onboarding submission failed', 500, ['reason' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/vendors — search/compare. Query params: q, category, area,
     * date (Y-m-d; hides vendors already booked/blocked that day), pricing_model,
     * min_price, max_price, min_rating, sort (rating|popular|price_asc|price_desc|newest), page.
     */
    public function browse(Request $request): void
    {
        $rawDate = trim((string) ($request->query['date'] ?? ''));
        if ($rawDate !== '' && !VendorDirectory::isValidDate($rawDate)) {
            Response::error('date must be a real calendar date in YYYY-MM-DD form', 422);
            return;
        }

        try {
            $result = VendorDirectory::search($request->query);
        } catch (Throwable $e) {
            error_log((string) $e);
            Response::error('Vendor directory is unavailable right now', 503);
            return;
        }

        Response::json([
            'data' => $result['vendors'],
            'page' => $result['page'],
            'has_more' => $result['has_more'],
        ]);
    }

    public function profile(Request $request): void
    {
        $vendorId = (int) $request->params['id'];

        try {
            $vendor = VendorDirectory::find($vendorId);
            if (!$vendor) {
                Response::notFound('Vendor not found');
                return;
            }
            $vendor['reviews'] = VendorDirectory::recentReviews($vendorId);
        } catch (Throwable $e) {
            error_log((string) $e);
            Response::error('Vendor directory is unavailable right now', 503);
            return;
        }

        Response::json($vendor);
    }

    public function save(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        // See Database::driver() and planning/00-portfolio/ui-implementation-plan.md
        // for why this branches (Vercel's Marketplace has no MySQL-compatible database).
        $sql = Database::driver() === 'pgsql'
            ? 'INSERT INTO saved_vendors (customer_id, vendor_id, created_at) VALUES (:customer_id, :vendor_id, NOW())
               ON CONFLICT (customer_id, vendor_id) DO NOTHING'
            : 'INSERT INTO saved_vendors (customer_id, vendor_id, created_at) VALUES (:customer_id, :vendor_id, NOW())
               ON DUPLICATE KEY UPDATE created_at = created_at';
        $stmt = $db->prepare($sql);
        $stmt->execute(['customer_id' => $user['id'], 'vendor_id' => $request->params['id']]);

        Response::json(['status' => 'saved']);
    }

    public function createBusinessAccount(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO business_accounts (primary_user_id, organization_name, created_at) VALUES (:user_id, :org_name, NOW())'
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'org_name' => $request->input('organization_name'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }
}
