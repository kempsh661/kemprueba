<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\CreditPayment;
use Carbon\Carbon;

class ConsultarClienteCredito extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cliente:consultar {documento : Número de documento del cliente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta los pagos de crédito y compras realizadas por un cliente específico';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $documento = $this->argument('documento');
        
        $this->info("=== CONSULTA DE CLIENTE DOCUMENTO: {$documento} ===");
        $this->newLine();
        
        // Buscar el cliente por documento
        $cliente = Customer::where('document', $documento)->first();
        
        if (!$cliente) {
            $this->error("No se encontró un cliente con el documento: {$documento}");
            return 1;
        }
        
        // Mostrar información del cliente
        $this->info("INFORMACIÓN DEL CLIENTE:");
        $this->line("Nombre: {$cliente->name}");
        $this->line("Documento: {$cliente->document}");
        $this->line("Email: " . ($cliente->email ?? 'No registrado'));
        $this->line("Teléfono: " . ($cliente->phone ?? 'No registrado'));
        $this->line("Dirección: " . ($cliente->address ?? 'No registrada'));
        $this->line("Saldo de Crédito: $" . number_format($cliente->credit_balance, 2));
        $this->newLine();
        
        // Consultar compras realizadas
        $compras = Sale::where('customer_document', $documento)
                      ->orWhere('customer_id', $cliente->id)
                      ->orderBy('sale_date', 'desc')
                      ->get();
        
        if ($compras->count() > 0) {
            $this->info("=== COMPRAS REALIZADAS ===");
            $this->newLine();
            
            $totalCompras = 0;
            foreach ($compras as $compra) {
                $fecha = $compra->sale_date ? $compra->sale_date->format('d/m/Y H:i:s') : 'Sin fecha';
                $this->line("ID Venta: {$compra->id}");
                $this->line("Fecha: {$fecha}");
                $this->line("Total: $" . number_format($compra->total, 2));
                $this->line("Método de Pago: {$compra->payment_method}");
                $this->line("Saldo Pendiente: $" . number_format($compra->remaining_balance ?? 0, 2));
                $this->line("Estado: " . ($compra->status ?? 'Activa'));
                
                // Mostrar items si están disponibles
                if (!empty($compra->items)) {
                    $this->line("Productos:");
                    foreach ($compra->items as $item) {
                        $nombre = $item['name'] ?? $item['product_name'] ?? 'Producto';
                        $cantidad = $item['quantity'] ?? 1;
                        $precio = $item['price'] ?? $item['unit_price'] ?? 0;
                        $this->line("  - {$nombre} x{$cantidad} = $" . number_format($precio * $cantidad, 2));
                    }
                }
                
                $this->line("---");
                $totalCompras += $compra->total;
            }
            
            $this->newLine();
            $this->info("TOTAL COMPRAS: $" . number_format($totalCompras, 2));
            $this->newLine();
        } else {
            $this->warn("No se encontraron compras para este cliente.");
            $this->newLine();
        }
        
        // Consultar pagos de crédito realizados
        $pagosCredito = CreditPayment::whereHas('sale', function($query) use ($documento, $cliente) {
            $query->where('customer_document', $documento)
                  ->orWhere('customer_id', $cliente->id);
        })->with('sale')->orderBy('created_at', 'desc')->get();
        
        if ($pagosCredito->count() > 0) {
            $this->info("=== PAGOS DE CRÉDITO REALIZADOS ===");
            $this->newLine();
            
            $totalPagos = 0;
            foreach ($pagosCredito as $pago) {
                $fecha = $pago->created_at->format('d/m/Y H:i:s');
                $this->line("ID Pago: {$pago->id}");
                $this->line("Fecha: {$fecha}");
                $this->line("Monto: $" . number_format($pago->amount, 2));
                $this->line("Método: {$pago->payment_method}");
                $this->line("Número de Transacción: " . ($pago->transaction_number ?? 'N/A'));
                $this->line("Venta Relacionada ID: {$pago->sale_id}");
                $this->line("---");
                $totalPagos += $pago->amount;
            }
            
            $this->newLine();
            $this->info("TOTAL PAGOS REALIZADOS: $" . number_format($totalPagos, 2));
            $this->newLine();
        } else {
            $this->warn("No se encontraron pagos de crédito para este cliente.");
            $this->newLine();
        }
        
        // Resumen final
        $this->info("=== RESUMEN ===");
        $this->line("Total Compras: $" . number_format($totalCompras ?? 0, 2));
        $this->line("Total Pagos: $" . number_format($totalPagos ?? 0, 2));
        $this->line("Saldo Actual: $" . number_format($cliente->credit_balance, 2));
        
        return 0;
    }
}
