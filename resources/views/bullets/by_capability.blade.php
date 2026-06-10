@php /** @var string $capability */ /** @var int $count */ @endphp
{{-- Diagnosis-first voice; concrete numbers; no hedging filler. --}}
@if ($count === 1)
Shipped one project with {{ $capability }} as a primary capability.
@else
Shipped {{ $count }} projects with {{ $capability }} as a primary capability across the portfolio.
@endif
