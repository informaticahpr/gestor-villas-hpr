# TPVADMIN — Diagnóstico General (Fase de Análisis)

## Fuente de este análisis

Este análisis se hizo a partir de `db_ejemplo/script.sql`, un script de generación de esquema
(estructura, sin datos) exportado con SSMS. El archivo está en **UTF-16LE**, tiene 4,727 líneas y
contiene: 76 `CREATE TABLE`, 32 `CREATE VIEW`, 74 procedimientos almacenados, 1 función escalar, y
0 constraints `FOREIGN KEY`.

**No se pudo hacer conexión en vivo al servidor SQL Server real** desde este entorno (sandbox sin acceso
de red saliente). Todo lo que sigue proviene exclusivamente de leer el script de esquema, no de
consultar datos reales. Esto tiene una limitación importante: **el script no trae filas de datos**
(no hay `INSERT INTO` con valores para las tablas de negocio), así que no pude verificar contra
datos reales ciertas ambigüedades de la lógica (detalladas en `account-statement-logic.md`).

Antes de construir la lógica de saldos definitiva, alguien con acceso real a SSMS debería correr las
consultas de verificación que dejo señaladas en ese documento.

## Hallazgo principal: TPVADMIN no fue diseñado para villas

`TPVADMIN` es el esquema de un sistema genérico de **punto de venta / facturación / cuentas por
cobrar y por pagar** (tablas como `INVE1` inventario, `FACT1` facturas de venta, `COMP1` compras,
`PROV1` proveedores, `REMI1` remisiones, `ALM1` almacenes). Todo indica que HPR **reutilizó este
software de POS para administrar villas como si fueran "clientes" de una tienda**:

- `CLIE1` (tabla de "clientes") ya tiene columnas que no tienen sentido para un cliente de tienda
  pero sí para una villa: `NOHAB` (núm. habitaciones), `NOBATH` (núm. baños), `CUOTA` (cuota
  mensual), `APLICOBRO` (bandera de si se le aplica el cobro automático), `FCONTRUC` (fecha de
  construcción — probablemente "Fecha de Entrega" del docx), `NOMED` (probablemente "medidor ENEE").
- El campo `#Villa` con formato `"A-1"` del documento de requerimientos corresponde a `CLIE1.CLV_CLIE`
  (`varchar(5)`, primary key).
- El "estado de cuenta con cargos/créditos/saldo" descrito en el documento de requerimientos
  corresponde casi exactamente a las columnas de las tablas `TempEstadoCtaCxc1_*`
  (`CARGOS`, `ABONOS`, `TOTAL`) que el sistema actual usa para armar ese reporte.

Esto confirma la hipótesis del documento de contexto (`CLAUDE_CODE_DB_CONTEXT.md`): hay que **partir
de la estructura real**, no de una relación conceptual inventada — y la estructura real es la de un
ERP de punto de venta adaptado, no un sistema de HOA/condominio nativo.

## Entidades identificadas y su tabla real

| Entidad conceptual (del negocio) | Tabla/vista real en TPVADMIN |
|---|---|
| Villas / Propietarios | `CLIE1` (+ `CONTACTCLIE1`, `OBSCCLIE1` para notas) |
| Facturas / Cargos (incl. cuota mensual) | `FACT1` (encabezado) + `PFACT1` (detalle/líneas) |
| Movimientos de cuenta (cargos y abonos aplicados) | `CUEN1` |
| Conceptos de cobro (qué tipo de cargo/abono es) | `CONC1` |
| Recibos de abono/pago | `RECCXC1` |
| Aplicación de un pago a una factura concreta | `PAGFAC1` |
| Vendedor/cobrador asociado | `VEND1` |
| Usuarios del sistema | `USUARIOS` |
| Empresa (datos de la administradora) | `EMPRESA` |
| Reporte de estado de cuenta (temporal, por sesión) | `TempEstadoCtaCxc1_1/5/6/10` |
| Reporte de cobranza / antigüedad de saldos (temporal) | `TempRptCobro1_1/5/6/10`, `TempMulti1_1/5/6/10` |

Existe además un juego paralelo de tablas `CLIE2`, `FACT2`, `VEND2`, etc. (sufijo `2`), lo que sugiere
que el software soporta **múltiples empresas/compañías en la misma base**. Todo lo relevante para
Villas HPR usa el sufijo `1` (`CLIE1`, `FACT1`, `CUEN1`, `CONC1`, `RECCXC1`...). Hay que confirmar en
la BD real que HPR efectivamente solo usa el conjunto "1" y no ambos.

Detalle de columnas: ver `database-tables.md`.
Relaciones entre tablas: ver `database-relations.md`.
Lógica de estado de cuenta y saldo: ver `account-statement-logic.md`.
Representación de deuda histórica: ver `historical-debt.md`.

## Regla de seguridad — respetada

No se generó, ni se intentó generar, ningún `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER` ni cambio de
esquema contra `TPVADMIN`. No se crearon migraciones ni tablas nuevas. Este análisis es de solo
lectura sobre el script proporcionado.
