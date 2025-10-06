<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\CreditPayment;
use Illuminate\Support\Facades\DB;

class CorregirSaldosCredito extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credito:corregir-saldos {--cliente= : Documento específico del cliente} {--dry-run : Solo mostrar cambios sin aplicarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige y sincroniza los saldos de crédito de los clientes basándose en compras y pagos reales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $documentoCliente = $this->option('cliente');
        $dryRun = $this->option('dry-run');
        
        $this->info("=== CORRECCIÓN DE SALDOS DE CRÉDITO ===");
        if ($dryRun) {
            $this->warn("MODO SIMULACIÓN - No se aplicarán cambios");
        }
        $this->newLine();
        
        // Obtener clientes a procesar
        if ($documentoCliente) {
            $clientes = Customer::where('document', $documentoCliente)->get();
            if ($clientes->isEmpty()) {
                $this->error("No se encontró un cliente con el documento: {$documentoCliente}");
                return 1;
            }
        } else {
            $clientes = Customer::all();
        }
        
        $this->info("Procesando " . $clientes->count() . " cliente(s)...");
        $this->newLine();
        
        $clientesCorregidos = 0;
        $totalCorrecciones = 0;
        
        foreach ($clientes as $cliente) {
            $this->line("Procesando cliente: {$cliente->name} (Doc: {$cliente->document})");
            
            // Calcular saldo real
            $saldoReal = $this->calcularSaldoReal($cliente);
            $saldoActual = $cliente->credit_balance;
            $diferencia = $saldoReal - $saldoActual;
            
            $this->line("  Saldo actual en BD: $" . number_format($saldoActual, 2));
            $this->line("  Saldo real calculado: $" . number_format($saldoReal, 2));
            $this->line("  Diferencia: $" . number_format($diferencia, 2));
            
            if (abs($diferencia) > 0.01) { // Solo si hay diferencia significativa
                if ($dryRun) {
                    $this->warn("  [SIMULACIÓN] Se actualizaría a: $" . number_format($saldoReal, 2));
                } else {
                    // Actualizar el saldo
                    $cliente->credit_balance = $saldoReal;
                    $cliente->save();
                    $this->info("  ✓ Saldo actualizado a: $" . number_format($saldoReal, 2));
                }
                $clientesCorregidos++;
                $totalCorrecciones += abs($diferencia);
            } else {
                $this->line("  ✓ Saldo correcto, no requiere corrección");
            }
            
            $this->line("  ---");
        }
        
        $this->newLine();
        $this->info("=== RESUMEN ===");
        $this->line("Clientes procesados: " . $clientes->count());
        $this->line("Clientes corregidos: " . $clientesCorregidos);
        $this->line("Total de correcciones: $" . number_format($totalCorrecciones, 2));
        
        if ($dryRun) {
            $this->warn("Para aplicar los cambios, ejecuta el comando sin --dry-run");
        } else {
            $this->info("✓ Correcciones aplicadas exitosamente");
        }
        
        return 0;
    }
    
    /**
     * Calcula el saldo real de un cliente basándose en compras y pagos
     */
    private function calcularSaldoReal(Customer $cliente)
    {
        // Obtener todas las compras a crédito del cliente
        $comprasCredito = Sale::where(function($query) use ($cliente) {
            $query->where('customer_document', $cliente->document)
                  ->orWhere('customer_id', $cliente->id);
        })->where('payment_method', 'credit')->get();
        
        $totalCompras = $comprasCredito->sum('total');
        
        // Obtener todos los pagos realizados por el cliente
        $pagosRealizados = CreditPayment::whereHas('sale', function($query) use ($cliente) {
            $query->where('customer_document', $cliente->document)
                  ->orWhere('customer_id', $cliente->id);
        })->get();
        
        $totalPagos = $pagosRealizados->sum('amount');
        
        // El saldo real es: compras - pagos
        return $totalCompras - $totalPagos;
    }
}
