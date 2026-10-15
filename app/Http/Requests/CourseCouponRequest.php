<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseCouponRequest extends FormRequest
{
   public function authorize(): bool
   {
      return true;
   }

   protected function prepareForValidation(): void
   {
      if (! $this->exists('assigned_email')) {
         return;
      }

      $email = strtolower(trim((string) $this->input('assigned_email')));

      $this->merge([
         'assigned_email' => $email === '' ? null : $email,
      ]);
   }

   public function rules(): array
   {
      $coupon = $this->route('coupon');
      $couponId = $coupon instanceof \App\Models\Course\CourseCoupon ? $coupon->id : ($coupon ?? '');

      return [
         'course_id' => 'nullable|exists:courses,id',
         'code' => 'required|string|max:50|unique:course_coupons,code,' . $couponId,
         'discount_type' => 'required|in:percentage,fixed',
         'discount' => 'required|numeric|min:0',
         'valid_from' => 'nullable|date',
         'valid_to' => 'nullable|date|after:valid_from',
         'usage_limit' => 'nullable|integer|min:1',
         'is_active' => 'boolean',
         'show_on_catalog' => 'boolean',
         'assigned_email' => 'nullable|email|max:255',
      ];
   }

   public function messages(): array
   {
      return [
         'code.required' => 'The coupon code is required.',
         'code.unique' => 'This coupon code already exists.',
         'discount_type.required' => 'Please select a discount type.',
         'discount.required' => 'Please specify the discount value.',
         'valid_to.after' => 'Valid to date must be after valid from date.',
      ];
   }
}
