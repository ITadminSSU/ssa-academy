<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_coupon_assigned_emails')) {
            Schema::create('course_coupon_assigned_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_coupon_id')->constrained('course_coupons')->cascadeOnDelete();
                $table->string('email');
                $table->timestamps();

                $table->unique(['course_coupon_id', 'email']);
            });
        }

        if (! Schema::hasTable('course_coupons') || ! Schema::hasColumn('course_coupons', 'assigned_email')) {
            return;
        }

        DB::table('course_coupons')
            ->select('id', 'assigned_email')
            ->whereNotNull('assigned_email')
            ->orderBy('id')
            ->each(function (object $coupon): void {
                $email = strtolower(trim((string) $coupon->assigned_email));

                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    return;
                }

                $exists = DB::table('course_coupon_assigned_emails')
                    ->where('course_coupon_id', $coupon->id)
                    ->where('email', $email)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('course_coupon_assigned_emails')->insert([
                    'course_coupon_id' => $coupon->id,
                    'email' => $email,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_coupon_assigned_emails');
    }
};
