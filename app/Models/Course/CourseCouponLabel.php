<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCouponLabel extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CourseCouponLabel $label) {
            CourseCoupon::query()
                ->where('course_coupon_label_id', $label->id)
                ->update(['course_coupon_label_id' => null]);
        });
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(CourseCoupon::class, 'course_coupon_label_id');
    }
}
