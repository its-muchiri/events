<?php

namespace EventCo\Models;

use EventCo\Config\Database;

final class EventBooking
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM event_bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function forCustomer(int $customerId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM event_bookings WHERE customer_id = :customer_id ORDER BY created_at DESC'
        );
        $stmt->execute(['customer_id' => $customerId]);

        return $stmt->fetchAll();
    }
}
