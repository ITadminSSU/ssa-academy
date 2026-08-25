<x-mail::message>
# New Fraud Training Tipline tip

A community tip was submitted on {{ $siteName }}.

**Reporter:** {{ $report->reporter_name ?: 'Not provided' }}  
**Email:** {{ $report->reporter_email ?: 'Not provided' }}  
**Link:** {{ $report->link ?: 'Not provided' }}  

**Details:**  
{{ $report->details ?: 'None provided' }}

<x-mail::button :url="$inboxUrl">
Open Tipline inbox
</x-mail::button>

Thanks,<br>
{{ $siteName }}
</x-mail::message>
