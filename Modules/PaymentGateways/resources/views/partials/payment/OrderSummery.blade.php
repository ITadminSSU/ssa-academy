<div class="order-summery">
   <div class="p-6">
      <h2 class="mb-4 text-lg font-semibold">{{ __('Total Amount') }}</h2>

      <div class="space-y-2">
         <div class="flex justify-between">
            <span>{{ __('Price') }}</span>
            <span>{{ number_format($subtotal, 2) }} {{ $currency }}</span>
         </div>

         @if (($checkoutMode ?? null) === 'upfront_subscription')
            <p class="text-muted-foreground text-xs">
               {{ __('Pay this enrollment amount now. Monthly subscription billing begins after the first free month.') }}
            </p>
         @elseif (($checkoutMode ?? null) === 'full_launch')
            <p class="text-muted-foreground text-xs">
               {{ __('Pay this enrollment amount now for full access. Your first monthly subscription charge is billed automatically about 30 days later.') }}
            </p>
         @elseif (($checkoutMode ?? null) === 'balance')
            <p class="text-muted-foreground text-xs">
               {{ __('Pay your launch balance now. Monthly subscription billing begins after your free period ends.') }}
            </p>
         @endif

         @if (isset($coupon) && $coupon && $couponDiscount > 0)
            <div class="flex justify-between text-emerald-600">
               <span>{{ __('Coupon Discount') }} ({{ strtoupper($coupon->code) }})</span>
               <span>- {{ number_format($couponDiscount, 2) }} {{ $currency }}</span>
            </div>
         @endif

         @if (config('payment.apply_selling_tax') && $taxAmount > 0)
            <div class="flex justify-between">
               <span>{{ __('Tax') }}</span>
               <span>+ {{ number_format($taxAmount, 2) }} {{ $currency }}</span>
            </div>
         @endif

         <div class="bg-border my-2 h-[1px] w-full"></div>

         <div class="flex justify-between font-bold">
            <span>{{ __('Total:') }}</span>
            <span>{{ number_format($finalPrice, 2) }} {{ $currency }}</span>
         </div>
      </div>

      <div class="summery-body">
         <div class="body-item mt-5 flex items-center justify-between font-semibold">
            <p class="title">{{ __('Pay With') }}</p>
            <p id="paymentMethod">{{ __('') }}</p>
         </div>
      </div>
   </div>
</div>
