<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PaymentAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class EventController extends Controller
{
    public function index(): View
    {
        return view('admin.events.index', [
            'events' => Event::withCount('occupiedRegistrations')->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('admin.events.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedEvent($request);
        $validated['slug'] = Str::slug($validated['title']).'-'.Str::lower(Str::random(6));
        $bannerPath = $this->storeBanner($request);

        if ($bannerPath) {
            $validated['banner_path'] = $bannerPath;
        }

        try {
            Event::create($validated);
        } catch (Throwable $exception) {
            if ($bannerPath) {
                Storage::disk('public')->delete($bannerPath);
            }

            throw $exception;
        }

        return redirect()->route('admin.events.index')->with('success', 'Curso criado com sucesso.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.edit', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $this->validatedEvent($request);
        $removeBanner = $request->boolean('remove_banner');

        if ($removeBanner && $request->hasFile('banner_image')) {
            throw ValidationException::withMessages([
                'banner_image' => 'Escolha uma nova imagem ou marque para remover a atual.',
            ]);
        }

        $bannerPath = $this->storeBanner($request);
        $oldBannerPath = $event->banner_path;

        if ($bannerPath) {
            $validated['banner_path'] = $bannerPath;
        } elseif ($removeBanner) {
            $validated['banner_path'] = null;
            $validated['banner_alt'] = null;
        }

        try {
            $event->update($validated);
        } catch (Throwable $exception) {
            if ($bannerPath) {
                Storage::disk('public')->delete($bannerPath);
            }

            throw $exception;
        }

        if (($bannerPath || $removeBanner) && $oldBannerPath && Str::startsWith($oldBannerPath, 'events/')) {
            Storage::disk('public')->delete($oldBannerPath);
        }

        return redirect()->route('admin.events.index')->with('success', 'Curso atualizado com sucesso.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $bannerPath = $event->banner_path;

        DB::transaction(function () use ($event): void {
            $registrationIds = $event->registrations()->pluck('id');

            if ($registrationIds->isNotEmpty()) {
                PaymentAttempt::whereIn('registration_id', $registrationIds)->delete();
                $event->registrations()->delete();
            }

            $event->delete();
        });

        if ($bannerPath && Str::startsWith($bannerPath, 'events/')) {
            Storage::disk('public')->delete($bannerPath);
        }

        return redirect()->route('admin.events.index')->with('success', 'Curso removido permanentemente.');
    }

    private function validatedEvent(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'place' => ['nullable', 'string', 'max:255'],
            'materials' => ['nullable', 'string', 'max:3000'],
            'banner_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner_alt' => ['nullable', 'string', 'max:255'],
            'remove_banner' => ['sometimes', 'boolean'],
            'price_reais' => ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registrations_open_at' => ['nullable', 'date'],
            'registrations_close_at' => ['nullable', 'date', 'after:registrations_open_at'],
            'cash_payment_deadline' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['required', 'in:draft,published,cancelled'],
            'is_paused' => ['sometimes', 'boolean'],
        ], [
            'title.required' => 'Informe o nome do curso.',
            'description.required' => 'Descreva o que será aprendido.',
            'price_reais.required' => 'Informe o preço do curso.',
            'price_reais.regex' => 'Informe um valor em reais, como 50,00.',
            'banner_image.image' => 'Escolha uma imagem válida para a capa.',
            'banner_image.mimes' => 'Use uma imagem JPG, PNG ou WebP.',
            'banner_image.max' => 'A imagem deve ter no máximo 2 MB.',
            'capacity.integer' => 'Informe a quantidade de vagas usando apenas números.',
            'capacity.min' => 'O limite deve ter pelo menos uma vaga.',
            'cash_payment_deadline.date' => 'Informe uma data e um horário válidos para o pagamento em dinheiro.',
        ]);

        $price = str_replace(',', '.', $validated['price_reais']);
        [$reais, $centavos] = array_pad(explode('.', $price, 2), 2, '0');
        $validated['price_cents'] = ((int) $reais * 100) + (int) str_pad($centavos, 2, '0');
        if ($validated['price_cents'] < 1) {
            throw ValidationException::withMessages(['price_reais' => 'O preço deve ser maior que zero.']);
        }
        unset($validated['price_reais'], $validated['banner_image'], $validated['remove_banner']);
        $validated['is_paused'] = $request->boolean('is_paused');

        return $validated;
    }

    private function storeBanner(Request $request): ?string
    {
        if (! $request->hasFile('banner_image')) {
            return null;
        }

        $path = $request->file('banner_image')->store('events', 'public');

        if (! $path) {
            throw ValidationException::withMessages([
                'banner_image' => 'Não foi possível salvar a imagem. Tente novamente.',
            ]);
        }

        return $path;
    }
}
