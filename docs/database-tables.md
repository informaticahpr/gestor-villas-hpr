# TPVADMIN — Tablas relevantes para Villas HPR

Fuente: `db_ejemplo/script.sql`. No hay conteo real de registros disponible (el script es solo
estructura). No hay `FOREIGN KEY` declaradas en ninguna tabla del esquema — todas las relaciones
son lógicas/de aplicación, reforzadas solo por convención de nombres de columnas.

---

## `dbo.CLIE1` — Villas / Propietarios

PK: `CLV_CLIE` (`varchar(5)`) — este es el `#Villa` (formato `"A-1"`) del documento de requerimientos.

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| NUM_REG | int identity | NOT NULL | correlativo interno |
| CLV_CLIE | varchar(5) | NOT NULL | **PK**, identificador de villa, ej. `A-1` |
| ST | varchar(1) | NULL | status (activo/inactivo, valores no confirmados) |
| NOMBRE | varchar(60) | NULL | nombre del propietario |
| RTN | varchar(20) | NULL | identificación fiscal |
| TELF | varchar(20) | NULL | teléfono (un solo campo — el docx pide 3: Teléfono/Celular/Otro) |
| MAIL | varchar(30) | NULL | correo (un solo campo — el docx pide 2) |
| DIR | varchar(255) | NULL | dirección / ubicación |
| ULTVTA | datetime | NULL | fecha de última "venta" (= último cargo aplicado) |
| VULTVTA | float | NULL | valor de la última venta/cargo |
| TOTVTA | float | NULL | total histórico de ventas/cargos |
| ULTPAGO | datetime | NULL | fecha del último pago/abono |
| **SALDO** | float | NULL | saldo actual del propietario (lo actualiza `SP_SALDOCLIE1`) |
| LIMITE_C | float | NULL | límite de crédito (heredado del POS, probablemente sin uso real aquí) |
| DIAS_C | int | NULL | días de crédito (ídem) |
| CLV_VEND | varchar(5) | NULL | vendedor/cobrador asociado → `VEND1` |
| OBS | int | NULL | FK lógica a `OBSCCLIE1.CLV_OBS` (observaciones) |
| ULTDOC | varchar(20) | NULL | último documento |
| **NOMED** | varchar(10) | NULL | candidato a "Medidor ENEE" del docx |
| **FCONTRUC** | datetime | NULL | candidato a "Fecha de Entrega" del docx (nombre sugiere "fecha construcción") |
| **NOHAB** | int | NULL | número de habitaciones |
| **NOBATH** | int | NULL | número de baños |
| **CUOTA** | float | NULL | valor de la cuota mensual de mantenimiento |
| **APLICOBRO** | int | NULL | candidato al checkbox "Apartado de cuota mensual" del docx (aplicar cargo recurrente) |

No hay columna de "Fecha de nacimiento" — falta respecto al docx, habría que confirmarlo en la BD real
o se agregará en el nuevo sistema.

Tablas satélite:
- `CONTACTCLIE1(NOMBRE, TELF, CLV_CLIE)` — contactos adicionales de la villa.
- `OBSCCLIE1` — catálogo de observaciones/notas referenciadas por `CLIE1.OBS`.
- `INFCLIE1` / `OBSINFCLIE1` — "información de cliente" alterna, usada por documentos (ver `FACT1`→`INFCLIE1` en `SP_REPORTEFACTCM1`); rol exacto sin confirmar, probablemente datos de facturación distintos al domicilio del cliente.

---

## `dbo.FACT1` — Facturas / Cargos (encabezado)

PK: `CVE_DOC` (`varchar(9)`).

| Columna | Tipo | Notas |
|---|---|---|
| TIPOD | varchar(1) | tipo de documento |
| ST | varchar(1) | status (`E`=Emitido, `C`=Anulado, por analogía con `VW_FLTODOCCMP1` de compras) |
| CVE_DOC | varchar(9) | **PK**, folio del cargo/factura |
| DOCDEI | varchar(20) | número de documento fiscal/impreso |
| DESCR_VTA | varchar(25) | descripción |
| FECHA_DOC / FECHA_VEN / FECHA_EMI | datetime | fecha doc, fecha vencimiento, fecha emisión |
| CAN_TOT, TOT_IMPU1, TOT_IMPU2, TOT_DESC, TOT_DESC2, TOT_COMI | float | totales/impuestos/descuentos |
| TOTAL | float | **monto total del cargo** |
| OBS_FACT | int | observación |
| CLV_VEND | varchar(5) | vendedor |
| **CVE_CLIE** | varchar(5) | **FK lógica a `CLIE1.CLV_CLIE`** — a qué villa pertenece el cargo |
| BLOQE | varchar(1) | bloqueo/estado del documento |

