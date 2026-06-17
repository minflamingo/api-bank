<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Kiểm tra role của người dùng hiện tại.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$roles   Danh sách role hợp lệ (sử dụng spread operator)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        
        // (1) Nếu người dùng chưa đăng nhập, chuyển hướng đến trang login
        if (!$user) {
            return redirect()->route('auth-login-basic');
        }

        // (2) Kiểm tra nếu $user->role có nằm trong danh sách ...$roles => cho phép đi tiếp
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // (3) Nếu không phù hợp role => điều hướng theo các trường hợp cụ thể
        switch ($user->role) {
            case 9:
                // Người dùng chưa kích hoạt => chuyển hướng đến trang 'need-activate'
                return redirect()->route('need-activate');
            //case 3:
                // Người dùng cần contact => chuyển hướng đến trang 'need-contact'
                //return redirect()->route('need-contact');
            case 1:
                // Admin => chuyển hướng về route 'v2'
                return redirect()->route('v2');
            default:
                // Role khác => chuyển hướng đến 'need-contact' (hoặc trả về 403)
                return redirect()->route('v2');
        }
    }
}
