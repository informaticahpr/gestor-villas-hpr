<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un permiso que tiene un cargo (Configuracion -> Permisos). El Administrador no tiene filas aqui:
 * siempre tiene todos y no se le pueden quitar (ver User::tienePermiso).
 *
 * Lo que es de solo lectura (buscar villas, estado de cuenta por villa, reimpresion) lo ven todos
 * los cargos y no tiene permiso; tampoco la pestaña Permisos, que es solo del Administrador.
 */
#[Fillable(['rol_id', 'permiso'])]
class RolPermiso extends Model
{
    protected $table = 'rol_permisos';

    public $timestamps = false;

    /** Catalogo: clave => [grupo, etiqueta, descripcion]. El orden es el de la pantalla. */
    public const CATALOGO = [
        'villas.crear' => ['Villas', 'Crear villas', 'Registrar villas nuevas con su propietario y encargado.'],
        'villas.editar' => ['Villas', 'Editar villas', 'Modificar los datos de una villa, su propietario y su encargado.'],
        'movimientos.aplicar' => ['Cargos y créditos', 'Aplicar cargos y créditos', 'Capturar cargos, abonos y la cuota de mantenimiento.'],
        'movimientos.anular' => ['Cargos y créditos', 'Anular movimientos', 'Anular un cargo o abono ya capturado (Reimpresión).'],
        'inicio.cobranza' => ['Inicio', 'Ver cobranza del mes', 'Cuentas por cobrar y lo recuperado en el mes.'],
        'inicio.completo' => ['Inicio', 'Ver saldo a favor y resumen del año', 'Indicadores completos de la pantalla de inicio.'],
        'reportes.generales' => ['Reportes', 'Reportes generales', 'Saldos generales, antigüedad de saldos y reporte por concepto.'],
        'config.conceptos' => ['Configuración', 'Conceptos', 'Crear, editar, activar y eliminar conceptos.'],
        'config.cuotas' => ['Configuración', 'Cuotas', 'Cuota de mantenimiento y cuotas especiales.'],
        'config.alquiladas' => ['Configuración', 'Villas alquiladas', 'Marcar las villas alquiladas a producción.'],
        'config.formas_pago' => ['Configuración', 'Formas de pago', 'Crear, editar, activar y eliminar formas de pago.'],
        'config.bitacora' => ['Configuración', 'Bitácora', 'Ver y exportar la bitácora de cambios.'],
        'usuarios.ver' => ['Usuarios', 'Ver usuarios', 'Ver la lista de usuarios del sistema.'],
        'usuarios.gestionar' => ['Usuarios', 'Gestionar usuarios', 'Crear, editar, habilitar, eliminar usuarios y cambiar contraseñas.'],
    ];

    /** Los accesos que cada cargo tenia fijos antes de existir esta pantalla (migracion y seeder). */
    public const INICIALES = [
        Role::DIRECTOR => [
            'villas.crear', 'villas.editar', 'movimientos.aplicar', 'movimientos.anular',
            'inicio.cobranza', 'inicio.completo', 'reportes.generales',
            'config.conceptos', 'config.cuotas', 'config.alquiladas', 'config.formas_pago', 'config.bitacora',
            'usuarios.ver',
        ],
        Role::SUPERVISOR => [
            'villas.crear', 'movimientos.aplicar', 'inicio.cobranza', 'reportes.generales', 'config.alquiladas',
        ],
        Role::CONSULTOR => [],
    ];

    /** Pone los permisos iniciales a los cargos que todavia no tienen ninguno. */
    public static function sembrarIniciales(): void
    {
        $roles = Role::pluck('id', 'nombre');
        foreach (self::INICIALES as $rol => $permisos) {
            if (! isset($roles[$rol]) || static::where('rol_id', $roles[$rol])->exists()) {
                continue;
            }
            static::insert(array_map(fn ($p) => ['rol_id' => $roles[$rol], 'permiso' => $p], $permisos));
        }
    }
}
