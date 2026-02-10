<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('student');
        
        $userData = [
            'id' => $user->id,
            'last_name' => $user->last_name,
            'first_name' => $user->first_name,
            'middle_initial' => $user->middle_initial,
            'email' => $user->email,
            'birthday' => $user->birthday?->format('Y-m-d'),
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role,
            'status' => $user->status,
            'profile_picture' => $user->profile_picture, // ✅ Add this
        ];
        
        if ($user->isStudent() && $user->student) {
            $userData['account_id'] = $user->student->account_id;
            $userData['student_id'] = $user->student_id;
            $userData['course'] = $user->course;
            $userData['year_level'] = $user->year_level;
        }
        
        if ($user->hasRole(['admin', 'accounting'])) {
            $userData['faculty'] = $user->faculty;
        }

        return Inertia::render('settings/Profile', [
            'user' => $userData,
            'mustVerifyEmail' => method_exists($user, 'hasVerifiedEmail')
                ? !$user->hasVerifiedEmail()
                : false,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $userUpdateData = [
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'email' => $validated['email'],
                'birthday' => $validated['birthday'] ?? $user->birthday,
                'phone' => $validated['phone'] ?? $user->phone,
                'address' => $validated['address'] ?? $user->address,
            ];

            if ($user->isStudent()) {
                $userUpdateData['student_id'] = $validated['student_id'] ?? $user->student_id;
                $userUpdateData['course'] = $validated['course'];
                $userUpdateData['year_level'] = $validated['year_level'];
                
                if (isset($validated['status']) && $request->user()->isAdmin()) {
                    $userUpdateData['status'] = $validated['status'];
                }
            }

            if ($user->hasRole(['admin', 'accounting'])) {
                $userUpdateData['faculty'] = $validated['faculty'] ?? $user->faculty;
            }

            $user->fill($userUpdateData);
            $user->save();

            if ($user->isStudent() && $user->student) {
                $statusMap = [
                    'active' => 'enrolled',
                    'graduated' => 'graduated',
                    'dropped' => 'inactive',
                ];

                $studentData = [
                    'last_name' => $validated['last_name'],
                    'first_name' => $validated['first_name'],
                    'middle_initial' => $validated['middle_initial'] ?? null,
                    'student_id' => $validated['student_id'] ?? $user->student->student_id,
                    'email' => $validated['email'],
                    'birthday' => $validated['birthday'] ?? $user->student->birthday,
                    'phone' => $validated['phone'] ?? $user->student->phone,
                    'address' => $validated['address'] ?? $user->student->address,
                    'course' => $validated['course'],
                    'year_level' => $validated['year_level'],
                ];

                if (isset($validated['status']) && $request->user()->isAdmin()) {
                    $studentData['status'] = $statusMap[$validated['status']] ?? $user->student->status;
                }

                $user->student->update($studentData);
            }

            DB::commit();

            return Redirect::route('profile.edit')
                ->with('success', 'Profile updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()
                ->withErrors(['error' => 'Failed to update profile.'])
                ->withInput();
        }
    }

    /**
     * ✅ FIX: Update profile picture
     */
    public function updatePicture(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048', // ✅ Add allowed types
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Delete old file if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $path = $request->file('profile_picture')->store('profile-pictures', 'public');

            $user->update(['profile_picture' => $path]);

            DB::commit();

            return Redirect::back()->with('success', 'Profile picture updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Profile picture update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()->withErrors(['profile_picture' => 'Failed to update profile picture.']);
        }
    }

    /**
     * ✅ FIX: Remove profile picture
     */
    public function removePicture(Request $request): RedirectResponse
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
                $user->update(['profile_picture' => null]);
            }

            DB::commit();

            return Redirect::back()->with('success', 'Profile picture removed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Profile picture removal failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()->withErrors(['error' => 'Failed to remove profile picture.']);
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        if ($user->isStudent() && $user->student && $user->student->account_id) {
            return Redirect::back()->withErrors([
                'error' => 'Student accounts cannot be deleted. Please contact administration.'
            ]);
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        DB::beginTransaction();
        try {
            $user->delete();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            DB::commit();

            return Redirect::to('/');

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->withErrors(['error' => 'Failed to delete account.']);
        }
    }
}