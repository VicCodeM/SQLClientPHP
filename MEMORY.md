# Memoria del Proyecto: SQL Client Web (Estilo Navicat / DBeaver)

> **Nota de Estándar:** Este archivo `MEMORY.md` es la memoria persistente interna del proyecto. Sigue la misma estructura, formato y metodología que la memoria universal (`C:\Users\Victor\.gemini\config\MEMORY.md`).

---

## 👤 Perfil y Parámetros del Proyecto
- **Proyecto:** SQL Client Web (Enterprise Edition)
- **Ruta Local:** `c:\Dev\Web\SQLClientPHP`
- **Usuario / Responsable:** Victor
- **Idioma:** Español

---

## 🐙 Configuración de Control de Versiones (Git, Gitea y GitHub)
- **Git Principal (Origin):** **Gitea**
  * **URL:** `https://git.vmsoi.xyz`
  * **Token:** `f06039319dfa3b57d5b26b2d886cad7420a9cf4d`
- **Git Espejo / Mirror (Secundario):** **GitHub** (PC Local)
- **Regla de Sincronización:** El origen principal es Gitea. GitHub se mantiene sincronizado como espejo.
- **Explicación Obligatoria:** En cada paso, ticket o commit se documenta una **explicación clara y detallada de lo que se hizo**, el porqué y las modificaciones realizadas.

---

## 🏗️ Metodología y Protocolo de Desarrollo
Ciclo de vida obligatorio y secuencial:
$$\text{Producto} \longrightarrow \text{Requisitos} \longrightarrow \text{Diseño (Arquitectura + Módulos)} \longrightarrow \text{Backlog} \longrightarrow \text{Ticket} \longrightarrow \text{IA (Desarrollo)} \longrightarrow \text{Pruebas} \longrightarrow \text{Revisión} \longrightarrow \text{Git (Gitea + GitHub con Explicación)} \longrightarrow \text{Siguiente Ticket}$$

### 💎 Reglas de Oro de Ingeniería de Software (Obligatorias):
1. **🚫 Cero Código Repetitivo y Cero Duplicación (DRY Estricto):** Métodos concisos, altamente reutilizables y modulares. Prohibido duplicar lógica o crear funciones redundantes. Pocas líneas de código claras y directas.
2. **🧹 Cero Código Muerto:** No se permiten clases, métodos, variables o imports no utilizados o residuales.
3. **🇪🇸 Dominio y Documentación en Español:** Comentarios, excepciones, interfaces y documentación explicativa en español profesional y técnico.
4. **✍️ Redacción 100% Humana y Natural:** Las explicaciones de cada paso, commits, mensajes de error y documentación deben leerse como redactadas por un ingeniero de software senior real, eliminando cualquier frase robótica o plantilla genérica de IA.
5. **✅ Pipeline de Validación Estricta por Ticket:** Todo ticket debe pasar de forma obligatoria por: Cero duplicación + Pruebas Pest 100% + PHPStan Nivel 8 + Pint PSR-12 + Explicación clara en Git (Gitea principal / GitHub espejo).

---

## 🏛️ Definición de Arquitectura y Stack Tecnológico

### Stack Técnico
- **Backend:** PHP 8.5+ / Laravel 11/12
- **Frontend:** Inertia.js / Vue 3 (Composition API + TS) + Tailwind CSS + Monaco Editor + Vue Motion Transitions + Lucide Animated Icons
- **Testing & Calidad:** Pest PHP, PHPStan (Nivel 8), Laravel Pint (PSR-12)
- **Patrón de Arquitectura:** **Hexagonal / Clean Architecture (Ports & Adapters)** con **Driver Strategy Pattern** para los motores de base de datos.
- **Manejo de Rendimiento:** Streaming SSE (Server-Sent Events) y Cursores PDO en memoria constante $O(1)$ para datasets masivos.
- **Seguridad:** Cifrado Envelope (AES-256-GCM) para credenciales remotas, soporte para túneles SSH y certificados SSL/TLS.
- **UI/UX & Motion:** Animaciones suaves tipo IDE moderno (shimmer loaders, tabs transitions, toast feedbacks, edición inline con resalte pulsante y toolbar completa de copiado multiformato: JSON, CSV, INSERT statements, cortar, duplicar).

### Motores Soportados y Jerarquía Segregada
> **Principio de Aislamiento:** Cada motor posee su propia sección dedicada en la interfaz y en el backend. Un servidor/instancia administra **múltiples bases de datos** de forma independiente sin mezclarse en listas planas:

