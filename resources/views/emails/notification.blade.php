<x-email-layout :title="$subject" :action-url="$actionUrl" :action-label="$actionText" :footnote="implode(' ', $outroLines)">
    @foreach($introLines as $line)
        <p style="margin:0 0 16px;">{{ $line }}</p>
    @endforeach
</x-email-layout>
