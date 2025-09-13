<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Exception\FirebaseException;

class ProductsController extends Controller
{
    private $firestore;
    
    public function __construct()
    {
        // Initialize Firebase with environment credentials
        try {
            $credentials = env('FIREBASE_CREDENTIALS');
            if ($credentials) {
                $firebaseConfig = json_decode($credentials, true);
                $factory = (new Factory)
                    ->withServiceAccount($firebaseConfig);
                $this->firestore = $factory->createFirestore();
            }
        } catch (\Exception $e) {
            \Log::error('Firebase initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all products for a store
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
            
            // Get products from local database (primary source)
            $products = Product::byStore($storeId)
                ->active()
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'store_id' => $product->store_id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => (float) $product->price,
                        'price_sell' => (float) $product->price_sell,
                        'stock' => $product->stock,
                        'unit' => $product->unit,
                        'category' => $product->category,
                        'description' => $product->description,
                        'image_url' => $product->image_url,
                        'is_active' => $product->is_active,
                        'is_low_stock' => $product->is_low_stock,
                        'is_out_of_stock' => $product->is_out_of_stock,
                        'formatted_price' => $product->formatted_price,
                        'formatted_price_sell' => $product->formatted_price_sell,
                        'created_at' => $product->created_at->toISOString(),
                        'updated_at' => $product->updated_at->toISOString()
                    ];
                });
            
            // If no products in database, seed with sample data
            if ($products->isEmpty()) {
                $this->seedSampleProducts($storeId);
                // Reload products after seeding
                $products = Product::byStore($storeId)
                    ->active()
                    ->orderBy('name')
                    ->get()
                    ->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'store_id' => $product->store_id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'price' => (float) $product->price,
                            'price_sell' => (float) $product->price_sell,
                            'stock' => $product->stock,
                            'unit' => $product->unit,
                            'category' => $product->category,
                            'description' => $product->description,
                            'image_url' => $product->image_url,
                            'is_active' => $product->is_active,
                            'is_low_stock' => $product->is_low_stock,
                            'is_out_of_stock' => $product->is_out_of_stock,
                            'formatted_price' => $product->formatted_price,
                            'formatted_price_sell' => $product->formatted_price_sell,
                            'created_at' => $product->created_at->toISOString(),
                            'updated_at' => $product->updated_at->toISOString()
                        ];
                    });
            }
            
            // Sync to Firebase in background if available
            $this->syncToFirebaseAsync($products->toArray());
            
            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => count($products),
                'source' => 'local_database',
                'firebase_sync' => $this->firestore ? 'enabled' : 'disabled'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error in products index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Seed sample products for immediate system usability
     */
    private function seedSampleProducts($storeId): void
    {
        $sampleProducts = [
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
                'is_active' => true
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
                'is_active' => true
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
                'is_active' => true
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
                'is_active' => true
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
                'is_active' => true
            ]
        ];
        
        foreach ($sampleProducts as $productData) {
            Product::create($productData);
        }
    }
    
    /**
     * Sync products to Firebase in background (non-blocking)
     */
    private function syncToFirebaseAsync(array $products): void
    {
        if (!$this->firestore) {
            return;
        }
        
        try {
            // In a real production system, this would be done via a job queue
            // For now, we'll do a basic sync
            foreach ($products as $product) {
                $this->firestore
                    ->collection('products')
                    ->add($product);
            }
        } catch (\Exception $e) {
            \Log::warning('Firebase sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync single product to Firebase
     */
    private function syncProductToFirebase(Product $product): void
    {
        if (!$this->firestore) {
            return;
        }
        
        try {
            $productData = [
                'store_id' => $product->store_id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'price_sell' => (float) $product->price_sell,
                'stock' => $product->stock,
                'unit' => $product->unit,
                'category' => $product->category,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'is_active' => $product->is_active,
                'created_at' => $product->created_at->toISOString(),
                'updated_at' => $product->updated_at->toISOString()
            ];
            
            $docRef = $this->firestore
                ->collection('products')
                ->add($productData);
            
            // Store Firebase ID for future sync
            $product->update(['firebase_id' => $docRef->id()]);
            
        } catch (\Exception $e) {
            \Log::warning('Firebase product sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Create new product
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|string',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Create product in local database (primary storage)
            $product = Product::create([
                'store_id' => $request->store_id,
                'name' => $request->name,
                'sku' => $request->sku,
                'price' => (float) $request->price,
                'price_sell' => (float) ($request->price_sell ?? $request->price),
                'stock' => (int) $request->stock,
                'unit' => $request->unit ?? 'pcs',
                'category' => $request->category ?? '',
                'description' => $request->description ?? '',
                'image_url' => $request->image_url ?? '',
                'is_active' => true
            ]);
            
            // Sync to Firebase in background if available
            $this->syncProductToFirebase($product);
            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan',
                'data' => [
                    'id' => $product->id,
                    'store_id' => $product->store_id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => (float) $product->price,
                    'price_sell' => (float) $product->price_sell,
                    'stock' => $product->stock,
                    'unit' => $product->unit,
                    'category' => $product->category,
                    'description' => $product->description,
                    'image_url' => $product->image_url,
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at->toISOString(),
                    'updated_at' => $product->updated_at->toISOString()
                ]
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Error creating product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get specific product
     */
    public function show($id): JsonResponse
    {
        try {
            $product = $this->firestore
                ->collection('products')
                ->document($id)
                ->snapshot();
            
            if (!$product->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }
            
            $data = $product->data();
            $productData = [
                'id' => $id,
                'name' => $data['name'] ?? '',
                'sku' => $data['sku'] ?? '',
                'price' => $data['price'] ?? 0,
                'stock' => $data['stock'] ?? 0,
                'category' => $data['category'] ?? '',
                'description' => $data['description'] ?? '',
                'image_url' => $data['image_url'] ?? '',
                'is_active' => $data['is_active'] ?? true,
                'created_at' => $data['created_at'] ?? '',
                'updated_at' => $data['updated_at'] ?? ''
            ];
            
            return response()->json([
                'success' => true,
                'data' => $productData
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update product
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|max:100',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'is_active' => 'sometimes|boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $product = $this->firestore
                ->collection('products')
                ->document($id)
                ->snapshot();
            
            if (!$product->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }
            
            $updateData = array_filter([
                'name' => $request->name,
                'sku' => $request->sku,
                'price' => $request->has('price') ? (float) $request->price : null,
                'stock' => $request->has('stock') ? (int) $request->stock : null,
                'category' => $request->category,
                'description' => $request->description,
                'image_url' => $request->image_url,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : null,
                'updated_at' => now()->toISOString()
            ], function ($value) {
                return $value !== null;
            });
            
            $this->firestore
                ->collection('products')
                ->document($id)
                ->update($updateData);
            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diupdate'
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete product
     */
    public function destroy($id): JsonResponse
    {
        try {
            $product = $this->firestore
                ->collection('products')
                ->document($id)
                ->snapshot();
            
            if (!$product->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }
            
            $this->firestore
                ->collection('products')
                ->document($id)
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ], 200);
            
        } catch (FirebaseException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}