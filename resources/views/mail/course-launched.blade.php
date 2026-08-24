<x-mail::message>
# Good news!

The course **{{ $courseTitle }}** you asked to be notified about is now open on {{ $siteName }}.

<x-mail::button :url="$courseUrl">
View course
</x-mail::button>

We hope you enjoy learning with us.

Thanks,<br>
{{ $siteName }}
</x-mail::message>
