<x-layout :title="$event->title" :step="1" :wide="true">
    <a class="back-link" href="{{ route('events.index') }}">← Todos os cursos</a>
    <div class="course-detail-grid">
        <div class="course-content">
            <p class="eyebrow">OFICINA PRESENCIAL</p>
            <h1>{{ $event->title }}</h1>
            <p class="lead">{{ $event->description }}</p>
            <div class="price-highlight"><span>Investimento por participante</span><strong>R$ {{ number_format($event->price_cents / 100, 2, ',', '.') }}</strong><small>{{ $event->cashPaymentIsAvailable() ? 'Pix ou dinheiro presencial' : 'Pagamento por Pix' }}</small></div>
            @if($canRegister)<a class="text-link signup-jump" href="#signup-title">Ir para inscrição ↓</a>@endif
            @if($event->banner_url)<img class="detail-banner" src="{{ $event->banner_url }}" alt="{{ $event->banner_alt ?: 'Imagem da oficina '.$event->title }}">@endif
            <section class="detail-section" aria-labelledby="details-title"><h2 id="details-title">Tudo o que você precisa saber</h2>
                <dl class="details-list">
                    <div><dt>Quando</dt><dd>{{ $event->starts_at?->format('d/m/Y \à\s H:i') ?? 'Data e horário a combinar' }}@if($event->ends_at) até {{ $event->ends_at->format('H:i') }}@endif</dd></div>
                    <div><dt>Onde</dt><dd>{{ $event->place ?: 'Local a confirmar' }}</dd></div>
                    @if($event->materials)<div><dt>Materiais</dt><dd>{{ $event->materials }}</dd></div>@endif
                    <div><dt>Investimento</dt><dd>R$ {{ number_format($event->price_cents / 100, 2, ',', '.') }} · {{ $event->cashPaymentIsAvailable() ? 'Pix ou dinheiro presencial' : 'Pix' }}</dd></div>
                    @if($event->capacity)<div><dt>Vagas</dt><dd>{{ max(0, $event->capacity - $event->occupied_registrations_count) }} de {{ $event->capacity }} disponíveis</dd></div>@endif
                </dl>
            </section>
        </div>
        <aside class="signup-card" aria-labelledby="signup-title">
            <div class="signup-card-top"><p class="eyebrow">SUA INSCRIÇÃO</p><h2 id="signup-title">Vamos começar?</h2><p>Preencha com os dados de quem vai participar da oficina. Outra pessoa pode ajudar você a pagar.</p></div>
            @if($errors->any())
                <div class="alert alert-error" id="error-summary" role="alert" tabindex="-1"><strong>Confira os campos abaixo</strong><ul>@foreach($errors->getMessages() as $field => $messages)<li>@if(in_array($field, ['participant_name', 'participant_whatsapp', 'participant_cpf', 'participant_email'], true))<a href="#{{ $field }}">{{ $messages[0] }}</a>@else{{ $messages[0] }}@endif</li>@endforeach</ul></div>
            @endif
            @if($canRegister)
                <form action="{{ route('events.register.review', $event->id) }}" method="POST" data-progress-form>@csrf
                    <x-input id="participant_name" name="participant_name" label="Nome completo" :value="$savedData['participant_name'] ?? ''" :required="true" autocomplete="name" help="Nome de quem vai fazer a oficina." />
                    <x-input id="participant_whatsapp" name="participant_whatsapp" label="WhatsApp com DDD" type="tel" :value="$savedData['participant_whatsapp'] ?? ''" :required="true" autocomplete="tel" inputmode="tel" mask="phone" help="Exemplo: (84) 99999-9999" />
                    <x-input id="participant_cpf" name="participant_cpf" label="CPF" type="text" :value="$savedData['participant_cpf'] ?? ''" :required="true" autocomplete="off" inputmode="numeric" mask="cpf" help="CPF de quem vai participar." />
                    <x-input id="participant_email" name="participant_email" label="E-mail" type="email" :value="$savedData['participant_email'] ?? ''" :required="true" autocomplete="email" help="Para contato sobre sua inscrição." />
                    <fieldset class="choice-group"><legend>Como deseja pagar? <span class="required-label">(obrigatório)</span></legend><label class="check-option"><input type="radio" name="payment_method" value="pix" required @checked(($savedData['payment_method'] ?? 'pix') === 'pix')><span><strong>Pix</strong><small>O código será gerado após a revisão.</small></span></label>@if($event->cashPaymentIsAvailable())<label class="check-option"><input type="radio" name="payment_method" value="cash" required @checked(($savedData['payment_method'] ?? '') === 'cash')><span><strong>Dinheiro, presencialmente</strong><small>Reserve sua vaga e pague até {{ $event->cash_payment_deadline->format('d/m/Y \à\s H:i') }}.</small></span></label>@endif</fieldset>
                    <button class="btn" type="submit" data-submit-label="Indo para revisão…">Revisar meus dados <span aria-hidden="true">→</span></button>
                    <p class="form-footnote">Você poderá conferir tudo antes de confirmar. Seus dados serão usados para organizar sua inscrição e entrar em contato sobre a oficina.</p>
                </form>
            @else
                <div class="alert alert-warning"><strong>Inscrições indisponíveis</strong><p>{{ $registrationUnavailableMessage }}</p></div>
            @endif
        </aside>
    </div>
</x-layout>
