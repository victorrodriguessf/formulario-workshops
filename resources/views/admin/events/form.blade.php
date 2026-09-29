@php($editing = isset($event))
@if($errors->any())<div class="alert alert-error" id="error-summary" role="alert" tabindex="-1"><strong>Confira as informações</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ $editing ? route('admin.events.update', $event) : route('admin.events.store') }}" method="POST" enctype="multipart/form-data" data-progress-form>@csrf @if($editing) @method('PUT') @endif
    <div class="admin-form-section"><div class="section-intro"><span class="section-index">01</span><div><h2>Apresentação</h2><p>O que o participante precisa saber sobre a oficina.</p></div></div>
        <x-input id="title" name="title" label="Nome da oficina" :value="$event->title ?? ''" :required="true" />
        <div class="form-group"><label for="description">Descrição <span class="required-label">(obrigatório)</span></label><span class="help-text">Conte o que a pessoa vai aprender, em palavras simples.</span><textarea id="description" name="description" class="field" rows="5" required>{{ old('description', $event->description ?? '') }}</textarea></div>
        <x-input id="place" name="place" label="Local" :value="$event->place ?? ''" help="Endereço ou nome do espaço onde a oficina acontecerá." />
        <div class="form-group"><label for="materials">Materiais necessários</label><textarea id="materials" name="materials" class="field" rows="3">{{ old('materials', $event->materials ?? '') }}</textarea></div>
    </div>
    <div class="admin-form-section"><div class="section-intro"><span class="section-index">02</span><div><h2>Data e inscrições</h2><p>Deixe em branco os campos que ainda não foram definidos.</p></div></div>
        <div class="form-grid"><x-input id="starts_at" name="starts_at" label="Início da oficina" type="datetime-local" :value="isset($event) && $event->starts_at ? $event->starts_at->format('Y-m-d\TH:i') : ''" /><x-input id="ends_at" name="ends_at" label="Fim da oficina" type="datetime-local" :value="isset($event) && $event->ends_at ? $event->ends_at->format('Y-m-d\TH:i') : ''" /></div>
        <div class="form-grid"><x-input id="registrations_open_at" name="registrations_open_at" label="Abrir inscrições em" type="datetime-local" :value="isset($event) && $event->registrations_open_at ? $event->registrations_open_at->format('Y-m-d\TH:i') : ''" /><x-input id="registrations_close_at" name="registrations_close_at" label="Encerrar inscrições em" type="datetime-local" :value="isset($event) && $event->registrations_close_at ? $event->registrations_close_at->format('Y-m-d\TH:i') : ''" /></div>
        <x-input id="cash_payment_deadline" name="cash_payment_deadline" label="Prazo para pagamento em dinheiro" type="datetime-local" :value="isset($event) && $event->cash_payment_deadline ? $event->cash_payment_deadline->format('Y-m-d\TH:i') : ''" help="Opcional. O pagamento em dinheiro será oferecido somente até esta data. Reservas não pagas serão canceladas automaticamente ao fim do prazo." />
        <x-input id="capacity" name="capacity" label="Limite de participantes" type="number" min="1" max="100000" :value="$event->capacity ?? ''" inputmode="numeric" help="Opcional. Ao preencher todas as vagas, novas inscrições serão encerradas automaticamente." />
    </div>
    <div class="admin-form-section"><div class="section-intro"><span class="section-index">03</span><div><h2>Valor e imagem</h2><p>O preço das inscrições já realizadas permanece o mesmo.</p></div></div>
        <x-input id="price_reais" name="price_reais" label="Preço em reais" inputmode="decimal" :value="isset($event) ? number_format($event->price_cents / 100, 2, ',', '') : ''" :required="true" help="Exemplo: 85,00" />
        <div class="form-group">
            <label for="banner_image">Imagem de capa <span class="required-label">(opcional)</span></label>
            <span class="help-text" id="banner-image-help">Tamanho recomendado: 1200 × 675 pixels (horizontal, proporção 16:9). Use JPG, PNG ou WebP de até 2 MB. A imagem aparecerá na lista de oficinas e na página de inscrição.</span>
            <input id="banner_image" name="banner_image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="banner-image-help" class="field image-input @error('banner_image') is-invalid @enderror" data-banner-input>
            @error('banner_image')<span class="error-message" role="alert">{{ $message }}</span>@enderror
        </div>
        @if($editing && $event->banner_url)
            <div class="banner-preview" data-current-banner @if(old('remove_banner')) hidden @endif><img src="{{ $event->banner_url }}" alt="{{ $event->banner_alt ?: 'Imagem atual da oficina' }}"><div><strong>Imagem atual</strong><p>Ao escolher outra, esta imagem será substituída.</p></div></div>
            <label class="check-option"><input type="checkbox" name="remove_banner" value="1" data-remove-banner @checked(old('remove_banner'))><span><strong>Remover imagem atual</strong><small>A oficina ficará sem foto até você enviar outra.</small></span></label>
        @endif
        <div class="banner-preview" data-new-banner-preview hidden><img data-new-banner-image alt="Prévia da nova imagem"><div><strong>Nova imagem escolhida</strong><p>Confira se a foto representa bem a oficina.</p></div></div>
        <x-input id="banner_alt" name="banner_alt" label="Descrição da imagem" :value="$event->banner_alt ?? ''" help="Descreva o que aparece na imagem para quem usa leitor de tela." />
    </div>
    <div class="admin-form-section"><div class="section-intro"><span class="section-index">04</span><div><h2>Publicação</h2><p>Publique quando as informações estiverem prontas.</p></div></div>
        <div class="form-group"><label for="status">Situação do curso</label><select id="status" name="status" class="field"><option value="draft" @selected(old('status', $event->status ?? 'draft') === 'draft')>Rascunho · não aparece para o público</option><option value="published" @selected(old('status', $event->status ?? '') === 'published')>Publicado · visível para o público</option><option value="cancelled" @selected(old('status', $event->status ?? '') === 'cancelled')>Cancelado</option></select></div>
        <label class="check-option"><input type="checkbox" name="is_paused" value="1" @checked(old('is_paused', $event->is_paused ?? false))><span><strong>Pausar novas inscrições</strong><small>O curso continua visível, mas o formulário fica indisponível.</small></span></label>
    </div>
    <div class="form-actions"><button class="btn" type="submit" data-submit-label="Salvando…">{{ $editing ? 'Salvar alterações' : 'Criar oficina' }}</button><a class="btn btn-secondary" href="{{ route('admin.events.index') }}">Cancelar</a></div>
</form>
