<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AccountBalance;
use Illuminate\Support\Facades\DB;

class CleanDuplicateBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balances:clean-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia registros duplicados de account_balances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Limpiando registros duplicados de account_balances...');

        // Obtener todos los registros agrupados por fecha
        $duplicates = AccountBalance::select('date', DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No se encontraron registros duplicados.');
            return;
        }

        foreach ($duplicates as $duplicate) {
            $this->info("Procesando fecha: {$duplicate->date} ({$duplicate->count} registros)");

            // Obtener todos los registros de esta fecha
            $balances = AccountBalance::where('date', $duplicate->date)
                ->orderBy('created_at', 'desc')
                ->get();

            // Mantener solo el más reciente que no esté cerrado, o el más reciente si todos están cerrados
            $keepBalance = null;
            foreach ($balances as $balance) {
                if (!$balance->is_closed) {
                    $keepBalance = $balance;
                    break;
                }
            }

            // Si todos están cerrados, mantener el más reciente
            if (!$keepBalance) {
                $keepBalance = $balances->first();
            }

            // Eliminar los demás registros
            $deletedCount = AccountBalance::where('date', $duplicate->date)
                ->where('id', '!=', $keepBalance->id)
                ->delete();

            $this->info("  - Mantenido: ID {$keepBalance->id} (cerrado: " . ($keepBalance->is_closed ? 'SÍ' : 'NO') . ")");
            $this->info("  - Eliminados: {$deletedCount} registros");
        }

        $this->info('Limpieza completada.');
    }
}
