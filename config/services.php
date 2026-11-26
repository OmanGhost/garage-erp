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
     | IP intelligence for website trial requests (country, ISP, VPN/proxy/hosting flags).
     | Default: ip-api.com free endpoint (no key). {ip} is replaced with the visitor address.
     | TRIAL_NETWORK_CHECKS=false disables the IP lookup and the email MX check.
     */
    'ip_lookup' => [
        'enabled' => env('TRIAL_NETWORK_CHECKS', true),
        'url' => env('IP_LOOKUP_URL', 'http://ip-api.com/json/{ip}?fields=status,message,country,countryCode,regionName,city,timezone,isp,org,as,mobile,proxy,hosting'),
        'timeout' => 4,
    ],

];
