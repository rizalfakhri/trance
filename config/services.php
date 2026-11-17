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

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'token' => env('OPENAI_API_KEY'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 180),
        'models' => [
            'gpt-image-2.6-sunburst' => [
                'label' => 'GPT Image 2.6 Sunburst',
                'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '8.00', 'output_tokens' => '30.00'],
            ],
            'gpt-image-2.6-flare' => [
                'label' => 'GPT Image 2.6 Flare',
                'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '8.00', 'output_tokens' => '30.00'],
            ],
        ],
        'max_output_tokens' => [
            'low' => ['1024x1024' => 450, '1536x1024' => 650, '1024x1536' => 650],
            'medium' => ['1024x1024' => 1600, '1536x1024' => 2400, '1024x1536' => 2400],
            'high' => ['1024x1024' => 6300, '1536x1024' => 9400, '1024x1536' => 9400],
        ],
        'input_image_tokens_per_reference' => 1600,
    ],

    'pixelforge' => [
        'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),
        'token' => env('PIXELFORGE_API_KEY'),
        'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),
        'webhook_tolerance' => (int) env('PIXELFORGE_WEBHOOK_TOLERANCE', 300),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
