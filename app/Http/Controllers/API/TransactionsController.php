<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Exception\FirebaseException;

class TransactionsController extends Controller
{
    private $firestore;
    private $database; // Realtime Database for live sync
    
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
            \Log::error('Firebase initialization failed in TransactionsController: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all transactions for a store
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $storeId = $request->query('store_id');
            $limit = $request->query('limit', 50);
            
            if (!$storeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store ID diperlukan'
                ], 400);
            }
            
            // Get transactions from local database (primary source)
            $transactions = Transaction::byStore($storeId)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->limit((int) $limit)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'store_id' => $transaction->store_id,
                        'transaction_number' => $transaction->transaction_number,
                        'customer_id' => $transaction->customer_id,
                        'customer_name' => $transaction->customer_name,
                        'total_amount' => (float) $transaction->total_amount,
                        'payment_method' => $transaction->payment_method,
                        'payment_method_label' => $transaction->payment_method_label,
                        'payment_status' => $transaction->payment_status,
                        'payment_status_label' => $transaction->payment_status_label,
                        'items' => $transaction->items,
                        'items_count' => $transaction->items_count,
                        'total_items_quantity' => $transaction->total_items_quantity,
                        'subtotal' => (float) $transaction->subtotal,
                        'discount' => (float) $transaction->discount,
                        'tax' => (float) $transaction->tax,
                        'formatted_total' => $transaction->formatted_total,
                        'notes' => $transaction->notes,
                        'cashier_id' => $transaction->cashier_id,
                        'cashier_name' => $transaction->cashier_name,
                        'created_at' => $transaction->created_at->toISOString(),
                        'updated_at' => $transaction->updated_at->toISOString()
                    ];
                });
            
            // If no transactions, seed with sample data
            if ($transactions->isEmpty()) {
                $this->seedSampleTransaction($storeId);
                // Reload transactions after seeding
                $transactions = Transaction::byStore($storeId)
                    ->with('customer')
                    ->orderBy('created_at', 'desc')
                    ->limit((int) $limit)
                    ->get()
                    ->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'store_id' => $transaction->store_id,
                            'transaction_number' => $transaction->transaction_number,
                            'customer_id' => $transaction->customer_id,
                            'customer_name' => $transaction->customer_name,
                            'total_amount' => (float) $transaction->total_amount,
                            'payment_method' => $transaction->payment_method,
                            'payment_method_label' => $transaction->payment_method_label,
                            'payment_status' => $transaction->payment_status,
                            'payment_status_label' => $transaction->payment_status_label,
                            'items' => $transaction->items,
                            'items_count' => $transaction->items_count,
                            'total_items_quantity' => $transaction->total_items_quantity,
                            'subtotal' => (float) $transaction->subtotal,
                            'discount' => (float) $transaction->discount,
                            'tax' => (float) $transaction->tax,
                            'formatted_total' => $transaction->formatted_total,
                            'notes' => $transaction->notes,
                            'cashier_id' => $transaction->cashier_id,
                            'cashier_name' => $transaction->cashier_name,
                            'created_at' => $transaction->created_at->toISOString(),
                            'updated_at' => $transaction->updated_at->toISOString()
                        ];
                    });
            }
            
            // Sync to Firebase in background if available
            $this->syncToFirebaseAsync($transactions->toArray());
            
            return response()->json([
                'success' => true,
                'data' => $transactions,
                'count' => count($transactions),
                'source' => 'local_database',
                'firebase_sync' => $this->firestore ? 'enabled' : 'disabled'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error in transactions index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new transaction (POS checkout)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,qris,receivables',
            'customer_name' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'cashier_id' => 'required|string',
            'cashier_name' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Generate transaction number
            $transactionNumber = Transaction::generateTransactionNumber();
            
            // Calculate totals
            $subtotal = 0;
            $processedItems = [];
            
            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['price'];
                $subtotal += $itemTotal;
                
                // Verify product exists and has enough stock
                $product = Product::find($item['product_id']);
                if (!$product) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Produk dengan ID {$item['product_id']} tidak ditemukan"
                    ], 400);
                }
                
                if ($product->stock < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}"
                    ], 400);
                }
                
                $processedItems[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'quantity' => (int) $item['quantity'],
                    'price' => (float) $item['price'],
                    'total' => $itemTotal,
                    'unit' => $product->unit
                ];
            }
            
            $discount = (float) ($request->discount ?? 0);
            $tax = (float) ($request->tax ?? 0);
            $totalAmount = $subtotal - $discount + $tax;
            
            // Find or create customer if specified
            $customerId = null;
            if ($request->customer_name && $request->customer_name !== 'Walk-in Customer') {
                $customer = Customer::where('store_id', $request->store_id)
                    ->where('name', $request->customer_name)
                    ->first();
                    
                if ($customer) {
                    $customerId = $customer->id;
                }
            }
            
            // Create transaction in local database
            $transaction = Transaction::create([
                'store_id' => $request->store_id,
                'transaction_number' => $transactionNumber,
                'customer_id' => $customerId,
                'customer_name' => $request->customer_name ?? 'Walk-in Customer',
                'items' => $processedItems,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total_amount' => $totalAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'receivables' ? 'pending' : 'paid',
                'notes' => $request->notes ?? '',
                'cashier_id' => $request->cashier_id,
                'cashier_name' => $request->cashier_name
            ]);
            
            // Update product stock
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $product->decreaseStock($item['quantity']);
            }
            
            // Update customer statistics and handle receivables
            if ($customerId) {
                $customer = Customer::find($customerId);
                
                // Add to receivables if payment method is receivables
                if ($request->payment_method === 'receivables') {
                    $customer->addReceivables($totalAmount, "Transaksi #{$transactionNumber}");
                }
                
                // Update transaction stats only for paid transactions
                if ($transaction->payment_status === 'paid') {
                    $customer->updateTransactionStats();
                }
            }
            
            DB::commit();
            
            // Sync to Firebase in background if available
            $this->syncTransactionToFirebase($transaction);
            
            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diproses',
                'data' => [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'customer_name' => $transaction->customer_name,
                    'total_amount' => (float) $transaction->total_amount,
                    'payment_method' => $transaction->payment_method,
                    'payment_status' => $transaction->payment_status,
                    'items' => $transaction->items,
                    'items_count' => $transaction->items_count,
                    'formatted_total' => $transaction->formatted_total,
                    'created_at' => $transaction->created_at->toISOString(),
                    'updated_at' => $transaction->updated_at->toISOString()
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating transaction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get specific transaction
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
            
            $transaction = $this->firestore
                ->collection('transactions')
                ->document($id)
                ->snapshot();
            
            if (!$transaction->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }
            
            $data = $transaction->data();
            $transactionData = [
                'id' => $id,
                'store_id' => $data['store_id'] ?? '',
                'transaction_number' => $data['transaction_number'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'items' => $data['items'] ?? [],
                'subtotal' => $data['subtotal'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 0,
                'payment_method' => $data['payment_method'] ?? '',
                'payment_status' => $data['payment_status'] ?? '',
                'notes' => $data['notes'] ?? '',
                'cashier_id' => $data['cashier_id'] ?? '',
                'cashier_name' => $data['cashier_name'] ?? '',
                'created_at' => $data['created_at'] ?? '',
                'updated_at' => $data['updated_at'] ?? ''
            ];
            
            return response()->json([
                'success' => true,
                'data' => $transactionData
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate transaction number
     */
    private function generateTransactionNumber(): string
    {
        $prefix = 'TRX';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        
        return $prefix . $date . $random;
    }
    
    /**
     * Update product stock after transaction
     */
    private function updateProductStock(array $items): void
    {
        try {
            foreach ($items as $item) {
                $productRef = $this->firestore
                    ->collection('products')
                    ->document($item['product_id']);
                
                $product = $productRef->snapshot();
                if ($product->exists()) {
                    $productData = $product->data();
                    $currentStock = $productData['stock'] ?? 0;
                    $newStock = max(0, $currentStock - $item['quantity']);
                    
                    $productRef->update([
                        'stock' => $newStock,
                        'updated_at' => now()->toISOString()
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error updating product stock: ' . $e->getMessage());
        }
    }
    
    /**
     * Send real-time update via Firebase Realtime Database
     */
    private function sendRealTimeUpdate(string $storeId, array $data): void
    {
        try {
            $this->database
                ->getReference('stores/' . $storeId . '/live_updates/' . now()->timestamp)
                ->set($data);
        } catch (\Exception $e) {
            \Log::error('Error sending real-time update: ' . $e->getMessage());
        }
    }
    
    /**
     * Get mock transactions data
     */
    /**
     * Seed sample transaction for immediate system usability
     */
    private function seedSampleTransaction($storeId): void
    {
        // Get first few products for sample transaction
        $products = Product::byStore($storeId)->active()->limit(2)->get();
        
        if ($products->count() < 2) {
            return; // Need at least 2 products for sample transaction
        }
        
        $transactionNumber = Transaction::generateTransactionNumber();
        
        $items = [
            [
                'product_id' => $products[0]->id,
                'product_name' => $products[0]->name,
                'quantity' => 2,
                'price' => (float) $products[0]->price_sell,
                'total' => 2 * (float) $products[0]->price_sell,
                'unit' => $products[0]->unit
            ],
            [
                'product_id' => $products[1]->id,
                'product_name' => $products[1]->name,
                'quantity' => 1,
                'price' => (float) $products[1]->price_sell,
                'total' => 1 * (float) $products[1]->price_sell,
                'unit' => $products[1]->unit
            ]
        ];
        
        $subtotal = collect($items)->sum('total');
        
        Transaction::create([
            'store_id' => $storeId,
            'transaction_number' => $transactionNumber,
            'customer_name' => 'Walk-in Customer',
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'total_amount' => $subtotal,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'notes' => 'Sample transaction untuk demo sistem',
            'cashier_id' => 'admin',
            'cashier_name' => 'Admin POS'
        ]);
    }
    
    /**
     * Sync transactions to Firebase in background (non-blocking)
     */
    private function syncToFirebaseAsync(array $transactions): void
    {
        if (!$this->firestore) {
            return;
        }
        
        try {
            foreach ($transactions as $transaction) {
                $this->firestore
                    ->collection('transactions')
                    ->add($transaction);
            }
        } catch (\Exception $e) {
            \Log::warning('Firebase transactions sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync single transaction to Firebase
     */
    private function syncTransactionToFirebase(Transaction $transaction): void
    {
        if (!$this->firestore) {
            return;
        }
        
        try {
            $transactionData = [
                'store_id' => $transaction->store_id,
                'transaction_number' => $transaction->transaction_number,
                'customer_id' => $transaction->customer_id,
                'customer_name' => $transaction->customer_name,
                'items' => $transaction->items,
                'subtotal' => (float) $transaction->subtotal,
                'discount' => (float) $transaction->discount,
                'tax' => (float) $transaction->tax,
                'total_amount' => (float) $transaction->total_amount,
                'payment_method' => $transaction->payment_method,
                'payment_status' => $transaction->payment_status,
                'notes' => $transaction->notes,
                'cashier_id' => $transaction->cashier_id,
                'cashier_name' => $transaction->cashier_name,
                'created_at' => $transaction->created_at->toISOString(),
                'updated_at' => $transaction->updated_at->toISOString()
            ];
            
            $docRef = $this->firestore
                ->collection('transactions')
                ->add($transactionData);
            
            // Store Firebase ID for future sync
            $transaction->update(['firebase_id' => $docRef->id()]);
            
        } catch (\Exception $e) {
            \Log::warning('Firebase transaction sync failed: ' . $e->getMessage());
        }
    }
}