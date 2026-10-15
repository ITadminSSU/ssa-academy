<?php

namespace App\Http\Requests;

use App\Models\ProfessionalType;
use App\Support\RegistrationProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remove_photo')) {
            $this->merge([
                'remove_photo' => filter_var($this->input('remove_photo'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('worked_as_construction_va')) {
            $this->merge([
                'worked_as_construction_va' => filter_var(
                    $this->input('worked_as_construction_va'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        if ($this->exists('referred_by') || $this->exists('referrer_is_employee')) {
            $referred = trim((string) $this->input('referred_by', ''));
            $team = $this->input('referrer_is_employee');

            $this->merge([
                'referred_by' => $referred,
                'referrer_is_employee' => $referred === '' || $team === null || $team === ''
                    ? null
                    : filter_var($team, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $softwareOptions = RegistrationProfileOptions::estimatingSoftware();
        $experienceOptions = RegistrationProfileOptions::constructionExperience();
        $selected = array_values(array_filter((array) $this->input('estimating_software', [])));
        $type = ProfessionalType::query()->find($this->input('professional_type_id'));
        $typeIsOther = $type && strcasecmp((string) $type->name, 'other') === 0;

        return [
            'name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:15360',
            'remove_photo' => 'nullable|boolean',
            'social_links' => 'nullable',
            'professional_type_id' => 'required|exists:professional_types,id',
            'professional_type_other' => [
                Rule::requiredIf($typeIsOther),
                'nullable',
                'string',
                'max:255',
            ],
            'estimating_software' => 'required|array|min:1',
            'estimating_software.*' => ['string', Rule::in($softwareOptions)],
            'estimating_software_other' => [
                Rule::requiredIf(in_array('Others', $selected, true)),
                'nullable',
                'string',
                'max:255',
            ],
            'construction_experience' => ['required', 'string', Rule::in($experienceOptions)],
            'worked_as_construction_va' => 'required|boolean',
            'referred_by' => 'nullable|string|max:255',
            'referrer_is_employee' => [
                Rule::requiredIf(filled($this->input('referred_by'))),
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'professional_type_id.required' => 'Please select your professional type.',
            'professional_type_other.required' => 'Please specify your professional type.',
            'estimating_software.required' => 'Please select at least one estimating software option.',
            'estimating_software.min' => 'Please select at least one estimating software option.',
            'estimating_software_other.required' => 'Please specify the other estimating software you have used.',
            'construction_experience.required' => 'Please select your years of construction experience.',
            'worked_as_construction_va.required' => 'Please indicate whether you have worked as a Construction Virtual Assistant.',
            'referrer_is_employee.required' => 'Please say whether your referrer is part of the SMARTSOURCING USA team.',
        ];
    }
}
