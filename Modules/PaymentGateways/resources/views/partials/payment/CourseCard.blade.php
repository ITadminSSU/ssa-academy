@php
   $isFree = ($item->pricing_type ?? null) === 'free';
   $hasDiscount = !empty($item->discount);
   $resolvedCheckoutMode = $checkoutMode ?? null;
   $isDepositCheckout = $resolvedCheckoutMode === 'deposit';
   $isBalanceCheckout = $resolvedCheckoutMode === 'balance';
   $isFullLaunchCheckout = $resolvedCheckoutMode === 'full_launch';
   $isLaunchCheckout = $isDepositCheckout || $isBalanceCheckout || $isFullLaunchCheckout;
   $offer = is_array($launchOffer ?? null) ? $launchOffer : [];
   $listPrice = (float) ($offer['list_price'] ?? 0);
   $offerPrice = (float) ($offer['offer_price'] ?? 0);
   $depositAmount = (float) ($offer['deposit_amount'] ?? 0);
   $balanceAmount = (float) ($offer['balance_amount'] ?? 0);
   $fullUpfrontPrice = (float) ($offer['full_upfront_price'] ?? 0);
   $courseDisplayPrice = $isFullLaunchCheckout
      ? ($fullUpfrontPrice > 0 ? $fullUpfrontPrice : $listPrice)
      : ($listPrice > 0 ? $listPrice : $offerPrice);
   $showOfferTotal = ! $isFullLaunchCheckout
      && $offerPrice > 0
      && abs($offerPrice - $courseDisplayPrice) > 0.009;
   $showListStrike = $isFullLaunchCheckout
      && $listPrice > 0
      && $fullUpfrontPrice > 0
      && $listPrice > $fullUpfrontPrice + 0.009;
   $formatMoney = fn ($amount) => $currency.' '.number_format((float) $amount, 2);
   $allowsCoupons = in_array($resolvedCheckoutMode, [
      null,
      'balance',
      'full_launch',
      'upfront_subscription',
      'legacy_one_time',
   ], true);

   // Prefer same-origin cover URL for courses (fresh R2 signature via redirect).
   // Direct private R2 URLs often fail in this Blade checkout page.
   if (($item_type ?? null) === 'course' && ! empty($item->id)) {
      $thumbnailUrl = route('course.cover', $item->id);
   } else {
      $rawImage = method_exists($item, 'getRawOriginal')
         ? ($item->getRawOriginal('thumbnail') ?: $item->getRawOriginal('banner'))
         : null;
      $thumbnailUrl = \App\Support\S3CompatibleStorage::attributeGet(
         is_string($rawImage) ? $rawImage : null
      );
      $thumbnailUrl = is_string($thumbnailUrl) ? trim($thumbnailUrl) : '';
      $thumbnailUrl = $thumbnailUrl !== '' ? $thumbnailUrl : null;
   }
@endphp

