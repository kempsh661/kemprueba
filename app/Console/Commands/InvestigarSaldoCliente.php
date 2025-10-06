<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\CreditPayment;
use Illuminate\Support\Facades\DB;

class InvestigarSaldoCliente extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cliente:investigar-saldo {documento : Número de documento del cliente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Investiga la discrepancia entre el saldo de crédito y los cálculos de compras/pagos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $documento = $this->argument('documento');
        
        $this->info("=== INVESTIGACIÓN DE SALDO - CLIENTE DOCUMENTO: {$documento} ===");
        $this->newLine();
        
        // Buscar el cliente
        $cliente = Customer::where('document', $documento)->first();
        
        if (!$cliente) {
            $this->error("No se encontró un cliente con el documento: {$documento}");
            return 1;
        }
        
        $this->info("INFORMACIÓN DEL CLIENTE:");
        $this->line("Nombre: {$cliente->name}");
        $this->line("Documento: {$documento}");
        $this->line("Saldo de Crédito en BD: $" . number_format($cliente->credit_balance, 2));
        $this->newLine();
        
        // 1. Calcular total de compras a crédito
        $comprasCredito = Sale::where(function($query) use ($documento, $cliente) {
            $query->where('customer_document', $documento)
                  ->orWhere('customer_id', $cliente->id);
        })->where('payment_method', 'credit')->get();
        
        $totalComprasCredito = $comprasCredito->sum('total');
        $this->info("=== ANÁLISIS DE COMPRAS A CRÉDITO ===");
        $this->line("Total compras a crédito: $" . number_format($totalComprasCredito, 2));
        $this->line("Número de compras a crédito: " . $comprasCredito->count());
        $this->newLine();
        
        // 2. Calcular total de pagos realizados
        $pagosRealizados = CreditPayment::whereHas('sale', function($query) use ($documento, $cliente) {
            $query->where('customer_document', $documento)
                  ->orWhere('customer_id', $cliente->id);
        })->get();
        
        $totalPagos = $pagosRealizados->sum('amount');
        $this->info("=== ANÁLISIS DE PAGOS REALIZADOS ===");
        $this->line("Total pagos realizados: $" . number_format($totalPagos, 2));
        $this->line("Número de pagos: " . $pagosRealizados->count());
        $this->newLine();
        
        // 3. Calcular saldo pendiente por venta
        $this->info("=== ANÁLISIS DETALLADO POR VENTA ===");
        $saldoPendienteCalculado = 0;
        
        foreach ($comprasCredito as $venta) {
            $pagosVenta = CreditPayment::where('sale_id', $venta->id)->sum('amount');
            $saldoVenta = $venta->total - $pagosVenta;
            $saldoPendienteCalculado += $saldoVenta;
            
            $this->line("Venta ID {$venta->id}:");
            $this->line("  Total: $" . number_format($venta->total, 2));
            $this->line("  Pagos: $" . number_format($pagosVenta, 2));
            $this->line("  Saldo: $" . number_format($saldoVenta, 2));
            $this->line("  Saldo en BD: $" . number_format($venta->remaining_balance ?? 0, 2));
            $this->line("  ---");
        }
        
        $this->newLine();
        $this->info("=== COMPARACIÓN DE SALDOS ===");
        $this->line("Saldo de crédito en BD: $" . number_format($cliente->credit_balance, 2));
        $this->line("Saldo pendiente calculado: $" . number_format($saldoPendienteCalculado, 2));
        $this->line("Diferencia: $" . number_format($cliente->credit_balance - $saldoPendienteCalculado, 2));
        $this->newLine();
        
        // 4. Verificar si hay ventas con remaining_balance diferente al calculado
        $this->info("=== VERIFICACIÓN DE REMAINING_BALANCE ===");
        $totalRemainingBalance = 0;
        
        foreach ($comprasCredito as $venta) {
            $remainingBalance = $venta->remaining_balance ?? 0;
            $totalRemainingBalance += $remainingBalance;
            
            if ($remainingBalance > 0) {
                $this->line("Venta ID {$venta->id}: $" . number_format($remainingBalance, 2));
            }
        }
        
        $this->line("Total remaining_balance: $" . number_format($totalRemainingBalance, 2));
        $this->newLine();
        
        // 5. Verificar si hay pagos duplicados o problemas en los datos
        $this->info("=== VERIFICACIÓN DE INTEGRIDAD DE DATOS ===");
        
        // Buscar pagos duplicados
        $pagosDuplicados = DB::table('credit_payments')
            ->select('sale_id', 'amount', 'created_at', DB::raw('COUNT(*) as payment_count'))
            ->whereIn('sale_id', $comprasCredito->pluck('id'))
            ->groupBy('sale_id', 'amount', 'created_at')
            ->having('payment_count', '>', 1)
            ->get();
            
        if ($pagosDuplicados->count() > 0) {
            $this->warn("Se encontraron posibles pagos duplicados:");
            foreach ($pagosDuplicados as $duplicado) {
                $this->line("Venta ID {$duplicado->sale_id}: $" . number_format($duplicado->amount, 2) . " (x{$duplicado->payment_count})");
            }
        } else {
            $this->line("No se encontraron pagos duplicados.");
        }
        
        $this->newLine();
        
        // 6. Resumen final
        $this->info("=== RESUMEN DE INVESTIGACIÓN ===");
        $this->line("1. Saldo en BD: $" . number_format($cliente->credit_balance, 2));
        $this->line("2. Total compras crédito: $" . number_format($totalComprasCredito, 2));
        $this->line("3. Total pagos: $" . number_format($totalPagos, 2));
        $this->line("4. Saldo calculado (compras - pagos): $" . number_format($totalComprasCredito - $totalPagos, 2));
        $this->line("5. Total remaining_balance: $" . number_format($totalRemainingBalance, 2));
        $this->line("6. Diferencia principal: $" . number_format($cliente->credit_balance - ($totalComprasCredito - $totalPagos), 2));
        
        return 0;
    }
}
