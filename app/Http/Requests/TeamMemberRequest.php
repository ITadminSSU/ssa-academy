<?php

namespace App\Http\Requests;

use App\Models\TeamMember;
use App\Rules\MaxWords;
use Illuminate\Foundation\Http\FormRequest;

class TeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);

        if (! $this->hasFile('photo')) {
            $this->request->remove('photo');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $photoRequired = ! $this->route('teamMember');

        return [
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'short_description' => ['nullable', 'string', 'max:65535', new MaxWords(TeamMember::DESCRIPTION_MAX_WORDS)],
            'photo' => [$photoRequired ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:15360'],
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'short_description' => 'description',
        ];
    }
}
