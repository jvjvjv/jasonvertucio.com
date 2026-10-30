@extends('layout')

@section('title', 'My Profile')

@section('main')
<div class="keystone-profile">

    <style>
        .keystone-profile {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .keystone-profile-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(authkit-text, #1f2937);
            margin-bottom: 2rem;
        }

        .keystone-profile-section {
            background: var(authkit-bg, #ffffff);
            border-radius: var(authkit-radius, 0.5rem);
            border: 1px solid #e5e7eb;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .keystone-profile-section h2 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(authkit-text, #1f2937);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(authkit-border, #e5e7eb);
        }

        .keystone-alert {
            padding: 1rem;
            border-radius: var(authkit-radius, 0.5rem);
            margin-bottom: 1.5rem;
        }

        .keystone-alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .keystone-alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <h1 class="keystone-profile-title">My Profile</h1>

    @if (session('status') === 'tool-visibility-updated')
        <div class="keystone-alert keystone-alert-success">
            Your chat tool settings have been saved.
        </div>
    @endif

    @if ($errors->any())
        <div class="keystone-alert keystone-alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Account Information --}}
    <section class="keystone-profile-section">
        <h2>Account Information</h2>
        @include('profile.components.account-info', ['user' => $user])
    </section>

    {{-- Roles & Permissions --}}
    @if(isset($roles))
    <section class="keystone-profile-section">
        <h2>Roles & Permissions</h2>
        @include('profile.components.roles-permissions', ['roles' => $roles, 'permissions' => $permissions])
    </section>
    @endif

    {{-- Sign-in (bspdx.com) --}}
    <section class="keystone-profile-section">
        <h2>Sign-in</h2>
        @include('profile.components.bspdx-account', ['identity' => $bspdxIdentity, 'accountUrl' => $bspdxAccountUrl])
    </section>

    {{-- Tool Payload Visibility --}}
    @can('manage-ai-tools')
        <section class="keystone-profile-section">
            <h2>Chat Tool Details</h2>
            @include('profile.components.tool-visibility-preference', ['user' => $user])
        </section>
    @endcan
</div>
@endsection
