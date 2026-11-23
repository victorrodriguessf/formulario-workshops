<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\Registration;
use App\Services\RegistrationMailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function __construct(private RegistrationMailer $registrationMailer) {}

    public function index(Request $request): View
    {
        $events = $request->user()->events()
            ->withCount('occupiedRegistrations')
            ->orderBy('title')
            ->get();

        return view('admin.registrations.index', [
            'registrations' => $this->filtered($request)->paginate(20)->withQueryString(),
            'events' => $events,
            'search' => $request->input('search', ''),
            'summary' => $this->summary($request, $events),
        ]);
    }

    /**
     * Totais que acompanham a busca e a oficina escolhidas (a situação não entra,
     * para que os números por situação continuem comparáveis entre si).
     */
    private function summary(Request $request, Collection $events): array
    {
        $base = $this->filtered($request, withStatus: false)->reorder()->setEagerLoads([]);

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $selectedEvent = $request->filled('event_id')
            ? $events->firstWhere('id', $request->input('event_id'))
            : null;

        return [
            'total' => (int) $byStatus->sum(),
            'confirmed' => (int) ($byStatus['confirmed'] ?? 0),
            'awaiting_payment' => (int) ($byStatus['awaiting_payment'] ?? 0),
            'other' => (int) $byStatus->except(['confirmed', 'awaiting_payment'])->sum(),
            'revenue_cents' => (int) (clone $base)->where('payment_received', true)->sum('agreed_price_cents'),
            'spots_left' => $selectedEvent?->capacity !== null
                ? max(0, $selectedEvent->capacity - $selectedEvent->occupied_registrations_count)
                : null,
        ];
    }

    public function show(Registration $registration): View
    {
        Gate::authorize('view', $registration);
        $registration->load(['event', 'paymentAttempts']);

        return view('admin.registrations.show', compact('registration'));
    }

    public function updatePayment(Request $request, Registration $registration): RedirectResponse
    {
        Gate::authorize('update', $registration);
        $validated = $request->validate([
            'payment_received' => ['required', 'boolean'],
        ]);

        if ($registration->payment_method !== 'cash') {
            throw ValidationException::withMessages([
                'payment_received' => 'O pagamento por Pix é atualizado automaticamente pelo sistema.',
            ]);
        }

        $result = DB::transaction(function () use ($registration, $validated): array {
            $current = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            $paymentReceived = (bool) $validated['payment_received'];

            if ($paymentReceived
                && ($current->status === 'expired'
                    || ($current->status !== 'confirmed' && $current->cash_payment_due_at?->isPast()))) {
                $current->update(['status' => 'expired', 'payment_received' => false]);

                return ['error' => 'expired'];
            }

            $current->update([
                'payment_received' => $paymentReceived,
                'status' => $paymentReceived
                    ? 'confirmed'
                    : ($current->cash_payment_due_at?->isPast() ? 'expired' : 'awaiting_payment'),
            ]);

            return ['registration' => $current];
        });

        if (($result['error'] ?? null) === 'expired') {
            throw ValidationException::withMessages([
                'payment_received' => 'O prazo desta reserva terminou e ela não pode mais ser confirmada.',
            ]);
        }

        $current = $result['registration'];

        if ($current->payment_received) {
            $this->registrationMailer->sendConfirmation($current);
        }

        return back()->with('success', 'Situação do pagamento atualizada.');
    }

    public function destroy(Registration $registration): RedirectResponse
    {
        Gate::authorize('delete', $registration);
        DB::transaction(function () use ($registration): void {
            PaymentAttempt::where('registration_id', $registration->id)->delete();
            $registration->delete();
        });

        Log::info('Inscrição removida pelo painel administrativo.', [
            'protocol' => $registration->protocol,
            'event_id' => $registration->event_id,
            'payment_method' => $registration->payment_method,
            'payment_received' => $registration->payment_received,
            'agreed_price_cents' => $registration->agreed_price_cents,
            'admin_id' => auth()->id(),
        ]);

        return redirect()->route('admin.registrations.index')
            ->with('success', "Inscrição {$registration->protocol} removida. A vaga foi liberada.");
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'inscricoes_'.date('Y-m-d_H-i').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Protocolo', 'Curso', 'Nome', 'WhatsApp', 'E-mail', 'Forma de pagamento', 'Pagamento recebido', 'Situação', 'Preço (R$)', 'Data'], ';');

            foreach ($this->filtered($request)->lazy(200) as $registration) {
                fputcsv($output, [
                    $this->safeCell($registration->protocol),
                    $this->safeCell($registration->event->title),
                    $this->safeCell($registration->participant_name),
                    $this->safeCell($registration->participant_whatsapp),
                    $this->safeCell($registration->participant_email),
                    $registration->payment_method === 'cash' ? 'Dinheiro' : 'Pix',
                    $registration->payment_received ? 'Sim' : 'Não',
                    $this->safeCell($registration->status),
                    number_format($registration->agreed_price_cents / 100, 2, ',', '.'),
                    $registration->created_at->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        $query = Registration::with('event')
            ->whereHas('event', fn (Builder $event) => $event->where('user_id', $request->user()->id))
            ->latest();
        $search = $request->input('search');
        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('participant_name', 'like', '%'.$search.'%')
                    ->orWhere('protocol', 'like', '%'.$search.'%')
                    ->orWhere('participant_email', 'like', '%'.$search.'%');
            });
        }
        if ($request->filled('event_id')) {
            $query->where('event_id', $request->input('event_id'));
        }
        if ($withStatus && in_array($request->input('status'), ['confirmed', 'awaiting_payment', 'cancelled', 'expired', 'review_required'], true)) {
            $query->where('status', $request->input('status'));
        }

        return $query;
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
