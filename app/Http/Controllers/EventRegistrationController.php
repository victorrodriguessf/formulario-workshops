<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PaymentAttempt;
use App\Models\Registration;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentRequestRejected;
use App\Services\RegistrationMailer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventRegistrationController extends Controller
{
    public function __construct(private RegistrationMailer $registrationMailer) {}

    public function index(): View
    {
        return view('events.index', [
            'events' => Event::withCount('occupiedRegistrations')
                ->where('status', 'published')
                ->orderBy('starts_at')
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $event = Event::withCount('occupiedRegistrations')->whereKey($id)->where('status', 'published')->firstOrFail();

        return view('events.show', [
            'event' => $event,
            'savedData' => $this->registrationDataFromSession($request, $event),
            'canRegister' => $event->acceptsNewRegistrations(),
            'registrationUnavailableMessage' => $this->registrationUnavailableMessage($event),
        ]);
    }

    public function review(Request $request, string $id): View
    {
        $event = Event::withCount('occupiedRegistrations')->whereKey($id)->where('status', 'published')->firstOrFail();
        abort_unless($event->acceptsNewRegistrations(), 403, $this->registrationUnavailableMessage($event));

        $validated = $request->validate([
            'participant_name' => ['required', 'string', 'min:3', 'max:255'],
            'participant_whatsapp' => ['required', 'string', 'max:25', function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = preg_replace('/\D/', '', (string) $value);
                if (str_starts_with($digits, '55') && strlen($digits) === 13) {
                    $digits = substr($digits, 2);
                }
                if (strlen($digits) !== 11) {
                    $fail('Informe o DDD e o número do celular. Exemplo: (84) 99999-9999.');
                }
            }],
            'participant_cpf' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->validCpf((string) $value)) {
                    $fail('Confira o CPF. Ele deve ter 11 números válidos.');
                }
            }],
            'participant_email' => ['required', 'email', 'max:255'],
            'payment_method' => ['required', Rule::in($event->cashPaymentIsAvailable() ? ['pix', 'cash'] : ['pix'])],
        ], [
            'participant_name.required' => 'Informe o nome completo do participante.',
            'participant_whatsapp.required' => 'Informe o celular com DDD.',
            'participant_cpf.required' => 'Informe o CPF do participante.',
            'participant_email.required' => 'Informe o e-mail do participante.',
            'participant_email.email' => 'Informe um e-mail válido.',
            'payment_method.required' => 'Escolha como deseja pagar.',
            'payment_method.in' => 'O pagamento em dinheiro não está disponível para esta oficina. Escolha Pix.',
        ]);

        $sessionData = $validated;
        $sessionData['participant_cpf_encrypted'] = Crypt::encryptString($validated['participant_cpf']);
        unset($sessionData['participant_cpf']);

        $request->session()->put($this->sessionKey($event), $sessionData);
        $request->session()->put($this->sessionKey($event).'_price', $event->price_cents);
        $request->session()->put(
            $this->sessionKey($event).'_cash_deadline',
            $event->cash_payment_deadline?->toIso8601String(),
        );

        return view('events.review', [
            'event' => $event,
            'data' => $validated,
            'receiverName' => config('services.payment.receiver_name'),
        ]);
    }

    public function process(Request $request, string $id): RedirectResponse
    {
        $event = Event::whereKey($id)->where('status', 'published')->firstOrFail();
        $data = $this->registrationDataFromSession($request, $event);
        if (! $data) {
            return redirect()->route('events.show', $event->id)->withErrors(['registration' => 'Sua revisão expirou. Preencha os dados novamente.']);
        }
        $cpf = preg_replace('/\D/', '', $data['participant_cpf']);
        $cpfHmac = hash_hmac('sha256', $cpf, config('app.key'));

        try {
            $result = DB::transaction(function () use ($request, $event, $data, $cpf, $cpfHmac): array {
                $lockedEvent = Event::whereKey($event->id)->where('status', 'published')->lockForUpdate()->firstOrFail();
                $existing = Registration::where('event_id', $lockedEvent->id)->where('cpf_lookup_hmac', $cpfHmac)->first();

                if ($existing) {
                    return ['existing' => $existing];
                }

                if ($request->session()->get($this->sessionKey($event).'_price') !== $lockedEvent->price_cents) {
                    return ['error' => 'price'];
                }

                if ($data['payment_method'] === 'cash'
                    && $request->session()->get($this->sessionKey($event).'_cash_deadline') !== $lockedEvent->cash_payment_deadline?->toIso8601String()) {
                    return ['error' => 'cash_deadline'];
                }

                if (! $lockedEvent->acceptsNewRegistrations()) {
                    return ['error' => 'unavailable'];
                }

                if ($data['payment_method'] === 'cash' && ! $lockedEvent->cashPaymentIsAvailable()) {
                    return ['error' => 'cash_unavailable'];
                }

                $phone = preg_replace('/\D/', '', $data['participant_whatsapp']);
                if (str_starts_with($phone, '55') && strlen($phone) === 13) {
                    $phone = substr($phone, 2);
                }

                $registration = Registration::create([
                    'event_id' => $lockedEvent->id,
                    'participant_name' => $data['participant_name'],
                    'participant_whatsapp' => $phone,
                    'participant_email' => $data['participant_email'],
                    'participant_cpf_encrypted' => Crypt::encryptString($cpf),
                    'cpf_lookup_hmac' => $cpfHmac,
                    'status' => 'awaiting_payment',
                    'payment_method' => $data['payment_method'],
                    'payment_received' => false,
                    'cash_payment_due_at' => $data['payment_method'] === 'cash' ? $lockedEvent->cash_payment_deadline : null,
                    'agreed_price_cents' => $lockedEvent->price_cents,
                    'protocol' => 'APP-'.strtoupper(Str::random(10)),
                ]);

                if ($registration->payment_method === 'pix') {
                    PaymentAttempt::create([
                        'registration_id' => $registration->id,
                        'idempotency_key' => (string) Str::uuid(),
                        'environment' => config('app.env'),
                        'amount_cents' => $lockedEvent->price_cents,
                        'currency' => 'BRL',
                        'local_status' => 'creating',
                        'expires_at' => $this->pixExpiresAt($lockedEvent),
                    ]);
                }

                return ['registration' => $registration];
            });
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('events.show', $event->id)->withErrors(['registration' => 'Não conseguimos criar a inscrição agora. Tente novamente.']);
        }

        if (($result['error'] ?? null) === 'price') {
            return redirect()->route('events.show', $event->id)->withErrors(['registration' => 'O valor desta oficina mudou. Confira o preço antes de continuar.']);
        }

        if (($result['error'] ?? null) === 'unavailable') {
            $event->loadCount('occupiedRegistrations');

            return redirect()->route('events.show', $event->id)->withErrors(['registration' => $this->registrationUnavailableMessage($event)]);
        }

        if (($result['error'] ?? null) === 'cash_unavailable') {
            return redirect()->route('events.show', $event->id)
                ->withErrors(['payment_method' => 'O prazo para pagamento em dinheiro terminou. Escolha Pix para continuar.']);
        }

        if (($result['error'] ?? null) === 'cash_deadline') {
            return redirect()->route('events.show', $event->id)
                ->withErrors(['payment_method' => 'O prazo para pagamento em dinheiro mudou. Confira a nova data antes de continuar.']);
        }

        if (isset($result['existing'])) {
            $existing = $result['existing'];
            if ($request->session()->get('registration_access_'.$existing->id)) {
                return redirect()->route('events.register.status', ['id' => $event->id, 'protocol' => $existing->protocol]);
            }

            return redirect()->route('events.show', $event->id)->withErrors(['participant_cpf' => 'Já existe uma inscrição para este CPF neste curso. Se precisar de ajuda, fale com a loja.']);
        }

        $registration = $result['registration'];
        $request->session()->put('registration_access_'.$registration->id, true);
        $request->session()->forget([
            $this->sessionKey($event),
            $this->sessionKey($event).'_price',
            $this->sessionKey($event).'_cash_deadline',
        ]);

        if ($registration->payment_method === 'cash') {
            $this->registrationMailer->sendCashReservation($registration);

            return redirect()->route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]);
        }

        $attempt = $registration->paymentAttempts()->latest('id')->firstOrFail();
        if (! $this->issuePix($request, $attempt)) {
            return redirect()->route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol])
                ->with('warning', 'Sua inscrição foi salva, mas ainda estamos conferindo a geração do Pix. Não faça outro pagamento agora.');
        }

        return redirect()->route('events.register.status', ['id' => $event->id, 'protocol' => $registration->protocol]);
    }

    public function retry(Request $request, string $id): RedirectResponse
    {
        $event = Event::whereKey($id)->where('status', 'published')->firstOrFail();
        $registration = Registration::where('event_id', $event->id)->where('protocol', $request->input('protocol'))->firstOrFail();
        abort_unless($request->session()->get('registration_access_'.$registration->id), 404);

        if (! $event->registrationPeriodIsOpen() || $registration->status !== 'awaiting_payment' || $registration->payment_method !== 'pix') {
            return redirect()->route('events.register.status', ['id' => $id, 'protocol' => $registration->protocol])
                ->with('warning', 'Uma nova cobrança não está disponível para esta inscrição.');
        }

        $attempt = DB::transaction(function () use ($registration): ?PaymentAttempt {
            Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            $latest = $registration->paymentAttempts()->lockForUpdate()->latest('id')->first();
            if (! $latest || ! in_array($latest->local_status, ['failed', 'expired', 'cancelled'], true)) {
                return null;
            }

            return PaymentAttempt::create([
                'registration_id' => $registration->id,
                'idempotency_key' => (string) Str::uuid(),
                'environment' => config('app.env'),
                'amount_cents' => $registration->agreed_price_cents,
                'currency' => 'BRL',
                'local_status' => 'creating',
                'expires_at' => $this->pixExpiresAt($registration->event),
            ]);
        });

        if (! $attempt) {
            return redirect()->route('events.register.status', ['id' => $id, 'protocol' => $registration->protocol])
                ->with('warning', 'Estamos conferindo a cobrança anterior. Aguarde antes de tentar novamente.');
        }

        $message = $this->issuePix($request, $attempt)
            ? 'Um novo código Pix foi gerado.'
            : 'Ainda não conseguimos gerar o Pix. Não faça outro pagamento agora.';

        return redirect()->route('events.register.status', ['id' => $id, 'protocol' => $registration->protocol])
            ->with('warning', $message);
    }

    public function status(Request $request, string $id): View
    {
        $event = Event::findOrFail($id);
        $registration = Registration::where('event_id', $event->id)
            ->where('protocol', $request->query('protocol'))
            ->firstOrFail();
        abort_unless($request->session()->get('registration_access_'.$registration->id), 404);

        if ($registration->expireOverdueCashPayment()) {
            $registration->refresh();
        }

        $attempt = $registration->paymentAttempts()->latest('id')->first();
        if (! $this->syncPixStatus($registration, $attempt)) {
            $request->session()->flash('warning', 'Não conseguimos atualizar o pagamento agora. Sua inscrição foi mantida.');
        }

        $registration->refresh();

        if ($registration->status === 'confirmed') {
            $this->registrationMailer->sendConfirmation($registration);
        } elseif ($registration->payment_method === 'cash' && $registration->status === 'awaiting_payment') {
            $this->registrationMailer->sendCashReservation($registration);
        }

        if ($registration->status === 'confirmed') {
            return view('events.confirmation', compact('event', 'registration'));
        }

        return view('events.payment', [
            'event' => $event,
            'registration' => $registration,
            'attempt' => $attempt,
            'pixData' => $attempt ? $request->session()->get('pix_data_'.$attempt->id, []) : [],
            'receiverName' => config('services.payment.receiver_name'),
            'isDemo' => app(PaymentGatewayInterface::class) instanceof FakePaymentGateway && app()->environment(['local', 'testing']),
            'canRetry' => $registration->status === 'awaiting_payment'
                && $registration->payment_method === 'pix'
                && $event->status === 'published'
                && $event->registrationPeriodIsOpen()
                && in_array($attempt?->local_status, ['failed', 'expired', 'cancelled'], true),
        ]);
    }

    public function checkStatus(Request $request, string $id): JsonResponse
    {
        $registration = Registration::where('event_id', $id)
            ->where('protocol', $request->query('protocol'))
            ->firstOrFail();
        abort_unless($request->session()->get('registration_access_'.$registration->id), 404);

        $attempt = $registration->paymentAttempts()->latest('id')->first();
        $this->syncPixStatus($registration, $attempt);

        return response()->json([
            'status' => $registration->refresh()->status,
            'payment' => $attempt?->refresh()->local_status,
        ]);
    }

    public function simulatePayment(Request $request, string $id): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']) && app(PaymentGatewayInterface::class) instanceof FakePaymentGateway, 404);
        $registration = Registration::where('event_id', $id)->where('protocol', $request->input('protocol'))->firstOrFail();
        abort_unless($request->session()->get('registration_access_'.$registration->id), 404);
        $attempt = $registration->paymentAttempts()->latest('id')->firstOrFail();
        abort_unless($attempt->local_status === 'pending', 403);
        $attempt->update(['local_status' => 'paid']);
        $registration->update(['status' => 'confirmed', 'payment_received' => true]);
        $this->registrationMailer->sendConfirmation($registration->refresh());

        return redirect()->route('events.register.status', ['id' => $id, 'protocol' => $registration->protocol]);
    }

    /**
     * Consults the provider for a pending Pix and applies the result; returns false when the provider could not be reached.
     */
    private function syncPixStatus(Registration $registration, ?PaymentAttempt $attempt): bool
    {
        if ($attempt?->local_status === 'paid' && $registration->status === 'awaiting_payment') {
            $registration->update(['status' => 'confirmed', 'payment_received' => true]);
        }

        if (! $attempt || ! in_array($attempt->local_status, ['pending', 'unknown'], true)) {
            return true;
        }

        try {
            $result = app(PaymentGatewayInterface::class)->checkPaymentStatus($attempt);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }

        if ($result['status'] === 'paid') {
            $attempt->update(['local_status' => 'paid']);
            $registration->update(['status' => 'confirmed', 'payment_received' => true]);
        } elseif ($result['status'] === 'failed') {
            $attempt->update(['local_status' => 'failed']);
        } elseif ($result['status'] === 'refunded') {
            $attempt->update(['local_status' => 'refunded']);
            $registration->update(['status' => 'cancelled', 'payment_received' => false]);
        } elseif ($result['status'] === 'review_required') {
            $attempt->update(['local_status' => 'disputed']);
            $registration->update(['status' => 'review_required']);
        }

        return true;
    }

    private function registrationUnavailableMessage(Event $event): string
    {
        return $event->hasAvailableSpots()
            ? 'As inscrições deste curso não estão abertas.'
            : 'As vagas desta oficina estão esgotadas.';
    }

    private function pixExpiresAt(Event $event): Carbon
    {
        $expiration = now()->addMinutes(60);

        return $event->registrations_close_at && $event->registrations_close_at->lessThan($expiration)
            ? $event->registrations_close_at
            : $expiration;
    }

    private function issuePix(Request $request, PaymentAttempt $attempt): bool
    {
        try {
            $pixData = app(PaymentGatewayInterface::class)->createPixPayment($attempt);
            $attempt->update(['order_id' => $pixData['provider_order_id'], 'local_status' => 'pending']);
            $request->session()->put('pix_data_'.$attempt->id, $pixData);

            return true;
        } catch (PaymentRequestRejected $exception) {
            report($exception);
            $attempt->update(['local_status' => 'failed']);

            return false;
        } catch (\Throwable $exception) {
            report($exception);
            $attempt->update(['local_status' => 'unknown']);

            return false;
        }
    }

    private function sessionKey(Event $event): string
    {
        return 'registration_data_'.$event->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationDataFromSession(Request $request, Event $event): array
    {
        $sessionKey = $this->sessionKey($event);
        $storedData = $request->session()->get($sessionKey, []);
        if (! is_array($storedData)) {
            $request->session()->forget($sessionKey);

            return [];
        }

        if (isset($storedData['participant_cpf'])) {
            $plainCpf = (string) $storedData['participant_cpf'];
            $storedData['participant_cpf_encrypted'] = Crypt::encryptString($plainCpf);
            unset($storedData['participant_cpf']);
            $request->session()->put($sessionKey, $storedData);
        } elseif (isset($storedData['participant_cpf_encrypted'])) {
            try {
                $plainCpf = Crypt::decryptString((string) $storedData['participant_cpf_encrypted']);
            } catch (DecryptException) {
                $request->session()->forget($sessionKey);

                return [];
            }
        } else {
            return [];
        }

        unset($storedData['participant_cpf_encrypted']);
        $storedData['participant_cpf'] = $plainCpf;

        return $storedData;
    }

    private function validCpf(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($length = 9; $length < 11; $length++) {
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * ($length + 1 - $index);
            }
            $check = ($sum * 10) % 11;
            if (($check === 10 ? 0 : $check) !== (int) $digits[$length]) {
                return false;
            }
        }

        return true;
    }
}