Detalle de líneas: `PFACT1(CVE_DOC, CVE_ART, CANT, PRECIO, TOT, ...)` — enlaza a `INVE1` (artículos).
Para la cuota mensual, es de esperar que exista un "artículo"/concepto en `INVE1` que representa la
cuota de mantenimiento y que cada mes se genera una `FACT1` con una línea `PFACT1` de ese concepto.
**Esto no está confirmado con datos reales.**

---

## `dbo.CUEN1` — Movimientos de cuenta corriente (el corazón del estado de cuenta)

Sin PK declarada. Esta es la tabla que alimenta el estado de cuenta (ver `account-statement-logic.md`).

| Columna | Tipo | Notas |
|---|---|---|
| CCLIE | varchar(5) | **FK lógica a `CLIE1.CLV_CLIE`** |
| STATUS | varchar(1) | status del movimiento |
| TIPO_MOV | int | **FK lógica a `CONC1.NUM_CPTO`** — define si es cargo o abono |
| NO_FACTURA | varchar(9) | factura relacionada |
| DOCTO | varchar(9) | documento que originó el movimiento (p.ej. el recibo de abono) |
| REFER | varchar(9) | documento de referencia — en cargos, probablemente = su propio folio; en abonos, el folio de la factura que se está pagando (ver `account-statement-logic.md`) |
| IMPORTE | float | monto del movimiento (signo/sentido por confirmar) |
| FECHA_APLI | datetime | fecha de aplicación — candidata a representar "a qué mes/período corresponde" |
| FECHA_VENC | datetime | fecha de vencimiento |
| CVE_VEND | varchar(5) | vendedor/cobrador |
| NUM_MONED, TCAMBIO | — | moneda y tipo de cambio |
| CCONREFER | varchar(1) | ¿tipo de referencia? sin confirmar |
| USUARIO | int | usuario que hizo el movimiento |
| OBS | int | observación |
| FECHA_ELAB | datetime | fecha de captura del registro |

`SP_INSERTCXC1` es el único punto de inserción conocido en `CUEN1` (uno por movimiento).

---

## `dbo.CONC1` — Catálogo de conceptos de cobro (cuentas por cobrar)

PK: `NUM_CPTO` (int).

| Columna | Tipo | Notas |
|---|---|---|
| NUM_CPTO | int | **PK** |
| DESCR | varchar(40) | descripción del concepto (ej. "Cuota mensual", "Cargo extraordinario", "Abono", "Anticipo"...) |
| **TIPO** | varchar(1) | `'A'` o `'C'` — determina si el concepto suma como cargo o como abono. **El sentido exacto de cada letra es contradictorio entre procedimientos, ver `account-statement-logic.md`.** |
| REFER | varchar(1) | usado como filtro `'N'` en varios procs (¿"normal"?) |
| MOD | int | módulo asociado |

Existe un catálogo paralelo `CONP1` (idéntica estructura) para cuentas por pagar (proveedores), no
relevante para propietarios.

---

## `dbo.RECCXC1` — Recibos de abono/pago de un propietario

PK: `NO_RECIBO` (varchar(9)).

| Columna | Tipo | Notas |
|---|---|---|
| NO_RECIBO | varchar(9) | **PK**, folio del recibo |
| SALDO_ANT | float | saldo del propietario antes del abono |
| ABONO | float | monto abonado en este recibo |
| SALDO_ACT | float | saldo después del abono |
| **CLV_CLIE** | varchar(5) | **FK lógica a `CLIE1`** |
| FECHA_ELAB | datetime | fecha del recibo |
| LETRAS | varchar(max) | monto en letras (para impresión) |
| SALDO_TOT | float | (sin confirmar diferencia con SALDO_ACT) |
| OBS | varchar(255) | observaciones |
| PORCON | varchar(max) | "por concepto de" — texto libre, probablemente el detalle de a qué se aplicó el abono |

Este es el **recibo/encabezado** del abono. La aplicación línea por línea del abono a facturas
específicas parece vivir en `PAGFAC1` (ver siguiente) y/o como filas individuales en `CUEN1` con
`DOCTO` = este `NO_RECIBO`.

---

## `dbo.PAGFAC1` — Aplicación de pagos a facturas (formas de pago)

Sin PK declarada.

| Columna | Tipo | Notas |
|---|---|---|
| TIPOD | varchar(1) | tipo de documento |
| DOCUMENTO | varchar(9) | documento pagado (factura o recibo — confirmar) |
| NUM_CPTO | int | forma de pago / concepto |
| DESCR | varchar(30) | descripción (ej. "Efectivo", "Transferencia", "Cheque") |
| IMPORTE | float | monto de esa forma de pago |
| BANCO, CTACHK, N_AUTOR, ID | — | datos bancarios/cheque |
| FECHADOC, FECHAVENC | datetime | fechas |
| **IMPORTEAPL** | float | monto efectivamente aplicado |

