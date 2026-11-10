@php
   $plan = $choicePlan['plan'] ?? 'recurring';
   $isInstallment = ($choicePlan['mode'] ?? '') === 'one_time_or_installment';
   $oneTime = (float) ($choicePlan['one_time'] ?? 0);
   $oneTimeCompare = (float) ($choicePlan['one_time_compare'] ?? 0);
   $recurring = (float) ($choicePlan['recurring'] ?? 0);
   $recurringCompare = (float) ($choicePlan['recurring_compare'] ?? 0);
   $months = (int) ($choicePlan['months'] ?? 0);
   $showOneTimeCompare = $oneTimeCompare > $oneTime + 0.009;
   $showRecurringCompare = $recurringCompare > $recurring + 0.009;
   $planUrl = function (string $next) use ($item, $item_type, $coupon) {
      return route('payments.index', array_filter([
         'from' => 'web',
         'item' => $item_type,
         'id' => $item->id,
         'plan' => $next,
         'coupon' => $coupon?->code,
      ]));
   };
@endphp

<p class="text-sm font-semibold uppercase tracking-wide">{{ __('Select payment option') }}</p>

<div class="grid gap-3 sm:grid-cols-2">
   <a
      href="{{ $planUrl('one_time') }}"
      class="rounded-xl border p-4 {{ $plan === 'one_time' ? 'border-blue-500 bg-blue-50' : 'border-border bg-background' }}"
   >
      <p class="font-semibold">{{ __('One-Time Payment') }}</p>
      <p class="text-muted-foreground mt-1 text-sm">{{ __('Pay in full and get complete access.') }}</p>
      <p class="mt-3 text-lg font-semibold">
         @if ($showOneTimeCompare)
            <span class="text-muted-foreground mr-2 text-sm line-through">{{ $formatMoney($oneTimeCompare) }}</span>
         @endif
         {{ $formatMoney($oneTime) }}
      </p>
   </a>

   <a
      href="{{ $planUrl('recurring') }}"
      class="relative rounded-xl border p-4 {{ $plan === 'recurring' ? 'border-blue-500 bg-blue-50' : 'border-border bg-background' }}"
   >
      <span class="absolute right-3 top-3 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-slate-900">{{ __('Best value') }}</span>
      <p class="font-semibold">{{ $isInstallment ? __('Payment plan') : __('Monthly plan') }}</p>
      <p class="text-muted-foreground mt-1 text-sm">
         @if ($isInstallment)
            {{ __(':count payments, then billing stops.', ['count' => $months]) }}
         @else
            {{ __('Spread your investment with monthly payments.') }}
         @endif
      </p>
      <p class="mt-3 text-lg font-semibold">
         @if ($showRecurringCompare)
            <span class="text-muted-foreground mr-2 text-sm line-through">{{ $formatMoney($recurringCompare) }} / {{ __('month') }}</span>
         @endif
         {{ $formatMoney($recurring) }} / {{ __('month') }}
      </p>
      @if (! ($choicePlan['recurring_synced'] ?? false))
         <p class="mt-2 text-xs text-amber-700">{{ __('This option needs a Stripe sync before checkout.') }}</p>
      @endif
   </a>
</div>
