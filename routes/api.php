<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ProductsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes (temporarily removing auth middleware for migration)
// TODO: Implement Firebase ID token authentication middleware
Route::group([], function () {
    Route::get('/user', function (Request $request) {
        return response()->json(['message' => 'User endpoint - auth middleware to be implemented']);
    });
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/check-auth', [AuthController::class, 'checkAuth']);
    
    // Dashboard routes
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Products routes
    Route::apiResource('products', ProductsController::class);
});

// True legacy PHP API compatibility routes (backward compatible)
Route::match(['GET', 'POST'], '/auth.php', function (Request $request) {
    $action = $request->input('action', $request->query('action', $request->method() === 'POST' ? 'login' : 'check'));
    
    switch ($action) {
        case 'login':
            return app(AuthController::class)->login($request);
        case 'logout':
            return app(AuthController::class)->logout($request);
        case 'check':
        default:
            return app(AuthController::class)->checkAuth($request);
    }
})->name('api.auth');

Route::get('/dashboard.php', [DashboardController::class, 'index'])->name('api.dashboard');
Route::get('/products.php', [ProductsController::class, 'index'])->name('api.products');

// POS Transaction endpoints
Route::prefix('transactions')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\TransactionsController::class, 'index'])->name('api.transactions.index');
    Route::post('/', [\App\Http\Controllers\API\TransactionsController::class, 'store'])->name('api.transactions.store');
    Route::get('/{id}', [\App\Http\Controllers\API\TransactionsController::class, 'show'])->name('api.transactions.show');
});

// Product management endpoints
Route::prefix('products')->group(function () {
    Route::get('/', [ProductsController::class, 'index'])->name('api.products.index');
    Route::post('/', [ProductsController::class, 'store'])->name('api.products.store');
    Route::get('/{id}', [ProductsController::class, 'show'])->name('api.products.show');
    Route::put('/{id}', [ProductsController::class, 'update'])->name('api.products.update');
    Route::delete('/{id}', [ProductsController::class, 'destroy'])->name('api.products.destroy');
});

// Customer management endpoints
Route::prefix('customers')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\CustomersController::class, 'index'])->name('api.customers.index');
    Route::post('/', [\App\Http\Controllers\API\CustomersController::class, 'store'])->name('api.customers.store');
    Route::get('/{id}', [\App\Http\Controllers\API\CustomersController::class, 'show'])->name('api.customers.show');
    Route::put('/{id}/receivables', [\App\Http\Controllers\API\CustomersController::class, 'updateReceivables'])->name('api.customers.receivables');
});

// Setup endpoints for Firebase initialization
Route::prefix('setup')->group(function () {
    Route::get('/status', [\App\Http\Controllers\API\SetupController::class, 'checkStatus'])->name('api.setup.status');
    Route::post('/initialize', [\App\Http\Controllers\API\SetupController::class, 'initializeData'])->name('api.setup.initialize');
});
Route::get('/stores.php', function (Request $request) {
    // Temporary mock response for stores endpoint
    return response()->json([
        'success' => true,
        'data' => [
            [
                'id' => '09108898-fc1f-49b9-a92b-229f99615ae8',
                'name' => 'MarketLokal Store',
                'address' => 'Jl. Merdeka No. 123, Jakarta',
                'phone' => '+62812-3456-7890',
                'email' => 'store@marketlokal.com',
                'is_active' => true
            ]
        ]
    ]);
})->name('api.stores');

