<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
   <meta charset="utf-8">
   <meta
      name="viewport"
      content="width=device-width, initial-scale=1"
   >
   <meta
      name="csrf-token"
      content="{{ csrf_token() }}"
   >
   <title>{{ __('frontend.offline_payment') }}</title>
   @vite(['resources/js/app.tsx'])
</head>

<body class="flex min-h-screen items-center justify-center bg-gray-50">
   <div class="payment mx-auto w-full max-w-[900px] p-6 py-8">
      <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
         {{-- Header --}}
         <div class="bg-blue-500 p-6 text-white">
            <h1 class="mb-2 text-2xl font-bold">{{ __('frontend.offline_payment_instructions') }}</h1>
            <p class="text-blue-100">{{ __('frontend.offline_payment_subtitle') }}
            </p>
         </div>

         <div class="space-y-6 p-6">
            {{-- Order Summary --}}
            <div class="rounded-lg border bg-gray-50 p-6">
               <h2 class="mb-4 text-lg font-semibold text-gray-800">{{ __('frontend.order_summary') }}</h2>
               <div class="space-y-3">
                  <div class="flex items-center justify-between">
                     <span class="text-gray-600">{{ __('frontend.item') }}:</span>
                     <span class="font-medium text-gray-900">{{ $item->title }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                     <span class="text-gray-600">{{ __('frontend.customer') }}:</span>
                     <span class="font-medium text-gray-900">{{ $user->name }}</span>
                  </div>
                  <div class="flex items-center justify-between border-t pt-3">
                     <span class="text-lg font-semibold text-gray-800">{{ __('frontend.total_amount') }}:</span>
                     <span class="text-2xl font-bold text-blue-600">{{ $currency }}
                        {{ number_format($amount, 2) }}</span>
                  </div>
               </div>
            </div>

            {{-- Payment Instructions --}}
            @if ($payment_instructions)
               <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-6">
                  <h2 class="mb-3 flex items-center text-lg font-semibold text-gray-800">
                     <svg
                        class="mr-2 h-5 w-5 text-yellow-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                     >
                        <path
                           stroke-linecap="round"
                           stroke-linejoin="round"
                           stroke-width="2"
                           d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        ></path>
                     </svg>
                     {{ __('frontend.payment_instructions') }}
                  </h2>
                  <div class="prose prose-sm max-w-none text-gray-700">
                     {!! $payment_instructions !!}
                  </div>
               </div>
            @endif

            {{-- Payment Details --}}
            @if ($payment_details)
               <div class="rounded-lg border border-blue-200 bg-blue-50 p-6">
                  <h2 class="mb-4 flex items-center text-lg font-semibold text-gray-800">
                     <svg
                        class="mr-2 h-5 w-5 text-blue-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                     >
                        <path
                           stroke-linecap="round"
                           stroke-linejoin="round"
                           stroke-width="2"
                           d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                        ></path>
                     </svg>
                     {{ __('frontend.payment_details') }}
                  </h2>
                  <div class="prose prose-sm max-w-none text-gray-700">
                     {!! $payment_details !!}
                  </div>
               </div>
            @endif

            {{-- Payment Submission Form --}}
            <div class="rounded-lg border bg-white p-6">
               <h2 class="mb-4 text-lg font-semibold text-gray-800">{{ __('frontend.submit_payment_details') }}</h2>

               <form
                  action="{{ route('payments.offline.submit') }}"
                  method="POST"
                  class="space-y-4"
               >
                  @csrf

                  <div>
                     <label
                        for="transaction_reference"
                        class="mb-2 block text-sm font-medium text-gray-700"
                     >
                        {{ __('frontend.transaction_reference_label') }} <span class="text-red-500">*</span>
                     </label>
                     <input
                        type="text"
                        id="transaction_reference"
                        name="transaction_reference"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        placeholder="{{ __('frontend.transaction_reference_placeholder') }}"
                        value="{{ old('transaction_reference') }}"
                     >
                     @error('transaction_reference')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                     @enderror
                  </div>

                  <div>
                     <label
                        for="payment_method"
                        class="mb-2 block text-sm font-medium text-gray-700"
                     >
                        {{ __('frontend.payment_method_used') }} <span class="text-red-500">*</span>
                     </label>
                     <select
                        id="payment_method"
                        name="payment_method"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                     >
                        <option value="">{{ __('frontend.select_payment_method') }}</option>
                        <option
                           value="bank_transfer"
                           {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}
                        >{{ __('frontend.bank_transfer') }}</option>
                        <option
                           value="cash_deposit"
                           {{ old('payment_method') == 'cash_deposit' ? 'selected' : '' }}
                        >{{ __('frontend.cash_deposit') }}</option>
                        <option
                           value="mobile_money"
                           {{ old('payment_method') == 'mobile_money' ? 'selected' : '' }}
                        >{{ __('frontend.mobile_money') }}</option>
                        <option
                           value="cheque"
                           {{ old('payment_method') == 'cheque' ? 'selected' : '' }}
                        >{{ __('frontend.cheque') }}</option>
                        <option
                           value="other"
                           {{ old('payment_method') == 'other' ? 'selected' : '' }}
                        >{{ __('frontend.other') }}</option>
                     </select>
                     @error('payment_method')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                     @enderror
                  </div>

                  <div>
                     <label
                        for="payment_date"
                        class="mb-2 block text-sm font-medium text-gray-700"
                     >
                        {{ __('frontend.payment_date') }} <span class="text-red-500">*</span>
                     </label>
                     <input
                        type="date"
                        id="payment_date"
                        name="payment_date"
                        required
                        max="{{ date('Y-m-d') }}"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        value="{{ old('payment_date', date('Y-m-d')) }}"
                     >
                     @error('payment_date')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                     @enderror
                  </div>

                  <div>
                     <label
                        for="notes"
                        class="mb-2 block text-sm font-medium text-gray-700"
                     >
                        {{ __('frontend.additional_notes') }} <span
                           class="text-xs text-gray-500">({{ __('frontend.optional') }})</span>
                     </label>
                     <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        placeholder="{{ __('frontend.payment_notes_placeholder') }}"
                     >{{ old('notes') }}</textarea>
                     @error('notes')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                     @enderror
                  </div>

                  {{-- Info Alert --}}
                  <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                     <div class="flex">
                        <svg
                           class="mr-3 mt-0.5 h-5 w-5 shrink-0 text-blue-600"
                           fill="currentColor"
                           viewBox="0 0 20 20"
                        >
                           <path
                              fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                              clip-rule="evenodd"
                           ></path>
                        </svg>
                        <div class="text-sm text-blue-800">
                           <p class="mb-1 font-medium">{{ __('frontend.important_notice') }}</p>
                           <p>{{ __('frontend.offline_payment_notice') }}
                           </p>
                        </div>
                     </div>
                  </div>

                  {{-- Action Buttons --}}
                  <div class="flex gap-3 pt-4">
                     <button
                        type="submit"
                        class="flex flex-1 items-center justify-center rounded-lg bg-blue-500 px-6 py-3 font-semibold text-white transition duration-200 hover:bg-blue-600"
                     >
                        <svg
                           class="mr-2 h-5 w-5"
                           fill="none"
                           stroke="currentColor"
                           viewBox="0 0 24 24"
                        >
                           <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                           ></path>
                        </svg>
                        {{ __('frontend.submit_payment_details') }}
                     </button>
                     <a
                        href="{{ route('payments.offline.cancel') }}"
                        class="shrink-0 rounded-lg bg-gray-200 px-6 py-3 font-semibold text-gray-700 transition duration-200 hover:bg-gray-300"
                     >
                        {{ __('common.cancel') }}
                     </a>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </div>
</body>

</html>
