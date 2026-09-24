# Diseño del esquema nuevo sobre TPVADMIN

Este documento propone las extensiones necesarias sobre `TPVADMIN` para soportar el sistema de
gestión de villas descrito en `informacion/Sistema de Gestión de Saldos Villas HPR.docx`, construido
directamente sobre el esquema legado (decisión del usuario: no se crea una base de datos nueva).

**Estado: propuesta de diseño, nada de esto ha sido ejecutado.** Ningún `ALTER`/`CREATE` debe
correrse contra la base real hasta que:

1. El usuario `dev` (u otro usuario de aplicación) tenga permisos de escritura sobre `TPVADMIN`
   (hoy es `db_datareader`, solo lectura).
2. Se hayan corrido y confirmado las consultas de `docs/pending-verification.sql`.
3. El usuario dé el visto bueno explícito para ejecutar el DDL.

Contexto completo de por qué se proponen estos cambios específicos: `docs/database-overview.md`,
`docs/database-tables.md`, `docs/database-relations.md`, `docs/account-statement-logic.md`,
`docs/historical-debt.md`.

## Principio rector

Todo lo de aquí es **aditivo**: agregar columnas nuevas (nulas, sin romper filas existentes) y crear
tablas nuevas. Nunca se propone `DROP`, `ALTER COLUMN` (cambiar tipo/tamaño de algo existente), ni
tocar `USUARIOS`, `FACT1`, `PFACT1`, `RECCXC1`, `PAGFAC1` ni ninguna otra tabla no listada aquí.

---

## A. Extensiones a tablas existentes

### A.1 `CUEN1` — PK real + período explícito

**Problema que resuelve:** `CUEN1` (el libro de movimientos — cargos y abonos) no tiene ninguna
`PRIMARY KEY` declarada, lo que impide que tablas nuevas (como `APP_AUDIT_LOG`) referencien un
movimiento específico de forma confiable. Tampoco existe una columna de período: hoy el "mes" de una
cuota solo se puede inferir de `FECHA_APLI`, lo cual es fràgil para el requisito central del
proyecto (saber exactamente qué mes está pendiente).

```sql
ALTER TABLE dbo.CUEN1 ADD ID_MOV INT IDENTITY(1,1) NOT NULL;
ALTER TABLE dbo.CUEN1 ADD CONSTRAINT PK_CUEN1_IDMOV PRIMARY KEY NONCLUSTERED (ID_MOV);
ALTER TABLE dbo.CUEN1 ADD ANIO SMALLINT NULL;
ALTER TABLE dbo.CUEN1 ADD MES TINYINT NULL;
```

- `ID_MOV`: SQL Server rellena automáticamente el `IDENTITY` para las filas existentes, en orden
  físico, sin modificar ningún valor de las columnas actuales. Ningún `INSERT INTO CUEN1
  (columnas...)` explícito del sistema legado se rompe, porque ninguno de los procedimientos
  existentes (`SP_INSERTCXC1`) lista una columna identity al insertar.
- `ANIO` / `MES`: nulos a propósito, para no exigir nada de inmediato a las ~decenas de miles de
  filas históricas que puedan existir. Se llenan con:
  - **Histórico**: comando de backfill de Artisan (`php artisan villas:backfill-periodos`), que
    deriva `ANIO`/`MES` de `FECHA_APLI` fila por fila. Se corre una sola vez, después del `ALTER`.
  - **Nuevo**: el sistema Laravel los llena explícitamente en cada `INSERT` que haga a partir de
    ahora (nunca más se infiere del `FECHA_APLI`).

### A.2 `CLIE1` — contacto y fecha de nacimiento

**Problema que resuelve:** el `.docx` pide 3 números de contacto (Teléfono / Celular / Otro), 2
correos electrónicos, y fecha de nacimiento. `CLIE1` hoy solo tiene `TELF` (uno) y `MAIL` (uno), y no
tiene fecha de nacimiento en absoluto.

```sql
ALTER TABLE dbo.CLIE1 ADD CELULAR VARCHAR(20) NULL;
ALTER TABLE dbo.CLIE1 ADD OTRO_TEL VARCHAR(20) NULL;
ALTER TABLE dbo.CLIE1 ADD MAIL2 VARCHAR(30) NULL;
ALTER TABLE dbo.CLIE1 ADD FECHA_NAC DATETIME NULL;
```

