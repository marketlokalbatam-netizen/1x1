<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\FirebaseException;

class AuthController extends Controller
{
    private $auth;
    
    public function __construct()
    {
        // TODO: Firebase integration will be completed after basic migration
        // $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
        // $this->auth = $factory->createAuth();
    }
    
    /**
     * User login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Temporary mock response during migration - will be replaced with Firebase auth
            return response()->json([
                'success' => false,
                'message' => 'Login not available during migration - Firebase auth pending',
                'migration_status' => 'Laravel API migration completed, Firebase auth integration pending'
            ], 501);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * User registration
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Create user in Firebase Auth
            $userProperties = [
                'email' => $request->email,
                'password' => $request->password,
                'displayName' => $request->name,
                'emailVerified' => false,
            ];
            
            $createdUser = $this->auth->createUser($userProperties);
            
            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'data' => [
                    'user' => [
                        'uid' => $createdUser->uid,
                        'email' => $createdUser->email,
                        'name' => $createdUser->displayName,
                        'email_verified' => $createdUser->emailVerified,
                    ]
                ]
            ], 201);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat registrasi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check authentication status
     */
    public function checkAuth(Request $request): JsonResponse
    {
        try {
            // Temporary mock response during migration - will be replaced with Firebase auth
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated - Firebase integration pending',
                'migration_status' => 'Laravel API migration completed, Firebase auth pending'
            ], 401);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication check failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * User logout (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $idToken = $request->bearerToken();
            
            if ($idToken) {
                $verifiedIdToken = $this->auth->verifyIdToken($idToken);
                $uid = $verifiedIdToken->claims()->get('sub');
                
                // Revoke refresh tokens for user
                $this->auth->revokeRefreshTokens($uid);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ], 200);
        }
    }
}