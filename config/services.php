<?php

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
    'default_public_key' => env('VCB_DEFAULT_PUBLIC_KEY', ''),
    'client_public_key' => env('VCB_CLIENT_PUBLIC_KEY', ''),
    'client_private_key' => env('VCB_CLIENT_PRIVATE_KEY', ''),
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

  'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/social/google/callback'),
  ],

];
