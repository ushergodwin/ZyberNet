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

        // Generate and save token if not already present
        if (!$user->api_token) {
            $tokenResult = $user->createToken(
                'ui-token',
                ['*'],
                now()->addMonths(2)
            );

            $user->api_token = $tokenResult->plainTextToken;
            $user->save();
        }

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
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only('name', 'email'));

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
