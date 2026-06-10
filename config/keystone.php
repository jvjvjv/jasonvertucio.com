<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Routes Automatically
    |--------------------------------------------------------------------------
    |
    | Determines whether Keystone should automatically load its routes.
    | Set to false to manually define routes in your application.
    |
    */

    'load_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The application's authenticatable model. Keystone uses this to resolve
    | the inverse role/permission relationships (e.g. KeystoneRole::users()).
    |
    */

    'user' => [
        'model' => \App\Models\User::class,
        'primary_key_type' => 'uuid',
        'table_name' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Features
    |--------------------------------------------------------------------------
    |
    | Toggle authentication features on/off. These control what functionality
    | is available in your application.
    |
    */

    'features' => [
        // Disable public registration - admin creates users
        'registration' => false,

        // Enable email verification
        'email_verification' => true,

        // Enable password reset functionality
        'password_reset' => true,

        // Enable two-factor authentication (TOTP via Fortify)
        'two_factor' => true,

        // Enable passkey authentication
        'passkeys' => true,

        // Enable passkey as a second factor
        'passkey_2fa' => true,

        // Enable API token authentication (Sanctum)
        'api_tokens' => true,

        // Enable profile information updates
        'update_profile' => true,

        // Enable password updates
        'update_passwords' => true,

        // Enable account deletion
        'account_deletion' => false,

        // Enable profile page
        'profile' => true,

        // Enable passwordless authentication
        'passwordless_login' => true,

        // Show permissions on profile page
        'show_permissions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Page Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the user profile page.
    |
    */

    'profile' => [
        // Profile route path
        'path' => '/profile',

        // Middleware for profile routes
        'middleware' => ['web', 'auth'],

        // Require password confirmation for sensitive operations
        'require_password_confirm' => true,

        // Password confirmation timeout (seconds)
        'password_timeout' => 10800, // 3 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Role & Permission Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Keystone's built-in role-based access control (RBAC).
    |
    */

    'rbac' => [
        // Enable multi-tenancy support for roles/permissions
        'multi_tenant' => false,

        // Cache expiration time for roles and permissions (in seconds)
        'cache_expiration' => 60 * 60 * 24, // 24 hours

        // Default role assigned to new users (null = no default role)
        'default_role' => 'user',

        // Super admin role that bypasses all permission checks
        'super_admin_role' => 'super-admin',

        // Default permissions for API access
        'api_permissions' => [
            'view-roles',
            'view-permissions',
            'assign-roles',
            'assign-permissions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passkey Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for WebAuthn/Passkey authentication.
    |
    */

    'passkey' => [
        // Relying Party name (your application name)
        'rp_name' => env('APP_NAME', 'Laravel'),

        // Relying Party ID (your domain)
        'rp_id' => env('APP_URL') ? parse_url(env('APP_URL'), PHP_URL_HOST) : 'localhost',

        // Timeout for passkey operations (in milliseconds)
        'timeout' => 60000,

        // User verification requirement: 'required', 'preferred', or 'discouraged'
        'user_verification' => 'preferred',

        // Attestation conveyance: 'none', 'indirect', or 'direct'
        'attestation' => 'none',

        // Allow users to have multiple passkeys
        'allow_multiple' => true,

        // Require passkey for specific user roles
        'required_for_roles' => [
            'admin',
            'super-admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for TOTP-based 2FA (Google Authenticator, Authy, etc.)
    |
    */

    'two_factor' => [
        // QR code size (in pixels)
        'qr_code_size' => 200,

        // Number of recovery codes to generate
        'recovery_codes_count' => 8,

        // Window of time to accept TOTP codes (in periods, 1 period = 30 seconds)
        'window' => 1,

        // Require 2FA for specific user roles
        'required_for_roles' => [
            // 'admin',
            // 'super-admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Paths
    |--------------------------------------------------------------------------
    |
    | Define where users should be redirected after various auth actions.
    |
    */

    'redirects' => [
        'login' => '/',
        'logout' => '/',
        'register' => '/',
        'password_reset' => '/login',
        'email_verification' => '/canvas',
        'two_factor_challenge' => '/canvas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for authentication attempts.
    |
    */

    'rate_limiting' => [
        // Maximum login attempts before lockout
        'max_login_attempts' => 5,

        // Lockout duration in minutes
        'lockout_duration' => 1,

        // Maximum 2FA attempts
        'max_2fa_attempts' => 3,

        // Maximum passkey attempts
        'max_passkey_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Settings (Optional)
    |--------------------------------------------------------------------------
    |
    | Enable these settings if you're using Spatie's Laravel Multitenancy
    | or a similar multi-tenant architecture.
    |
    */

    'multi_tenancy' => [
        // Enable multi-tenant support
        'enabled' => false,

        // Tenant column name in database tables
        'tenant_column' => 'tenant_id',

        // Automatically scope queries by tenant
        'auto_scope' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Settings
    |--------------------------------------------------------------------------
    |
    | Additional session configuration for enhanced security.
    |
    */

    'session' => [
        // Regenerate session ID after login
        'regenerate_on_login' => true,

        // Remember me duration (in minutes)
        'remember_duration' => 60 * 24 * 30, // 30 days

        // Require password confirmation for sensitive operations (in minutes)
        'password_timeout' => 10800, // 3 hours
    ],

];
