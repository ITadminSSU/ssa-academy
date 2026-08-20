<?php

namespace App\Http\Requests;

use App\Support\DashboardWelcomeOverlay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDashboardWelcomeOverlayRequest extends FormRequest
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
            'enabled' => 'required|boolean',
            'headline' => 'nullable|string|max:160',
            'body' => 'nullable|string|max:2000',
            'cta_label' => 'nullable|string|max:80',
            'cta_url' => 'nullable|string|max:500',
            'poster_url' => 'nullable|string|max:1000',
            'new_poster' => 'nullable|image|max:5120',
            'clear_poster' => 'nullable|boolean',
            'video_type' => ['required', Rule::in([
                DashboardWelcomeOverlay::VIDEO_NONE,
                DashboardWelcomeOverlay::VIDEO_FILE,
                DashboardWelcomeOverlay::VIDEO_EMBED,
            ])],
            'video_url' => 'nullable|string|max:2000',
            'autoplay_muted' => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN),
            'clear_poster' => filter_var($this->input('clear_poster'), FILTER_VALIDATE_BOOLEAN),
            'autoplay_muted' => filter_var($this->input('autoplay_muted', true), FILTER_VALIDATE_BOOLEAN),
            'video_type' => $this->input('video_type', DashboardWelcomeOverlay::VIDEO_NONE),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ctaUrl = trim((string) $this->input('cta_url', ''));

            if ($ctaUrl !== '' && ! DashboardWelcomeOverlay::isAllowedUrl($ctaUrl)) {
                $validator->errors()->add('cta_url', 'Use a site path like /dashboard/browse/all, or an http(s), mailto, or tel link.');
            }

            $videoType = (string) $this->input('video_type', DashboardWelcomeOverlay::VIDEO_NONE);
            $videoUrl = trim((string) $this->input('video_url', ''));

            if ($videoType !== DashboardWelcomeOverlay::VIDEO_NONE) {
                if ($videoUrl === '') {
                    $validator->errors()->add('video_url', 'Add a video URL or set video type to None.');
                } elseif (! DashboardWelcomeOverlay::isAllowedVideoUrl($videoUrl)) {
                    $validator->errors()->add('video_url', 'Video URL must start with http:// or https://.');
                }
            }

            if (! $this->boolean('enabled')) {
                return;
            }

            $headline = trim((string) $this->input('headline', ''));
            $body = trim((string) $this->input('body', ''));
            $cta = trim((string) $this->input('cta_label', ''));
            $poster = trim((string) $this->input('poster_url', ''));
            $hasNewPoster = $this->hasFile('new_poster');
            $hasVideo = $videoType !== DashboardWelcomeOverlay::VIDEO_NONE && $videoUrl !== '';

            if ($headline === '' && $body === '' && $cta === '' && $poster === '' && ! $hasNewPoster && ! $hasVideo) {
                $validator->errors()->add('headline', 'Add a headline, body, poster, video, or CTA before turning this on.');
            }
        });
    }
}
