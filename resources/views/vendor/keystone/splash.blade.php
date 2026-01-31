<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSPDX AuthKit - Complete Laravel Authentication</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .hero {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #111827;
        }

        .subtitle {
            font-size: 1.25rem;
            color: #6b7280;
            margin-bottom: 2rem;
        }

        .badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .badge {
            background: #f3f4f6;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .feature {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }

        .feature:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .feature h3 {
            color: #111827;
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }

        .feature p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        h2 {
            font-size: 1.875rem;
            margin-bottom: 1.5rem;
            color: #111827;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.5rem;
        }

        .requirements {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirement::before {
            content: "✓";
            color: #10b981;
            font-weight: bold;
            font-size: 1.25rem;
        }

        .code-block {
            background: #1f2937;
            color: #f9fafb;
            padding: 1.5rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 1rem 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .installation-steps {
            counter-reset: step;
        }

        .step {
            margin-bottom: 2rem;
            padding-left: 3rem;
            position: relative;
        }

        .step::before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .step h3 {
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .links {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        footer {
            text-align: center;
            padding: 2rem;
            color: white;
            font-size: 0.875rem;
        }

        .demo-users {
            background: #fef3c7;
            border: 2px solid #fbbf24;
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 1rem 0;
        }

        .demo-users h4 {
            color: #92400e;
            margin-bottom: 0.5rem;
        }

        .demo-users ul {
            list-style: none;
            padding-left: 0;
        }

        .demo-users li {
            color: #78350f;
            padding: 0.25rem 0;
        }

        .demo-users code {
            background: #fde68a;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Hero Section -->
        <div class="hero">
            <div class="logo">
                <div class="logo-icon">🔐</div>
                <div>
                    <h1>BSPDX AuthKit</h1>
                    <p class="subtitle">Complete Authentication Package for Laravel 12</p>
                </div>
            </div>

            <div class="badges">
                <span class="badge">PHP 8.2+</span>
                <span class="badge">Laravel 12.0+</span>
                <span class="badge">MIT License</span>
                <span class="badge">Production Ready</span>
            </div>

            <p style="font-size: 1.125rem; margin-bottom: 2rem;">
                A comprehensive, production-ready authentication package combining the power of Laravel Fortify,
                Sanctum,
                Spatie Laravel Permission, and Spatie Laravel Passkeys to provide a full-featured auth system.
            </p>

            <div class="features">
                <div class="feature">
                    <div class="feature-icon">🔐</div>
                    <h3>Standard Authentication</h3>
                    <p>Powered by Laravel Fortify with email verification and password resets</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">👥</div>
                    <h3>RBAC System</h3>
                    <p>Complete Role-Based Access Control using Spatie Laravel Permission</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">📱</div>
                    <h3>TOTP 2FA</h3>
                    <p>Two-Factor Authentication with Google Authenticator, Authy, etc.</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🔑</div>
                    <h3>Passkey Authentication</h3>
                    <p>Modern WebAuthn/FIDO2 login for passwordless authentication</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🛡️</div>
                    <h3>Passkey as 2FA</h3>
                    <p>Use passkeys as a second factor for enhanced security</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🌐</div>
                    <h3>API Support</h3>
                    <p>Full Sanctum integration for API authentication</p>
                </div>
            </div>

            <div class="links">
                <a href="https://github.com/TheBootstrapParadox/AuthKit" class="cta" target="_blank">View on GitHub</a>
                <a href="https://packagist.org/packages/bspdx/authkit" class="link" target="_blank">Packagist →</a>
                <a href="https://github.com/TheBootstrapParadox/AuthKit/wiki" class="link" target="_blank">Documentation
                    →</a>
                <a href="https://github.com/TheBootstrapParadox/AuthKit/issues" class="link" target="_blank">Issues
                    →</a>
            </div>
        </div>

        <!-- Requirements -->
        <div class="section">
            <h2>Requirements</h2>
            <div class="requirements">
                <div class="requirement">PHP 8.2 or higher</div>
                <div class="requirement">Laravel 12.0 or higher</div>
                <div class="requirement">MySQL 5.7+ / PostgreSQL 9.6+ / SQLite 3.8.8+</div>
                <div class="requirement">HTTPS (required for Passkeys)</div>
            </div>
        </div>

        <!-- Installation -->
        <div class="section">
            <h2>Installation</h2>
            <div class="installation-steps">
                <div class="step">
                    <h3>Install via Composer</h3>
                    <div class="code-block">composer require bspdx/authkit</div>
                </div>

                <div class="step">
                    <h3>Publish Configuration & Assets</h3>
                    <div class="code-block"># Publish configuration
                        php artisan vendor:publish --tag=authkit-config

                        # Publish migrations
                        php artisan vendor:publish --tag=authkit-migrations

                        # Publish example routes
                        php artisan vendor:publish --tag=authkit-routes

                        # Publish database seeders
                        php artisan vendor:publish --tag=authkit-seeders</div>
                </div>

                <div class="step">
                    <h3>Run Migrations</h3>
                    <div class="code-block">php artisan migrate</div>
                    <p style="margin-top: 0.5rem; color: #6b7280;">
                        Creates tables for two-factor authentication, roles, permissions, passkeys, and personal access
                        tokens.
                    </p>
                </div>

                <div class="step">
                    <h3>Seed Demo Data (Optional)</h3>
                    <div class="code-block">php artisan db:seed --class=AuthKitSeeder</div>

                    <div class="demo-users">
                        <h4>🎉 Demo Users Created</h4>
                        <ul>
                            <li><code>superadmin@example.com</code> - Super Admin</li>
                            <li><code>admin@example.com</code> - Admin</li>
                            <li><code>editor@example.com</code> - Editor</li>
                            <li><code>user@example.com</code> - Regular User</li>
                        </ul>
                        <p style="margin-top: 0.5rem;"><strong>All passwords:</strong> <code>password</code></p>
                    </div>
                </div>

                <div class="step">
                    <h3>Update User Model</h3>
                    <div class="code-block">use BSPDX\AuthKit\Traits\HasAuthKit;

                        class User extends Authenticatable
                        {
                        use HasAuthKit;

                        // ... rest of your model
                        }</div>
                </div>
            </div>
        </div>

        <!-- Configuration -->
        <div class="section">
            <h2>Configuration</h2>
            <p style="margin-bottom: 1rem;">
                The package configuration is located at <code
                    style="background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">config/authkit.php</code>
            </p>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Enable/Disable Features</h3>
            <div class="code-block">'features' => [
                'registration' => true,
                'email_verification' => true,
                'two_factor' => true,
                'passkeys' => true,
                'passkey_2fa' => true,
                'api_tokens' => true,
                ]</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">RBAC Settings</h3>
            <div class="code-block">'rbac' => [
                'default_role' => 'user',
                'super_admin_role' => 'super-admin',
                ]</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Passkey Settings</h3>
            <div class="code-block">'passkey' => [
                'rp_name' => env('APP_NAME', 'Laravel'),
                'rp_id' => env('PASSKEY_RP_ID', 'localhost'),
                'allow_multiple' => true,
                ]</div>
        </div>

        <!-- Middleware -->
        <div class="section">
            <h2>Middleware</h2>
            <p style="margin-bottom: 1rem;">AuthKit provides three powerful middleware aliases:</p>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Role Middleware</h3>
            <div class="code-block">// Single role
                Route::middleware(['auth', 'role:admin'])->group(function () {
                // Only admins
                });

                // Multiple roles (OR logic)
                Route::middleware(['auth', 'role:admin,editor'])->group(function () {
                // Admins OR editors
                });</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Permission Middleware</h3>
            <div class="code-block">Route::middleware(['auth', 'permission:edit-posts'])->group(function () {
                // Only users with 'edit-posts' permission
                });</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">2FA Enforcement Middleware</h3>
            <div class="code-block">Route::middleware(['auth', '2fa'])->group(function () {
                // Ensures users have 2FA enabled if required for their role
                });</div>
        </div>

        <!-- Blade Components -->
        <div class="section">
            <h2>Blade Components</h2>
            <p style="margin-bottom: 1rem;">Drop-in, framework-agnostic Blade components:</p>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Login Form</h3>
            <div class="code-block">&lt;x-authkit::login-form
                :show-passkey-option="true"
                :show-remember-me="true"
                :show-register-link="true"
                /&gt;</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Register Form</h3>
            <div class="code-block">&lt;x-authkit::register-form
                :show-login-link="true"
                /&gt;</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Two-Factor Challenge</h3>
            <div class="code-block">&lt;x-authkit::two-factor-challenge
                :show-recovery-code-option="true"
                /&gt;</div>

            <h3 style="font-size: 1.25rem; margin: 1.5rem 0 0.75rem; color: #374151;">Passkey Components</h3>
            <div class="code-block">&lt;x-authkit::passkey-register /&gt;
                &lt;x-authkit::passkey-login /&gt;</div>
        </div>

        <!-- Quick Start -->
        <div class="section">
            <h2>Quick Start</h2>
            <p style="margin-bottom: 1rem; font-size: 1.125rem;">Get up and running in 5 minutes:</p>

            <div class="code-block"># 1. Install package
                composer require bspdx/authkit
                php artisan vendor:publish --tag=authkit-config
                php artisan vendor:publish --tag=authkit-migrations
                php artisan migrate
                php artisan db:seed --class=AuthKitSeeder

                # 2. Start server
                ./vendor/bin/sail up

                # 3. Visit https://localhost/login
                # Use: admin@example.com / password</div>

            <p
                style="margin-top: 1.5rem; padding: 1rem; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 0.25rem;">
                <strong>💡 Pro Tip:</strong> Passkeys require HTTPS! Check out our
                <a href="https://github.com/TheBootstrapParadox/AuthKit/blob/main/docs/https-setup.md" class="link"
                    target="_blank">HTTPS Setup Guide</a>
                for local development.
            </p>
        </div>

        <!-- Support -->
        <div class="section">
            <h2>Support & Resources</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div>
                    <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #111827;">📚 Documentation</h3>
                    <a href="https://github.com/TheBootstrapParadox/AuthKit/wiki" class="link" target="_blank">Full
                        Documentation →</a>
                </div>
                <div>
                    <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #111827;">🐛 Issues</h3>
                    <a href="https://github.com/TheBootstrapParadox/AuthKit/issues" class="link" target="_blank">Report
                        a Bug →</a>
                </div>
                <div>
                    <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #111827;">💬 Discussions</h3>
                    <a href="https://github.com/TheBootstrapParadox/AuthKit/discussions" class="link"
                        target="_blank">Join the Community →</a>
                </div>
                <div>
                    <h3 style="font-size: 1.125rem; margin-bottom: 0.5rem; color: #111827;">🔒 Security</h3>
                    <a href="mailto:info@bspdx.com" class="link">info@bspdx.com</a>
                </div>
            </div>
        </div>

        <!-- Credits -->
        <div class="section">
            <h2>Built With</h2>
            <p style="margin-bottom: 1rem;">AuthKit stands on the shoulders of giants:</p>
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                <a href="https://github.com/laravel/fortify" class="badge" target="_blank"
                    style="text-decoration: none; cursor: pointer;">Laravel Fortify</a>
                <a href="https://github.com/laravel/sanctum" class="badge" target="_blank"
                    style="text-decoration: none; cursor: pointer;">Laravel Sanctum</a>
                <a href="https://github.com/spatie/laravel-permission" class="badge" target="_blank"
                    style="text-decoration: none; cursor: pointer;">Spatie Laravel Permission</a>
                <a href="https://github.com/spatie/laravel-passkeys" class="badge" target="_blank"
                    style="text-decoration: none; cursor: pointer;">Spatie Laravel Passkeys</a>
            </div>
        </div>
    </div>

    <footer>
        <p>BSPDX AuthKit - MIT License - Built with ❤️ for the Laravel Community</p>
        <p style="margin-top: 0.5rem; opacity: 0.8;">© {{ date('Y') }} BSPDX. All rights reserved.</p>
    </footer>
</body>

</html>