<x-mail::message>
# Inscrição confirmada

Olá, {{ $registration->participant_name }}!

Seu pagamento foi confirmado e sua inscrição para **{{ $registration->event->title }}** está garantida.

**Valor pago:** R$ {{ number_format($registration->agreed_price_cents / 100, 2, ',', '.') }}  
**Protocolo:** {{ $registration->protocol }}

@if($registration->event->starts_at)
**Data:** {{ $registration->event->starts_at->format('d/m/Y \à\s H:i') }}
@endif

@if($registration->event->place)
**Local:** {{ $registration->event->place }}
@endif

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
