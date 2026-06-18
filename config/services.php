<?php

$legacyVcbKeys = [];
$legacyVcbDecode = __DIR__ . '/../public/v1.0/core/class/decode.php';
if (is_readable($legacyVcbDecode)) {
  $legacyVcbSource = (string) file_get_contents($legacyVcbDecode);
  foreach (['defaultPublicKey', 'clientPublicKey', 'clientPrivateKey'] as $legacyVcbKey) {
    if (preg_match('/define\("' . $legacyVcbKey . '",\s*"(.*?)"\);/s', $legacyVcbSource, $legacyVcbMatch)) {
      $legacyVcbKeys[$legacyVcbKey] = stripcslashes($legacyVcbMatch[1]);
    }
  }
}

return [

  /*
  |--------------------------------------------------------------------------
  | Third Party Services
  |--------------------------------------------------------------------------
  |
  | This file is for storing the credentials for third party services such
  | as Mailgun, Postmark, AWS and more. This file provides the de facto
  | location for this type of information, allowing packages to have
  | a conventional file to locate the various service credentials.
  |
  */

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'slack' => [
    'notifications' => [
      'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
      'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
  ],

  'vcb_captcha' => [
    'api_url' => env('VCB_CAPTCHA_API_URL', 'https://captcha.apibank.com.vn/api/vcb'),
    'api_key' => env('VCB_CAPTCHA_API_KEY', ''),
    'theme' => env('VCB_CAPTCHA_THEME', 'MASS'),
  ],

  'vcb_rsa' => [
    'default_public_key' => env('VCB_DEFAULT_PUBLIC_KEY') ?: (
      !empty($legacyVcbKeys['defaultPublicKey'])
        ? $legacyVcbKeys['defaultPublicKey']
        : (is_readable(public_path('v1.0/core/serverPublic.pem'))
          ? base64_encode((string) file_get_contents(public_path('v1.0/core/serverPublic.pem')))
          : '')
    ),
    'client_public_key' => env('VCB_CLIENT_PUBLIC_KEY') ?: (
      !empty($legacyVcbKeys['clientPublicKey'])
        ? $legacyVcbKeys['clientPublicKey']
        : preg_replace(
          '/\-+BEGIN PUBLIC KEY\-+|\-+END PUBLIC KEY\-+|\s+/',
          '',
          (string) @file_get_contents(public_path('v1.0/core/clientPublic.pem'))
        )
    ),
    'client_private_key' => env('VCB_CLIENT_PRIVATE_KEY') ?: (
      !empty($legacyVcbKeys['clientPrivateKey'])
        ? $legacyVcbKeys['clientPrivateKey']
        : (is_readable(public_path('v1.0/core/clientPrivate.pem'))
          ? (string) file_get_contents(public_path('v1.0/core/clientPrivate.pem'))
          : '')
    ),
  ],

  'mbbank' => [
    'captcha_url' => env('MBBANK_CAPTCHA_API_URL', 'https://captcha.apibank.com.vn/api/mbb'),
    'captcha_key' => env('MBBANK_CAPTCHA_API_KEY', env('VCB_CAPTCHA_API_KEY', '')),
    'encrypt_url' => env('MBBANK_ENCRYPT_URL', 'http://127.0.0.1:3197/encrypt'),
    'authorization' => env('MBBANK_AUTHORIZATION', ''),
  ],

  'api_package' => [
    'monthly_price' => (int) env('API_PACKAGE_MONTHLY_PRICE', 20000),
  ],

  'bank_api' => [
    'account_limit' => (int) env('BANK_API_ACCOUNT_LIMIT', 3),
  ],

  'realtime_cache' => [
    'store' => env('APIBANK_REALTIME_CACHE_STORE', 'redis'),
    'stale_after_seconds' => (int) env('APIBANK_STALE_AFTER_SECONDS', 90),
  ],

  'quanly_webhook' => [
    'url' => env('QUANLY_WEBHOOK_URL', ''),
    'secret' => env('QUANLY_WEBHOOK_SECRET', ''),
    'events' => env('QUANLY_WEBHOOK_EVENTS', 'transaction.created,transaction.updated,balance.updated,account.session_expired'),
  ],

  'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/social/google/callback'),
  ],

  'quanly_account_link' => [
    'secret' => env('QUANLY_ACCOUNT_LINK_SECRET', ''),
    'issuer' => env('QUANLY_ACCOUNT_LINK_ISSUER', 'quanly.3w.com.vn'),
    'audience' => env('QUANLY_ACCOUNT_LINK_AUDIENCE', 'apibank.com.vn'),
  ],

];
