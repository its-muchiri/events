<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Request;
use EventCo\Core\Response;

final class PaymentController
{
    public function stkPush(Request $request): void
    {
        Response::json(['status' => 'stk_push_initiated', 'checkout_request_id' => null], 202);
    }

    public function card(Request $request): void
    {
        Response::json(['status' => 'card_charge_initiated'], 202);
    }

    public function mpesaCallback(Request $request): void
    {
        Response::json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function myEarnings(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM payments WHERE user_id = :user_id AND type = \'payout\' ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $request->user['id'] ?? null]);

        Response::json($stmt->fetchAll());
    }
}