1. **🐘 Sección PostgreSQL** *(Prioridad Principal)*: Manejo de múltiples bases de datos por instancia, esquemas (`public`, personalizados), tipos avanzados (JSONB, Arrays, UUIDs), Triggers, Funciones PL/pgSQL, Visual EXPLAIN ANALYZE.
2. **🐬 Sección MySQL / MariaDB**: Múltiples bases de datos por servidor, Storage Engines (InnoDB/MyISAM), Stored Procedures, Triggers, Views.
3. **🔒 Sección SQLCipher (Community Edition)**: Múltiples archivos locales cifrados con contraseña/passphrase (AES-256), directivas PRAGMA de seguridad y optimización.
4. **🪶 Sección SQLite**: Gestión de múltiples archivos locales de base de datos y bases en memoria, modo WAL y vacuum.
5. **🪟 Sección SQL Server / Community**: Múltiples bases de datos por instancia vía adaptadores desacoplados `DatabaseDriverContract`.

---

## 🧩 Módulos del Sistema
1. **M1: Auth & Master Vault:** Login maestro, RBAC, gestión de usuarios y cifrado AES-256 de credenciales.
2. **M2: Connection & SSH Tunnel Manager:** Bóveda de conexiones, prueba de conectividad en vivo, túneles SSH y organización por grupos/entornos.
3. **M3: Schema & Metadata Explorer:** Árbol reactivo de objetos (schemas, tablas, vistas, funciones, triggers, secuencias).
4. **M4: Advanced Monaco SQL Editor & Ghost Text Copilot:** Autocompletado inteligente contextual, sugerencias predictivas estilo Copilot mientras se escribe (Ghost text para JOINs, WHERE y agregaciones), resaltado de sintaxis, multi-tab y visor de plan de ejecución.
5. **M5: Interactive Data Grid:** Hoja de datos con paginación virtual, filtros, ordenamiento y edición inline con generación de DML.
6. **M6: Table Designer & DDL Generator:** Creador visual de tablas con previsualización DDL en tiempo real.
7. **M7: Visual ERD & Visual Query Builder:** Diagramas Entidad-Relación interactivos y **Constructor Visual de Consultas** (lienzo drag & drop para unir tablas visualmente con JOINs, seleccionar columnas con checkbox y previsualizar SQL en vivo).
8. **M8: Import / Export & Dump Engine:** Asistente de exportación/importación (CSV, JSON, SQL Dumps).
9. **M9: History, Snippets & Audit Logger:** Historial de ejecuciones, snippets compartidos y logs de auditoría de sentencias destructivas.
10. **M10: AI SQL Assistant (Groq Cloud Dynamic Engine):** Asistente IA para Text-to-SQL, optimización de queries, análisis EXPLAIN, auto-fix de errores y motor Copilot inline. Conexión dinámica a `console.groq.com`, descubrimiento en tiempo real de todos los modelos disponibles (sin hardcoding) y recomendación automática del mejor modelo para SQL.

---

## 📋 Backlog de Tickets y Estado de Avance

| Ticket | Descripción | Estado |
| :--- | :--- | :---: |
| **#01** | Inicialización del proyecto Laravel 13, herramientas de calidad (Pest, PHPStan Lvl 8, Pint) y configuración de remotos Git (Gitea principal / GitHub espejo). | **Completado** |
| **#02** | Migraciones y modelos del DER interno (Users, Roles, Workspaces, Connections, SSH Tunnels, History, Audit) con UUIDs y pruebas de integración. | **Completado** |
| **#03** | Bóveda de conexiones seguras y servicio de cifrado Envelope AES-256-GCM con derivación HKDF y DTOs inmutables. | **Completado** |
| **#04** | Contrato `DatabaseDriverContract`, clase base DRY e Implementación completa del **Driver PostgreSQL** (introspección profunda, DDL y EXPLAIN). | **Completado** |
| **#05** | Implementación de Drivers secundarios (**MySQL/MariaDB**, **SQLite** y **SQLCipher Community Edition** con soporte para bases cifradas). | **Completado** |
| **#06** | Motor de Ejecución de Consultas con Paginación de Cursores y Streaming Server-Sent Events ($O(1)$ RAM). | **Completado** |
| **#07** | Integración del Frontend (Inertia.js + Vue 3 + Tailwind CSS), Monaco SQL Editor con Autocompletado inteligente y atajos F5 / Ctrl+Enter. | **Completado** |
| **#08** | Data Grid Interactivo con Edición Inline de registros, inserción modal, borrado seguro y concurrencia por clave primaria. | **Completado** |
| **#09** | Diseñador Visual de Tablas (DDL en tiempo real), Diagramas ERD y **Visual Query Builder** interactivo. | **Completado** |
| **#10** | Historial de Consultas con re-ejecución, Snippets de Código con tags y Registro de Auditoría de Seguridad. | **Completado** |
| **#11** | Asistente IA con Groq Cloud: Descubrimiento dinámico de modelos (`/models`), recomendación automática, Text-to-SQL, Copilot y optimización. | Pendiente |

