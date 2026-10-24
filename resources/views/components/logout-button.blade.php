@props(['label' => 'Log out'])
<form method="POST" action="{{ route('logout') }}" class="contents">
    @csrf
    <button type="submit" {{ $attributes }}>{{ $label }}</button>
</form>
