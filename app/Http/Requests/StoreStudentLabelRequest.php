<?php

namespace App\Http\Requests;

use App\Models\StudentLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'color' => strtolower(trim((string) $this->input('color'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = mb_strtolower((string) $this->input('name'));

            $taken = StudentLabel::query()
                ->whereRaw('lower(name) = ?', [$name])
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'A label with this name already exists.');
            }
        });
    }
}
