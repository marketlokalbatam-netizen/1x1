<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Exception\FirebaseException;

class DashboardController extends Controller
{
    private $firestore;
    
    public function __construct()
    {
        // TODO: Firebase integration will be added after basic migration is working
        // $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
        // $this->firestore = $factory->createFirestore();
    }
    
    /**
     * Get dashboard data
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
            
            // Temporary mock data while we complete migration
            $dashboardData = [
                'today_sales' => 850000,
                'today_transactions' => 23,
                'total_products' => 145,
                'total_customers' => 67,
                'recent_transactions' => [
                    [
                        'id' => '1',
                        'customer_name' => 'Walk-in Customer',
                        'total_amount' => 45000,
                        'payment_method' => 'cash',
                        'created_at' => now()->toISOString(),
                        'items_count' => 3
                    ],
                    [
                        'id' => '2',
                        'customer_name' => 'Budi Santoso',
                        'total_amount' => 125000,
                        'payment_method' => 'digital',
                        'created_at' => now()->subHours(2)->toISOString(),
                        'items_count' => 7
                    ]
                ],
                'top_products' => [
                    [
                        'name' => 'Indomie Goreng',
                        'sold_count' => 25,
                        'revenue' => 500000
                    ],
                    [
                        'name' => 'Aqua 600ml', 
                        'sold_count' => 18,
                        'revenue' => 360000
                    ]
                ],
                'sales_chart' => [
                    ['date' => now()->subDays(6)->format('Y-m-d'), 'sales' => 650000],
                    ['date' => now()->subDays(5)->format('Y-m-d'), 'sales' => 720000],
                    ['date' => now()->subDays(4)->format('Y-m-d'), 'sales' => 580000],
                    ['date' => now()->subDays(3)->format('Y-m-d'), 'sales' => 890000],
                    ['date' => now()->subDays(2)->format('Y-m-d'), 'sales' => 950000],
                    ['date' => now()->subDays(1)->format('Y-m-d'), 'sales' => 760000],
                    ['date' => now()->format('Y-m-d'), 'sales' => 850000]
                ],
                'store_info' => [
                    'id' => $storeId,
                    'name' => 'MarketLokal Store',
                    'address' => 'Jl. Merdeka No. 123, Jakarta',
                    'phone' => '+62812-3456-7890',
                    'email' => 'store@marketlokal.com'
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $dashboardData
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function getTodaySales($storeId)
    {
        try {
            // Get today's transactions from Firestore
            $today = now()->startOfDay();
            $tomorrow = now()->addDay()->startOfDay();
            
            $transactions = $this->firestore
                ->collection('transactions')
                ->where('store_id', '==', $storeId)
                ->where('created_at', '>=', $today->toISOString())
                ->where('created_at', '<', $tomorrow->toISOString())
                ->documents();
            
            $totalSales = 0;
            foreach ($transactions as $transaction) {
                $data = $transaction->data();
                $totalSales += $data['total_amount'] ?? 0;
            }
            
            return $totalSales;
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTodayTransactions($storeId)
    {
        try {
            $today = now()->startOfDay();
            $tomorrow = now()->addDay()->startOfDay();
            
            $transactions = $this->firestore
                ->collection('transactions')
                ->where('store_id', '==', $storeId)
                ->where('created_at', '>=', $today->toISOString())
                ->where('created_at', '<', $tomorrow->toISOString())
                ->documents();
            
            return iterator_count($transactions);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTotalProducts($storeId)
    {
        try {
            $products = $this->firestore
                ->collection('products')
                ->where('store_id', '==', $storeId)
                ->documents();
            
            return iterator_count($products);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTotalCustomers($storeId)
    {
        try {
            $customers = $this->firestore
                ->collection('customers')
                ->where('store_id', '==', $storeId)
                ->documents();
            
            return iterator_count($customers);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getRecentTransactions($storeId)
    {
        try {
            $transactions = $this->firestore
                ->collection('transactions')
                ->where('store_id', '==', $storeId)
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->documents();
            
            $recentTransactions = [];
            foreach ($transactions as $transaction) {
                $data = $transaction->data();
                $recentTransactions[] = [
                    'id' => $transaction->id(),
                    'customer_name' => $data['customer_name'] ?? 'Walk-in Customer',
                    'total_amount' => $data['total_amount'] ?? 0,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'created_at' => $data['created_at'] ?? now()->toISOString(),
                    'items_count' => count($data['items'] ?? [])
                ];
            }
            
            return $recentTransactions;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getTopProducts($storeId)
    {
        try {
            // This would require aggregation - for now return sample data
            return [
                [
                    'name' => 'Produk Popular 1',
                    'sold_count' => 25,
                    'revenue' => 500000
                ],
                [
                    'name' => 'Produk Popular 2', 
                    'sold_count' => 18,
                    'revenue' => 360000
                ]
            ];
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getSalesChart($storeId)
    {
        try {
            // Generate 7 days sales data
            $salesData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $salesData[] = [
                    'date' => $date->format('Y-m-d'),
                    'sales' => rand(100000, 1000000) // Mock data for now
                ];
            }
            
            return $salesData;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getStoreInfo($storeId)
    {
        try {
            $store = $this->firestore
                ->collection('stores')
                ->document($storeId)
                ->snapshot();
            
            if ($store->exists()) {
                $data = $store->data();
                return [
                    'id' => $storeId,
                    'name' => $data['name'] ?? 'MarketLokal Store',
                    'address' => $data['address'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'email' => $data['email'] ?? ''
                ];
            }
            
            return [
                'id' => $storeId,
                'name' => 'MarketLokal Store',
                'address' => '',
                'phone' => '',
                'email' => ''
            ];
            
        } catch (Exception $e) {
            return [
                'id' => $storeId,
                'name' => 'MarketLokal Store',
                'address' => '',
                'phone' => '',
                'email' => ''
            ];
        }
    }
}