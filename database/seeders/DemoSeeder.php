<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dados fictícios para a apresentação: dois organizadores, oficinas e inscrições.
 *
 * Uso: php artisan db:seed --class=DemoSeeder
 * A senha dos organizadores vem de DEMO_ADMIN_PASSWORD; sem ela, uma senha aleatória é gerada e exibida.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! env('ALLOW_DEMO_SEED')) {
            throw new RuntimeException('DemoSeeder bloqueado em produção. Use um banco de demonstração e defina ALLOW_DEMO_SEED=true.');
        }

        $password = env('DEMO_ADMIN_PASSWORD') ?: Str::password(12, symbols: false);

        $loja = User::updateOrCreate(['username' => 'loja.demo'], ['name' => 'Armarinho (demo)', 'password' => $password]);
        $instrutora = User::updateOrCreate(['username' => 'instrutora.demo'], ['name' => 'Instrutora convidada (demo)', 'password' => $password]);

        $bordado = $this->course($loja, [
            'title' => 'Bordado livre para iniciantes',
            'description' => 'Pontos básicos do bordado livre: atrás, corrente, haste e nó francês. Você sai com um bastidor pronto.',
            'materials' => 'Todo o material está incluso.',
            'price_cents' => 8500,
            'capacity' => 10,
            'starts_at' => now()->addDays(10)->setTime(14, 0),
        ]);

        $amigurumi = $this->course($loja, [
            'title' => 'Amigurumi: primeiro bichinho',
            'description' => 'Anel mágico, aumentos e diminuições para montar um bichinho de crochê.',
            'materials' => 'Traga uma agulha de 2,5 mm. Linhas e enchimento por conta da loja.',
            'price_cents' => 12000,
            'capacity' => 8,
            'starts_at' => now()->addDays(17)->setTime(9, 0),
        ]);

        $patchwork = $this->course($instrutora, [
            'title' => 'Patchwork: almofada em blocos',
            'description' => 'Corte, montagem de blocos e acabamento de uma capa de almofada.',
            'materials' => 'Tecidos inclusos. Traga tesoura de tecido, se tiver.',
            'price_cents' => 15000,
            'capacity' => 6,
            'starts_at' => now()->addDays(24)->setTime(14, 0),
        ]);

        $lotada = $this->course($instrutora, [
            'title' => 'Macramê: suporte de planta',
            'description' => 'Nós básicos do macramê para montar um suporte de vaso.',
            'materials' => 'Material incluso.',
            'price_cents' => 7000,
            'capacity' => 2,
            'starts_at' => now()->addDays(8)->setTime(15, 0),
        ]);

        $this->registrations($bordado, [
            ['Maria Clara Souza', 'pix', 'confirmed'],
            ['Joana Ribeiro', 'pix', 'confirmed'],
            ['Carla Menezes', 'cash', 'confirmed'],
            ['Paula Andrade', 'cash', 'awaiting_payment'],
            ['Rita Nogueira', 'pix', 'expired'],
        ]);
        $this->registrations($amigurumi, [
            ['Luiza Fernandes', 'pix', 'confirmed'],
            ['Beatriz Almeida', 'cash', 'awaiting_payment'],
        ]);
        $this->registrations($patchwork, [
            ['Helena Duarte', 'pix', 'confirmed'],
        ]);
        $this->registrations($lotada, [
            ['Sofia Martins', 'pix', 'confirmed'],
            ['Lara Cavalcanti', 'cash', 'confirmed'],
        ]);

        $this->command?->info('Organizadores de demonstração: loja.demo e instrutora.demo');
        if (! env('DEMO_ADMIN_PASSWORD')) {
            $this->command?->warn("Senha gerada para os dois (anote, não será mostrada de novo): {$password}");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function course(User $owner, array $data): Event
    {
        return $owner->events()->updateOrCreate(['title' => $data['title']], $data + [
            'slug' => Str::slug($data['title']).'-demo',
            'place' => 'Armarinho Ponto a Ponto (endereço fictício para demonstração)',
            'status' => 'published',
            'is_paused' => false,
            'registrations_open_at' => now()->subDays(5),
            'registrations_close_at' => $data['starts_at']->copy()->subDay(),
            'ends_at' => $data['starts_at']->copy()->addHours(3),
            'cash_payment_deadline' => $data['starts_at']->copy()->subDays(2),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $people
     */
    private function registrations(Event $event, array $people): void
    {
        $event->registrations()->delete();

        foreach ($people as [$name, $method, $status]) {
            $cpf = $this->fakeCpf();

            Registration::create([
                'event_id' => $event->id,
                'participant_name' => $name,
                'participant_whatsapp' => '8490000'.random_int(1000, 9999),
                'participant_email' => Str::slug($name, '.').'@exemplo.com',
                'participant_cpf_encrypted' => Crypt::encryptString($cpf),
                'cpf_lookup_hmac' => hash_hmac('sha256', $cpf, config('app.key')),
                'status' => $status,
                'payment_method' => $method,
                'payment_received' => $status === 'confirmed',
                'cash_payment_due_at' => $method === 'cash' ? $event->cash_payment_deadline : null,
                'agreed_price_cents' => $event->price_cents,
                'protocol' => 'DEMO-'.strtoupper(Str::random(8)),
            ]);
        }
    }

    /**
     * Gera um CPF com dígitos verificadores válidos, só para dados de teste.
     */
    private function fakeCpf(): string
    {
        $digits = array_map(fn () => random_int(0, 9), range(1, 9));

        foreach ([10, 11] as $weight) {
            $sum = 0;
            foreach ($digits as $index => $digit) {
                $sum += $digit * ($weight - $index);
            }
            $remainder = ($sum * 10) % 11;
            $digits[] = $remainder === 10 ? 0 : $remainder;
        }

        return implode('', $digits);
    }
}
