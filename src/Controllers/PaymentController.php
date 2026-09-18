<?php

namespace EventCo\Controllers;

use EventCo\Config\Database;
use EventCo\Core\Auth;
use EventCo\Core\Request;
use EventCo\Core\Response;

final class PaymentController
{
    public function stkPush(Request $request): void
    {
        if (!Auth::requireUser($request)) {
            return;
        }

        Response::json(['status' => 'stk_push_initiated', 'checkout_request_id' => null], 202);
    }

    public function card(Request $request): void
    {
        if (!Auth::requireUser($request)) {
            return;
        }

        Response::json(['status' => 'card_charge_initiated'], 202);
    }

    public function mpesaCallback(Request $request): void
    {
        Response::json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function myEarnings(Request $request): void
    {
        $user = Auth::requireUser($request);
        if (!$user) {
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM payments WHERE user_id = :user_id AND type = \'payout\' ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $user['id']]);

        Response::json($stmt->fetchAll());
    }
}
