@php /** @var string $industry */ /** @var int $count */ @endphp
@if ($count === 1)
Shipped one project for an {{ $industry }} client.
@else
Shipped {{ $count }} projects across {{ $industry }} clients.
@endif
