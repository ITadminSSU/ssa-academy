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

   {{-- CSS only — do not boot the Inertia React app on this Blade checkout page. --}}
   @vite(['resources/css/app.css'])
</head>

<body class="ssu-page-shell min-h-screen font-sans">
   <div class="payment mx-auto w-full max-w-[1200px] px-6 pt-6 pb-8 md:px-7">
      <a href="{{ url('/courses/all') }}" class="text-primary mb-4 inline-flex text-sm font-semibold underline-offset-4 hover:underline">Back</a>

      <div class="mb-6 rounded-xl bg-[#1a344f] px-6 py-5 text-white">
         <p class="text-xs font-bold tracking-[0.14em] text-[#e89a1b] uppercase">Secure checkout</p>
         <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">{{ $item->title }}</h1>
         @if (filled($item->short_description ?? null) && $item->short_description !== $item->title)
            <p class="mt-1 text-sm text-white/80">{{ $item->short_description }}</p>
         @endif
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
               @if (! empty($choice['plan']))
                  <input type="hidden" name="plan" value="{{ $choice['plan'] }}" />
               @endif

               <button
                  id="checkout"
                  type="submit"
                  class="w-full cursor-pointer rounded-full bg-[#e89a1b] p-3 font-semibold text-[#1a344f] hover:bg-[#f0ac3a]"
               >
                  {{ __('Checkout') }}
               </button>
            </form>
         </div>
      </div>
   </div>

   <script src="{{ asset('script/payment.js') }}"></script>
</body>

</html>
