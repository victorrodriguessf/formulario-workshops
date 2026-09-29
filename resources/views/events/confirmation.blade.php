<x-layout title="Inscrição confirmada" :step="4">
    <div class="success-panel"><div class="success-symbol" aria-hidden="true">✓</div><p class="eyebrow">PAGAMENTO CONFIRMADO</p><h1>Sua inscrição está confirmada!</h1><p>Que bom ter você com a gente. Guarde as informações abaixo para o dia da oficina.</p></div>
    <div class="flow-card"><h2>Informações da oficina</h2><dl class="review-list"><div><dt>Curso</dt><dd>{{ $event->title }}</dd></div><div><dt>Participante</dt><dd>{{ $registration->participant_name }}</dd></div><div><dt>Quando</dt><dd>{{ $event->starts_at?->format('d/m/Y \à\s H:i') ?? 'Data a combinar' }}</dd></div><div><dt>Onde</dt><dd>{{ $event->place ?: 'Local a confirmar' }}</dd></div><div><dt>Protocolo</dt><dd><strong>{{ $registration->protocol }}</strong></dd></div></dl></div>
    <a class="btn btn-secondary" href="{{ route('events.index') }}">Ver outros cursos</a>
</x-layout>