---

## 📝 Registro Histórico de Pasos y Commits

### 🔹 Ticket #01: Setup del Proyecto, Estándares de Calidad y Control de Versiones Dual
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Creación e instalación limpia del esqueleto **Laravel 13.29.0** con **PHP 8.5.1 (ZTS x64)**.
  2. Instalación y configuración del framework de pruebas **Pest PHP 4.7** con integración de Laravel.
  3. Configuración del analizador estático **Larastan / PHPStan 2.2** en **Nivel 8 Estricto** (`phpstan.neon`).
  4. Configuración del formateador y linter **Laravel Pint** con estándar PSR-12 (`pint.json`).
  5. Adición de scripts unificados de calidad en `composer.json` (`composer format`, `composer analyse`, `composer test`, `composer check`).
  6. Creación y vinculación del repositorio principal en **Gitea** (`https://git.vmsoi.xyz/Victor/SQLClientPHP.git`) como `origin` y del repositorio espejo en **GitHub** (`https://github.com/VicCodeM/SQLClientPHP.git`) como `github`.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 2 tests pasados exitosamente.
- **Commit Asociado:** `feat(setup): initialize Laravel 13 project with Pest, PHPStan Level 8, Pint and dual Git remotes`

### 🔹 Ticket #02: Modelos Eloquent y Migraciones del DER Interno con UUIDs
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Creación y ejecución de 7 migraciones de base de datos con identificadores UUID como claves primarias y foráneas con eliminación en cascada donde corresponde.
  2. Implementación de los 10 modelos Eloquent del núcleo del sistema:
     - `User`: Administrador maestro, control de estados y verificación de permisos.
     - `Role` y `Permission`: Control de acceso granular basado en roles (RBAC).
     - `Workspace` y `ConnectionGroup`: Organización de entornos de trabajo y agrupación visual por color.
     - `Connection`: Soporte multimotor (`pgsql`, `mysql`, `sqlite`, `sqlcipher`, `sqlsrv`) con credenciales y contraseñas cifradas en reposo.
     - `SshTunnel`: Configuración de túneles SSH bastion con credenciales y llaves privadas encriptadas.
     - `QueryHistory`, `SavedQuery` y `AuditLog`: Trazabilidad de consultas, snippets con etiquetas y auditoría de acciones sensibles.
  3. Creación de suite completa de pruebas en Pest (`tests/Feature/InternalDatabaseModelsTest.php`) validando relaciones, persistencia, integridad referencial y comprobación de permisos.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 6 tests pasados con 18 aserciones.
- **Commit Asociado:** `feat(core): implement internal database migrations and Eloquent models with UUIDs and RBAC`

### 🔹 Ticket #03: Bóveda de Cifrado Envelope AES-256-GCM y Capa de DTOs Seguros
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Creación de la interfaz `EncryptedVaultContract` y la implementación `EncryptedVaultService` con algoritmo autenticado **AES-256-GCM** y derivación de clave con **HKDF (SHA-256)**.
  2. Jerarquía de excepciones de seguridad (`VaultException`, `EncryptionException`, `DecryptionException`) con detección de paquetes manipulados o firmas MAC inválidas.
  3. Creación de DTOs inmutables de solo lectura (`ConnectionConfigDTO`, `SshTunnelDTO`) para el paso tipado y seguro de configuraciones de bases de datos remotas y túneles SSH.
  4. Registro y vinculación del servicio en `AppServiceProvider` como Singleton de la aplicación.
  5. Suite de pruebas en Pest (`tests/Feature/EncryptedVaultServiceTest.php`) validando cifrado, descifrado, verificación AAD contra manipulación, detección de integridad y resolución de DTOs.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 10 tests pasados con 36 aserciones.
- **Commit Asociado:** `feat(vault): implement AES-256-GCM authenticated vault service and typed ConnectionConfigDTOs`

