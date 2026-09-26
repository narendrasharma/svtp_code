<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
    ],

    'paytm' => [
        'merchant_id' => env('PAYTM_MERCHANT_ID'),
        'merchant_key' => env('PAYTM_MERCHANT_KEY'),
        'website' => env('PAYTM_WEBSITE', 'WEBSTAGING'),
        'env' => env('PAYTM_ENV', 'STAGING'),
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'ai' => [
        'enabled' => env('AI_ENABLED'),
        'provider' => env('AI_PROVIDER', 'openai'),
        'knowledge' => ['enabled' => env('AI_KNOWLEDGE_ENABLED', false)],
        'actions' => ['enabled' => env('AI_AGENT_ACTIONS_ENABLED', false)],
        'embedding' => [
            'provider' => env('AI_EMBEDDING_PROVIDER', 'openai'),
            'model' => env('AI_EMBEDDING_MODEL', env('AI_EMBEDDING_PROVIDER', 'openai') === 'gemini' ? 'gemini-embedding-2' : 'text-embedding-3-small'),
            'models' => [
                'openai' => ['text-embedding-3-small', 'text-embedding-3-large'],
                'gemini' => ['gemini-embedding-2', 'gemini-embedding-001'],
            ],
        ],
        'providers' => [
            'azure' => [
                'model' => env('AZURE_FOUNDRY_DEPLOYMENT', ''),
                'key' => env('AZURE_FOUNDRY_API_KEY'),
                'endpoint' => env('AZURE_FOUNDRY_ENDPOINT', ''),
                'tool_calling' => env('AZURE_FOUNDRY_TOOL_CALLING', false),
            ],
            'openai' => [
                'model' => env('AI_MODEL', 'gpt-4o-mini'),
                'key' => env('OPENAI_API_KEY'),
            ],
            'gemini' => [
                'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
                'key' => env('GEMINI_API_KEY'),
            ],
            'claude' => [
                'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
                'key' => env('ANTHROPIC_API_KEY'),
            ],
        ],
    ],

];
