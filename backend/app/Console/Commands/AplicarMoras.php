<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Services\MoraService;
use App\Support\Formato;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Aplica la mora del mes (ver MoraService). Lo corre el programador de tareas todos los dias (ver
 * routes/console.php): antes del dia 5 no hace nada y nunca duplica, asi que es seguro repetirlo.
 * --fecha permite probar otro dia (ej. --fecha=2026-11-05).
 */
class AplicarMoras extends Command
{
    protected $signature = 'moras:aplicar {--fecha= : Fecha a usar como "hoy" (AAAA-MM-DD), para pruebas}';

    protected $description = 'Aplica la mora del mes a las villas que deben (a partir del día 5)';

    public function handle(MoraService $moras): int
    {
        $hoy = $this->option('fecha') ? Carbon::parse($this->option('fecha'))->startOfDay() : Carbon::today();
        $r = $moras->aplicarDelMes($hoy);

        if ($r['aplicadas'] > 0) {
            Bitacora::registrar('mora', 'crear', "Se aplicó la mora de {$r['periodo']} ({$r['porcentaje']}%) a {$r['aplicadas']} villa(s) por ".Formato::monto($r['total']).'.');
        }
        $this->info("Mora {$r['periodo']}: {$r['aplicadas']} villa(s), total ".Formato::monto($r['total']).($hoy->day < MoraService::DIA_APLICACION ? ' (todavía no es día '.MoraService::DIA_APLICACION.')' : ''));

        return self::SUCCESS;
    }
}