Mapeo a la UI del `.docx`:

| Campo UI | Columna |
|---|---|
| Teléfono | `TELF` (ya existe) |
| Celular | `CELULAR` (nueva) |
| Otro | `OTRO_TEL` (nueva) |
| Correo Electrónico 1 | `MAIL` (ya existe) |
| Correo Electrónico 2 | `MAIL2` (nueva) |
| Fecha de nacimiento | `FECHA_NAC` (nueva) |

Campos del `.docx` que ya tienen columna existente y no requieren cambio: `#Villa` → `CLV_CLIE`,
Ubicación → `DIR`, Nombre del Propietario → `NOMBRE`, Fecha de Entrega → `FCONTRUC` (a confirmar
nombre de negocio, ver nota en `database-tables.md`), Medidor ENEE → `NOMED`, cuota mensual →
`CUOTA`, checkbox de cuota mensual → `APLICOBRO`, saldo → `SALDO`.

### A.3 `CONC1` — condicional a la verificación pendiente

No se propone ningún cambio de estructura por ahora. La consulta 1 de
`docs/pending-verification.sql` determinará si `TIPO='A'`/`'C'` es consistente en los datos reales.

- **Si es consistente** (todos los conceptos de cargo tienen la misma letra y todos los de abono la
  otra): no se toca la tabla, solo se corrige la documentación con la letra correcta y la aplicación
  Laravel usa ese mapeo confirmado.
- **Si resulta ambiguo o mixto**: se agrega una columna de override explícito:

```sql
ALTER TABLE dbo.CONC1 ADD ES_CARGO BIT NULL; -- 1 = cargo, 0 = abono
```

y se puebla manualmente registro por registro (es un catálogo pequeño, no una tabla transaccional).

### A.4 `USUARIOS` — sin cambios

Ver `APP_USERS` en la sección B — el sistema nuevo usa su propia tabla de usuarios, no extiende ni
reutiliza `USUARIOS`.

---

## B. Tablas nuevas

### B.1 `ROLES`

```sql
CREATE TABLE dbo.ROLES (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    NOMBRE VARCHAR(20) NOT NULL UNIQUE,     -- DIRECTOR, ADMIN, SUPERVISOR
    DESCRIPCION VARCHAR(255) NULL
);
```

Valores iniciales esperados (a insertar en un seeder de Laravel, no aquí):
`DIRECTOR` (full access), `ADMIN` (full access), `SUPERVISOR` (aplicar cargos/abonos/reportes, sin
gestión de usuarios ni contraseñas — permisos finos exactos pendientes de definir con el usuario).

### B.2 `APP_USERS`

**Problema que resuelve:** `USUARIOS.PASS` es `varchar(20)` — no es compatible con hashing seguro de
contraseñas (bcrypt, que usa Laravel por defecto) y muy probablemente esa tabla la sigue usando la
aplicación de escritorio legada. El sistema nuevo necesita su propia tabla de usuarios para
autenticación vía Laravel Sanctum, sin tocar ni depender de `USUARIOS`.

```sql
CREATE TABLE dbo.APP_USERS (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    NOMBRE VARCHAR(100) NOT NULL,
    EMAIL VARCHAR(150) NOT NULL UNIQUE,
    PASSWORD VARCHAR(255) NOT NULL,          -- hash bcrypt
    ROL_ID INT NOT NULL REFERENCES dbo.ROLES(ID),
    ACTIVO BIT NOT NULL DEFAULT 1,
    REMEMBER_TOKEN VARCHAR(100) NULL,
    CREATED_AT DATETIME NULL,
    UPDATED_AT DATETIME NULL
);
```

### B.3 `APP_AUDIT_LOG` (recomendado, no bloqueante)

**Problema que resuelve:** ninguna tabla legada registra con detalle quién aplicó qué cargo/abono
más allá de un `USUARIO INT` crudo en `CUEN1` (sin poder saber, por ejemplo, desde qué pantalla, con
qué observación adicional, o auditar ediciones a datos de la villa). Para un sistema que maneja
dinero de propietarios, tener trazabilidad es valioso.

