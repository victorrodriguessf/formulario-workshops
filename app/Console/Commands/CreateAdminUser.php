<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Cria um usuário administrador inicial de forma segura';

    public function handle(): int
    {
        $this->info('Criação de Administrador');

        $name = $this->ask('Nome do administrador');
        $username = $this->ask('Usuário de acesso');

        $password = $this->secret('Senha');
        $passwordConfirm = $this->secret('Confirme a senha');

        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $passwordConfirm,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN._-]+$/u', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return 1;
        }

        User::create([
            'name' => $name,
            'username' => $username,
            'password' => Hash::make($password),
        ]);

        $this->info('Administrador criado com sucesso!');

        return 0;
    }
}
