<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Services\RegistrationMailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function __construct(private RegistrationMailer $registrationMailer) {}

    public function index(Request $request): View
    {
        return view('admin.registrations.index', [
            'registrations' => $this->filtered($request)->paginate(20)->withQueryString(),
            'events' => Event::orderBy('title')->get(['id', 'title']),
            'search' => $request->input('search', ''),
            'counts' => [
                'confirmed' => Registration::where('status', 'confirmed')->count(),
                'awaiting_payment' => Registration::where('status', 'awaiting_payment')->count(),
                'other' => Registration::whereNotIn('status', ['confirmed', 'awaiting_payment'])->count(),
            ],
        ]);
    }

    public function show(Registration $registration): View
    {
        $registration->load(['event', 'paymentAttempts']);

        return view('admin.registrations.show', compact('registration'));
    }

    public function updatePayment(Request $request, Registration $registration): RedirectResponse
    {
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

    private function filtered(Request $request): Builder
    {
        $query = Registration::with('event')->latest();
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
        if (in_array($request->input('status'), ['confirmed', 'awaiting_payment', 'cancelled', 'expired', 'review_required'], true)) {
            $query->where('status', $request->input('status'));
        }

        return $query;
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
