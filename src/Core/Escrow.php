<?php

namespace EventCo\Core;

use PDO;

/**
 * Minimal implementation of the shared Escrow & Commission Engine (see
 * planning/00-portfolio/shared-architecture.md module 2) scoped to what
 * event.co.ke's MVP needs: hold a completed deposit charge until the vendor
 * marks the booking completed, then release it to the vendor minus platform
 * commission. Trust-tier retention/release-condition defaults follow
 * shared-architecture.md's trust-tiering table. Mirrored from
 * laundry.co.ke's reference implementation for portfolio consistency.
 *
 * Real B2C disbursement (actually moving KES to the vendor's M-Pesa
 * account) is out of scope for this pass — see MVP_STATUS.md's Known
 * issues. `release()` records the payout as a `payments` row with
 * status='pending', ready for a future payout-batch job to execute
 * against Daraja's B2C API.
 */
final class Escrow
{
    /** Default commission rate used when no commission_rules row matches. */
    private const DEFAULT_COMMISSION_PERCENTAGE = 18.0;

    /**
     * Opens an escrow hold for a completed deposit charge. Called once a
     * payment's M-Pesa callback confirms success.
     */
    public static function hold(PDO $db, int $paymentId, int $bookingId, float $amount): void
    {
        [$retentionPercentage, $releaseCondition, $releaseAt] = self::trustTierDefaults($amount);

        $stmt = $db->prepare(
            'INSERT INTO escrow_transactions
                (payment_id, booking_id, held_amount, retention_percentage, release_condition, release_at, status)
             VALUES (:payment_id, :booking_id, :held_amount, :retention_percentage, :release_condition, :release_at, \'held\')'
        );
        $stmt->execute([
            'payment_id' => $paymentId,
            'booking_id' => $bookingId,
            'held_amount' => $amount,
            'retention_percentage' => $retentionPercentage,
            'release_condition' => $releaseCondition,
            'release_at' => $releaseAt,
        ]);
    }

    /**
     * Releases a booking's held escrow to its vendor minus platform
     * commission, on booking completion. No-ops (returns null) if there's
     * nothing held for this booking.
     *
     * @return array{commission_amount:float,payout_amount:float,retained_amount:float}|null
     */
    public static function release(PDO $db, int $bookingId, int $vendorId): ?array
    {
        $stmt = $db->prepare(
            'SELECT * FROM escrow_transactions WHERE booking_id = :booking_id AND status = \'held\' FOR UPDATE'
        );
        $stmt->execute(['booking_id' => $bookingId]);
        $escrow = $stmt->fetch();

        if (!$escrow) {
            return null;
        }

        $heldAmount = (float) $escrow['held_amount'];
        $retentionPercentage = (float) $escrow['retention_percentage'];
        $commissionRate = self::commissionRate($db);

        $commissionAmount = round($heldAmount * $commissionRate / 100, 2);
        $retainedAmount = round($heldAmount * $retentionPercentage / 100, 2);
        $payoutAmount = round($heldAmount - $commissionAmount - $retainedAmount, 2);

        $paymentStmt = $db->prepare('SELECT method FROM payments WHERE id = :id');
        $paymentStmt->execute(['id' => $escrow['payment_id']]);
        $originalMethod = $paymentStmt->fetchColumn() ?: 'mpesa_stk';

        $insertPayment = $db->prepare(
            'INSERT INTO payments (user_id, booking_id, type, method, amount, currency, status, created_at, updated_at)
             VALUES (:user_id, :booking_id, :type, :method, :amount, \'KES\', :status, NOW(), NOW())'
        );

        // Platform commission — a ledger entry, not an external transfer, so
        // it settles immediately on the same rail the charge arrived on.
        $insertPayment->execute([
            'user_id' => $vendorId,
            'booking_id' => $bookingId,
            'type' => 'commission',
            'method' => $originalMethod,
            'amount' => $commissionAmount,
            'status' => 'completed',
        ]);

        // Vendor payout — the actual outbound leg, executed later by a
        // payout-batch job against Daraja's B2C API (not built this pass).
        $insertPayment->execute([
            'user_id' => $vendorId,
            'booking_id' => $bookingId,
            'type' => 'payout',
            'method' => 'mpesa_b2c',
            'amount' => $payoutAmount,
            'status' => 'pending',
        ]);

        $newStatus = $retainedAmount > 0 ? 'partially_released' : 'released';
        $updateEscrow = $db->prepare(
            'UPDATE escrow_transactions SET status = :status, released_at = NOW() WHERE id = :id'
        );
        $updateEscrow->execute(['status' => $newStatus, 'id' => $escrow['id']]);

        return [
            'commission_amount' => $commissionAmount,
            'payout_amount' => $payoutAmount,
            'retained_amount' => $retainedAmount,
        ];
    }

    /**
     * @return array{0:float,1:string,2:?string} [retention_percentage, release_condition, release_at]
     */
    private static function trustTierDefaults(float $amount): array
    {
        if ($amount < 5000) {
            // Tier 1 — near-instant release on completion confirmation.
            return [0.0, 'customer_confirmation', null];
        }

        if ($amount < 100000) {
            // Tier 2 — held until vendor marks completed, or a 48h
            // auto-release safety net (release_at recorded for a future
            // scheduled job; not built this pass).
            $releaseAt = (new \DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');
            return [0.0, 'customer_confirmation', $releaseAt];
        }

        // Tier 3 — retain 10%, released only after admin review.
        return [10.0, 'admin_release', null];
    }

    private static function commissionRate(PDO $db): float
    {
        $stmt = $db->prepare(
            "SELECT value, commission_type FROM commission_rules
             WHERE category = 'event_booking'
                AND effective_from <= NOW()
                AND (effective_to IS NULL OR effective_to > NOW())
             ORDER BY effective_from DESC LIMIT 1"
        );
        $stmt->execute();
        $rule = $stmt->fetch();

        if ($rule && $rule['commission_type'] === 'percentage') {
            return (float) $rule['value'];
        }

        return self::DEFAULT_COMMISSION_PERCENTAGE;
    }
}
