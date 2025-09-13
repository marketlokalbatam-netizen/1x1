<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Exception\FirebaseException;

class SetupController extends Controller
{
    private $firestore;
    private $database;
    
    public function __construct()
    {
        // Initialize Firebase
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
            \Log::error('Firebase initialization failed in SetupController: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize Firebase with sample data
     */
    public function initializeData(Request $request): JsonResponse
    {
        try {
            if (!$this->firestore || !$this->database) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase tidak tersedia'
                ], 500);
            }
            
            $storeId = $request->query('store_id', '09108898-fc1f-49b9-a92b-229f99615ae8');
            
            // Setup sample store
            $this->setupStore($storeId);
            
            // Setup sample products
            $productIds = $this->setupProducts($storeId);
            
            // Setup sample customers
            $customerIds = $this->setupCustomers($storeId);
            
            // Setup sample transaction
            $transactionId = $this->setupSampleTransaction($storeId, $productIds, $customerIds);
            
            return response()->json([
                'success' => true,
                'message' => 'Firebase berhasil diinisialisasi dengan data sample',
                'data' => [
                    'store_id' => $storeId,
                    'products_created' => count($productIds),
                    'customers_created' => count($customerIds),
                    'sample_transaction' => $transactionId,
                    'next_steps' => [
                        'Access products: /api/products?store_id=' . $storeId,
                        'Access customers: /api/customers?store_id=' . $storeId,
                        'Access transactions: /api/transactions?store_id=' . $storeId,
                        'Create transaction: POST /api/transactions',
                        'Test POS: Open the frontend application'
                    ]
                ]
            ], 200);
            
        } catch (FirebaseException $e) {
            \Log::error('Firebase error in setup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error Firebase: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            \Log::error('General error in setup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function setupStore($storeId): void
    {
        $storeData = [
            'id' => $storeId,
            'name' => 'MarketLokal Store',
            'address' => 'Jl. Merdeka No. 123, Jakarta Selatan',
            'phone' => '+62812-3456-7890',
            'email' => 'store@marketlokal.com',
            'owner_name' => 'Admin',
            'is_active' => true,
            'settings' => [
                'currency' => 'IDR',
                'tax_rate' => 0,
                'receipt_footer' => 'Terima kasih telah berbelanja!'
            ],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];
        
        $this->firestore
            ->collection('stores')
            ->document($storeId)
            ->set($storeData);
    }
    
    private function setupProducts($storeId): array
    {
        $products = [
            [
                'store_id' => $storeId,
                'name' => 'Indomie Goreng',
                'sku' => 'IDG-001',
                'price' => 3500,
                'price_sell' => 3500,
                'stock' => 150,
                'unit' => 'pcs',
                'category' => 'Makanan Instan',
                'description' => 'Mie instan rasa ayam bawang',
                'image_url' => '',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'store_id' => $storeId,
                'name' => 'Aqua 600ml',
                'sku' => 'AQU-600',
                'price' => 2500,
                'price_sell' => 2500,
                'stock' => 200,
                'unit' => 'botol',
                'category' => 'Minuman',
                'description' => 'Air mineral dalam kemasan botol',
                'image_url' => '',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'store_id' => $storeId,
                'name' => 'Teh Botol Sosro',
                'sku' => 'TBS-350',
                'price' => 3000,
                'price_sell' => 3000,
                'stock' => 75,
                'unit' => 'botol',
                'category' => 'Minuman',
                'description' => 'Teh manis dalam kemasan botol',
                'image_url' => '',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'store_id' => $storeId,
                'name' => 'Beras Premium 5kg',
                'sku' => 'BRS-5KG',
                'price' => 65000,
                'price_sell' => 65000,
                'stock' => 30,
                'unit' => 'kg',
                'category' => 'Sembako',
                'description' => 'Beras premium kualitas terbaik',
                'image_url' => '',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'store_id' => $storeId,
                'name' => 'Minyak Goreng 1L',
                'sku' => 'MG-1L',
                'price' => 15000,
                'price_sell' => 15000,
                'stock' => 50,
                'unit' => 'botol',
                'category' => 'Sembako',
                'description' => 'Minyak goreng kemasan 1 liter',
                'image_url' => '',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ]
        ];
        
        $productIds = [];
        foreach ($products as $product) {
            $docRef = $this->firestore
                ->collection('products')
                ->add($product);
            $productIds[] = $docRef->id();
        }
        
        return $productIds;
    }
    
    private function setupCustomers($storeId): array
    {
        $customers = [
            [
                'store_id' => $storeId,
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'email' => 'budi@example.com',
                'address' => 'Jl. Mawar No. 123, Jakarta Selatan',
                'total_receivables' => 0,
                'total_spent' => 0,
                'total_transactions' => 0,
                'notes' => 'Customer setia, selalu bayar tepat waktu',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ],
            [
                'store_id' => $storeId,
                'name' => 'Siti Aminah',
                'phone' => '081987654321',
                'email' => '',
                'address' => 'Jl. Melati No. 45, Jakarta Pusat',
                'total_receivables' => 0,
                'total_spent' => 0,
                'total_transactions' => 0,
                'notes' => '',
                'is_active' => true,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ]
        ];
        
        $customerIds = [];
        foreach ($customers as $customer) {
            $docRef = $this->firestore
                ->collection('customers')
                ->add($customer);
            $customerIds[] = $docRef->id();
        }
        
        return $customerIds;
    }
    
    private function setupSampleTransaction($storeId, $productIds, $customerIds): string
    {
        $transactionNumber = 'TRX' . now()->format('Ymd') . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        $transactionData = [
            'store_id' => $storeId,
            'transaction_number' => $transactionNumber,
            'customer_name' => 'Walk-in Customer',
            'items' => [
                [
                    'product_id' => $productIds[0] ?? 'prod-001',
                    'product_name' => 'Indomie Goreng',
                    'quantity' => 2,
                    'price' => 3500,
                    'total' => 7000,
                    'unit' => 'pcs'
                ],
                [
                    'product_id' => $productIds[1] ?? 'prod-002',
                    'product_name' => 'Aqua 600ml',
                    'quantity' => 1,
                    'price' => 2500,
                    'total' => 2500,
                    'unit' => 'botol'
                ]
            ],
            'subtotal' => 9500,
            'discount' => 0,
            'tax' => 0,
            'total_amount' => 9500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'notes' => 'Sample transaction',
            'cashier_id' => 'admin',
            'cashier_name' => 'Admin',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];
        
        $docRef = $this->firestore
            ->collection('transactions')
            ->add($transactionData);
        
        return $docRef->id();
    }
    
    /**
     * Check Firebase connection status
     */
    public function checkStatus(): JsonResponse
    {
        try {
            if (!$this->firestore || !$this->database) {
                return response()->json([
                    'firebase_connected' => false,
                    'message' => 'Firebase tidak tersedia'
                ], 500);
            }
            
            // Test Firestore connection
            $testDoc = $this->firestore
                ->collection('_test')
                ->document('connection_test');
            
            $testDoc->set([
                'test' => true,
                'timestamp' => now()->toISOString()
            ]);
            
            // Test Realtime Database connection  
            $this->database
                ->getReference('_test/connection')
                ->set([
                    'test' => true,
                    'timestamp' => now()->toISOString()
                ]);
            
            return response()->json([
                'firebase_connected' => true,
                'firestore_status' => 'connected',
                'realtime_db_status' => 'connected',
                'message' => 'Firebase siap digunakan',
                'setup_endpoint' => '/api/setup/initialize'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'firebase_connected' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}