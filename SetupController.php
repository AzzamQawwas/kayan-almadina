<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View
    {
        abort_if(app()->environment('production'), 404);

        return view('setup.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(app()->environment('production'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $admin = DB::transaction(function () use ($data): User {
            $migrationLock = DB::table('migrations')
                ->where('migration', '0001_01_01_000000_create_users_table')
                ->lockForUpdate()
                ->first();

            abort_unless($migrationLock, 503, 'يجب إعداد قاعدة البيانات قبل إنشاء حساب المدير.');

            if (User::query()->where('role', User::ROLE_ADMIN)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'تم إعداد حساب المدير مسبقًا. سجّل الدخول بحسابك.',
                ]);
            }

            return User::create($data + [
                'role' => User::ROLE_ADMIN,
                'active' => true,
            ]);
        });

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'تم إعداد حساب المدير. يمكنك الآن إضافة حسابات الفريق.');
    }
}
