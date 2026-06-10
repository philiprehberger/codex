@php /** @var string $architecture */ /** @var int $count */ @endphp
@if ($count === 1)
Designed and shipped one {{ $architecture }} system.
@else
Designed and shipped {{ $count }} {{ $architecture }} systems.
@endif
