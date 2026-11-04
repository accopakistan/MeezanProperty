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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    /*
    | Language model: Google Gemini API, the only active provider. Every call goes
    | through App\Services\Agents\AiProvider (server-side only; the key never
    | reaches the browser). A key saved (encrypted) in Admin -> AI Command Center
    | -> Gemini Settings takes priority over GEMINI_API_KEY.
    |
    | Checked against Google's documentation on 4 Nov 2026 (aistudio.google.com/docs):
    | - generateContent at {base_url}/models/{model}:generateContent, key in the
    |   x-goog-api-key header; streamGenerateContent?alt=sse for streaming.
    | - gemini-3.8-flash: the Flash model Google recommends for new projects, with a
    |   free tier. Test connection confirms it is in your account's model list.
    | - Models from 3.6 on ignore temperature; thinkingLevel needs Gemini 3 or later.
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_MODEL') ?: 'gemini-3.8-flash',
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_retries' => (int) env('GEMINI_MAX_RETRIES', 2),
        // Thinking is billed as output: agents only route, pick workflows and word short summaries.
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        'min_output_tokens' => (int) env('GEMINI_MIN_OUTPUT_TOKENS', 512),
        // free | paid. On the free tier Google does not charge; costs shown are the paid-tier
        // equivalent, and Google may use free-tier content to improve its products.
        'billing_tier' => env('GEMINI_BILLING_TIER', 'free'),
        /*
        | Paid-tier prices, USD per 1M tokens (output includes thinking), from Google's
        | pricing page on 4 Nov 2026. Prices entered in Usage & Cost take priority.
        | A period applies from its "from" date (inclusive).
        */
        'pricing' => [
            'gemini-3.8-flash' => [['from' => null, 'input' => 0.75, 'output' => 3.75], ['from' => '2027-01-01', 'input' => 1.50, 'output' => 7.50]],
            'gemini-3.6-flash' => [['from' => null, 'input' => 0.75, 'output' => 3.75], ['from' => '2027-01-01', 'input' => 1.50, 'output' => 7.50]],
            'gemini-3.5-flash-lite' => [['from' => null, 'input' => 0.30, 'output' => 2.50]],
            'gemini-3.1-flash-lite' => [['from' => null, 'input' => 0.25, 'output' => 1.50]],
        ],
        'pricing_checked_on' => '2026-11-04',
    ],

    /*
    | WhatsApp Business Platform (Cloud API). Outbound sending stays off until
    | WHATSAPP_SEND_ENABLED=true and every credential below is present.
    */
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
        'send_enabled' => (bool) env('WHATSAPP_SEND_ENABLED', false),
    ],

    /*
    | Agent connectors (Phase 15). Empty by default: agents report them as
    | "Not configured" and never claim data or publishing they do not have.
    */
    'google' => [
        'search_console_site' => env('GOOGLE_SEARCH_CONSOLE_SITE'),
        'search_console_credentials' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS'),
        'analytics_property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
        'analytics_credentials' => env('GOOGLE_ANALYTICS_CREDENTIALS'),
    ],
    'meta_graph' => [
        'page_id' => env('META_PAGE_ID'),
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),
        'access_token' => env('META_PAGE_ACCESS_TOKEN'),
    ],
    'sms' => [
        'provider' => env('SMS_PROVIDER'),
        'api_key' => env('SMS_API_KEY'),
    ],
    'web_search' => [
        'provider' => env('WEB_SEARCH_PROVIDER'),
        'api_key' => env('WEB_SEARCH_API_KEY'),
    ],

];
