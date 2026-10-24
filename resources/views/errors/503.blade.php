@extends('errors.layout')

@section('status', '503')
@section('message', 'The site is currently in maintenance mode, but should spring back up any time now.')

@section('styles')
    <style>
        html { overflow-x: hidden; }

        .maintenance {
            width: 100vw;
            text-align: center;
        }

        .hazard {
            height: 40px;
            background-color: var(--color-dark);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Cpath fill='%23b35e06' d='M0 20 20 0h20L0 40zM20 40l20-20v20z'/%3E%3C/svg%3E");
            background-size: 40px 40px;
        }

        .maintenance h2 {
            font-size: clamp(2rem, 8vw, 4.5rem);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 1.5rem 1rem 1rem;
        }

        .maintenance p {
            max-width: 600px;
            margin: 1.5rem auto 0;
            padding: 0 1rem;
        }
    </style>
@endsection

@section('content')
    <div class="maintenance">
        <div class="hazard" aria-hidden="true"></div>
        <h2>Under Maintenance</h2>
        <div class="hazard" aria-hidden="true"></div>
        <p>@yield('message')</p>
    </div>
@endsection