### 🔹 Ticket #04: DatabaseDriverContract y Driver PostgreSQL Completo
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Creación de la suite de DTOs de metadatos de base de datos (`TableMetadataDTO`, `ColumnMetadataDTO`, `IndexMetadataDTO`, `ForeignKeyMetadataDTO`, `ViewMetadataDTO`, `FunctionMetadataDTO`, `TriggerMetadataDTO`, `SequenceMetadataDTO`, `QueryResultDTO`, `ExplainResultDTO`).
  2. Definición del contrato `DatabaseDriverContract` y la clase base abstracta reutilizable `AbstractDatabaseDriver` (principio DRY).
  3. Implementación integral de `PostgresDriver` con soporte para introspección profunda de esquemas, tablas, vistas materializadas, funciones PL/pgSQL, triggers, secuencias, generador DDL inverso y planes de ejecución JSON.
  4. Implementación de `DatabaseDriverManager` (Factory / Registry) con soporte para extensión de drivers comunitarios (`extend()`).
  5. Suite de pruebas en Pest (`tests/Feature/PostgresDriverTest.php`) validando resolución, contratos, extensibilidad y generación de DDL.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 14 tests pasados con 46 aserciones.
- **Commit Asociado:** `feat(drivers): implement DatabaseDriverContract, PostgresDriver and DatabaseDriverManager`

### 🔹 Ticket #05: Drivers Secundarios (MySQL, SQLite y SQLCipher Community Edition)
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Implementación de `MySQLDriver` con soporte para MySQL y MariaDB (introspección en `information_schema`, storage engines, claves foráneas, índices compuestos, triggers, procedures, generador DDL y `EXPLAIN FORMAT=JSON`).
  2. Implementación de `SQLiteDriver` para bases de datos en archivo local y en memoria `:memory:`, con soporte para `PRAGMA table_info`, `PRAGMA foreign_key_list`, `PRAGMA index_list`, `sqlite_master` y `EXPLAIN QUERY PLAN`.
  3. Implementación de `SQLCipherDriver` (extensión directa y DRY de `SQLiteDriver`) con soporte transparente para bases de datos cifradas con AES-256 mediante directivas `PRAGMA key`, compatibilidad v3/v4, tamaño de página y rondas KDF.
  4. Actualización del catálogo y registro en `DatabaseDriverManager` para resolución automática de `mysql`, `mariadb`, `sqlite` y `sqlcipher`.
  5. Suite de pruebas en Pest (`tests/Feature/SecondaryDriversTest.php`) validando introspección completa en memoria, ejecución parametrizada y configuración de parámetros de cifrado.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 17 tests pasados con 74 aserciones.
- **Commit Asociado:** `feat(drivers): implement MySQLDriver, SQLiteDriver and SQLCipherDriver adapters`

