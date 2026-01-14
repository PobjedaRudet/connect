<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        Log::info('API login attempt', $request->all());
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['message' => 'Neispravni kredencijali.'], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $abilities = $this->abilitiesForFunkcija($user->funkcija);

        $token = $user->createToken($data['device_name'], $abilities);
        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'funkcija' => $user->funkcija,
                'isadmin' => (bool) $user->isadmin,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Odjavljeni ste.']);
    }

    public function me(Request $request)
    {
        $u = $request->user();
        return response()->json([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'funkcija' => $u->funkcija,
            'isadmin' => (bool) $u->isadmin,
        ]);
    }

    private function abilitiesForFunkcija(?string $funkcija): array
    {
        $base = ['orders:view', 'approvals:view'];
        $approver = ['approvals:approve', 'approvals:reject', 'approvals:approve-one-up'];
        $isApprover = in_array($funkcija, ['Šef Komercijale','Direktor Komercijale','Direktor Proizvodnje','Zamjenik1','Zamjenik2','Šef Operative'], true);
        return $isApprover ? array_merge($base, $approver) : $base;
    }
}
