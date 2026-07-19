<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
   <meta charset="utf-8">
   <meta
      name="viewport"
      content="width=device-width, initial-scale=1"
   >

   <!-- CSRF Token -->
   <meta
      name="csrf-token"
      content="{{ csrf_token() }}"
   >

   <title>{{ __('Payment Checkout') }} | {{ config('branding.name') }}</title>

   <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|source-sans-3:400,500,600,700" rel="stylesheet" />

   {{-- vites --}}
   {{-- @routes
    @viteReactRefresh --}}
   @vite(['resources/js/app.tsx'])
</head>

<body class="ssu-page-shell flex min-h-screen items-center justify-center font-sans">
   <div class="payment mx-auto w-full max-w-[1200px] p-6 py-8 md:p-7">
      <div class="mb-6">
         <p class="ssu-kicker">Secure checkout</p>
         <h1 class="font-display text-2xl font-semibold tracking-tight">{{ config('branding.name') }}</h1>
      </div>
      <div class="grid grid-cols-12 items-start gap-7">
         <div class="ssu-surface-card col-span-12 p-6 md:col-span-8">
            @include('paymentgateways::partials.payment.CourseCard')
         </div>
         <div class="ssu-surface-card col-span-12 overflow-hidden md:col-span-4">
            <div class="space-y-6 p-6">
               @include('paymentgateways::partials.payment.PaymentMethod')

               @if (session('error'))
                  <div class="rounded-lg border border-red-500 bg-red-50 p-4 text-red-500">
                     {{ session('error') }}
                  </div>
               @endif
            </div>

            @include('paymentgateways::partials.payment.OrderSummery')

            <form
               id="checkoutForm"
               class="p-6 pt-0"
            >
               @csrf

               {{-- OTP send phone number for SSLCommerz --}}
               <div class='mb-3'>
                  <input
                     id="otpPhone"
                     name="phone"
                     class="border-border hidden w-full rounded-sm border px-3 py-2 text-sm"
                     placeholder="Enter phone number to get OTP"
                  >
                  @error('phone')
                     <div class="text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                  @enderror
               </div>

               <input
                  type="hidden"
                  name="from"
                  value="{{ $from }}"
               />
               <input
                  type="hidden"
                  name="item_type"
                  value="{{ $item_type }}"
               />
               <input
                  type="hidden"
                  name="item_id"
                  value="{{ $id }}"
               />
               <input
                  type="hidden"
                  name="coupon"
                  value="{{ $coupon ? $coupon->code : null }}"
               />
               <input
                  type="hidden"
                  name="subtotal"
                  value="{{ $subtotal }}"
               />
               <input
                  type="hidden"
                  name="couponDiscount"
                  value="{{ $couponDiscount }}"
               />
               <input
                  type="hidden"
                  name="discountedPrice"
                  value="{{ $discountedPrice }}"
               />
               <input
                  type="hidden"
                  name="taxAmount"
                  value="{{ $taxAmount }}"
               />
               <input
                  type="hidden"
                  name="finalPrice"
                  value="{{ $finalPrice }}"
               />

               <button
                  id="checkout"
                  type="submit"
                  class="w-full cursor-pointer rounded-lg bg-blue-500 p-3 text-white hover:bg-blue-600/95"
               >
                  {{ __('Checkout') }}
               </button>
            </form>
         </div>
      </div>
   </div>

   <script src="{{ asset('script/payment.js') }}"></script>
   <script>
      // Function to apply coupon code when clicking on available coupons
      function applyCouponCode(code) {
         const couponInput = document.getElementById('coupon');
         if (couponInput) {
            couponInput.value = code;
            // Optionally auto-submit the form
            couponInput.closest('form').submit();
         }
      }
   </script>
</body>

</html>
