<?php

namespace App\Http\Requests;

use App\Support\LandingOverlay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLandingOverlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return canManagePlatformSettings();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'overlay_enabled' => 'required|boolean',
            'overlay_headline' => 'nullable|string|max:160',
            'overlay_pains_title' => 'nullable|string|max:160',
            'overlay_pains' => 'nullable|array|max:12',
            'overlay_pains.*' => 'nullable|string|max:240',
            'overlay_solution_title' => 'nullable|string|max:160',
            'overlay_solution_html' => 'nullable|string|max:20000',
            'overlay_cta_label' => 'nullable|string|max:80',
            'overlay_cta_url' => 'nullable|string|max:500',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'overlay_enabled' => filter_var($this->input('overlay_enabled'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = trim((string) $this->input('overlay_cta_url', ''));

            if ($url !== '' && ! LandingOverlay::isAllowedUrl($url)) {
                $validator->errors()->add('overlay_cta_url', 'Use a site path like /register, or an http(s), mailto, or tel link.');
            }

            if (! $this->boolean('overlay_enabled')) {
                return;
            }

            $headline = trim((string) $this->input('overlay_headline', ''));
            $solution = trim(strip_tags((string) $this->input('overlay_solution_html', '')));
            $pains = array_filter((array) $this->input('overlay_pains', []));
            $cta = trim((string) $this->input('overlay_cta_label', ''));

            if ($headline === '' && $solution === '' && $pains === [] && $cta === '') {
                $validator->errors()->add('overlay_headline', 'Add overlay content before turning it on.');
            }
        });
    }
}
