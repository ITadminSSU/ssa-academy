@php
   $label = (string) ($cta['label'] ?? '');
   $url = (string) ($cta['url'] ?? '');
   $lower = strtolower($label);
   $color = $cta['button_color'] ?? null;

   if (! is_string($color) || $color === '') {
      $color = str_contains($lower, 'facebook')
         ? '#1877F2'
         : (str_contains($lower, 'explore') ? '#8C2A23' : '#1F4D3A');
   }
@endphp
@if ($url !== '' && $label !== '')
   <a
      href="{{ $url }}"
      target="_blank"
      rel="noopener noreferrer"
      style="display: inline-block; padding: 12px 24px; background-color: {{ $color }}; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px; line-height: 1.2; border-radius: 999px; -webkit-border-radius: 999px;"
   >
      {{ $label }}
   </a>
@endif
