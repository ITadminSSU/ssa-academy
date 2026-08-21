@php
    $mailLogoVariant = $mailLogoVariant ?? 'dark';
    $mailLogoPath = $mailLogoVariant === 'light'
        ? (string) config('branding.logos.light', '/assets/branding/ssa-academy-logo-white.png')
        : (string) config('branding.logos.dark', '/assets/branding/ssa-academy-logo-black.png');
    $mailLogoUrl = url($mailLogoPath);
    $mailLogoAlt = (string) config('branding.name', config('app.name'));
    $mailLogoWidth = (int) ($mailLogoWidth ?? 180);
@endphp
<img
   src="{{ $mailLogoUrl }}"
   alt="{{ $mailLogoAlt }}"
   width="{{ $mailLogoWidth }}"
   style="display: block; max-width: {{ $mailLogoWidth }}px; width: {{ $mailLogoWidth }}px; height: auto; border: 0; outline: none; text-decoration: none;"
/>
