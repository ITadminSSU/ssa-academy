<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class ResendHttpClient
{
    public static function isAvailable(): bool
    {
        return ! empty(config('services.resend.key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function send(array $payload): string
    {
        $apiKey = (string) config('services.resend.key');

        if ($apiKey === '') {
            throw new \RuntimeException('Resend API key is not configured.');
        }

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.resend.com/emails', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Resend API error ('.$response->status().'): '.$response->body());
        }

        return (string) ($response->json('id') ?? '');
    }
}
