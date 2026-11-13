@php
   $plan = $choicePlan['plan'] ?? 'recurring';
   $isInstallment = ($choicePlan['mode'] ?? '') === 'one_time_or_installment';
   $oneTime = (float) ($choicePlan['one_time'] ?? 0);
   $oneTimeCompare = (float) ($choicePlan['one_time_compare'] ?? 0);
   $recurring = (float) ($choicePlan['recurring'] ?? 0);
   $recurringCompare = (float) ($choicePlan['recurring_compare'] ?? 0);
   $months = (int) ($choicePlan['months'] ?? 0);
   $bestValue = $choicePlan['best_value'] ?? 'none';
   if (! in_array($bestValue, ['one_time', 'recurring', 'none'], true)) {
      $bestValue = 'none';
   }
   $showOneTimeDisplay = $oneTimeCompare > 0 && $oneTimeCompare < $oneTime - 0.009;
   $showRecurringDisplay = $recurringCompare > 0 && $recurringCompare < $recurring - 0.009;
   $planUrl = function (string $next) use ($item, $item_type, $coupon, $plan) {
      return route('payments.index', array_filter([
         'from' => 'web',
         'item' => $item_type,
         'id' => $item->id,
         'plan' => $next,
         'coupon' => $next === 'one_time' && $plan === 'one_time' ? $coupon?->code : null,
      ]));
   };
@endphp

<p class="text-sm font-semibold uppercase tracking-wide">{{ __('Select payment option') }}</p>

<div class="grid gap-3 sm:grid-cols-2">
   <a
      href="{{ $planUrl('one_time') }}"
      class="relative rounded-xl border p-4 {{ $plan === 'one_time' ? 'border-blue-500 bg-blue-50' : 'border-border bg-background' }}"
   >
      @if ($bestValue === 'one_time')
         <span class="absolute right-3 top-3 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-slate-900">{{ __('Best value') }}</span>
      @endif
      <div class="flex items-start gap-2 {{ $bestValue === 'one_time' ? 'pr-16' : '' }}">
         <span class="mt-1 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 {{ $plan === 'one_time' ? 'border-blue-600' : 'border-slate-400' }}" aria-hidden="true">
            @if ($plan === 'one_time')
               <span class="choice-plan-radio-selected h-2 w-2 rounded-full bg-blue-600"></span>
            @endif
         </span>
         <p class="font-semibold">{{ __('One-Time Payment') }}</p>
      </div>
      <p class="text-muted-foreground mt-1 text-sm">{{ __('Pay in full and get complete access.') }}</p>
      <p class="mt-3 text-lg font-semibold">
         @if ($showOneTimeDisplay)
            <span class="text-muted-foreground mr-2 text-sm line-through">{{ $formatMoney($oneTime) }}</span>
         @endif
         {{ $formatMoney($showOneTimeDisplay ? $oneTimeCompare : $oneTime) }}
      </p>
      @if ($showOneTimeDisplay)
         <p class="text-muted-foreground mt-1 text-xs">{{ __('Apply a coupon to get this price.') }}</p>
      @endif
   </a>

   <a
      href="{{ $planUrl('recurring') }}"
      class="relative rounded-xl border p-4 {{ $plan === 'recurring' ? 'border-blue-500 bg-blue-50' : 'border-border bg-background' }}"
   >
      @if ($bestValue === 'recurring')
         <span class="absolute right-3 top-3 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-slate-900">{{ __('Best value') }}</span>
      @endif
      <div class="flex items-start gap-2 {{ $bestValue === 'recurring' ? 'pr-16' : '' }}">
         <span class="mt-1 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 {{ $plan === 'recurring' ? 'border-blue-600' : 'border-slate-400' }}" aria-hidden="true">
            @if ($plan === 'recurring')
               <span class="choice-plan-radio-selected h-2 w-2 rounded-full bg-blue-600"></span>
            @endif
         </span>
         <p class="font-semibold">{{ $isInstallment ? __('Payment plan') : __('Monthly plan') }}</p>
      </div>
      <p class="text-muted-foreground mt-1 text-sm">
         @if ($isInstallment)
            {{ __(':count payments, then billing stops.', ['count' => $months]) }}
         @else
            {{ __('Spread your investment with monthly payments.') }}
         @endif
      </p>
      <p class="mt-3 text-lg font-semibold">
         @if ($showRecurringDisplay)
            <span class="text-muted-foreground mr-2 text-sm line-through">{{ $formatMoney($recurring) }} / {{ __('month') }}</span>
         @endif
         {{ $formatMoney($showRecurringDisplay ? $recurringCompare : $recurring) }} / {{ __('month') }}
      </p>
      @if (! ($choicePlan['recurring_synced'] ?? false))
         <p class="mt-2 text-xs text-amber-700">{{ __('This option needs a Stripe sync before checkout.') }}</p>
      @endif
   </a>
</div>
