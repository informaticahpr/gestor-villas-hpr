<?php

namespace Database\Seeders;

use App\Models\Villa;
use Illuminate\Database\Seeder;

class VillaSeeder extends Seeder
{
    /**
     * Definiciones de villas de ejemplo y las reglas para generar su historial de movimientos
     * (usadas tambien por MovimientoSeeder).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definiciones(): array
    {
        return [
            [
                'id' => 'A-1', 'nombres' => 'Ana', 'apellidos' => 'Sánchez',
                'dir' => 'Calle Los Pinos #12', 'telf' => '2234-5601', 'celular' => '9988-1201',
                'mail' => 'ana.sanchez@example.com', 'nomed' => 'ENEE-1001', 'fcontruc' => '2023-11-15',
                'fecha_nac' => '1980-03-12', 'nohab' => 3, 'nobath' => 2, 'cuota' => 500,
                'inicio' => '2024-01-01', 'meses_impagos' => [],
                // villa con convenio especial: cuota mensual reducida por trato particular
                'cuota_especial' => true, 'monto_cuota_especial' => 350,
            ],
            [
                'id' => 'A-2', 'nombres' => 'Carlos', 'apellidos' => 'Martínez',
                'dir' => 'Calle Los Pinos #14', 'telf' => '2234-5602', 'celular' => '9988-1202',
                'mail' => 'carlos.martinez@example.com', 'nomed' => 'ENEE-1002', 'fcontruc' => '2023-12-01',
                'fecha_nac' => '1975-07-22', 'nohab' => 4, 'nobath' => 3, 'cuota' => 500,
                'inicio' => '2024-01-01', 'meses_impagos' => ['2026-06', '2026-07', '2026-08'],
            ],
            [
                'id' => 'A-3', 'nombres' => 'María', 'apellidos' => 'López',
                'dir' => 'Avenida Central #3', 'telf' => '2234-5603', 'celular' => '9988-1203',
                'mail' => 'maria.lopez@example.com', 'nomed' => 'ENEE-1003', 'fcontruc' => '2024-05-20',
                'fecha_nac' => '1990-01-05', 'nohab' => 2, 'nobath' => 2, 'cuota' => 450,
                'inicio' => '2024-06-01', 'meses_impagos' => [],
            ],
            [
                'id' => 'A-4', 'nombres' => 'José', 'apellidos' => 'Ramírez',
                'dir' => 'Calle Los Robles #7', 'telf' => '2234-5604', 'celular' => '9988-1204',
                'mail' => 'jose.ramirez@example.com', 'nomed' => 'ENEE-1004', 'fcontruc' => '2019-04-10',
                'fecha_nac' => '1968-11-30', 'nohab' => 3, 'nobath' => 2, 'cuota' => 400,
                'inicio' => '2019-06-01', 'meses_impagos' => [],
                // dejo de pagar en enero 2020 y nunca se puso al dia: deuda historica real
                'impago_desde' => '2020-01',
            ],
            [
                'id' => 'A-5', 'nombres' => 'Laura', 'apellidos' => 'Torres',
                'dir' => 'Avenida Central #9', 'telf' => '2234-5605', 'celular' => '9988-1205',
                'mail' => 'laura.torres@example.com', 'nomed' => 'ENEE-1005', 'fcontruc' => '2023-08-18',
                'fecha_nac' => '1985-09-14', 'nohab' => 3, 'nobath' => 3, 'cuota' => 500,
                'inicio' => '2024-01-01', 'meses_impagos' => [], 'abono_extra' => 1000,
            ],
            [
                'id' => 'B-1', 'nombres' => 'Roberto', 'apellidos' => 'Díaz',
                'dir' => 'Calle Las Palmas #2', 'telf' => '2234-5606', 'celular' => '9988-1206',
                'mail' => 'roberto.diaz@example.com', 'nomed' => 'ENEE-1006', 'fcontruc' => '2025-01-05',
                'fecha_nac' => '1982-05-19', 'nohab' => 3, 'nobath' => 2, 'cuota' => 450,
                'inicio' => '2025-01-01', 'meses_impagos' => ['2026-08'],
            ],
            [
                'id' => 'B-2', 'nombres' => 'Patricia', 'apellidos' => 'Flores',
                'dir' => 'Calle Las Palmas #4', 'telf' => '2234-5607', 'celular' => '9988-1207',
                'mail' => 'patricia.flores@example.com', 'nomed' => 'ENEE-1007', 'fcontruc' => '2025-02-10',
                'fecha_nac' => '1979-02-27', 'nohab' => 2, 'nobath' => 2, 'cuota' => 450,
                'inicio' => '2025-01-01', 'meses_impagos' => ['2026-07', '2026-08'],
            ],
            [
                'id' => 'B-3', 'nombres' => 'Miguel Ángel', 'apellidos' => 'Cruz',
                'dir' => 'Avenida Norte #21', 'telf' => '2234-5608', 'celular' => '9988-1208',
                'mail' => 'miguel.cruz@example.com', 'nomed' => 'ENEE-1008', 'fcontruc' => '2022-10-02',
                'fecha_nac' => '1972-12-08', 'nohab' => 4, 'nobath' => 3, 'cuota' => 500,
                'inicio' => '2023-01-01', 'meses_impagos' => [],
            ],
            [
                'id' => 'B-4', 'nombres' => 'Sofía', 'apellidos' => 'Mendoza',
                'dir' => 'Avenida Norte #25', 'telf' => '2234-5609', 'celular' => '9988-1209',
                'mail' => 'sofia.mendoza@example.com', 'nomed' => 'ENEE-1009', 'fcontruc' => '2023-09-14',
                'fecha_nac' => '1988-06-17', 'nohab' => 3, 'nobath' => 2, 'cuota' => 500,
                'inicio' => '2024-01-01', 'meses_impagos' => [], 'cargo_extra' => 300,
            ],
            [
                'id' => 'B-5', 'nombres' => 'Diego', 'apellidos' => 'Castillo',
                'dir' => 'Calle Las Palmas #8', 'telf' => '2234-5610', 'celular' => '9988-1210',
                'mail' => 'diego.castillo@example.com', 'nomed' => 'ENEE-1010', 'fcontruc' => '2025-01-20',
                'fecha_nac' => '1977-04-03', 'nohab' => 3, 'nobath' => 2, 'cuota' => 500,
                'inicio' => '2025-01-01',
                'meses_impagos' => ['2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08'],
            ],
            [
                'id' => 'C-1', 'nombres' => 'Elena', 'apellidos' => 'Vargas',
                'dir' => 'Calle del Bosque #5', 'telf' => '2234-5611', 'celular' => '9988-1211',
                'mail' => 'elena.vargas@example.com', 'nomed' => 'ENEE-1011', 'fcontruc' => '2024-03-11',
                'fecha_nac' => '1983-10-25', 'nohab' => 2, 'nobath' => 2, 'cuota' => 500,
                'inicio' => '2024-01-01', 'meses_impagos' => [],
                'pago_parcial' => ['mes' => '2026-08', 'pagado' => 250],
            ],
            [
                'id' => 'C-2', 'nombres' => 'Fernando', 'apellidos' => 'Rojas',
                'dir' => 'Calle del Bosque #9', 'telf' => '2234-5612', 'celular' => '9988-1212',
                'mail' => 'fernando.rojas@example.com', 'nomed' => 'ENEE-1012', 'fcontruc' => '2023-07-07',
                'fecha_nac' => '1965-08-21', 'nohab' => 4, 'nobath' => 3, 'cuota' => 500,
                'inicio' => '2024-01-01', 'meses_impagos' => [], 'aplicobro' => false,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definiciones() as $d) {
            Villa::create([
                'CLV_CLIE' => $d['id'],
                'NOMBRES' => $d['nombres'],
                'APELLIDOS' => $d['apellidos'],
                'DIR' => $d['dir'],
                'TELF' => $d['telf'],
                'CELULAR' => $d['celular'],
                'MAIL' => $d['mail'],
                'NOMED' => $d['nomed'],
                'FCONTRUC' => $d['fcontruc'],
                'FECHA_NAC' => $d['fecha_nac'],
                'NOHAB' => $d['nohab'],
                'NOBATH' => $d['nobath'],
                'APLICOBRO' => $d['aplicobro'] ?? true,
                'CUOTA_ESPECIAL' => $d['cuota_especial'] ?? false,
                'MONTO_CUOTA_ESPECIAL' => $d['monto_cuota_especial'] ?? null,
                'SALDO' => 0,
            ]);
        }
    }
}
