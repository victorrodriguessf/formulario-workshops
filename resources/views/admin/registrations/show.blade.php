<x-layout :title="'Inscrição '.$registration->protocol" :admin="true" :wide="true">
    <div class="admin-heading"><div><a class="back-link" href="{{ route('admin.registrations.index') }}">← Voltar para inscrições</a><p class="eyebrow">DETALHES DA INSCRIÇÃO</p><h1>{{ $registration->participant_name }}</h1><p>Protocolo {{ $registration->protocol }}</p></div></div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif

    <div class="detail-grid">
        <section class="info-panel"><h2>Participante</h2><dl class="review-list"><div><dt>Nome</dt><dd>{{ $registration->participant_name }}</dd></div><div><dt>WhatsApp</dt><dd>{{ $registration->participant_whatsapp }}</dd></div><div><dt>E-mail</dt><dd>{{ $registration->participant_email }}</dd></div><div><dt>Inscrição feita em</dt><dd>{{ $registration->created_at->format('d/m/Y \à\s H:i') }}</dd></div></dl></section>
        <section class="info-panel">
            <h2>Oficina e pagamento</h2>
            <dl class="review-list"><div><dt>Oficina</dt><dd>{{ $registration->event->title }}</dd></div><div><dt>Valor</dt><dd>R$ {{ number_format($registration->agreed_price_cents / 100, 2, ',', '.') }}</dd></div><div><dt>Forma</dt><dd>{{ $registration->payment_method === 'cash' ? 'Dinheiro, presencialmente' : 'Pix' }}</dd></div>@if($registration->cash_payment_due_at)<div><dt>Prazo do dinheiro</dt><dd>{{ $registration->cash_payment_due_at->format('d/m/Y \à\s H:i') }}</dd></div>@endif<div><dt>Pagamento recebido</dt><dd><strong>{{ $registration->payment_received ? 'Sim' : 'Não' }}</strong></dd></div><div><dt>Situação</dt><dd><span class="status-pill status-{{ $registration->status }}">{{ ['confirmed' => 'Confirmada', 'awaiting_payment' => 'Aguardando pagamento', 'cancelled' => 'Cancelada', 'expired' => 'Expirada', 'review_required' => 'Precisa de análise'][$registration->status] ?? $registration->status }}</span></dd></div></dl>

            @if($registration->payment_method === 'cash')
                @if($registration->status === 'expired')
                    <div class="alert alert-warning">O prazo desta reserva terminou. O pagamento não pode mais ser confirmado neste cadastro.</div>
                @else
                <form action="{{ route('admin.registrations.payment.update', $registration) }}" method="POST" class="payment-admin-form">@csrf @method('PATCH')
                    <fieldset class="choice-group"><legend>O pagamento em dinheiro foi recebido?</legend><label class="check-option"><input type="radio" name="payment_received" value="1" @checked(old('payment_received', $registration->payment_received ? '1' : '0') === '1')><span><strong>Sim</strong><small>Confirma a inscrição do participante.</small></span></label><label class="check-option"><input type="radio" name="payment_received" value="0" @checked(old('payment_received', $registration->payment_received ? '1' : '0') === '0')><span><strong>Não</strong><small>Mantém a inscrição aguardando pagamento.</small></span></label></fieldset>
                    <button class="btn" type="submit">Salvar situação do pagamento</button>
                </form>
                @endif
            @else
                <p class="panel-note">O pagamento por Pix é confirmado automaticamente pelo sistema.</p>
            @endif
        </section>
    </div>

    <section class="info-panel payment-history"><h2>Histórico de pagamentos</h2>@forelse($registration->paymentAttempts->sortByDesc('created_at') as $attempt)<div class="history-row"><div><strong>{{ ['paid' => 'Pago', 'pending' => 'Aguardando pagamento', 'unknown' => 'Em análise', 'creating' => 'Criando Pix', 'failed' => 'Falhou', 'expired' => 'Expirado'][$attempt->local_status] ?? $attempt->local_status }}</strong><span>{{ $attempt->created_at->format('d/m/Y H:i') }}</span></div><span>R$ {{ number_format($attempt->amount_cents / 100, 2, ',', '.') }}</span></div>@empty<p>{{ $registration->payment_method === 'cash' ? 'Pagamento em dinheiro ainda sem confirmação no histórico.' : 'Sem tentativas de pagamento registradas.' }}</p>@endforelse</section>

    <section class="info-panel payment-history"><h2>Remover inscrição</h2><p>Use quando o participante desistir. Faça o reembolso antes de remover, pois os dados de contato e o histórico de pagamento serão apagados.</p><form action="{{ route('admin.registrations.destroy', $registration) }}" method="POST" data-progress-form data-confirm="Remover a inscrição de {{ $registration->participant_name }}? Os dados e o histórico de pagamento serão excluídos permanentemente e a vaga será liberada. Faça o reembolso antes, se necessário. Esta ação não pode ser desfeita.">@csrf @method('DELETE')<button class="btn btn-danger" type="submit" data-submit-label="Removendo…">Remover inscrição</button></form></section>
</x-layout>
