<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class CourseCouponAssignedEmail extends Model
{
    protected $fillable = [
        'course_coupon_id',
        'email',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CourseCoupon::class, 'course_coupon_id');
    }

    /**
     * @return array{emails: list<string>, invalid: list<string>}
     */
    public static function parseList(mixed $value): array
    {
        $parts = is_array($value)
            ? $value
            : preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        $emails = [];
        $invalid = [];

        foreach ($parts ?: [] as $part) {
            $email = strtolower(trim((string) $part));

            if ($email === '') {
                continue;
            }

            $validator = Validator::make(
                ['email' => $email],
                ['email' => 'email|max:255'],
            );

            if ($validator->fails()) {
                $invalid[] = $email;

                continue;
            }

            $emails[$email] = $email;
        }

        return [
            'emails' => array_values($emails),
            'invalid' => array_values(array_unique($invalid)),
        ];
    }
}
