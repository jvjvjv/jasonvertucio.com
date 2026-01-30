<x-mail::message>
Hello {{ $code->name }},

Jason has updated his resume to v{{ $version }}. Your resume share code remains valid.

<x-mail::button color="primary" url="{{ $shareUrl }}"><strong>Click here to view the updated resume</strong></x-mail::button>

You can always click on the following link:

[{{ $shareUrl }}]({{ $shareUrl }})

to access the latest version. If you have any questions, feel free to reach out to Jason.
<x-slot:subcopy>
    <small>
        You hare receiving this email because you have opted to receive Jason's resume updates. If you no longer wish to receive these updates and lose access to the resume, please email <a href="mailto:me@jasonvertucio.com?subject=UNSUBSCRIBE: Resume">me@jasonvertucio.com</a>.
    </small>
</x-slot:subcopy>
</x-mail::message>
