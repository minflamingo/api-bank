<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

use Illuminate\Support\Facades\Hash;
use App\Models\XLog;

class AccountSettingsAccount extends Controller
{
    // (A) Hiển thị trang Account Settings
    public function index()
    {
        $user = Auth::user();
        return view('content.pages.pages-account-settings-account', compact('user'));
    }

    // (B) Upload ảnh qua AJAX, và XÓA ảnh cũ nếu có
    public function store(Request $request)
    {
        // Validate file ảnh
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Lấy user đang đăng nhập
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy user đăng nhập'
            ], 422);
        }

        // 1) Xóa file ảnh cũ (nếu có). Avatar Google là URL ngoài, không nằm trong disk public.
        if (!empty($user->avatar) && !$this->isExternalAvatar($user->avatar)) {
            // user->avatar = "avatars/xxx.jpg" => xóa file trong storage/app/public/avatars
            Storage::disk('public')->delete($user->avatar);
        }

        // 2) Lưu file ảnh mới
        $file = $request->file('avatar');
        // Lưu vào thư mục "avatars" (trong storage/app/public)
        $path = $file->store('avatars', 'public'); // kết quả "avatars/xxx.jpg"

        // 3) Tạo link đầy đủ để hiển thị cho người dùng
        $fullUrl = asset('storage/' . $path);
		$user = Auth::user();
		if ($user) {
			$user->avatar = $path;
			$user->save();
		}


        // 4) Trả về JSON cho code Javascript (fetch)
        return response()->json([
            'success'    => true,
            'image_path' => $fullUrl, // dùng gán <img src="...">
            'file_path'  => $path     // "avatars/xxx.jpg", để lưu DB
        ]);
    }

    // (C) Xoá file ảnh qua AJAX (nút “Xóa”)
    public function destroy(Request $request)
    {
        $filePath = $request->input('file_path');
        if (!$filePath) {
            return response()->json([
                'success' => false,
                'message' => 'No file path provided'
            ], 422);
        }

        // Xoá file khỏi disk 'public' nếu đây là avatar upload nội bộ.
        if (!$this->isExternalAvatar($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
		// Cập nhật avatar = '' cho user hiện tại (nếu cần)
		$user = Auth::user();
		if ($user) {
			$user->avatar = '';
			$user->save();
		}


        return response()->json(['success' => true]);
    }

    private function isExternalAvatar(?string $avatar): bool
    {
        return is_string($avatar) && (bool) preg_match('/^https?:\/\//i', trim($avatar));
    }

    // (D) Cập nhật thông tin user (bao gồm avatar)
    public function update(Request $request)
    {
        // Validate các trường form
        $request->validate([
            'displayName' => 'nullable|string|max:50',
            'phone'       => 'nullable|numeric',
        ]);

        // Lấy user
        $user = Auth::user();
        if (!$user) {
            return redirect()->back()->withErrors(['Không tìm thấy user đăng nhập.']);
        }

        // Cập nhật các field
        $user->display_name = $request->input('displayName');
        $user->phone        = $request->input('phone');


        // Lưu thay đổi
        $user->save();

        return redirect()
            ->back()
            ->with('success', 'Cập nhật thông tin tài khoản thành công!');
    }
	
	public function security()
    {
        $user = Auth::user();
        return view('content.pages.security', compact('user'));
    }
	
	public function updatePassword(Request $request)
    {


        // Validate form
        // Yêu cầu name="current_password", name="password" và "password_confirmation"
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:8',
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu cũ',
            'password.required'         => 'Vui lòng nhập mật khẩu mới',
            'password.confirmed'        => 'Mật khẩu xác nhận không khớp',
            'password.min'              => 'Mật khẩu phải ít nhất :min ký tự',
        ]);

        // Lấy user đang đăng nhập
        $user = Auth::user();
        if (!$user) {
            return redirect()->back()->withErrors(['Không tìm thấy user đăng nhập.']);
        }

        // Kiểm tra mật khẩu cũ
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['Mật khẩu cũ không đúng.']);
        }

        // Cập nhật mật khẩu mới
        $user->password = Hash::make($request->password);
        $user->save();

        // Ghi log sự kiện thành công
        XLog::create([
            'ip'   => $request->ip(),
            'user' => Auth::id() ?? 0,
            'log'  => 'Đổi mật khẩu thành công',
            'notes'=> 'Người dùng ID=' . (Auth::id() ?? 0) . ' đã đổi mật khẩu.'
        ]);

        return redirect()->back()->with('success', 'Đổi mật khẩu thành công!');
    }
}
