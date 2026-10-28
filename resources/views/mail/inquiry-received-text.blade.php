New portfolio inquiry #{{ $inquiry->id }}

Name: {{ $inquiry->name }}
Email: {{ $inquiry->email }}
Phone: {{ $inquiry->phone ?: 'Not provided' }}
Source: {{ $inquiry->source }}

Message:
{{ $inquiry->message }}

Reply to this email to contact the client.
