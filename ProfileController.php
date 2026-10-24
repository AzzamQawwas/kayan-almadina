<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->store('profile-avatars', 'local');
            if (! is_string($newAvatarPath)) {
                abort(500, 'تعذر حفظ صورة الحساب.');
            }
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if ($newAvatarPath !== null) {
            $updates['avatar_path'] = $newAvatarPath;
            $updates['avatar_mime_type'] = $request->file('avatar')->getMimeType();
        } elseif ($request->boolean('remove_avatar') && $oldAvatarPath !== null) {
            $updates['avatar_path'] = null;
            $updates['avatar_mime_type'] = null;
        }

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        try {
            $user->update($updates);
        } catch (\Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('local')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($newAvatarPath !== null && $oldAvatarPath !== null) {
            Storage::disk('local')->delete($oldAvatarPath);
        } elseif ($request->boolean('remove_avatar') && $oldAvatarPath !== null) {
            Storage::disk('local')->delete($oldAvatarPath);
        }

        return redirect()->route('profile.edit')->with('success', 'تم تحديث بيانات حسابك.');
    }

    public function avatar(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(
            $user->avatar_path !== null
            && in_array($user->avatar_mime_type, ['image/jpeg', 'image/png', 'image/webp'], true),
            404,
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->avatar_path), 404);

        return response()->file($disk->path($user->avatar_path), [
            'Content-Type' => $user->avatar_mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