<div class="border-border bg-background space-y-6 rounded-xl border p-6 shadow-sm">
   <div class="flex flex-col gap-4 md:flex-row">
      <div class="bg-muted relative h-48 w-full overflow-hidden rounded-lg md:h-40 md:w-56">
         {{-- Placeholder underneath; shown only if the real cover fails to load. --}}
         <div
            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-gradient-to-br from-[#1a2744] via-[#243b66] to-[#2d4a7a] px-3 text-center"
            aria-hidden="true"
         >
            <svg
               xmlns="http://www.w3.org/2000/svg"
               viewBox="0 0 24 24"
               fill="none"
               stroke="currentColor"
               stroke-width="1.5"
               class="h-10 w-10 text-white/90"
            >
               <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 19.75a48.62 48.62 0 0 1 8.232-3.256 60.438 60.438 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 4.5c2.97 0 5.822.655 8.4 1.834a50.636 50.636 0 0 0-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 9.75c2.585 0 5.077-.655 7.24-1.803"
               />
            </svg>
            <span class="line-clamp-2 text-sm font-semibold text-white/90">{{ $item->title }}</span>
         </div>

         @if ($thumbnailUrl)
            <img
               alt="{{ $item->title }}"
               src="{{ $thumbnailUrl }}"
               class="relative z-10 h-full w-full bg-muted object-cover"
               loading="eager"
               decoding="async"
               onerror="this.remove()"
            >
         @endif
      </div>

      <div class="flex-1">
         <h3 class="mb-2 text-xl font-semibold">{{ $item->title }}</h3>
         @if (filled($item->short_description) && $item->short_description !== $item->title)
            <p class="text-muted-foreground mb-4 line-clamp-3">
               {{ $item->short_description }}
            </p>
         @endif
      </div>
   </div>

   @unless ($isFree)
      <div class="border-border bg-muted/50 space-y-3 rounded-lg border border-dashed p-4">
         @if ($isLaunchCheckout)
            <div class="flex items-center justify-between text-sm font-medium">
               <span>Course Price</span>
               <span class="text-base space-x-2">
                  @if ($showListStrike)
                     <span class="text-muted-foreground line-through">{{ $formatMoney($listPrice) }}</span>
                     <span class="font-semibold">{{ $formatMoney($courseDisplayPrice) }}</span>
                  @else
                     <span class="font-semibold">{{ $formatMoney($courseDisplayPrice) }}</span>
                  @endif
               </span>
            </div>

            @if ($showOfferTotal && $isDepositCheckout)
               <div class="flex items-center justify-between text-sm font-medium">
                  <span>Pre-registration total</span>
                  <span class="text-primary text-base font-semibold">{{ $formatMoney($offerPrice) }}</span>
               </div>
            @endif

            @if ($isDepositCheckout)
               <div class="flex items-center justify-between text-sm font-medium">
                  <span>Deposit due today</span>
                  <span class="text-base font-semibold">{{ $formatMoney($depositAmount) }}</span>
               </div>
               @if ($balanceAmount > 0)
                  <div class="flex items-center justify-between text-sm font-medium">
                     <span>Balance due at launch</span>
                     <span class="text-base">{{ $formatMoney($balanceAmount) }}</span>
                  </div>
               @endif
               <p class="text-muted-foreground text-xs leading-relaxed">
                  You are paying the deposit now to reserve your seat. Pay the remaining balance on launch day or within the grace period to unlock the course and receive 30 days of free subscription from that payment.
                  @if (! empty($offer['deposit_non_refundable']))
                     The deposit is non-refundable. If you miss the grace deadline, the free month is cancelled and later enrollment is at the full upfront price.
                  @endif
               </p>
            @elseif ($isBalanceCheckout)
               @if ($depositAmount > 0)
                  <div class="flex items-center justify-between text-sm font-medium">
                     <span>Deposit already paid</span>
                     <span class="text-base">{{ $formatMoney($depositAmount) }}</span>
                  </div>
               @endif
               <div class="flex items-center justify-between text-sm font-medium">
                  <span>Balance due today</span>
                  <span class="text-base font-semibold">{{ $formatMoney($balanceAmount > 0 ? $balanceAmount : $subtotal) }}</span>
               </div>
               <p class="text-muted-foreground text-xs leading-relaxed">
                  Paying this balance unlocks the course and starts 30 days of free subscription from today. Monthly billing begins after those 30 days.
               </p>
            @endif
         @else
            <div class="flex items-center justify-between text-sm font-medium">
               <span>Course Price</span>
               <span class="text-base">
                  @if ($hasDiscount)
                     <span class="text-muted-foreground line-through">
                        {{ $currency }} {{ $item->price }}
                     </span>
                  @else
                     <span class="font-semibold">
                        {{ $currency }} {{ $item->price }}
                     </span>
                  @endif
               </span>
            </div>

            @if ($hasDiscount)
               <div class="flex items-center justify-between text-sm font-medium">
                  <span>Discounted Price</span>
                  <span class="text-primary text-base font-semibold">
                     {{ $currency }} {{ $item->discount_price }}
                  </span>
               </div>
            @endif
         @endif

         @if ($coupon)
            <div class="flex items-center justify-between text-sm font-medium text-emerald-600">
               <span>Coupon Discount ({{ strtoupper($coupon->code) }})</span>
               <span>-{{ $currency }} {{ $couponDiscount }}</span>
            </div>
         @endif

         <div class="flex items-center justify-between text-base font-semibold">
            <span>Total Payment</span>
            <span>{{ $isLaunchCheckout ? $formatMoney($discountedPrice) : $currency.' '.$discountedPrice }}</span>
         </div>
      </div>
   @else
      <div class="rounded-lg border border-dashed border-emerald-500 bg-emerald-50 p-4 text-emerald-700">
         <p class="text-sm font-semibold uppercase tracking-wide">Free Course</p>
         <p class="text-2xl font-bold">Enjoy the course for free!</p>
      </div>
   @endunless

   @unless ($isFree)
      @if ($allowsCoupons)
      <div class="space-y-3">
         <p class="text-muted-foreground text-sm font-medium uppercase tracking-wide">
            Apply Coupon
         </p>

         @if ($coupon)
            <div
               class="flex items-center justify-between rounded-lg border border-emerald-500 bg-emerald-50 px-4 py-3 text-sm"
            >
               <div>
                  <p class="font-medium text-emerald-700">Coupon Applied</p>
                  <p class="text-lg font-semibold uppercase text-emerald-700">{{ $coupon->code }}</p>
               </div>
               <a
                  href="{{ route('payments.index', ['from' => 'web', 'item' => $item_type, 'id' => $item->id]) }}"
                  class="text-primary text-sm font-semibold underline underline-offset-4"
               >
                  Remove
               </a>
            </div>
         @else
            @if (! empty($couponError))
               <div class="rounded-lg border border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                  {{ $couponError }}
               </div>
            @endif

            <form
               method="GET"
               action="{{ route('payments.index', ['from' => 'web', 'item' => $item_type, 'id' => $item->id]) }}"
               class="flex flex-col gap-3 sm:flex-row"
            >
               <div class="relative flex-1">
                  <input
                     id="coupon"
                     type="text"
                     name="coupon"
                     class="@if (! empty($couponError)) border-amber-500 focus:border-amber-500 focus:ring-amber-500/40 @else border-border focus:border-primary focus:ring-primary/40 @endif bg-background h-10 w-full rounded-md border px-4 py-3 text-base focus:outline-none focus:ring-2"
                     placeholder="Enter coupon code if you have one"
                     value="{{ $couponInput ?? old('coupon') }}"
                  >
               </div>

               <button
                  type="submit"
                  class="bg-primary text-primary-foreground hover:bg-primary/90 h-10 rounded-md px-6 text-sm font-semibold uppercase tracking-wide transition"
               >
                  Apply
               </button>
            </form>
         @endif
      </div>
      @endif
   @endunless
</div>