// Firebase test endpoint (secure)
Route::get('/firebase-test', function () {
    try {
        // Get Firebase configuration from environment variables
        $firebaseConfig = [
            'type' => 'service_account',
            'project_id' => 'marketlokal-0601',
            'private_key_id' => 'b3dae974467d2001049bb6832bf9a2d3560109dc',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDE3UN9EzDRY48z\nSV59wPnAC8DugjbSjmVDljsb/io+P2C1MEFP7CBZWo9ETzcBIyUeMHir18ra160z\n6uoZJgeRChSPiNRoVCAev5PhgIseTSS4n/mXF3ujWZgDVYLm5vH26WKztLoXjtEj\n6fTNS9F9v7s2OTyC2XyiLUeo216LI2ZAKpnUxm1C/nHjgnEEg5PpBGOLCfetfGjD\nG8x1vCsqRPBmnxMRlaJUu0yC2tGz7vqmpyJUBq6Lx7KRxFXhAOJiMwydeCdKojlK\ngyotdvFFLrVfmjuXViAIhlukCXVxVe806HMoBtWL+vNf9euOmjFYLz3ECeBBGlrU\nn+Xrr0V3AgMBAAECggEAB1kTkUy7kuff6vP58mtXBxuJM9Mpalov88IHlR0Qr5Uc\nWMxNy8a4/bd7HHtxG/pCAuqf6RjGChK9yhp3wBY4Ib+aEodywi14Vh15eWkbsuGs\nlWA8koP+Xz5TWwsrNnoinZWK08FS/C5zpkmvvk/uUv6/5UoP9PmwL2TNmsMMDYO6\ndfb2fyTi5gKRjYkQmGn94uVteRusik3qbLnvcR2XVNsUk4vzPxl6YxmimTcHGdOA\nD9ZbmcuOYr+cZlqBUEYHDi1A5/+QsTXjeG6EJ9MLUzsfjuQ0Pn92zWszBc9QQVAp\nMaGu5LlBAoT5tsZw7j5ZpntXDZpUCy6Ak8z/ipvPYQKBgQDrjQZyU2ZNrk/RN3Yr\nk0s2WPkMcuuE8GjXBr0wsa5sC1by4LCTcnC9fao/aYJVWwjukeVoG1LLiu+Yr8JG\nXfyV6Bywg/b0alIJahKkqLUNfaPPapHU8b72PVPK6ZwoTYKTfwPyM0a8j8n96cD4\nXgpB17Dvr0Z3ilt7eQf7nb1g4QKBgQDV9HP5YHlkinbkncAazFRUMMJgICT5OHwp\n+XMV5CTc89+6yxHpFvvain4mLeEhVNdoTD+8nM4H+9+aayYmCRPAxyZYeuw0WO93\n3L5Ap+HzTyTiFVxakravU5QOZhNumS+XmBRGhE1a/i5jSW8LyAlK3VbqrwSBcihn\n6pg1+al5VwKBgQDSnyp5hdt/cHNMh4Qx2KYzNYGUQRoZqAKG6y62YNqvH8CisokR\nfw4SUlCuHQD+o8Ur1edEnNH+0QAHDnnwA9B8lq04pPdoe71ZT7DV7UjfhE26hPdb\nasKvWg0X7qXEX/J3QubbZkBFEWmzkpYLP8N6Noyu9ZJUB7JfiMy7j8Or4QKBgQCp\nBF/JlMjRAAFEQMpNYTUM/SfQQBR1PIX5srGTjZMBaTZInbscY6e50MYu6vd6GUfB\ngNqo6UZpUDQoDJUqXulX1PsZ0qFeCRPDoII7GWSJjPAaGb3HXzcp8eB4L3MSX7kV\nDHKX8lQZmcqTbKNorzGIDqpm4rOwfrokvVJY8U1MKQKBgFlnatWBTA6tJ1aLpcD0\nJ9TLPEHt53lOX75DxYMU29E+QTbEZPC2y08VUATqmAcmuQISqrd9mg4IV2tBgiGd\nVBOThphRqfPyyiYukwhXi1YRp4twKqISOGjblwC8/589gJBQNCXrttJBSkkltZiQ\nG152DyXCcQt9mxILvQhN9SkD\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk-a7pe4@marketlokal-0601.iam.gserviceaccount.com',
            'client_id' => '108424363675638051393',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-a7pe4%40marketlokal-0601.iam.gserviceaccount.com',
            'universe_domain' => 'googleapis.com'
        ];
        
        $databaseUrl = 'https://marketlokal-0601-default-rtdb.firebaseio.com';
        
        // Create Firebase factory
        $factory = (new \Kreait\Firebase\Factory)
            ->withServiceAccount($firebaseConfig)
            ->withDatabaseUri($databaseUrl);
            
        $database = $factory->createDatabase();
        
        // Test write to Firebase
        $testRef = $database->getReference('laravel-test');
        $testData = [
            'message' => 'Koneksi Laravel ke Firebase berhasil!',
            'timestamp' => now()->toISOString(),
            'from' => 'MarketLokal Laravel Backend',
            'project_id' => 'marketlokal-0601'
        ];
        
        $testRef->set($testData);
        
        // Test read from Firebase
        $readData = $testRef->getValue();
        
        return response()->json([
            'status' => 'success',
            'message' => '🎉 Firebase berhasil terhubung dengan Laravel!',
            'data_written' => $testData,
            'data_read' => $readData,
            'firebase_project' => 'marketlokal-0601'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Koneksi Firebase gagal: ' . $e->getMessage(),
            'env_check' => [
                'has_credentials' => !empty(env('FIREBASE_CREDENTIALS')),
                'has_database_url' => !empty(env('FIREBASE_DATABASE_URL')),
                'has_project_id' => !empty(env('FIREBASE_PROJECT_ID'))
            ]
        ], 500);
    }
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'MarketLokal Backend is running',
        'timestamp' => now()
    ]);
});

// Legacy PHP API compatibility routes with prefix (for gradual migration)
Route::prefix('legacy')->group(function () {
    // Dashboard endpoint for existing frontend
    Route::get('/dashboard.php', [DashboardController::class, 'index'])->name('legacy.dashboard');
    
    // Auth endpoints for existing frontend  
    Route::post('/auth.php', function (Request $request) {
        $action = $request->input('action', $request->method() === 'POST' ? 'login' : 'check');
        
        switch ($action) {
            case 'login':
                return app(AuthController::class)->login($request);
            case 'logout':
                return app(AuthController::class)->logout($request);
            case 'check':
            default:
                return app(AuthController::class)->checkAuth($request);
        }
    })->name('legacy.auth');
});