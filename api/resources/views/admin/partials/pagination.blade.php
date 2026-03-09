@if($paginator->hasPages())
    @foreach($elements as $element)
        @if($element['type'] === 'previous')
            @if($element['url'])
                <a href="{{ $element['url'] }}">←</a>
            @else
                <span class="text-muted">←</span>
            @endif
        @elseif($element['type'] === 'next')
            @if($element['url'])
                <a href="{{ $element['url'] }}">→</a>
            @else
                <span class="text-muted">→</span>
            @endif
        @elseif($element['type'] === 'dots')
            <span>…</span>
        @else
            @if($element['active'])
                <span class="active"><span>{{ $element['label'] }}</span></span>
            @else
                <a href="{{ $element['url'] }}">{{ $element['label'] }}</a>
            @endif
        @endif
    @endforeach
@endif