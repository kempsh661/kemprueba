<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;

class AssignTempDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:assign-temp-documents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asigna documentos temporales a clientes que no tengan documento';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Buscando clientes sin documento...');
        
        $customersWithoutDocument = Customer::whereNull('document')
            ->orWhere('document', '')
            ->get();
        
        if ($customersWithoutDocument->isEmpty()) {
            $this->info('✅ No hay clientes sin documento.');
            return;
        }
        
        $this->info("📝 Encontrados {$customersWithoutDocument->count()} clientes sin documento.");
        
        $bar = $this->output->createProgressBar($customersWithoutDocument->count());
        $bar->start();
        
        foreach ($customersWithoutDocument as $customer) {
            $customer->document = 'TEMP-' . $customer->id;
            $customer->save();
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('✅ Documentos temporales asignados correctamente.');
        $this->info('💡 Recuerda actualizar estos documentos con valores reales.');
    }
}
