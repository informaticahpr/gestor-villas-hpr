# TPVADMIN — Relaciones entre entidades (Fase de Análisis)

Ninguna relación está declarada como `FOREIGN KEY` en el esquema (0 constraints de este tipo en
todo `script.sql`). Todas las relaciones descritas abajo son **lógicas**, inferidas de:
1. nombres de columna compartidos (`CLV_CLIE` / `CCLIE` / `CVE_CLIE`),
2. joins usados dentro de las vistas y procedimientos almacenados existentes.

Esto es evidencia indirecta, no una garantía de integridad referencial — en el sistema real pueden
existir movimientos "huérfanos" (p.ej. un `CUEN1` sin `FACT1` correspondiente) que la BD nunca
impidió.

## Diagrama lógico (confirmado por los stored procedures)

```
CLIE1 (villa/propietario)
  CLV_CLIE (PK)
    │
    ├─< FACT1.CVE_CLIE            (cargos/facturas de esa villa)
    │      └─< PFACT1.CVE_DOC     (líneas de la factura → INVE1.CLV_ART = concepto/artículo)
    │
    ├─< CUEN1.CCLIE               (todo movimiento de cuenta: cargo o abono)
    │      ├── CUEN1.TIPO_MOV ──> CONC1.NUM_CPTO   (¿es cargo 'A' o abono 'C'? — ver account-statement-logic.md)
    │      ├── CUEN1.NO_FACTURA   (factura relacionada)
    │      ├── CUEN1.DOCTO        (documento que originó el movimiento, p.ej. un recibo)
    │      └── CUEN1.REFER        (documento de referencia — clave para saber a qué factura se abonó)
    │
    ├─< RECCXC1.CLV_CLIE          (recibos de abono/pago emitidos a esa villa)
    │      └── PAGFAC1.DOCUMENTO  (formas de pago del recibo/factura — efectivo/transferencia/cheque)
    │
    ├─< CONTACTCLIE1.CLV_CLIE     (contactos adicionales)
    └── CLIE1.OBS ──> OBSCCLIE1.CLV_OBS   (nota/observación de la villa)

CONC1 (catálogo de conceptos CxC)
  NUM_CPTO (PK) ──< CUEN1.TIPO_MOV
  TIPO ('A' | 'C')   ← determina si el concepto es cargo o abono

VEND1 (vendedor/cobrador)
  CLV_VEND (PK) ──< CLIE1.CLV_VEND, FACT1.CLV_VEND, CUEN1.CVE_VEND
```

## Relación factura ↔ movimiento de cuenta ↔ abono

Esta es la relación crítica para poder decir "qué mes está pagado y cuál no" (requisito central del
proyecto). Reconstruida a partir de `SP_INSERTCXC1`, `SP_DETALLECXC1` y `SP_FACTDETALLECXC1`:

1. Cuando se genera un cargo (ej. la cuota de un mes), se crea:
   - una fila en `FACT1` (encabezado, folio = `CVE_DOC`) con `CVE_CLIE` = la villa,
   - una o más líneas en `PFACT1` con ese mismo `CVE_DOC`,
   - **y además** una fila en `CUEN1` (vía `SP_INSERTCXC1`) con `CCLIE` = la villa, `TIPO_MOV`
     apuntando a un concepto `CONC1` de tipo cargo, y `NO_FACTURA` / `REFER` apuntando al `CVE_DOC`
     de esa factura.
2. Cuando se recibe un abono/pago, se crea:
   - una fila en `RECCXC1` (encabezado del recibo, folio = `NO_RECIBO`),
   - una fila en `CUEN1` con `CCLIE` = la villa, `TIPO_MOV` apuntando a un concepto de tipo abono,
     `DOCTO` = el `NO_RECIBO`, y **`REFER` = el `CVE_DOC` de la factura que se está pagando** (esto
     es lo que permite, en `SP_DETALLECXC1`, calcular el saldo pendiente *por factura* agrupando por
     `REFER`).
   - opcionalmente, filas en `PAGFAC1` si el pago se dividió en varias formas de pago.

Esto significa que **`CUEN1.REFER` es el campo bisagra**: conecta cada abono con la factura/cuota
concreta que salda, y es lo que permitiría en el sistema nuevo reconstruir, para cada villa, qué
meses específicos siguen pendientes — tal como pide el documento de contexto (`historical-debt.md`
profundiza en esto).

**Advertencia:** esta reconstrucción es consistente con el código de los procedimientos, pero no fue
verificada contra filas reales (el script no trae datos). Antes de dar esto por definitivo hay que
correr, contra la base real y de solo lectura, algo como:

```sql
SELECT TOP 50 * FROM CUEN1 WHERE CCLIE = '<alguna villa con historial>' ORDER BY FECHA_APLI;
SELECT TOP 20 * FROM CONC1;
SELECT TOP 20 * FROM FACT1 WHERE CVE_CLIE = '<esa misma villa>';
```
y confirmar visualmente que `REFER` efectivamente enlaza abonos con su factura de origen.

## Multiempresa

Existe un segundo juego de tablas con sufijo `2` (`CLIE2`, `FACT2`, `VEND2`, `MINV2`, `CONMIV2`,
`OBSMINV2`, `COMP2`...) con la misma estructura que las de sufijo `1`. Esto es compatible con que
`TPVADMIN` sea una base multiempresa/multi-negocio, y que HPR use únicamente el conjunto `1`.
**A confirmar**: revisar la tabla `EMPRESA` y `USRXEMPRE` en la BD real para ver cuántas empresas
están configuradas y cuál corresponde a Villas HPR.

## Ausencia de un concepto explícito de "período/mes"

Ninguna tabla tiene una columna tipo `PERIODO`, `MES`, `ANIO` o similar. El único dato temporal
disponible por movimiento es `FECHA_APLI` (fecha de aplicación) y `FECHA_VENC` (fecha de
vencimiento) en `CUEN1`/`FACT1`. Esto implica que **el "mes" de una cuota se infiere por la fecha del
cargo**, no por un campo de período dedicado — importante para el diseño del nuevo sistema, que si
quiere manejar explícitamente "Enero 2020 pendiente / Febrero 2020 pagado" como pide el documento de
requerimientos, probablemente necesite agregar esa dimensión explícita en vez de heredarla.
