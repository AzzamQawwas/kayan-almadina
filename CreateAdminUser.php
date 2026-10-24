<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the first system administrator securely.';

    public function handle(): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->error('An administrator already exists. Create additional users from the admin screen.');

            return self::FAILURE;
        }

        $name = $this->ask('Administrator name');
        $email = $this->ask('Administrator email');
        $password = $this->secret('Administrator password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            ],
            [
                'password.confirmed' => 'The password confirmation did not match.',
            ],
        );

        $passwordConfirmation = $this->secret('Confirm administrator password');
        $validator->setData($validator->getData() + ['password_confirmation' => $passwordConfirmation]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $this->info('Administrator created. Sign in using the supplied credentials.');

        return self::SUCCESS;
    }
}
