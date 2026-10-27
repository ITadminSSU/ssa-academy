<?php

use App\Enums\CourseBillingModel;
use App\Models\Course\Course;
use App\Support\InstallmentBillingRules;
use App\Support\StripeInvoiceIds;

it('labels the installment plan for the catalog card', function () {
    expect(InstallmentBillingRules::planLabel(15, 6, 6))->toBe('$15/mo for 6 months, then $6/mo');

    $course = new Course([
        'billing_model' => CourseBillingModel::INSTALLMENT_SUBSCRIPTION,
        'pricing_type' => 'paid',
        'installment_price' => 15,
        'installment_months' => 6,
        'subscription_price' => 6,
    ]);

    expect($course->installmentPlanLabel())->toBe('$15/mo for 6 months, then $6/mo');
    expect($course->usesSubscriptionBilling())->toBeFalse();
});

it('counts the checkout invoice as installment 1 and switches only after the last paid installment', function () {
    $installmentPrice = 'price_installment';
    $ongoingPrice = 'price_ongoing';

    expect(InstallmentBillingRules::shouldSwitchToOngoing(1, 6, true, false, $installmentPrice, $ongoingPrice))->toBeFalse();
    expect(InstallmentBillingRules::shouldSwitchToOngoing(5, 6, true, false, $installmentPrice, $ongoingPrice))->toBeFalse();
    expect(InstallmentBillingRules::shouldSwitchToOngoing(6, 6, true, false, $installmentPrice, $ongoingPrice))->toBeTrue();
    expect(InstallmentBillingRules::shouldSwitchToOngoing(6, 6, true, false, $ongoingPrice, $ongoingPrice))->toBeFalse();
});

it('does not change the installment price while an invoice is unpaid or another invoice is open', function () {
    $installmentPrice = 'price_installment';
    $ongoingPrice = 'price_ongoing';

    expect(InstallmentBillingRules::isInstallmentPrice('price_other', $installmentPrice, $installmentPrice, $ongoingPrice))->toBeFalse();
    expect(InstallmentBillingRules::isInstallmentPrice($ongoingPrice, $installmentPrice, $ongoingPrice, $ongoingPrice))->toBeFalse();
    expect(InstallmentBillingRules::isInstallmentPrice($installmentPrice, $installmentPrice, $installmentPrice, $ongoingPrice))->toBeTrue();

    expect(InstallmentBillingRules::shouldSwitchToOngoing(6, 6, false, false, $installmentPrice, $ongoingPrice))->toBeFalse();
    expect(InstallmentBillingRules::shouldSwitchToOngoing(6, 6, true, true, $installmentPrice, $ongoingPrice))->toBeFalse();
});

it('reads the recurring price id from a stripe invoice', function () {
    $invoice = (object) [
        'lines' => (object) [
            'data' => [
                (object) ['price' => (object) ['id' => 'price_installment']],
            ],
        ],
    ];

    expect(StripeInvoiceIds::recurringPriceId($invoice))->toBe('price_installment');
});