```sql
CREATE TABLE dbo.APP_AUDIT_LOG (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    APP_USER_ID INT NOT NULL REFERENCES dbo.APP_USERS(ID),
    ACCION VARCHAR(50) NOT NULL,             -- CARGO_APLICADO, ABONO_APLICADO, VILLA_EDITADA, ...
    TABLA_AFECTADA VARCHAR(50) NOT NULL,     -- CUEN1, CLIE1, FACT1, ...
    REGISTRO_ID VARCHAR(50) NOT NULL,        -- ID_MOV, CLV_CLIE, CVE_DOC, según corresponda
    DETALLE VARCHAR(MAX) NULL,
    FECHA DATETIME NOT NULL DEFAULT GETDATE()
);
```

Esta tabla depende de `A.1` (necesita `CUEN1.ID_MOV` para poder referenciar un movimiento concreto en
`REGISTRO_ID` de forma confiable) y de `B.2` (`APP_USERS`). Aplicar en ese orden.

---

## C. Lógica que vive en Laravel, no en la base de datos

Estas necesidades del `.docx` **no requieren tablas ni columnas nuevas** — se resuelven con
consultas/servicios en el backend:

- **Cálculo de saldo (`CLIE1.SALDO`)**: reimplementado en un `SaldoService` de Laravel usando el
  mapeo `TIPO` ya confirmado (no depender de `SP_SALDOCLIE1`, cuya lógica de signos está en duda
  hasta la verificación pendiente). El stored procedure legado se puede seguir invocando en paralelo
  solo para comparar resultados durante la transición, no como fuente de verdad.
- **Estado de cuenta del último año** (pestaña de la villa en el modal de CRUD): `SELECT`
  sobre `CUEN1` filtrando `CCLIE` y `FECHA_APLI >= DATEADD(year, -1, GETDATE())`.
- **Reporte de estado de cuenta individual/general por rango de fechas**: `SELECT` sobre
  `CUEN1`/`FACT1` filtrando por `CCLIE`/`CVE_CLIE` y rango de `FECHA_APLI`, igual que
  `SP_DETALLECXC1` pero reimplementado en Laravel con el mapeo `TIPO` confirmado.
- **Reporte "Saldo Villas General" con antigüedad (30/60/90/+90 días) y porcentajes**: consulta
  agrupando `CUEN1` por villa, acumulando cargos pendientes por antigüedad respecto a hoy. No
  requiere tabla nueva; puede documentarse como una vista SQL de referencia
  (`VW_ANTIGUEDAD_SALDOS`, opcional) o implementarse directamente como query en el backend.
  Los porcentajes del reporte son calculados (proporción sobre el total), no almacenados.
- **Filtros "omitir saldos al día" / "omitir saldos a favor"**: filtros de la consulta anterior
  (`SALDO <> 0`, `SALDO > 0`), sin impacto en el esquema.
- **Exportar a Excel/PDF/imprimir**: responsabilidad de librerías del lado de Laravel
  (ej. `maatwebsite/excel`, `dompdf`/`barryvdh/laravel-dompdf`), sin impacto en el esquema.

---

## Orden de aplicación recomendado (cuando se autorice ejecutar)

1. `A.1` (`CUEN1`: `ID_MOV`, `ANIO`, `MES`)
2. `A.2` (`CLIE1`: contacto y fecha de nacimiento)
3. `A.3` (`CONC1`, solo si la verificación lo requiere)
4. `B.1` (`ROLES`) → `B.2` (`APP_USERS`, depende de `ROLES`) → `B.3` (`APP_AUDIT_LOG`, depende de
   `APP_USERS` y de `CUEN1.ID_MOV`)
5. Backfill de `ANIO`/`MES` en `CUEN1` histórico (Artisan command, no DDL)

## Explícitamente fuera de alcance de este documento

- Permisos finos por rol (qué exactamente puede/no puede hacer `SUPERVISOR`).
- El mapeo definitivo de `CONC1.TIPO` (condicionado a la verificación pendiente).
- Tratamiento especial de la deuda anterior a 2020, si resulta necesario.
- Scaffolding del proyecto Laravel/Vue (`backend/`, `frontend/`) — se aborda en una siguiente ronda.
