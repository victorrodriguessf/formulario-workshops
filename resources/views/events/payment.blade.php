<x-layout :title="$registration->payment_method === 'cash' ? 'Pagamento presencial' : 'Pagamento por Pix'" :step="3">
    @if($registration->status === 'review_required')
        <div class="flow-heading"><p class="eyebrow">SITUAÇÃO DA INSCRIÇÃO</p><h1>Estamos analisando seu pagamento</h1><p>Não faça outro Pix. Fale com a loja e informe o protocolo abaixo para receber ajuda.</p></div>
    @elseif($registration->status === 'cancelled')
        <div class="flow-heading"><p class="eyebrow">SITUAÇÃO DA INSCRIÇÃO</p><h1>Inscrição cancelada</h1><p>O pagamento foi devolvido ou a inscrição foi cancelada. Fale com a loja se precisar de ajuda.</p></div>
    @elseif($registration->status === 'expired')
        <div class="flow-heading"><p class="eyebrow">SITUAÇÃO DA INSCRIÇÃO</p><h1>O prazo da sua reserva terminou</h1><p>O pagamento em dinheiro não foi confirmado até o prazo informado e a vaga foi liberada. Faça uma nova inscrição caso ainda existam vagas.</p></div>
    @elseif($registration->payment_method === 'cash')
        <div class="flow-heading"><p class="eyebrow">ETAPA 3 DE 4</p><h1>Sua inscrição foi reservada com sucesso</h1><p>@if($registration->cash_payment_due_at) Você terá até <strong>{{ $registration->cash_payment_due_at->format('d/m/Y \à\s H:i') }}</strong> para pagar em dinheiro presencialmente. @else Faça o pagamento em dinheiro presencialmente. @endif A inscrição será confirmada pela loja depois que o pagamento for recebido.</p></div>
    @else
        <div class="flow-heading"><p class="eyebrow">ETAPA 3 DE 4</p><h1>Hora de pagar com Pix</h1><p>Sua inscrição foi recebida. Ela será confirmada quando o pagamento for aprovado.</p></div>
    @endif

    @if(session('warning'))<div class="alert alert-warning" role="status">{{ session('warning') }}</div>@endif
    @if(session('info'))<div class="alert alert-info" role="status">{{ session('info') }}</div>@endif

    @if($registration->status === 'awaiting_payment')
        <div class="payment-summary"><div><span>Oficina</span><strong>{{ $event->title }}</strong></div><div><span>Valor a pagar</span><strong class="payment-value">R$ {{ number_format($registration->agreed_price_cents / 100, 2, ',', '.') }}</strong></div></div>

        @if($registration->payment_method === 'cash')
            <div class="flow-card">
                <h2>Como concluir</h2>
                <p>Faça o pagamento em dinheiro presencialmente @if($registration->cash_payment_due_at) até <strong>{{ $registration->cash_payment_due_at->format('d/m/Y \à\s H:i') }}</strong>@endif. A equipe da loja marcará o pagamento como recebido e sua inscrição será confirmada.</p>
                <div class="notice-soft">Enquanto o pagamento não for confirmado pelo administrador, o cadastro aparecerá como <strong>aguardando pagamento</strong>.</div>
                <a class="btn btn-secondary" href="{{ route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]) }}">Atualizar situação</a>
            </div>
        @elseif($attempt?->local_status === 'pending' && $attempt->expires_at?->isPast())
            <div class="alert alert-warning"><h2>O prazo deste Pix terminou</h2><p>Estamos verificando se a cobrança foi encerrada pelo banco. Confira novamente daqui a pouco. Seus dados continuam salvos.</p></div>
        @elseif($attempt?->local_status === 'pending' && !empty($pixData['qr_code']))
            <div class="flow-card">
                <h2>Como pagar</h2><p>Escolha o jeito mais fácil para você. Este código pode ser pago até <strong>{{ $attempt->expires_at?->format('d/m/Y \à\s H:i') }}</strong>.</p>
                <div class="payment-methods">
                    <div class="method-card"><span class="method-number">01</span><h3>No mesmo celular</h3><p>Copie o código, abra o aplicativo do seu banco e escolha <strong>Pix Copia e Cola</strong>.</p></div>
                    <div class="method-card"><span class="method-number">02</span><h3>Em outro aparelho</h3><p>Abra o Pix no banco e aponte a câmera para o QR Code abaixo.</p></div>
                </div>
                @if(!empty($pixData['qr_code_base64']))<div class="qr-wrap"><img src="data:image/png;base64,{{ $pixData['qr_code_base64'] }}" alt="QR Code para pagamento Pix. O mesmo código está disponível em texto logo abaixo."></div>@endif
                <label for="pix-code" class="pix-label">Código Pix Copia e Cola</label>
                <textarea id="pix-code" class="pix-code" readonly rows="3">{{ $pixData['qr_code'] }}</textarea>
                <button type="button" class="btn btn-secondary" data-copy-target="pix-code" data-copy-feedback="copy-feedback">Copiar código Pix</button>
                <p id="copy-feedback" class="copy-feedback" role="status" aria-live="polite">Se não conseguir copiar, toque no código acima e selecione o texto.</p>
                <div class="notice-soft">Confira o valor de <strong>R$ {{ number_format($registration->agreed_price_cents / 100, 2, ',', '.') }}</strong>@if($receiverName) e o recebedor <strong>{{ $receiverName }}</strong>@endif antes de concluir no banco.</div>
            </div>
        @elseif($attempt?->local_status === 'unknown' || $attempt?->local_status === 'creating')
            <div class="alert alert-warning"><h2>Estamos conferindo seu Pix</h2><p>Seus dados estão salvos. Aguarde um pouco e verifique novamente. Evite fazer outro pagamento agora.</p></div>
        @elseif($canRetry)
            <div class="flow-card"><h2>Este código Pix não está mais válido</h2><p>Seus dados continuam salvos. Você pode gerar um novo código para a mesma inscrição.</p><form action="{{ route('events.register.retry', $event->id) }}" method="POST" data-progress-form>@csrf<input type="hidden" name="protocol" value="{{ $registration->protocol }}"><button class="btn" type="submit" data-submit-label="Gerando novo Pix…">Gerar novo Pix <span aria-hidden="true">→</span></button></form></div>
        @else
            <div class="alert alert-warning"><h2>Código Pix indisponível</h2><p>Este código não está mais disponível nesta sessão. Sua inscrição continua salva. Fale com a loja e informe o protocolo abaixo para receber ajuda.</p></div>
        @endif

        @if($registration->payment_method === 'pix')
            @php($pollsPayment = in_array($attempt?->local_status, ['pending', 'unknown', 'creating'], true))
            <div class="flow-card check-card" @if($pollsPayment) data-payment-poll="{{ route('events.register.status.check', ['id' => $event->id, 'protocol' => $registration->protocol]) }}" data-payment-state="{{ $attempt->local_status }}" @endif>
                <h2>Já fez o pagamento?</h2>
                @if($pollsPayment)
                    <p>Pode deixar esta página aberta: assim que o banco aprovar o Pix, ela mostra a confirmação sozinha.</p>
                    <p class="payment-poll-status" data-payment-poll-status role="status" aria-live="polite">Aguardando a confirmação do pagamento…</p>
                @else
                    <p>Volte a esta página depois de pagar. A confirmação pode levar alguns instantes.</p>
                @endif
                <a class="btn" href="{{ route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]) }}">Já paguei · verificar pagamento <span aria-hidden="true">→</span></a>
                @if($isDemo && $attempt?->local_status === 'pending')<form action="{{ route('events.register.demo-payment', $event->id) }}" method="POST" class="demo-form">@csrf<input type="hidden" name="protocol" value="{{ $registration->protocol }}"><button class="demo-button" type="submit">Simular pagamento aprovado (ambiente local)</button></form>@endif
            </div>
        @endif
    @endif

    <p class="protocol-note">Guarde seu protocolo: <strong>{{ $registration->protocol }}</strong></p>
</x-layout>
