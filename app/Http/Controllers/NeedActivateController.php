<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NeedActivateController extends Controller
{
    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('v2');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
