<?php

namespace App\Http\Requests;

use App\Support\AuthenticatedUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseWishlistRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => AuthenticatedUser::resolve(
                $this->input('user_id') ? (int) $this->input('user_id') : null,
            ),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
        ];
    }
}
