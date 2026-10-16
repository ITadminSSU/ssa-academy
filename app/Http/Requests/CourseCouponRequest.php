<?php

namespace App\Http\Requests;

use App\Models\Course\CourseCoupon;
use App\Models\Course\CourseCouponAssignedEmail;
use Illuminate\Foundation\Http\FormRequest;

class CourseCouponRequest extends FormRequest
{
   public function authorize(): bool
   {
      return true;
   }

   protected function prepareForValidation(): void
   {
      if ($this->exists('assigned_emails')) {
         $parsed = CourseCouponAssignedEmail::parseList($this->input('assigned_emails'));

         $this->merge([
            'assigned_emails' => $parsed['emails'],
            'assigned_emails_invalid' => $parsed['invalid'],
         ]);

         return;
      }

      if (! $this->exists('assigned_email')) {
         return;
      }

      $parsed = CourseCouponAssignedEmail::parseList($this->input('assigned_email'));

      $this->merge([
         'assigned_emails' => $parsed['emails'],
         'assigned_emails_invalid' => $parsed['invalid'],
         'assigned_email' => count($parsed['invalid']) === 0 && count($parsed['emails']) === 1
            ? $parsed['emails'][0]
            : null,
      ]);
   }

   public function withValidator($validator): void
   {
      $validator->after(function ($validator) {
         $invalid = $this->input('assigned_emails_invalid', []);

         if (is_array($invalid) && $invalid !== []) {
            $validator->errors()->add(
               'assigned_emails',
               'Enter a valid email address for every person on the list.',
            );
         }

         $emails = $this->input('assigned_emails', []);

         if (is_array($emails) && count($emails) > 1 && ! CourseCoupon::assignedEmailTableAvailable()) {
            $validator->errors()->add(
               'assigned_emails',
               'The email list could not be saved.',
            );
         }
      });
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
         'assigned_emails' => 'nullable|array',
         'assigned_emails.*' => 'email|max:255',
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
