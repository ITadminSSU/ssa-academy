<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['type' => 'payment', 'sub_type' => 'bank_transfer'],
            [
                'title' => 'Bank Transfer Settings',
                'fields' => [
                    'active' => false,
                    'payment_instructions' => 'Complete your payment via bank transfer using the account details below. Upload your proof of payment on the next screen.',
                    'payment_details' => 'Bank Name: \nAccount Name: \nAccount Number: \nRouting / SWIFT:',
                ],
            ],
        );

        Setting::firstOrCreate(
            ['type' => 'payment', 'sub_type' => 'wire_transfer'],
            [
                'title' => 'Wire Transfer Settings',
                'fields' => [
                    'active' => false,
                    'payment_instructions' => 'Complete your payment via wire transfer. International wires may take 3–5 business days to clear.',
                    'payment_details' => 'Beneficiary: \nBank: \nSWIFT/BIC: \nIBAN/Account: \nReference:',
                ],
            ],
        );
    }

    public function down(): void
    {
        Setting::where('type', 'payment')
            ->whereIn('sub_type', ['bank_transfer', 'wire_transfer'])
            ->delete();
    }
};
