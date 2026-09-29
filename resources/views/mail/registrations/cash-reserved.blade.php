<x-mail::message>
# Sua vaga foi reservada

Olá, {{ $registration->participant_name }}!

Recebemos sua inscrição para **{{ $registration->event->title }}**. Como você escolheu pagar em dinheiro, sua vaga está reservada e o pagamento ainda está pendente.

Faça o pagamento presencialmente até **{{ $registration->cash_payment_due_at->format('d/m/Y \à\s H:i') }}**. Depois desse prazo, a reserva será cancelada automaticamente caso o pagamento não tenha sido confirmado.

**Valor:** R$ {{ number_format($registration->agreed_price_cents / 100, 2, ',', '.') }}  
**Protocolo:** {{ $registration->protocol }}

@if($registration->event->place)
**Local:** {{ $registration->event->place }}
@endif

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
