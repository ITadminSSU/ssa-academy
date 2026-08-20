<?php

namespace App\Http\Requests;

use App\Models\DashboardWelcomeCampaign;
use App\Support\DashboardWelcomeOverlay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDashboardWelcomeCampaignRequest extends FormRequest
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
            'title' => 'required|string|max:160',
            'enabled' => 'required|boolean',
            'priority' => 'required|integer|min:0|max:9999',
            'weight' => 'required|integer|min:1|max:10000',
            'show_frequency' => ['required', Rule::in([
                DashboardWelcomeCampaign::FREQUENCY_UNTIL_DISMISSED,
                DashboardWelcomeCampaign::FREQUENCY_EVERY_HOME_VISIT,
            ])],
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
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
            'new_video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime|max:204800',
            'clear_video' => 'nullable|boolean',
            'autoplay_muted' => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN),
            'clear_poster' => filter_var($this->input('clear_poster'), FILTER_VALIDATE_BOOLEAN),
            'clear_video' => filter_var($this->input('clear_video'), FILTER_VALIDATE_BOOLEAN),
            'autoplay_muted' => filter_var($this->input('autoplay_muted', true), FILTER_VALIDATE_BOOLEAN),
            'starts_at' => $this->blankToNull('starts_at'),
            'ends_at' => $this->blankToNull('ends_at'),
        ]);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) === '' ? null : $value;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ctaUrl = trim((string) $this->input('cta_url', ''));
            if ($ctaUrl !== '' && ! DashboardWelcomeOverlay::isAllowedUrl($ctaUrl)) {
                $validator->errors()->add('cta_url', 'Use a site path or an http(s), mailto, or tel link.');
            }

            $videoType = (string) $this->input('video_type', DashboardWelcomeOverlay::VIDEO_NONE);
            $videoUrl = trim((string) $this->input('video_url', ''));
            $hasNewVideo = $this->hasFile('new_video');

            if ($videoType === DashboardWelcomeOverlay::VIDEO_EMBED) {
                if ($videoUrl === '') {
                    $validator->errors()->add('video_url', 'Add an embed URL or set video type to None.');
                } elseif (! DashboardWelcomeOverlay::isAllowedVideoUrl($videoUrl)) {
                    $validator->errors()->add('video_url', 'Video URL must start with http:// or https://.');
                }
            }

            if ($videoType === DashboardWelcomeOverlay::VIDEO_FILE) {
                $campaign = $this->route('campaign');
                $hasExistingFile = $campaign instanceof DashboardWelcomeCampaign
                    && $campaign->video_type === DashboardWelcomeOverlay::VIDEO_FILE
                    && filled($campaign->video_url)
                    && ! $this->boolean('clear_video');

                if ($videoUrl === '' && ! $hasNewVideo && ! $hasExistingFile) {
                    $validator->errors()->add('new_video', 'Upload a video file or paste a direct video URL.');
                } elseif ($videoUrl !== '' && ! $hasNewVideo && ! DashboardWelcomeOverlay::isAllowedVideoUrl($videoUrl)) {
                    $validator->errors()->add('video_url', 'Video URL must start with http:// or https://.');
                }
            }

            if (! $this->boolean('enabled')) {
                return;
            }

            $hasContent = trim((string) $this->input('headline', '')) !== ''
                || trim((string) $this->input('body', '')) !== ''
                || trim((string) $this->input('cta_label', '')) !== ''
                || trim((string) $this->input('poster_url', '')) !== ''
                || $this->hasFile('new_poster')
                || $hasNewVideo
                || ($videoType !== DashboardWelcomeOverlay::VIDEO_NONE && $videoUrl !== '');

            if (! $hasContent) {
                $validator->errors()->add('headline', 'Add headline, body, poster, video, or CTA before enabling.');
            }
        });
    }
}
