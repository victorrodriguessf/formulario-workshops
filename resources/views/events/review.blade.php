<x-layout title="Confira seus dados" :step="2">
    <div class="flow-heading"><p class="eyebrow">ETAPA 2 DE 4</p><h1>Confira seus dados</h1><p>Está tudo certo? Sua inscrição será criada só depois da confirmação.</p></div>
    <div class="flow-card">
        <div class="review-block"><h2>A oficina</h2><dl class="review-list"><div><dt>Curso</dt><dd>{{ $event->title }}</dd></div><div><dt>Valor</dt><dd><strong>R$ {{ number_format($event->price_cents / 100, 2, ',', '.') }}</strong></dd></div><div><dt>Pagamento</dt><dd>{{ $data['payment_method'] === 'cash' ? 'Dinheiro, presencialmente' : 'Pix' }}</dd></div></dl></div>
        <div class="review-block"><h2>Participante</h2><dl class="review-list"><div><dt>Nome</dt><dd>{{ $data['participant_name'] }}</dd></div><div><dt>WhatsApp</dt><dd>{{ $data['participant_whatsapp'] }}</dd></div><div><dt>CPF</dt><dd>{{ $data['participant_cpf'] }}</dd></div><div><dt>E-mail</dt><dd>{{ $data['participant_email'] }}</dd></div></dl></div>
        @if($data['payment_method'] === 'pix')<div class="notice-soft">@if($receiverName) Ao pagar, confira se o recebedor exibido no banco é <strong>{{ $receiverName }}</strong>. @else Confira o nome do recebedor no aplicativo do banco antes de pagar. @endif</div>@else<div class="notice-soft">Sua vaga ficará reservada. Pague em dinheiro na loja até <strong>{{ $event->cash_payment_deadline->format('d/m/Y \à\s H:i') }}</strong> para que a equipe confirme sua inscrição.</div>@endif
        <form action="{{ route('events.register.process', $event->id) }}" method="POST" data-progress-form>@csrf<button class="btn" type="submit" data-submit-label="Confirmando inscrição…">{{ $data['payment_method'] === 'cash' ? 'Confirmar e pagar presencialmente' : 'Gerar Pix de R$ '.number_format($event->price_cents / 100, 2, ',', '.') }} <span aria-hidden="true">→</span></button></form>
        <a class="btn btn-secondary" href="{{ route('events.show', $event->id) }}">Corrigir meus dados</a>
    </div>
</x-layout>
