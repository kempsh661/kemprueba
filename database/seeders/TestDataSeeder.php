<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\FixedCost;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener el usuario por defecto
        $user = User::first();
        
        if (!$user) {
            $this->command->error('No hay usuarios en la base de datos. Ejecuta primero el DatabaseSeeder.');
            return;
        }

        $this->command->info('Creando datos de prueba para el usuario: ' . $user->name);

        // 1. Crear categorías
        $categories = [
            ['name' => 'Bebidas', 'code' => 'BEB', 'user_id' => $user->id],
            ['name' => 'Comidas', 'code' => 'COM', 'user_id' => $user->id],
            ['name' => 'Snacks', 'code' => 'SNK', 'user_id' => $user->id],
            ['name' => 'Postres', 'code' => 'PST', 'user_id' => $user->id],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['code' => $categoryData['code'], 'user_id' => $user->id],
                $categoryData
            );
        }

        $this->command->info('Categorías creadas');

        // 2. Crear productos
        $bebidasCategory = Category::where('code', 'BEB')->where('user_id', $user->id)->first();
        $comidasCategory = Category::where('code', 'COM')->where('user_id', $user->id)->first();
        $snacksCategory = Category::where('code', 'SNK')->where('user_id', $user->id)->first();
        $postresCategory = Category::where('code', 'PST')->where('user_id', $user->id)->first();

        $products = [
            // PRODUCTOS PRINCIPALES - COMIDAS (estos absorben la mayoría de costos fijos)
            [
                'name' => 'Pollo Pechuga Asada',
                'code' => 'PPA',
                'price' => 18000,
                'cost' => 9000,
                'category_id' => $comidasCategory->id,
                'user_id' => $user->id,
                'stock' => 50,
                'is_main_product' => true, // Producto principal
                'cost_weight' => 5, // Mayor peso en distribución de costos
            ],
            [
                'name' => 'Pollo Pernil Completo',
                'code' => 'PPC',
                'price' => 22000,
                'cost' => 11000,
                'category_id' => $comidasCategory->id,
                'user_id' => $user->id,
                'stock' => 30,
                'is_main_product' => true,
                'cost_weight' => 5,
            ],
            [
                'name' => 'Pollo Completo',
                'code' => 'PCMP',
                'price' => 35000,
                'cost' => 18000,
                'category_id' => $comidasCategory->id,
                'user_id' => $user->id,
                'stock' => 20,
                'is_main_product' => true,
                'cost_weight' => 8, // Peso más alto por ser más caro
            ],
            [
                'name' => 'Alitas House (10 unidades)',
                'code' => 'AH10',
                'price' => 25000,
                'cost' => 12000,
                'category_id' => $comidasCategory->id,
                'user_id' => $user->id,
                'stock' => 40,
                'is_main_product' => true,
                'cost_weight' => 6,
            ],
            [
                'name' => 'Combo Mini Nuggets',
                'code' => 'CMN',
                'price' => 15000,
                'cost' => 7500,
                'category_id' => $comidasCategory->id,
                'user_id' => $user->id,
                'stock' => 60,
                'is_main_product' => true,
                'cost_weight' => 4,
            ],
            [
                'name' => 'Hamburguesa Clásica',
                'code' => 'HBC',
                'price' => 12000,
                'cost' => 6000,
                'category_id' => $comidasCategory->id,
                'user_id' => $user->id,
                'stock' => 50,
                'is_main_product' => true,
                'cost_weight' => 3,
            ],
            
            // PRODUCTOS SECUNDARIOS - BEBIDAS (menor peso en costos fijos)
            [
                'name' => 'Coca Cola 600ml',
                'code' => 'CC600',
                'price' => 3500,
                'cost' => 2000,
                'category_id' => $bebidasCategory->id,
                'user_id' => $user->id,
                'stock' => 100,
                'is_main_product' => false,
                'cost_weight' => 1,
            ],
            [
                'name' => 'Agua Cristal 500ml',
                'code' => 'AC500',
                'price' => 2000,
                'cost' => 1200,
                'category_id' => $bebidasCategory->id,
                'user_id' => $user->id,
                'stock' => 150,
                'is_main_product' => false,
                'cost_weight' => 0.5,
            ],
            [
                'name' => 'Jugo Hit Mango 1L',
                'code' => 'JHM1L',
                'price' => 4500,
                'cost' => 2800,
                'category_id' => $bebidasCategory->id,
                'user_id' => $user->id,
                'stock' => 80,
                'is_main_product' => false,
                'cost_weight' => 1,
            ],
            
            // PRODUCTOS SECUNDARIOS - SNACKS Y POSTRES
            [
                'name' => 'Papas Margarita',
                'code' => 'PM',
                'price' => 2500,
                'cost' => 1500,
                'category_id' => $snacksCategory->id,
                'user_id' => $user->id,
                'stock' => 200,
                'is_main_product' => false,
                'cost_weight' => 0.5,
            ],
            [
                'name' => 'Helado Artesanal',
                'code' => 'HA',
                'price' => 5000,
                'cost' => 2500,
                'category_id' => $postresCategory->id,
                'user_id' => $user->id,
                'stock' => 50,
                'is_main_product' => false,
                'cost_weight' => 1,
            ],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['code' => $productData['code'], 'user_id' => $user->id],
                $productData
            );
        }

        $this->command->info('Productos creados');

        // 3. Crear clientes
        $customers = [
            [
                'name' => 'María González',
                'document' => '12345678',
                'email' => 'maria@email.com',
                'phone' => '3001234567',
                'user_id' => $user->id,
            ],
            [
                'name' => 'Carlos Ramírez',
                'document' => '87654321',
                'email' => 'carlos@email.com',
                'phone' => '3009876543',
                'user_id' => $user->id,
            ],
            [
                'name' => 'Ana Martínez',
                'document' => '11223344',
                'email' => 'ana@email.com',
                'phone' => '3005566778',
                'user_id' => $user->id,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(
                ['document' => $customerData['document'], 'user_id' => $user->id],
                $customerData
            );
        }

        $this->command->info('Clientes creados');

        // 4. Crear costos fijos
        $fixedCosts = [
            [
                'name' => 'Arriendo Local',
                'amount' => 800000,
                'user_id' => $user->id,
                'is_active' => true,
                'is_paid' => true,
            ],
            [
                'name' => 'Servicios Públicos',
                'amount' => 200000,
                'user_id' => $user->id,
                'is_active' => true,
                'is_paid' => true,
            ],
            [
                'name' => 'Salarios',
                'amount' => 1200000,
                'user_id' => $user->id,
                'is_active' => true,
                'is_paid' => true,
            ],
            [
                'name' => 'Internet y Teléfono',
                'amount' => 120000,
                'user_id' => $user->id,
                'is_active' => true,
                'is_paid' => true,
            ],
        ];

        foreach ($fixedCosts as $costData) {
            FixedCost::firstOrCreate(
                ['name' => $costData['name'], 'user_id' => $user->id],
                $costData
            );
        }

        $this->command->info('Costos fijos creados');

        // 5. Crear ventas del mes pasado
        $lastMonth = Carbon::now()->subMonth();
        $products = Product::where('user_id', $user->id)->get();
        $customers = Customer::where('user_id', $user->id)->get();

        // Crear 50 ventas distribuidas en el mes pasado
        for ($i = 1; $i <= 50; $i++) {
            // Fecha aleatoria del mes pasado
            $saleDate = $lastMonth->copy()->addDays(rand(0, $lastMonth->daysInMonth - 1));
            
            // Cliente aleatorio
            $customer = $customers->random();
            
            // 1-3 productos por venta
            $saleProducts = $products->random(rand(1, 3));
            
            $items = [];
            $total = 0;
            
            foreach ($saleProducts as $product) {
                $quantity = rand(1, 5);
                $price = $product->price;
                $subtotal = $quantity * $price;
                
                $items[] = [
                    'productId' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price
                ];
                
                $total += $subtotal;
            }
            
            // Tipo de pago aleatorio
            $paymentMethods = ['cash', 'card', 'transfer', 'credit'];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
            
            $saleData = [
                'user_id' => $user->id,
                'customer_name' => $customer->name,
                'customer_document' => $customer->document,
                'customer_phone' => $customer->phone,
                'customer_email' => $customer->email,
                'total' => $total,
                'subtotal' => $total,
                'tax' => 0,
                'discount' => 0,
                'payment_method' => $paymentMethod,
                'items' => json_encode($items),
                'status' => 'COMPLETED',
                'sale_date' => $saleDate,
                'created_at' => $saleDate,
                'updated_at' => $saleDate,
            ];
            
            // Si es a crédito, agregar saldo pendiente
            if ($paymentMethod === 'credit') {
                $saleData['remaining_balance'] = $total;
            } else {
                $saleData['cash_received'] = $total;
            }
            
            Sale::create($saleData);
        }

        $this->command->info('50 ventas del mes pasado creadas');

        // 6. Crear algunas ventas del mes actual para comparar
        $currentMonth = Carbon::now();
        
        for ($i = 1; $i <= 15; $i++) {
            // Fecha aleatoria del mes actual
            $saleDate = $currentMonth->copy()->addDays(rand(0, min(15, $currentMonth->daysInMonth - 1)));
            
            // Cliente aleatorio
            $customer = $customers->random();
            
            // 1-2 productos por venta
            $saleProducts = $products->random(rand(1, 2));
            
            $items = [];
            $total = 0;
            
            foreach ($saleProducts as $product) {
                $quantity = rand(1, 3);
                $price = $product->price;
                $subtotal = $quantity * $price;
                
                $items[] = [
                    'productId' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price
                ];
                
                $total += $subtotal;
            }
            
            $saleData = [
                'user_id' => $user->id,
                'customer_name' => $customer->name,
                'customer_document' => $customer->document,
                'customer_phone' => $customer->phone,
                'customer_email' => $customer->email,
                'total' => $total,
                'subtotal' => $total,
                'tax' => 0,
                'discount' => 0,
                'payment_method' => 'cash',
                'cash_received' => $total,
                'items' => json_encode($items),
                'status' => 'COMPLETED',
                'sale_date' => $saleDate,
                'created_at' => $saleDate,
                'updated_at' => $saleDate,
            ];
            
            Sale::create($saleData);
        }

        $this->command->info('15 ventas del mes actual creadas');
        $this->command->info('🎉 Datos de prueba creados exitosamente');
        $this->command->info('📊 Resumen:');
        $this->command->info('   - 4 categorías');
        $this->command->info('   - 6 productos');
        $this->command->info('   - 3 clientes');
        $this->command->info('   - 4 costos fijos');
        $this->command->info('   - 50 ventas del mes pasado');
        $this->command->info('   - 15 ventas del mes actual');
        $this->command->info('');
        $this->command->info('🔑 Ahora puedes usar el módulo de costos de productos para calcular automáticamente');
    }
}