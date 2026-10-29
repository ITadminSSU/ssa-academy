<?php

namespace App\Support;

use Illuminate\Validation\Validator;

class PersonName
{
    public static function clean(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function split(string $name): array
    {
        $name = self::clean($name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], isset($parts[1]) ? trim($parts[1]) : ''];
    }

    public static function join(?string $given, ?string $last): string
    {
        return self::clean(trim((string) $given).' '.trim((string) $last));
    }

    public static function greeting(?string $given): string
    {
        $given = trim((string) $given);

        return $given !== '' ? $given : 'there';
    }

    /**
     * Stored parts win. A row that has not been split yet uses the first word of the full name.
     *
     * @return array{0: string, 1: string}
     */
    public static function webhookNames(?string $given, ?string $last, ?string $fullName): array
    {
        $given = trim((string) $given);

        if ($given !== '') {
            return [$given, trim((string) $last)];
        }

        return self::split((string) $fullName);
    }

    public static function fits(?string $given, ?string $last): bool
    {
        return mb_strlen(self::join($given, $last)) <= 255;
    }

    /**
     * @return array{given_name: string, last_name: ?string, name: string}
     */
    public static function attributes(string $given, ?string $last): array
    {
        $given = self::clean($given);
        $last = self::clean((string) $last);

        return [
            'given_name' => $given,
            'last_name' => $last !== '' ? $last : null,
            'name' => self::join($given, $last),
        ];
    }

    public static function validatePair(Validator $validator, ?string $given, ?string $last): void
    {
        if (! self::fits($given, $last)) {
            $validator->errors()->add('last_name', 'The given name and last name together must be 255 characters or fewer.');
        }
    }
}