`SP_FORMAPAGO1(@CVE_DOC, @TIPOD)` suma `IMPORTEAPL` de `PAGFAC1` para un documento — sugiere que
esta tabla registra **las formas de pago de un recibo/factura** (posible pago mixto: parte
efectivo, parte transferencia), más que el enlace "qué factura específica cubre este pago". Su
existencia junto a `CUEN1.REFER` sugiere dos mecanismos distintos coexistiendo: uno para
"a qué factura se abonó" (`CUEN1.REFER`) y otro para "cómo se pagó" (`PAGFAC1`). **A confirmar con
datos reales.**

---

## `dbo.USUARIOS` — Usuarios del sistema actual

PK: `CLV_USER` (int). Tiene ~130 columnas de permisos granulares por módulo
(`MFACT`, `MINVE`, `MCMP`, `MCXC`, `MCXP`, `CXCNEWC`, `CXCEDTC`, `RPTSLCXC`, etc. — todas `int`,
probablemente flags 0/1). **No existe una columna de "rol" con nombre** (`DIRECTOR`/`ADMIN`/
`SUPERVISOR` no existen como concepto en este esquema). El sistema de roles pedido en el documento
de requerimientos (Director/Admin/Supervisor) **es un concepto nuevo a diseñar**, no algo que se
pueda migrar 1:1 desde `USUARIOS`.

---

## `dbo.EMPRESA` — Datos de la administradora

Un solo registro esperado: nombre, RTN, teléfono, celular, web, dirección comercial/fiscal, logo,
correo. Útil para membretar reportes y estados de cuenta.

---

## Tablas temporales de reporte (por sesión/terminal)

Sufijos `_1`, `_5`, `_6`, `_10` = una copia física de la tabla por terminal/sesión concurrente (patrón
típico de apps de escritorio multiusuario sin tablas temporales reales de SQL Server). Todas
comparten estructura entre sufijos:

- **`TempEstadoCtaCxc1_*`**: `CLV_CLIE, NOMBRE, TIPO_MOV, DESCR, NO_FACTURA, DOCTO, FECHA_APLI, FECHA_VENC, CARGOS, ABONOS, TOTAL` — el **shape exacto del estado de cuenta** que pide el documento de requerimientos (columnas Fecha/Descripción/Cargos/Créditos/Saldo).
- **`TempMulti1_*`**: `CCLIE, NO_FACTURA, DOCTO, REFER, DOCTODEI, IMPORTE, SALDO, FECHA_APLI, FECHA_VENC, TIPO_MOV, DESCR, MARCAR` — usada aparentemente para selección múltiple de facturas a pagar (columna `MARCAR`).
- **`TempRptCobro1_*`**: `CCLIE, NO_FACTURA, DOCTO, REFER, IMPORTE, SALDO, FECHA_APLI, FECHA_VENC, ULT_PAGO, TIPO_MOV, DESCR` — para el reporte de cobranza/antigüedad de saldos.
- **`TempCptoCxc1_*`**, **`TempCtrlArchivs_*`**: soporte adicional, contenido exacto sin confirmar.

**No hay ningún `CREATE VIEW` ni `CREATE PROCEDURE` en el script que llene estas tablas Temp.** Eso
implica que el llenado se hace desde la aplicación de escritorio (probablemente Delphi/PowerBuilder),
ejecutando sentencias `INSERT` dinámicas no incluidas en este export. Es decir: **la lógica completa
del estado de cuenta no vive 100% en la base de datos** — una parte vive en el cliente de escritorio
actual, fuera de nuestro alcance de análisis con este script.

---

## Procedimientos almacenados relevantes (cuentas por cobrar / villas)

| Procedimiento | Qué hace |
|---|---|
| `SP_INSERTCLIE1` | Alta de villa/propietario |
| `SP_UPDATECLIE1` | Edición de villa/propietario |
| `SP_INSERTFACT1` / `SP_INSERTPFACT1` | Alta de factura/cargo y su(s) línea(s) |
| `SP_INSERTCXC1` | Inserta un movimiento en `CUEN1` (cargo o abono) |
| `SP_INSERTRECCXC1` | Alta de recibo de abono en `RECCXC1` |
| `SP_INSERTPAGFAC1` | Registra una forma de pago aplicada |
| `SP_SALDOCLIE1` | Recalcula y persiste `CLIE1.SALDO` a partir de `CUEN1` |
| `SP_DETALLECXC1` | Detalle de movimientos de un cliente en un rango de fechas, con saldo por documento |
| `SP_FACTDETALLECXC1` | Detalle de movimientos ligados a una factura específica |
| `SP_ANTICIPOCXC1` | Cálculo relacionado a anticipos/saldo a favor |
| `SP_RECIBOCXC1` | Trae los datos de un recibo específico para reimprimir |
| `SP_REPORTEFACTCC1` / `SP_REPORTEFACTCM1` | Datos para imprimir una factura |
| `SP_REPORTEPFACT1` | Líneas de una factura para impresión |

Cuerpo completo de cada uno, ver `account-statement-logic.md` (los relevantes al saldo están
transcritos y comentados ahí).
