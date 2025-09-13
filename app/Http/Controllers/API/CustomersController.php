<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Exception\FirebaseException;

class CustomersController extends Controller
{
    private $firestore;
    private $database;
    
    public function __construct()
    {
        // Initialize Firebase with environment credentials
        try {
            $credentials = env('FIREBASE_CREDENTIALS');
            $databaseUrl = env('FIREBASE_DATABASE_URL');
            
            if ($credentials && $databaseUrl) {
                $firebaseConfig = json_decode($credentials, true);
                $factory = (new Factory)
                    ->withServiceAccount($firebaseConfig)
                    ->withDatabaseUri($databaseUrl);
                
                $this->firestore = $factory->createFirestore();
                $this->database = $factory->createDatabase();
            }
        } catch (\Exception $e) {
            \Log::error('Firebase initialization failed in CustomersController: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all customers for a store
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $storeId = $request->query('store_id');
            
            if (!$storeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store ID diperlukan'
                ], 400);
            }
            
            // Get customers from local database (primary source)
            $customers = Customer::byStore($storeId)
                ->active()
                ->orderBy('name')
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'store_id' => $customer->store_id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'email' => $customer->email,
                        'address' => $customer->address,
                        'total_receivables' => (float) $customer->total_receivables,
                        'total_spent' => (float) $customer->total_spent,
                        'total_transactions' => $customer->total_transactions,
                        'has_receivables' => $customer->has_receivables,
                        'formatted_receivables' => $customer->formatted_receivables,
                        'formatted_total_spent' => $customer->formatted_total_spent,
                        'notes' => $customer->notes,
                        'is_active' => $customer->is_active,
                        'created_at' => $customer->created_at->toISOString(),
                        'updated_at' => $customer->updated_at->toISOString()
                    ];
                });
            
            // If no customers, seed with sample data
            if ($customers->isEmpty()) {
                $this->seedSampleCustomers($storeId);
                // Reload customers after seeding
                $customers = Customer::byStore($storeId)
                    ->active()
                    ->orderBy('name')
                    ->get()
                    ->map(function ($customer) {
                        return [
                            'id' => $customer->id,
                            'store_id' => $customer->store_id,
                            'name' => $customer->name,
                            'phone' => $customer->phone,
                            'email' => $customer->email,
                            'address' => $customer->address,
                            'total_receivables' => (float) $customer->total_receivables,
                            'total_spent' => (float) $customer->total_spent,
                            'total_transactions' => $customer->total_transactions,
                            'has_receivables' => $customer->has_receivables,
                            'formatted_receivables' => $customer->formatted_receivables,
                            'formatted_total_spent' => $customer->formatted_total_spent,
                            'notes' => $customer->notes,
                            'is_active' => $customer->is_active,
                            'created_at' => $customer->created_at->toISOString(),
                            'updated_at' => $customer->updated_at->toISOString()
                        ];
                    });
            }
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'count' => count($customers),
                'source' => 'local_database',
                'firebase_sync' => $this->firestore ? 'enabled' : 'disabled'
            ], 200);
            
        } catch (FirebaseException $e) {
            \Log::error('Firebase error in customers index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data customer: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            \Log::error('General error in customers index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new customer
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|string',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Create customer in local database (primary storage)
            $customer = Customer::create([
                'store_id' => $request->store_id,
                'name' => $request->name,
                'phone' => $request->phone ?? '',
                'email' => $request->email ?? '',
                'address' => $request->address ?? '',
                'notes' => $request->notes ?? '',
                'is_active' => true
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil ditambahkan',
                'data' => [
                    'id' => $customer->id,
                    'store_id' => $customer->store_id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                    'total_receivables' => (float) $customer->total_receivables,
                    'total_spent' => (float) $customer->total_spent,
                    'total_transactions' => $customer->total_transactions,
                    'notes' => $customer->notes,
                    'is_active' => $customer->is_active,
                    'created_at' => $customer->created_at->toISOString(),
                    'updated_at' => $customer->updated_at->toISOString()
                ]
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Error creating customer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get specific customer
     */
    public function show($id): JsonResponse
    {
        try {
            if (!$this->firestore) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase tidak tersedia'
                ], 500);
            }
            
            $customer = $this->firestore
                ->collection('customers')
                ->document($id)
                ->snapshot();
            
            if (!$customer->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak ditemukan'
                ], 404);
            }
            
            $data = $customer->data();
            $customerData = [
                'id' => $id,
                'store_id' => $data['store_id'] ?? '',
                'name' => $data['name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'address' => $data['address'] ?? '',
                'total_receivables' => $data['total_receivables'] ?? 0,
                'total_spent' => $data['total_spent'] ?? 0,
                'total_transactions' => $data['total_transactions'] ?? 0,
                'notes' => $data['notes'] ?? '',
                'is_active' => $data['is_active'] ?? true,
                'created_at' => $data['created_at'] ?? '',
                'updated_at' => $data['updated_at'] ?? ''
            ];
            
            return response()->json([
                'success' => true,
                'data' => $customerData
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update customer receivables
     */
    public function updateReceivables(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'type' => 'required|in:add,subtract',
            'transaction_id' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            if (!$this->firestore) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase tidak tersedia'
                ], 500);
            }
            
            $customerRef = $this->firestore
                ->collection('customers')
                ->document($id);
            
            $customer = $customerRef->snapshot();
            if (!$customer->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak ditemukan'
                ], 404);
            }
            
            $customerData = $customer->data();
            $currentReceivables = $customerData['total_receivables'] ?? 0;
            
            $amount = (float) $request->amount;
            $newReceivables = $request->type === 'add' 
                ? $currentReceivables + $amount 
                : max(0, $currentReceivables - $amount);
            
            $customerRef->update([
                'total_receivables' => $newReceivables,
                'updated_at' => now()->toISOString()
            ]);
            
            // Log receivables transaction
            $this->logReceivablesTransaction($id, [
                'type' => $request->type,
                'amount' => $amount,
                'previous_balance' => $currentReceivables,
                'new_balance' => $newReceivables,
                'transaction_id' => $request->transaction_id ?? '',
                'notes' => $request->notes ?? '',
                'created_at' => now()->toISOString()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Receivables berhasil diupdate',
                'data' => [
                    'previous_receivables' => $currentReceivables,
                    'new_receivables' => $newReceivables,
                    'amount_changed' => $amount
                ]
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Log receivables transaction
     */
    private function logReceivablesTransaction($customerId, array $data): void
    {
        try {
            $this->firestore
                ->collection('receivables_logs')
                ->add(array_merge($data, ['customer_id' => $customerId]));
        } catch (\Exception $e) {
            \Log::error('Error logging receivables transaction: ' . $e->getMessage());
        }
    }
    
    /**
     * Get mock customers data
     */
    /**
     * Seed sample customers for immediate system usability
     */
    private function seedSampleCustomers($storeId): void
    {
        $sampleCustomers = [
            [
                'store_id' => $storeId,
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'email' => 'budi@example.com',
                'address' => 'Jl. Mawar No. 123, Jakarta Selatan',
                'notes' => 'Customer setia, selalu bayar tepat waktu',
                'is_active' => true
            ],
            [
                'store_id' => $storeId,
                'name' => 'Siti Aminah',
                'phone' => '081987654321',
                'email' => '',
                'address' => 'Jl. Melati No. 45, Jakarta Pusat',
                'notes' => '',
                'is_active' => true
            ],
            [
                'store_id' => $storeId,
                'name' => 'Ahmad Rahman',
                'phone' => '082345678901',
                'email' => 'ahmad@example.com',
                'address' => 'Jl. Kenanga No. 78, Jakarta Timur',
                'notes' => 'Sering beli dalam jumlah besar',
                'is_active' => true
            ]
        ];
        
        foreach ($sampleCustomers as $customerData) {
            Customer::create($customerData);
        }
    }
}