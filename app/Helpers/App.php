<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

function isDBConnected(): bool
{
   try {
      DB::connection()->getPdo();
      return true;
   } catch (\Exception $e) {
      return false;
   }
}

function setSmtpConfig(array $config)
{
   config([
      'mail.default' => $config['mail_mailer'] ?? 'smtp',
      'mail.mailers.smtp.host' => $config['mail_host'] ?? '',
      'mail.mailers.smtp.port' => $config['mail_port'] ?? '',
      'mail.mailers.smtp.encryption' => $config['mail_encryption'] ?? '',
      'mail.mailers.smtp.username' => $config['mail_username'] ?? null,
      'mail.mailers.smtp.password' => $config['mail_password'] ?? null,
      'mail.from.address' => $config['mail_from_address'],
      'mail.from.name' => $config['mail_from_name'] ?? 'System',
   ]);
}

function testSmtpConnection(array $config)
{
   // Basic validation
   if (empty($config['mail_from_address']) || !filter_var($config['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
      throw new \Exception('A valid from email address is required');
   }

   // Set mail config temporarily
   $previousConfig = config('mail');

   // Configure mail with test settings
   setSmtpConfig($config);

   // Send a test email to the admin email
   // COMMENTED OUT FOR DEVELOPMENT - SMTP sending is disabled
   // $subject = 'SMTP Test Email';
   // $body = 'This is a test email to verify your SMTP settings. If you received this email, your SMTP configuration is working correctly.';
   // $recipient = $config['mail_from_address'];

   // Mail::raw($body, function ($message) use ($recipient, $subject) {
   //    $message->to($recipient)->subject($subject);
   // });

   // Reset config
   config(['mail' => $previousConfig]);

   return true;
}

function setPaypalConfig(array $config, string $mode = 'sandbox')
{
   config(['paypal.mode' => $mode]); // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
   config(['paypal.sandbox.client_id' => $config['sandbox_client_id']]);
   config(['paypal.sandbox.client_secret' => $config['sandbox_secret_key']]);
   config(['paypal.live.client_id' => $config['production_client_id']]);
   config(['paypal.live.client_secret' => $config['production_secret_key']]);

   $config = [
      'mode'    => $mode, // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
      'sandbox' => [
         'client_id'     => $config['sandbox_client_id'],
         'client_secret' => $config['sandbox_secret_key'],
         'app_id'        => 'APP-80W284485P519543T',
      ],
      'live' => [
         'client_id'     => $config['production_client_id'],
         'client_secret' => $config['production_secret_key'],
         'app_id'        => '',
      ],
      'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
      'currency'       => 'USD',
      'notify_url'     => '', // Change this accordingly for your application.
      'locale'         => 'en_US', // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
      'validate_ssl'   => true, // Validate SSL when creating api client.
   ];

   return $config;
}

function apiResponse(array $data = [], array $flash = [], int $status = 200)
{
   return response()->json([
      'data' => $data,
      'flash' => $flash,
   ], $status);
}

/**
 * Rewrite localhost / 127.0.0.1 media URLs to the current APP_URL (e.g. ngrok)
 * and normalise the public storage path segment (see normalize_public_storage_url).
 */
function public_asset_url(?string $url): ?string
{
   if ($url === null || trim($url) === '') {
      return $url;
   }

   $url = trim($url);
   $appUrl = rtrim((string) config('app.url'), '/');

   if ($appUrl === '') {
      return tidy_public_url(normalize_public_storage_url($url));
   }

   if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
      return tidy_public_url(normalize_public_storage_url($appUrl . $url));
   }

   // A protocol-relative "//storage/..." path makes the browser treat the first
   // segment as a hostname (404). Treat it as a root-relative app path instead.
   if (str_starts_with($url, '//')) {
      return tidy_public_url(normalize_public_storage_url($appUrl . '/' . ltrim($url, '/')));
   }

   $parsed = parse_url($url);

   if (!isset($parsed['host'])) {
      return tidy_public_url(normalize_public_storage_url($url));
   }

   $localHosts = ['localhost', '127.0.0.1', '::1'];
   $appHost = parse_url($appUrl, PHP_URL_HOST);

   if (!in_array($parsed['host'], $localHosts, true) && $parsed['host'] !== $appHost) {
      return tidy_public_url($url);
   }

   $path = $parsed['path'] ?? '';
   $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

   return tidy_public_url(normalize_public_storage_url($appUrl . $path . $query));
}

/**
 * Collapse redundant slashes in a URL while preserving the scheme separator
 * (e.g. "https://"). Fixes malformed paths such as "//storage/app/public/x.jpg"
 * or "https://host//storage/x.jpg" that would otherwise 404.
 */
function tidy_public_url(string $url): string
{
   if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*://)(.*)$#', $url, $matches)) {
      return $matches[1] . (string) preg_replace('#/{2,}#', '/', $matches[2]);
   }

   return (string) preg_replace('#/{2,}#', '/', $url);
}

/**
 * Insert the configured public storage path segment (e.g. "app/public") into a
 * "/storage/..." URL when the host serves public files from that deeper path.
 *
 * Controlled by config('filesystems.public_storage_path'). When empty (default,
 * including local dev) the URL is returned unchanged. The operation is idempotent:
 * URLs that already contain the segment are left as-is.
 */
function normalize_public_storage_url(string $url): string
{
   $segment = trim((string) config('filesystems.public_storage_path', ''), '/');

   if ($segment === '') {
      return $url;
   }

   $needle = '/storage/';
   $pos = strpos($url, $needle);

   if ($pos === false) {
      return $url;
   }

   $replacement = '/storage/' . $segment . '/';

   // Already normalised.
   if (str_starts_with(substr($url, $pos), $replacement)) {
      return $url;
   }

   return substr($url, 0, $pos) . $replacement . substr($url, $pos + strlen($needle));
}
