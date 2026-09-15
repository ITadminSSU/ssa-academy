<?php

namespace App\Support;

class SiteAlert
{
    public const DEFAULT_MESSAGE = 'The academy will be temporarily unavailable for scheduled maintenance.';

    public const MAX_LENGTH = 280;

    /**
     * @param  array<string, mixed>|null  $fields
     * @return array{enabled: bool, message: string}
     */
    public static function fromFields(?array $fields): array
    {
        $fields = is_array($fields) ? $fields : [];
        $message = trim(strip_tags((string) ($fields['site_alert_message'] ?? '')));

        if (mb_strlen($message) > self::MAX_LENGTH) {
            $message = mb_substr($message, 0, self::MAX_LENGTH);
        }

        return [
            'enabled' => filter_var($fields['site_alert_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $fields
     */
    public static function visibleMessage(?array $fields): ?string
    {
        $alert = self::fromFields($fields);

        if (! $alert['enabled']) {
            return null;
        }

        return $alert['message'] !== '' ? $alert['message'] : self::DEFAULT_MESSAGE;
    }
}
