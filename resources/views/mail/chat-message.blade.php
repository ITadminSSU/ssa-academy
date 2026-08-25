<x-mail::message>
# New message

You have a new message on {{ $siteName }}.

**Course:** {{ $conversation->course?->title }}  
**From:** {{ $message->sender?->name ?? 'Someone' }}

@if($message->body)
**Message:**  
{{ $message->body }}
@else
An image was shared with you.
@endif

<x-mail::button :url="$messagesUrl">
Open Messages
</x-mail::button>

Thanks,<br>
{{ $siteName }}
</x-mail::message>