### 🔹 Ticket #06: Motor de Ejecución de Consultas y Streaming Server-Sent Events ($O(1)$ RAM)
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Creación de la interfaz `QueryExecutionEngineContract` y servicio `QueryExecutionEngineService` para ejecución síncrona y streaming reactivo en memoria constante.
  2. Implementación de generadores PHP (`yield`) para streaming de grandes volúmenes de filas por paquetes (chunks) hacia el frontend vía Server-Sent Events (SSE).
  3. Mecanismo de protección para conexiones en modo "Solo Lectura" (`is_read_only`), bloqueando operaciones de mutación (`DROP`, `TRUNCATE`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`) con la excepción `ReadOnlyViolationException`.
  4. Registro automático en `QueryHistory` (duración en ms, estado, filas afectadas) y en `AuditLog` para sentencias DDL y modificaciones críticas.
  5. Controlador API `QueryExecutionController` y rutas REST/SSE registradas en `routes/api.php`.
  6. Suite de pruebas en Pest (`tests/Feature/QueryExecutionEngineTest.php`) validando ejecución, streaming por chunks, bloqueo de sólo lectura y endpoints API.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 21 tests pasados con 105 aserciones.
- **Commit Asociado:** `feat(engine): implement QueryExecutionEngineService with O(1) RAM SSE streaming and read-only protection`

### 🔹 Ticket #07: Frontend Inertia.js, Vue 3 y Monaco SQL Editor con Autocompletado
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Instalación y configuración de la capa SPA moderna: **Inertia.js**, **Vue 3 (Composition API & TypeScript)** y **Tailwind CSS**.
  2. Creación del componente reutilizable `MonacoSqlEditor.vue` con tema oscuro (`vs-dark`), resaltado de sintaxis SQL, autocompletado predictivo para palabras clave y objetos de la base de datos, y atajos de ejecución por teclado (`F5` y `Ctrl+Enter`).
  3. Implementación de la vista principal `Studio/Index.vue` con soporte multi-pestaña, selector dinámico de conexiones activas con insignias visuales de entorno (`Production`, `Staging`, `Development`, `Read-Only`) y barra de exportación rápida (JSON, CSV, INSERTs).
  4. Creación de `StudioController.php` y rutas web `/` y `/studio`.
  5. Pruebas de integración en Pest (`tests/Feature/StudioWorkspaceTest.php`) validando la respuesta reactiva de Inertia.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 22 tests pasados con 119 aserciones.
  - `npm run build`: Compilación exitosa en 0 errores.
- **Commit Asociado:** `feat(ui): implement Inertia Vue 3 studio layout and Monaco SQL editor with autocompletion`

### 🔹 Ticket #08: Data Grid Interactivo con Edición Inline de Registros
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Creación del componente `InteractiveDataGrid.vue` con soporte para edición en caliente por celda (doble clic), marcado dirty en ámbar, botón de revertir cambios y botón de persistencia atómica por lotes.
  2. Implementación de modal de inserción con generación de formularios dinámicos a partir de las columnas de la tabla y eliminación segura con comprobación de claves primarias.
  3. Controlador API `TableDataController.php` con endpoints seguros para paginación (`table/data`), actualización inline (`table/row/update`), inserción (`table/row/insert`) y borrado (`table/row/delete`).
  4. Protección estricta con excepción `ReadOnlyViolationException` ante cualquier intento de edición en conexiones de sólo lectura.
  5. Registro automático en `AuditLog` (`DML_UPDATE_INLINE`, `DML_INSERT_ROW`, `DML_DELETE_ROW`) con dirección IP y detalle de los registros modificados.
  6. Suite de pruebas en Pest (`tests/Feature/TableDataControllerTest.php`) con 4 escenarios de prueba completos.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 26 tests pasados con 137 aserciones.
  - `npm run build`: Compilación exitosa en 0 errores.
- **Commit Asociado:** `feat(grid): implement InteractiveDataGrid with inline editing, row insertion, deletion and audit logs`

### 🔹 Ticket #09: Diseñador Visual de Tablas, Diagramas ERD y Visual Query Builder
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Implementación de `TableDesigner.vue`: creación gráfica de esquemas de tabla, tipos de datos, constraints PK/FK, valores por defecto y generador de previsualización DDL reactivo.
  2. Implementación de `ErdDiagramView.vue`: extracción y visualización gráfica del grafo relacional de esquemas (nodos de tabla con atributos e indicadores de clave primaria y aristas de relaciones de clave foránea).
  3. Implementación de `VisualQueryBuilder.vue`: constructor visual de consultas SQL (selección de campos, JOINs interactivos, filtros WHERE con operadores booleanos AND/OR, ordenamiento y botón de apertura en el editor SQL).
  4. Controlador API `SchemaDesignController.php` con endpoints `GET /api/v1/connections/{id}/schema/erd` y `POST /api/v1/connections/{id}/table/create`.
  5. Suite de pruebas en Pest (`tests/Feature/SchemaDesignControllerTest.php`) con 3 tests completos.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 29 tests pasados con 151 aserciones.
  - `npm run build`: Compilación exitosa en 0 errores.
- **Commit Asociado:** `feat(designer): implement visual TableDesigner, ErdDiagramView and VisualQueryBuilder components`

### 🔹 Ticket #10: Historial de Consultas, Snippets de Código y Pista de Auditoría
- **Fecha:** 2026-08-26
- **Acciones Realizadas:**
  1. Implementación de `QueryHistoryDrawer.vue`: panel deslizante de historial con filtrado por estado (`success`/`error`), búsqueda textual y re-ejecución instantánea hacia el editor SQL.
  2. Implementación de `SavedQueriesModal.vue`: modal para almacenamiento, edición, categorización con tags y apertura de snippets SQL reutilizables.
  3. Implementación de `AuditLogModal.vue`: visor de auditoría administrativa para trazabilidad de sentencias DDL y mutaciones DML con visualización JSON expandible e IP de cliente.
  4. Controladores API `QueryHistoryController.php`, `SavedQueryController.php` y `AuditLogController.php` con endpoints REST registrados en `routes/api.php`.
  5. Suite de pruebas en Pest (`tests/Feature/HistorySnippetsAuditTest.php`) validando filtrado de historial, CRUD de snippets y consulta de auditoría.
- **Resultados de Calidad:**
  - `composer format` (Pint): 100% aprobado.
  - `composer analyse` (PHPStan Lvl 8): 0 errores.
  - `composer test` (Pest): 32 tests pasados con 167 aserciones.
  - `npm run build`: Compilación exitosa en 0 errores.
- **Commit Asociado:** `feat(history): implement query history drawer, saved query snippets and security audit log viewer`
