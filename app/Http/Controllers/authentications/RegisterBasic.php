<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;  // Để bắn event Registered
use Illuminate\Validation\ValidationException;
use App\Models\User;

class RegisterBasic extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('reg_math_a') || !$request->session()->has('reg_math_b')) {
            $request->session()->put('reg_math_a', random_int(2, 19));
            $request->session()->put('reg_math_b', random_int(2, 19));
        }

        return view('content.authentications.auth', [
            'mode' => 'register',
        ]);
    }

    public function store(Request $request)
    {
        $rawUsername   = $request->username;
        $asciiUsername = Str::ascii($rawUsername);
        $asciiUsername = strtolower($asciiUsername);
        $asciiUsername = preg_replace('/[^a-z0-9]/', '', $asciiUsername);

        $request->merge(['username' => $asciiUsername]);

        $a = (int) $request->session()->get('reg_math_a', 0);
        $b = (int) $request->session()->get('reg_math_b', 0);
        $expected = $a + $b;

        $request->validate([
            'username'     => 'required|min:3|max:255|alpha_num|unique:users,name',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:6|max:255|confirmed',
            'terms'        => 'accepted',
            'human_answer' => ['required', 'integer', function ($attribute, $value, $fail) use ($expected) {
                if ((int) $value !== (int) $expected) {
                    $fail('Câu trả lời xác thực không đúng. Vui lòng thử lại.');
                }
            }],
        ], [
            'terms.accepted' => 'Bạn cần đồng ý điều khoản để đăng ký.',
            'human_answer.required' => 'Vui lòng trả lời câu hỏi xác thực.',
            'human_answer.integer' => 'Câu trả lời xác thực phải là số.',
        ]);

        if ($a === 0 && $b === 0) {
            throw ValidationException::withMessages([
                'human_answer' => 'Phiên xác thực đã hết hạn. Vui lòng tải lại trang và thử lại.',
            ]);
        }

        $token = md5(Str::random(32) . microtime(true));
        $user = User::create([
            'name'     => $request->username,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => 9, // Giả sử 9 là trạng thái unactive
            'ip'       => $request->ip(),
            'device'   => Str::limit((string) $request->userAgent(), 250, ''),
            'level' => 0,
            'amount' => 0,
            'total_paid' => 0,
            'banned' => 0,
            'token' => $token,
        ]);

        event(new Registered($user));
        $request->session()->forget(['reg_math_a', 'reg_math_b']);

        Auth::login($user);

        return redirect('/')
               ->with('success', 'Đăng ký thành công! Hãy kiểm tra email để kích hoạt tài khoản.');
    }
}
