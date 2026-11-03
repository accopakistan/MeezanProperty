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
    | Language-model providers. Every call goes through App\Services\Agents\AiProvider.
    | Keys can also be saved (encrypted) in Admin -> AI Command Center -> AI Providers,
    | which takes priority over these values. Server-side only.
    | OpenAI: any OpenAI-compatible chat-completions endpoint works.
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // OPENAI_DEFAULT_MODEL is the agent-era name; OPENAI_MODEL stays supported.
        'model' => env('OPENAI_DEFAULT_MODEL') ?: env('OPENAI_MODEL'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
        'min_output_tokens' => (int) env('OPENAI_MIN_OUTPUT_TOKENS', 256),
        // Thinking depth on api.openai.com. Agents only classify and word short summaries.
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        // No default on purpose: choose one from the list your own account returns.
        'model' => env('ANTHROPIC_DEFAULT_MODEL'),
        // API version sent in the anthropic-version header.
        'version' => env('ANTHROPIC_VERSION', '2026-10-21'),
        // Thinking depth (low, medium, high). Agents only classify and word short summaries.
        'effort' => env('ANTHROPIC_EFFORT', 'low'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 60),
        'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 2),
        'min_output_tokens' => (int) env('ANTHROPIC_MIN_OUTPUT_TOKENS', 1024),
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
