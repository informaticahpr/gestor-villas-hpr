-- =====================================================================
-- Consultas de verificación pendientes — Villas HPR / TPVADMIN
-- =====================================================================
-- SOLO LECTURA. Correr con el usuario `dev` (db_datareader) desde SSMS,
-- conectado al servidor SQL Server real (ver informacion/conexion_db.txt, no versionado) / Base: TPVADMIN
--
-- Objetivo: resolver las ambigüedades marcadas como pendientes en
-- docs/account-statement-logic.md, docs/historical-debt.md y
-- docs/new-schema-design.md ANTES de ejecutar cualquier ALTER/CREATE
-- contra la base real.
--
-- Copiar el resultado de cada bloque y compartirlo para actualizar la
-- documentación y desbloquear el diseño condicional (CONC1.ES_CARGO).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) ¿Qué significa CONC1.TIPO = 'A' vs 'C'?
--    SP_SALDOCLIE1 y SP_DETALLECXC1/SP_FACTDETALLECXC1 usan estas letras
--    en sentidos opuestos (ver docs/account-statement-logic.md). Esta
--    consulta lo resuelve mirando la descripción real de cada concepto.
-- ---------------------------------------------------------------------
SELECT NUM_CPTO, DESCR, TIPO, REFER, MOD
FROM CONC1
ORDER BY TIPO, NUM_CPTO;

-- Si aparece algo como 'CUOTA MANTENIMIENTO' o 'CARGO' con TIPO='A',
-- entonces 'A' = Cargo y 'C' = Abono (y SP_DETALLECXC1 tiene la
-- nomenclatura correcta). Si es al revés, es lo contrario.


-- ---------------------------------------------------------------------
-- 2) Rango de fechas y volumen de movimientos en CUEN1
--    Confirma si el desglose mensual histórico (pre-2020) existe como
--    filas individuales o si en algún punto se consolidó.
-- ---------------------------------------------------------------------
SELECT
    MIN(FECHA_APLI) AS movimiento_mas_antiguo,
    MAX(FECHA_APLI) AS movimiento_mas_reciente,
    COUNT(*) AS total_movimientos
FROM CUEN1;

-- ¿Existen conceptos que sugieran una consolidación/migración de saldo?
SELECT *
FROM CONC1
WHERE DESCR LIKE '%SALDO%'
   OR DESCR LIKE '%INICIAL%'
   OR DESCR LIKE '%MIGRA%'
   OR DESCR LIKE '%ANTERIOR%';

-- Historial completo y cronológico de una villa con deuda antigua conocida
-- (reemplazar 'A-1' por una villa real con historial desde 2020 o antes):
SELECT CCLIE, TIPO_MOV, NO_FACTURA, DOCTO, REFER, IMPORTE, FECHA_APLI, FECHA_VENC
FROM CUEN1
WHERE CCLIE = 'A-1'
ORDER BY FECHA_APLI;

-- ¿El total de cargos en FACT1 por villa cuadra con el total de cargos en CUEN1?
SELECT CVE_CLIE, SUM(TOTAL) AS total_facturado
FROM FACT1
GROUP BY CVE_CLIE
ORDER BY CVE_CLIE;


-- ---------------------------------------------------------------------
-- 3) ¿Cuántas "empresas" hay configuradas? ¿Se usa solo el juego de
--    tablas sufijo "1" (CLIE1, FACT1, CUEN1...) o también el "2"?
-- ---------------------------------------------------------------------
SELECT * FROM EMPRESA;

SELECT * FROM USRXEMPRE;

-- Si CLIE2 tiene registros reales (no solo estructura vacía), Villas HPR
-- podría estar usando más de una "empresa" dentro de la misma BD:
SELECT COUNT(*) AS filas_clie1 FROM CLIE1;
SELECT COUNT(*) AS filas_clie2 FROM CLIE2;
