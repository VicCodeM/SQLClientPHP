# Backlog del Proyecto: SQL Client Web (Enterprise Edition)

Este documento gestiona el backlog del proyecto siguiendo el flujo:
$$\text{Producto} \to \text{Requisitos} \to \text{Diseño} \to \text{Backlog} \to \text{Ticket} \to \text{IA} \to \text{Pruebas} \to \text{Revisión} \to \text{Git} \to \text{Siguiente}$$

---

## 📋 Épica 1: Arquitectura Base, Seguridad y Modelo de Datos

### [ ] Ticket #01: Setup del Proyecto y Estándares de Calidad
- **Descripción:** Inicialización de Laravel (PHP 8.3+), configuración de herramientas de calidad (Pest PHP, PHPStan nivel 8, Laravel Pint) y configuración de repositorios Git (Gitea/GitHub).
- **Criterios de Aceptación:**
  - Proyecto Laravel instalado y configurado limpiamente.
  - Scripts de análisis estático y pruebas corriendo sin errores.

### [ ] Ticket #02: Migraciones y Modelos del DER Interno
- **Descripción:** Implementar las migraciones para Users (Login Maestro), Roles, Permissions, Workspaces, ConnectionGroups, Connections, SSHTunnels, QueryHistories, SavedQueries y AuditLogs.
- **Criterios de Aceptación:**
  - Migraciones ejecutadas con UUIDs como llaves primarias.
  - Relaciones Eloquent e integridad referencial cubiertas con tests.

### [ ] Ticket #03: Bóveda de Conexiones y Cifrado Envelope (AES-256)
- **Descripción:** Implementar el servicio de encriptación y desencriptación segura de credenciales de bases de datos remotas y túneles SSH.
- **Criterios de Aceptación:**
  - Las contraseñas nunca se guardan en texto plano.
  - Casts / Encryptors personalizados en Eloquent probados con Pest.

---

## 📋 Épica 2: Motor de Conexiones y Adaptadores de Bases de Datos

### [ ] Ticket #04: Contrato `DatabaseDriverContract` y Driver PostgreSQL
- **Descripción:** Crear la interfaz de abstracción para motores y la implementación completa de **PostgreSQL** (inspección de esquemas, tablas, tipos de datos, vistas, funciones, triggers, secuencias).
- **Criterios de Aceptación:**
  - Conexión dinámica exitosa con test de conectividad en tiempo real.
  - Inspector de metadatos PostgreSQL probado contra esquemas reales.

### [ ] Ticket #05: Drivers MySQL, SQLite y SQLCipher (Community Edition)
- **Descripción:** Implementación de los adaptadores para MySQL/MariaDB, SQLite estándar y **SQLCipher (Community Edition)** con soporte para apertura de bases cifradas con clave/passphrase (AES-256) respetando el contrato unificado.
- **Criterios de Aceptación:**
  - Soporte de introspección de esquemas para MySQL y archivos SQLite.
  - Conexión, autenticación por frase de paso y lectura/escritura en archivos `.db` cifrados con SQLCipher.

---

## 📋 Épica 3: Capa de Ejecución, Streaming y Editor SQL

### [ ] Ticket #06: Motor de Ejecución de Consultas y Streaming SSE
- **Descripción:** Motor de ejecución asíncrono con cursores PDO y streaming de resultados vía Server-Sent Events para soportar grandes volúmenes de datos.
- **Criterios de Aceptación:**
  - Consultas pesadas no saturan la memoria RAM del servidor.
  - Métricas de tiempo de ejecución (ms) y filas afectadas calculadas con precisión.

### [ ] Ticket #07: Editor SQL con Monaco Editor, Autocompletado y Ghost Text Copilot
- **Descripción:** Integración del editor Monaco con syntax highlighting, auto-completado contextual (tablas/columnas según la conexión activa), sugerencias predictivas estilo Copilot (Ghost text) mientras se escribe y múltiples pestañas.
- **Criterios de Aceptación:**
  - Sugerencias inteligentes de nombres de tablas, columnas y JOINs automáticos basados en FKs.
  - Formateador SQL y atajos de teclado profesionales (F5, Ctrl+Enter).

---

## 📋 Épica 4: Data Grid Interactivo, Diseñador, Visual Builder y Diagramas

### [ ] Ticket #08: Data Grid Interactivo con Edición Inline
- **Descripción:** Tabla interactiva de resultados con paginación virtual, filtros por columna, ordenamiento y edición inline de celdas con generación de UPDATE/INSERT.
- **Criterios de Aceptación:**
  - Edición en caliente de celdas con confirmación de cambios.

### [ ] Ticket #09: Diseñador Visual de Tablas, Diagramas ERD y Visual Query Builder
- **Descripción:** 
  1. Renderizado interactivo del diagrama Entidad-Relación basado en claves foráneas.
  2. **Visual Query Builder interactivo:** lienzo drag & drop para agregar tablas, tildar campos, conectar columnas para JOINs, agregar filtros visuales y generar el SQL en tiempo real.
- **Criterios de Aceptación:**
  - Diagrama visual navegable con zoom/pan.
  - Constructor visual de consultas que genera SQL válido y sincronizado con el editor de código.

### [ ] Ticket #10: Historial, Snippets, Export/Import y Auditoría
- **Descripción:** Panel de historial de consultas ejecutadas, gestión de snippets guardados, asistente de exportación (CSV/JSON/SQL Dump) y registro de auditoría.
- **Criterios de Aceptación:**
  - Exportación sin cortes de memoria y registro de logs de auditoría para sentencias destructivas.

---

## 📋 Épica 5: Inteligencia Artificial & Optimización Inteligente

### [ ] Ticket #11: Asistente IA con Groq Cloud API Dinámico (Text-to-SQL y Copilot)
- **Descripción:** Integración con Groq Cloud (`console.groq.com`). Descubrimiento dinámico de modelos vía `GET /openai/v1/models`, selector en tiempo real con recomendación inteligente de modelo para SQL, generación Text-to-SQL, optimizador de consultas, corrector de errores y motor de sugerencias predictivas inline.
- **Criterios de Aceptación:**
  - Cero modelos hardcodeados en el código. Los modelos se consultan directamente desde el catálogo de Groq.
  - El sistema detecta y preselecciona con badge "(Recomendado)" el modelo superior activo para tareas de SQL.
  - Flujo de Text-to-SQL y asistente de optimización de queries con inyección de esquema contextual.
