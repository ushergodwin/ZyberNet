<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Handle user login (session-based + token generation).
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials'])->onlyInput('email');
        }

        $user = Auth::user();

        // Delete old tokens
        $user->tokens()->where('name', 'ui-token')->delete();

        // Always generate a new token
        $tokenResult = $user->createToken(
            'ui-token',
            ['*'],
            now()->addMonths(2)
        );

        $user->api_token = $tokenResult->plainTextToken;
        $user->save();

        return redirect()->intended('/dashboard');
    }

    /**
     * Handle user logout and token cleanup.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Delete token from DB and revoke token
            $user->api_token = null;
            $user->save();

            $user->tokens()->where('name', 'ui-token')->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Optional: Register a new user and assign token.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        $tokenResult = $user->createToken(
            'ui-token',
            ['*'],
            now()->addMonths(2)
        );

        $user->api_token = $tokenResult->plainTextToken;
        $user->save();

        return response()->json([
            'message' => 'User registered successfully',
            'user'    => $user,
            'token'   => $user->api_token,
        ]);
    }

    // update user profile
    public function updateUser(Request $request, $id)
    {

        $user = User::where('id', $id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        if ($request->filled('password')) {
            // get the old password
            $oldPassword = $user->password;
            // Hash the new password
            $newPassword = Hash::make($request->input('password'));
            // Check if the new password is different from the old password
            if (Hash::check($request->input('password'), $oldPassword)) {
                return response()->json(['error' => 'New password cannot be the same as the old password'], 422);
            }
            $user->password = $newPassword;
        }
        $user->save();
        // Regenerate token if email changed
        if ($request->input('email') !== $user->getOriginal('email')) {
            $tokenResult = $user->createToken(
                'ui-token',
                ['*'],
                now()->addMonths(2)
            );
            $user->api_token = $tokenResult->plainTextToken;
            $user->save();
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    // deleteUser
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Ensure the user is not the currently authenticated user
        if (Auth::id() === $user->id) {
            return response()->json(['error' => 'You cannot delete your own account'], 202);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // restoreUser
    public function restoreUser($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        // Ensure the user is not the currently authenticated user
        if (Auth::id() === $user->id) {
            return response()->json(['error' => 'You cannot restore your own account'], 202);
        }

        $user->restore();

        return response()->json(['message' => 'User restored successfully']);
    }
}