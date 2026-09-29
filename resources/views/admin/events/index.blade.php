<x-layout title="Cursos" :admin="true" :wide="true">
    <div class="admin-heading admin-heading-actions">
        <div>
            <p class="eyebrow">PAINEL ADMINISTRATIVO</p>
            <h1>Suas oficinas</h1>
            <p>Crie cursos e acompanhe o que está publicado.</p>
        </div>
        <a class="btn" href="{{ route('admin.events.create') }}">+ Nova oficina</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="stat-strip">
        <div><strong>{{ $events->total() }}</strong><span>Oficinas no total</span></div>
        <div><strong>{{ $events->getCollection()->where('status', 'published')->count() }}</strong><span>Publicadas nesta página</span></div>
    </div>

    <section class="admin-list" aria-label="Lista de oficinas">
        <div class="list-header"><h2>Todos os cursos</h2><span>{{ $events->total() }} {{ $events->total() === 1 ? 'resultado' : 'resultados' }}</span></div>
        @forelse($events as $event)
            <article class="admin-row">
                <div class="admin-row-main">
                    <span class="status-pill status-{{ $event->status }}">{{ ['draft' => 'Rascunho', 'published' => 'Publicado', 'cancelled' => 'Cancelado'][$event->status] ?? $event->status }}</span>
                    <h3>{{ $event->title }}</h3>
                    <p>{{ $event->starts_at?->format('d/m/Y · H:i') ?? 'Data a combinar' }} <span class="dot">·</span> R$ {{ number_format($event->price_cents / 100, 2, ',', '.') }}
                        @if($event->is_paused)
                            <span class="dot">·</span> Inscrições pausadas
                        @endif
                        @if($event->capacity)
                            <span class="dot">·</span> {{ $event->occupied_registrations_count }}/{{ $event->capacity }} vagas ocupadas
                        @endif
                    </p>
                </div>
                <div class="row-actions">
                    @if($event->status === 'published')
                        <a class="text-link" href="{{ route('events.show', $event->id) }}" target="_blank" rel="noopener">Ver página ↗</a>
                    @endif
                    <a class="btn btn-secondary" href="{{ route('admin.events.edit', $event) }}">Editar</a>
                    <form action="{{ route('admin.events.destroy', $event) }}" method="POST" data-progress-form data-confirm="Tem certeza? O curso, suas inscrições e os históricos de pagamento serão excluídos permanentemente. Esta ação não pode ser desfeita.">@csrf @method('DELETE')<button class="btn btn-danger" type="submit" data-submit-label="Excluindo…">Excluir curso</button></form>
                </div>
            </article>
        @empty
            <div class="empty-state"><span aria-hidden="true">✳</span><h3>Comece pela primeira oficina</h3><p>Crie um curso para abrir as inscrições.</p><a class="btn" href="{{ route('admin.events.create') }}">Criar oficina</a></div>
        @endforelse
    </section>
    <div class="pagination-wrap">{{ $events->links() }}</div>
</x-layout>
