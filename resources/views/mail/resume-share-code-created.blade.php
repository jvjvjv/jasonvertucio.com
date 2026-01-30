<x-mail::message>

Hello {{ $code->name }},

Jason has shared his current resume (v{{ $version }}) with you.

<x-mail::button color="primary" url="{{ $shareUrl }}"><strong>Click here to view the resume</strong></x-mail::button>

If the button does not work, you can always click on the following link: [{{ $shareUrl }}]({{ $shareUrl }})

Your resume share code is:

<x-mail::panel>
    {{ $code->id }}
</x-mail::panel>

This code {{ $code->expires_at ? 'expires on ' . $code->expires_at->format('F j, Y') : 'never expires' }}. If you have any questions, feel free to reach out to Jason.
<x-slot:subcopy>
    <small>
        You hare receiving this email because you have opted to receive Jason's resume updates. If you no longer wish to receive these updates and lose access to the resume, please email <a href="mailto:me@jasonvertucio.com?subject=UNSUBSCRIBE: Resume">me@jasonvertucio.com</a>.
    </small>
</x-slot:subcopy>
</x-mail::message>
