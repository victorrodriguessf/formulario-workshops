<x-layout title="Cursos e oficinas" :wide="true">
    @php($whatsappUrl = 'https://wa.me/+5584987570828?text=Ol%C3%A1!%20Estou%20vindo%20pelo%20site%20e%20gostaria%20de%20comprar%20aqui%20pelo%20WhatsApp!')
    <section class="hero-banner">
        <h1 class="sr-only">Cursos e oficinas para você</h1>
        <div class="hero-banner-art">
            <picture>
                <source media="(max-width: 899px)" srcset="{{ asset('images/hero-banner-mobile.webp') }}" type="image/webp" width="1254" height="1254">
                <source media="(max-width: 899px)" srcset="{{ asset('images/hero-banner-mobile.jpg') }}" width="1254" height="1254">
                <source srcset="{{ asset('images/hero-banner.webp') }}" type="image/webp">
                <img src="{{ asset('images/hero-banner.jpg') }}" alt="Aonde as ideias criam formas! Escolha uma oficina, veja os detalhes e inscreva-se com calma. O pagamento pode ser por Pix ou em dinheiro. Quer comprar materiais? Fale com a gente e peça pelo WhatsApp." width="3200" height="992">
            </picture>
            <a class="hero-hotspot hero-hotspot-courses" href="#cursos" aria-label="Ver oficinas disponíveis"></a>
            <a class="hero-hotspot hero-hotspot-whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Comprar pelo WhatsApp (abre em nova aba)"></a>
        </div>
    </section>
    <section id="cursos" class="section-block">
        <div class="section-heading"><div><p class="eyebrow">ENCONTRE SEU PRÓXIMO PROJETO</p><h2>Oficinas disponíveis</h2></div><span class="section-count">{{ $events->count() }} {{ $events->count() === 1 ? 'curso' : 'cursos' }}</span></div>
        @if($events->isEmpty())
            <div class="empty-state"><span aria-hidden="true">✳</span><h3>Novas oficinas vêm por aí</h3><p>Em breve, os cursos aparecerão aqui. Volte para conferir.</p></div>
        @else
            <div class="course-grid {{ $events->count() === 1 ? 'course-grid-featured' : '' }}">
                @foreach($events as $event)
                    <article class="course-card">
                        <div class="course-card-art">@if($event->banner_url)<img src="{{ $event->banner_url }}" alt="">@else<span class="course-card-art-mark" aria-hidden="true">✳</span>@endif</div>
                        <div class="course-card-body"><p class="eyebrow">OFICINA PRESENCIAL</p><h3>{{ $event->title }}</h3><p class="course-excerpt">{{ Str::limit($event->description, 130) }}</p>
                            <div class="course-meta"><span>{{ $event->starts_at?->format('d/m/Y') ?? 'Data a combinar' }}@if($event->capacity) · {{ max(0, $event->capacity - $event->occupied_registrations_count) }} vagas @endif</span><strong>R$ {{ number_format($event->price_cents / 100, 2, ',', '.') }}</strong></div>
                            <a href="{{ route('events.show', $event->id) }}" class="btn course-card-action">{{ $event->hasAvailableSpots() ? 'Ver detalhes e se inscrever' : 'Ver detalhes · vagas esgotadas' }} <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
    <section class="how-section" aria-labelledby="how-title">
        <h2 id="how-title">Como funciona a inscrição</h2>
        <div class="home-quicksteps">
            <div><span>1</span><p><strong>Escolha uma oficina</strong><small>Veja preço, data e local.</small></p></div>
            <div><span>2</span><p><strong>Preencha seus dados</strong><small>Confira antes de continuar.</small></p></div>
            <div><span>3</span><p><strong>Escolha como pagar</strong><small>Use Pix ou pague em dinheiro na loja.</small></p></div>
        </div>
    </section>
</x-layout>
