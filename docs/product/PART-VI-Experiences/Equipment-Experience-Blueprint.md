# Equipment Experience Blueprint

**Sprint:** UX-3.B · **Estado:** `Borrador — pendiente aprobación` · **Fecha:** Junio 2026

**Autoridad:** Este documento es la especificación de diseño oficial del módulo Equipos. Todo desarrollo dentro del módulo deberá obedecer este Blueprint. Los cambios al Blueprint requieren revisión de producto.

**Input:** [UX-3.0 — Equipment Experience Discovery](../PART-VI-Discovery/UX-3.0-Equipment-Discovery.md)

---

## I. Propósito del módulo

### El problema que resuelve

En mantenimiento industrial, el conocimiento sobre un activo vive en cuatro lugares simultáneos: en la memoria del técnico que lo opera hace cinco años, en una carpeta de manuales en la oficina del jefe de mantenimiento, en un Excel de historial de fallas actualizado a medias, y en el sistema CMMS que nadie consulta porque "nunca tiene la información que necesitas cuando la necesitas".

El módulo de Equipos de Fronda existe para ser ese quinto lugar que reemplaza a los cuatro anteriores.

No es un catálogo de inventario. Un catálogo responde "¿qué equipos tenemos?". El módulo de Equipos de Fronda responde "¿cuál es el estado operacional de cada activo, qué pasó con él, qué debería pasar, y qué necesito hacer ahora?"

### Las decisiones que ayuda a tomar

**El técnico decide:** ¿Existe ya una OT para este problema o debo reportar uno nuevo? ¿Cómo se hizo el mantenimiento anterior de este equipo? ¿Dónde está el manual?

**El supervisor decide:** ¿Cuáles de mis equipos requieren atención hoy? ¿Este equipo puede seguir operando o necesita intervención inmediata? ¿Tengo capacidad técnica para atenderlo?

**El planeador decide:** ¿Qué equipos no tienen plan preventivo activo? ¿Cuáles tienen preventivos vencidos? ¿Cuál es la tendencia de disponibilidad del mes anterior?

**El gerente decide:** ¿Qué equipos son los más problemáticos y cuánto cuestan? ¿Estamos cumpliendo los indicadores de disponibilidad comprometidos?

**El administrador decide:** ¿Cómo estructura la jerarquía de activos (planta → área → equipo → componente)? ¿Qué datos son obligatorios para registrar un equipo nuevo?

### Por qué existe la Ficha 360°

La Ficha de Equipo es el corazón del módulo porque en la industria, las preguntas sobre un activo no son de un solo tipo. En una sola conversación sobre un compresor, el supervisor puede necesitar: su número de serie (para llamar al fabricante), cuántas fallas tuvo este año (para decidir si reparar o reemplazar), cuándo vence el próximo preventivo (para coordinar el turno), y quién hizo el último mantenimiento (para pedirle detalles). Toda esa información debe estar en el mismo lugar — no distribuida en cuatro módulos distintos.

---

## II. Principios del módulo

Estos principios son exclusivos del módulo de Equipos. Complementan los principios generales de The Fronda Book sin contradecirlos.

---

**1. La ficha del activo es el centro del sistema**

Todo flujo que involucra a un equipo comienza o regresa a su ficha. Crear una OT para el equipo, ver su historial, acceder a sus documentos, registrar una lectura — todas estas acciones son accesibles desde la ficha sin salir a otro módulo.

La ficha no es de consulta. Es de trabajo.

---

**2. El QR siempre responde una pregunta**

Cuando un técnico escanea el código QR de un equipo, la pantalla que aparece responde inmediatamente: ¿hay algo que debo atender? La primera información visible no es el nombre del equipo ni su código — es su estado operacional y las alertas activas. Si hay OTs abiertas, las muestra. Si hay un preventivo vencido, lo muestra. Si todo está bien, lo dice también.

El QR es la puerta de entrada al sistema para el usuario de campo. Esa puerta debe abrir al lugar correcto.

---

**3. El técnico nunca busca información dos veces**

Un técnico que llegó a la ficha de un equipo ya tiene el contexto. El sistema no debe obligarle a reconstruirlo cuando realiza una acción desde ahí. Si crea una OT desde la ficha del equipo, la OT nace con el equipo preseleccionado. Si reporta un problema, el equipo está en el reporte. El contexto viaja con la acción.

---

**4. Toda acción nace desde el activo**

El flujo correcto no es: ir a Órdenes de Trabajo → crear nueva OT → buscar el equipo. El flujo correcto es: estar en la ficha del equipo → crear OT → la OT ya tiene el equipo.

Este principio se aplica a todas las acciones: crear OT, reportar problema, registrar lectura de medidor, asignar plan preventivo. El activo es el punto de partida, no el resultado de una búsqueda.

---

**5. El estado del activo cambia lo que el usuario ve**

Un equipo en estado "En mantenimiento" muestra la OT activa prominentemente. Un equipo "Activo" muestra sus KPIs de disponibilidad. Un equipo "Crítico" muestra su historial de fallas recientes. Un equipo "Retirado" muestra su fecha de retiro y el motivo. La ficha no es estática — se adapta al estado del activo para mostrar la información más relevante para ese estado.

---

**6. Los KPIs siempre tienen período**

Un indicador sin período no es un indicador. Disponibilidad: 87.3% — ¿de cuándo? El módulo nunca muestra un KPI sin su período de referencia. Siempre: "87.3% · últimos 90 días". Y siempre con comparativa: "↑2.1pp vs período anterior".

---

**7. La lista responde antes de que el usuario filtre**

La vista por defecto de la lista de equipos no es "todos los equipos". Es "los equipos que requieren tu atención ahora" — filtrados por rol. Un supervisor ve primero sus equipos con alertas activas. Un técnico ve los equipos con OTs asignadas a él. El jefe de mantenimiento ve los equipos críticos con preventivos vencidos. El filtro "Todos" existe, pero no es el punto de partida.

---

## III. Las superficies del módulo

### Desktop — Panel de operaciones

**Propósito:** El espacio de trabajo del supervisor, planeador, y jefe de mantenimiento. Donde se analiza, se planifica, y se gestiona la flota completa.

**Puede hacer:**
- Ver la lista completa de equipos con filtros multidimensionales
- Acceder a la Ficha 360° con toda la información del activo
- Crear OTs, reportar problemas, registrar lecturas desde la ficha
- Ver KPIs con período y comparativa
- Navegar entre entidades relacionadas (equipo → OT → equipo) sin perder contexto
- Gestionar componentes y sub-equipos
- Descargar PDF del equipo
- Editar campos operacionales del equipo (estado, criticidad, notas, ubicación)

**No debe hacer:**
- Reemplazar al panel de administración para la creación de equipos nuevos o la configuración de campos maestros
- Mostrar KPIs sin período explícito
- Llevar al usuario a un contexto externo (Filament) para acciones comunes
- Duplicar información en más de un lugar de la misma pantalla

---

### Mobile PWA — Herramienta de campo

**Propósito:** La herramienta del técnico durante la intervención. Diseñada para usarse con una mano, con guantes, con luz variable, bajo presión de tiempo.

**Puede hacer:**
- Escanear QR y llegar a la ficha del equipo
- Ver el estado operacional inmediato: OTs activas, alertas, preventivos vencidos
- Acceder a documentación técnica (PDFs de manuales, esquemas)
- Crear una OT o reportar un problema con el mínimo de pasos
- Registrar una lectura de medidor
- Ver el historial reciente de intervenciones (últimas 5)
- Ver los datos básicos de identificación del equipo

**No debe hacer:**
- Mostrar KPIs de confiabilidad (MTBF, MTTR, disponibilidad) — no son útiles en campo
- Mostrar información financiera (costo de reemplazo, vida útil)
- Permitir edición de datos maestros del equipo
- Mostrar el historial completo paginado (solo las últimas 5 intervenciones)
- Requerir más de 3 taps para ejecutar cualquier acción primaria

---

### Admin Panel (Filament) — Configuración de activos

**Propósito:** La herramienta del administrador para crear y configurar equipos. No es el lugar de trabajo diario — es el backstage.

**Puede hacer:**
- Crear equipos nuevos con todos sus campos maestros
- Configurar categorías, fabricantes, proveedores
- Gestionar la jerarquía de activos
- Importar equipos desde plantilla
- Gestionar documentos y fotos del equipo
- Configurar campos personalizados por categoría
- Administrar permisos de acceso por planta

**No debe hacer:**
- Ser el lugar donde el supervisor actualiza el estado de un equipo
- Ser necesario para acciones operacionales cotidianas

---

### QR — Puerta de entrada física-digital

**Propósito:** El puente entre el activo físico y su representación digital. El técnico que ve el QR en el equipo escanea y llega directamente a la ficha mobile.

**Debe responder siempre:**
- ¿Hay OTs activas para este equipo?
- ¿Hay alertas o preventivos vencidos?
- ¿Qué acciones están disponibles?

**La URL del QR** apunta a la ficha mobile del equipo. Si el usuario está autenticado, llega directamente. Si no, llega al login y después a la ficha.

**No debe hacer:**
- Llegar a la ficha desktop (el QR es para campo, el campo es mobile)
- Mostrar una página de "equipo no encontrado" sin opciones de acción
- Requerir que el usuario busque el equipo después de escanear

---

### Offline — Campo sin señal

**Propósito:** Fronda en campo opera en entornos con conectividad intermitente. El módulo de Equipos debe funcionar en modo offline para las acciones críticas.

**Offline disponible:**
- Ver la ficha del equipo si fue visitada recientemente (cache de las últimas 10 fichas)
- Ver documentación técnica descargada previamente
- Crear un reporte de problema (sincroniza cuando hay señal)
- Registrar una lectura de medidor (sincroniza cuando hay señal)

**Offline no disponible:**
- Ver OTs actualizadas en tiempo real
- Crear OTs completas (requiere confirmación del servidor)
- Acceder a documentos no descargados previamente

El modo offline no es transparente: la interfaz indica claramente cuando está mostrando datos en caché y cuándo fue la última sincronización.

---

### PDF — El equipo en papel

**Propósito:** La representación del equipo para contextos donde el sistema no está disponible: reuniones, inspecciones, auditorías, contratos.

**Incluye:** Identidad completa, KPIs del período más reciente con su período explícito, historial de OTs del último año, planes preventivos activos, lista de documentos disponibles (con fechas de vencimiento si aplica), fotos principales.

**No incluye:** Historial completo ilimitado, información financiera detallada, datos de acceso (tokens, QR clicable).

---

### API — Integraciones

**Propósito:** Exponer los datos del módulo a sistemas externos: ERPs, sistemas de producción, herramientas de análisis.

**Expone:** Ficha del equipo, KPIs calculados (con período), historial de actividad, estado operacional en tiempo real.

**No expone:** Datos financieros sin autenticación de rol gerencial, datos de otros tenants (aislamiento estricto).

---

## IV. Arquitectura de navegación

### El flujo fundamental

La arquitectura de navegación del módulo está diseñada para que el usuario pueda llegar, actuar, y regresar sin perder contexto en ningún punto.

```
LISTA DE EQUIPOS
      │
      ▼
FICHA DEL EQUIPO ←──────────────────────────────┐
      │                                          │
      ├──→ CREAR OT ──→ FICHA DE OT ──→ [Volver al equipo]
      │
      ├──→ REPORTAR PROBLEMA ──→ SOLICITUD CREADA ──→ [Volver al equipo]
      │
      ├──→ VER TODAS LAS OTs DEL EQUIPO (lista filtrada) ──→ FICHA OT ──┐
      │                                                                   │
      │         [Volver a OTs del EQ-001 ←]  [Volver al equipo ←]       │
      │                                                                   │
      ├──→ FICHA DE EQUIPO PADRE (via Context Banner) ──→ [Volver ←]    │
      │                                                                   │
      ├──→ FICHA DE COMPONENTE / SUB-EQUIPO ──→ [Volver al equipo ←]   │
      │                                                                   │
      ├──→ PLANTA / ÁREA (via link en ubicación) ──→ [Volver ←]         │
      │                                                                   │
      └──→ DOCUMENTO TÉCNICO (PDF viewer / download) ──→ [Volver ←]     │
                                                                          │
                                              FICHA DE OT ───────────────┘
```

**Regla de navegación:** Toda salida de la ficha del equipo produce un "Volver a [Nombre del equipo]" visible. El usuario nunca tiene que recordar de dónde vino.

**Implementación técnica del contexto:** El parámetro `from` y `fromId` en la URL ya existe en el código actual. Se extiende para cubrir todos los flujos descritos arriba.

---

### Flujos de entrada al módulo

```
MENÚ LATERAL → Equipos → LISTA → FICHA
COMMAND PALETTE (⌘K) → buscar equipo → FICHA
QR SCAN (mobile) → directamente → FICHA MOBILE
HOME → "Mis equipos recientes" (favoritos) → FICHA
HOME → "Equipos con alertas" → FICHA
NOTIFICACIÓN → preventivo vencido → FICHA → sección Mantenimiento
OT → link al equipo → FICHA → [Volver a OT]
```

---

### Profundidad máxima de navegación

El usuario nunca debe estar a más de 3 niveles de la ficha del equipo para completar una acción relacionada con ese equipo. Si una acción requiere más de 3 niveles, es señal de que el flujo está mal diseñado.

---

## V. Arquitectura de la Ficha 360°

### El problema del orden actual

El orden actual de secciones (Información → Estado → Componentes → OTs → Preventivos → Repuestos → Fotos → Documentos → Historial) fue construido incrementalmente, no diseñado. Cada sección se agregó cuando la funcionalidad estuvo lista.

El orden ideal se construye respondiendo: ¿qué necesita ver primero el usuario más frecuente de la ficha?

El usuario más frecuente de la ficha es el **supervisor que llega a un equipo después de recibir un reporte de problema**. Necesita: estado operacional actual → acciones disponibles → historial reciente → contexto de mantenimiento.

---

### Nueva arquitectura de secciones

```
STICKY HEADER (siempre visible)
├── Breadcrumbs con ancestros
├── Identidad: foto · código · nombre · badges · ubicación
├── KPI Strip: Disponibilidad · MTBF · MTTR · Fallas · Downtime
│             [período: últimos 90d ▾]  [↑↓ vs período anterior]
└── BARRA DE ACCIONES PRIMARIAS
    [Crear OT ▾]  [Reportar problema]  [Registrar lectura]  [···]
    
ANCHOR NAV
[Operación] [Mantenimiento] [Activo] [Docs & Fotos] [Historial]

═══════════════════════════════════════════════════════════════
SECCIÓN: OPERACIÓN  (primera en carga, responde "¿qué pasa ahora?")
│
├── Context Banner: equipo padre (si es componente)
├── Estado operacional actual
│   ├── Si "en mantenimiento": OT activa con técnico y tiempo estimado
│   ├── Si "activo con OTs": lista de OTs abiertas (max 3, link a todas)
│   └── Si "activo sin alertas": resumen de última intervención
│
└── Sub-equipos con alertas (si los hay): máx 3 con señal visual

═══════════════════════════════════════════════════════════════
SECCIÓN: MANTENIMIENTO  ("¿qué está programado?")
│
├── Planes preventivos activos con schedule
│   └── Próximo vencimiento prominente si está en riesgo
├── Historial de OTs recientes (últimas 6)
│   └── [Ver todas las OTs de este equipo →] (con filtro pre-aplicado)
└── Repuestos más utilizados (top 5 de los últimos 12 meses)

═══════════════════════════════════════════════════════════════
SECCIÓN: ACTIVO  ("¿qué es este equipo?")
│
├── Identificación: código, modelo, N° serie, asset tag
├── Ubicación: planta (link), área (link), notas de ubicación
├── Fabricante & Proveedor
├── Fechas: compra, instalación, puesta en marcha, garantía
└── Financiero: precio de compra, costo de reemplazo, vida útil

═══════════════════════════════════════════════════════════════
SECCIÓN: DOCS & FOTOS  ("¿qué documentación existe?")
│
├── Documentos ordenados por tipo
│   ├── Procedimientos de mantenimiento (destacados en mobile)
│   ├── Manuales y datasheets
│   └── Otros documentos
└── Galería de fotos con lightbox

═══════════════════════════════════════════════════════════════
SECCIÓN: HISTORIAL  ("¿qué pasó con este equipo?")
│
├── Filtros por tipo de evento: [Todos] [OTs] [Preventivos] [Paradas] [Lecturas]
└── Timeline con eventos paginados (50 por carga, botón "ver más")
```

### Por qué este orden

**Operación primero:** El supervisor que llega con urgencia no quiere ver la fecha de instalación — quiere saber si el equipo tiene OTs abiertas y qué hacer. La sección Operación responde "¿qué pasa ahora?".

**Mantenimiento segundo:** Después de entender el estado actual, el usuario necesita el contexto de mantenimiento: ¿qué está programado? ¿cuándo fue la última intervención? ¿qué repuestos se usan más?

**Activo tercero:** La información estática (número de serie, fabricante, fechas) rara vez cambia y se consulta con menos frecuencia. Va al tercer lugar.

**Docs & Fotos cuarto:** Documentos específicos que se consultan cuando se necesitan, no en cada visita.

**Historial último:** El historial completo es para análisis y auditoría, no para la operación diaria. Va al final donde los usuarios analíticos pueden encontrarlo.

---

### Consolidación del KPI duplicado

**Hoy:** Los KPIs aparecen en el header strip (pequeños) y de nuevo en la sección "Estado del activo" (grandes). Dos veces la misma información.

**Blueprint:** Los KPIs aparecen una sola vez: en el header strip, con un selector de período y una comparativa contra el período anterior. La sección "Estado del activo" desaparece como sección independiente. Su contenido único (Costo acumulado, Última intervención) se integra en la sección Mantenimiento donde tiene contexto semántico correcto.

---

## VI. Mapa de acciones por rol

### Técnico

**Las tres acciones principales:**

| Acción | Dónde aparece | Clicks |
|---|---|---|
| Ver OTs asignadas al equipo | Sección Operación → OTs activas | 0 (visible al llegar) |
| Crear OT para el equipo | Barra de acciones del header → [Crear OT] | 1 click |
| Acceder a documentación técnica | Sección Docs & Fotos → Procedimientos | 2 clicks (nav + doc) |

**En mobile (campo):**

| Acción | Dónde aparece | Taps |
|---|---|---|
| Ver OTs activas del equipo | Primera sección visible tras QR scan | 0 (visible al llegar) |
| Reportar problema | Botón primario en la ficha | 1 tap |
| Abrir manual PDF | Sección Documentos → Procedimientos | 2 taps |

**Lo que el técnico nunca tiene que hacer:**
- Salir de la ficha para buscar si hay una OT de este equipo
- Ir al módulo de Documentos para buscar el manual
- Crear una OT y luego buscar el equipo manualmente

---

### Supervisor

**Las tres acciones principales:**

| Acción | Dónde aparece | Clicks |
|---|---|---|
| Ver equipos de su área con alertas | Lista con filtro de área pre-aplicado | 0 (filtro guardado) |
| Crear OT para un equipo | Ficha del equipo → [Crear OT ▾] → Correctiva | 2 clicks |
| Ver estado de todos sus equipos | Lista → filtro por área | 1 click |

**El flujo de inicio de turno:**
```
1. Abrir Fronda → Lista de Equipos (filtro guardado: "Mi área + Activos")
2. Identificar equipos con señales → click en el que tiene OT activa
3. Ver sección Operación → decidir acción
4. Si crea OT → queda vinculada al equipo automáticamente
5. Volver a la lista → siguiente equipo
```

**Reducción de fricción vs hoy:**
- Hoy: no existe filtro por área → workaround manual (scrollear, memorizar)
- Blueprint: filtro de área guardado, lista empieza con equipos de su área

---

### Planeador / Jefe de Mantenimiento

**Las tres acciones principales:**

| Acción | Dónde aparece | Clicks |
|---|---|---|
| Ver equipos críticos sin plan activo | Lista → filtro: Críticos + Sin plan | 2 clicks |
| Revisar vencimientos de preventivos | Sección Mantenimiento de cada ficha | 1 click |
| Comparar disponibilidad de equipos | Vista de tabla (nueva) con columna KPI | 1 click |

**Vista de tabla (nueva en Blueprint):**
La lista de equipos tendrá un toggle "Vista tabla" que muestra: código, nombre, estado, criticidad, disponibilidad del período, fallas del período, último preventivo, próximo preventivo. Permite ordenar por cualquier columna.

---

### Gerente

**Las tres acciones principales:**

| Acción | Dónde aparece | Clicks |
|---|---|---|
| Ver ranking de equipos por fallas | Lista → ordenar por columna "Fallas" | 2 clicks |
| Ver disponibilidad de flota crítica | Lista → filtro Críticos → columna Disponibilidad | 2 clicks |
| Ver costo acumulado por equipo | Vista tabla → columna Costo acumulado | 2 clicks |

**Lo que el gerente no necesita hacer desde el módulo de Equipos:**
El gerente no gestiona equipos individualmente. Usa la lista para detectar outliers y la ficha para entender por qué un equipo tiene problemas. El análisis profundo vive en el Dashboard Ejecutivo (Capítulo analítico, PDR-007).

---

### Administrador

**Las tres acciones principales:**

| Acción | Dónde aparece | Clicks |
|---|---|---|
| Crear un equipo nuevo | Lista → [+ Nuevo equipo] → modal de creación rápida o panel admin | 1 click |
| Editar datos maestros | Ficha → ícono de edición en sección Activo | 2 clicks |
| Gestionar documentos del equipo | Ficha → Sección Docs & Fotos → [+ Agregar documento] | 2 clicks |

**Cambio respecto a hoy:** La edición de campos operacionales (estado, criticidad, notas, ubicación) es posible directamente desde la ficha del ops panel, sin ir a Filament. Filament sigue siendo el lugar para configuración de campos maestros (categorías, fabricantes) y creación de equipos complejos.

---

## VII. Estados del activo — cómo cambia la interfaz

El estado del equipo no es solo un badge. Modifica lo que aparece primero y qué acciones están disponibles.

---

### Activo

El estado normal. La ficha muestra su información completa.

```
Header: badge [Activo] en emerald
KPI Strip: visible con disponibilidad, MTBF, MTTR
Sección Operación: muestra OTs recientes abiertas (si las hay)
                   O "Sin intervenciones activas · Última: hace 14 días"
Acciones disponibles: [Crear OT ▾] [Reportar problema] [Registrar lectura]
```

---

### En mantenimiento

El equipo está siendo intervenido activamente. La OT activa es la información más importante.

```
Header: badge [En mantenimiento] en amber
KPI Strip: visible, pero con nota "Disponibilidad afectada por intervención activa"
Sección Operación (prominente):
  ┌──────────────────────────────────────────────────────────┐
  │ 🔧 En intervención desde hace 4 horas                   │
  │ OT-2026-EQ001-000234 · Falla en sello mecánico          │
  │ Técnico: Juan Torres · Estimado: 2 horas restantes       │
  │                                              [Ver OT →]  │
  └──────────────────────────────────────────────────────────┘
Acciones disponibles: [Ver OT activa] [Agregar técnico a OT] [···]
NO disponible: [Crear nueva OT] (la acción se desactiva para evitar duplicados;
               el menú ··· tiene "Crear OT adicional" como acción consciente)
```

---

### Detenido (Fuera de servicio)

El equipo está parado sin OT activa — situación de alerta.

```
Header: badge [Fuera de servicio] en red
Contexto de alerta visible inmediatamente:
  ┌──────────────────────────────────────────────────────────┐
  │ ⚠ Equipo detenido · Sin OT registrada                  │
  │ Última operación registrada: hace 3 días                 │
  │ Reportado por: Ana García (ayer, 14:32)                  │
  └──────────────────────────────────────────────────────────┘
Acciones primarias: [Crear OT urgente] [Ver reporte de problema]
El KPI de downtime se calcula desde el momento del último registro de parada.
```

---

### Crítico (por criticidad, no por estado)

Un equipo con criticality = critical recibe tratamiento especial independientemente de su estado operacional.

```
Header: badge [Crítico] siempre visible además del badge de estado
Ficha con una banda de color rojo-sutil en el borde superior del sticky header
(no intrusivo, pero distinguible al scrollear entre fichas)
Lista: aparece con un punto rojo en la card para identificación rápida
Preventivos vencidos en un equipo crítico: alerta naranja en la sección Mantenimiento
```

---

### Retirado

El equipo ya no está en servicio. La ficha cambia a modo archivo.

```
Header: badge [Retirado] en gray
Banner permanente en la ficha:
  ┌──────────────────────────────────────────────────────────┐
  │ Equipo retirado el 15 ene 2026 · Motivo: Fin de vida útil│
  └──────────────────────────────────────────────────────────┘
KPI Strip: oculto (no tiene sentido calcular disponibilidad de un equipo retirado)
Secciones disponibles: solo Activo e Historial
Acciones NO disponibles: crear OT, reportar problema, registrar lectura
Acciones disponibles: [Descargar informe histórico] [Ver historial completo]
La lista muestra equipos retirados solo cuando el filtro lo incluye explícitamente.
```

---

## VIII. Jerarquía de información

### Lo que siempre está visible (sticky header)

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SIEMPRE VISIBLE — no se oculta al hacer scroll
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  • Breadcrumbs (de dónde vengo)
  • Nombre del equipo (quién es)
  • Estado actual — badge prominente (cómo está)
  • KPI de disponibilidad — una sola cifra con período (señal de salud)
  • Barra de acciones primarias (qué puedo hacer)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

El resto del KPI strip (MTBF, MTTR, Fallas, Downtime) puede colapsarse en pantallas menores a 1280px, mostrando solo "Disponibilidad" en el header y el strip completo solo al expandir o en la sección Mantenimiento.

---

### Lo que puede colapsarse

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
COLAPSABLE — visible por defecto, puede minimizarse
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  • KPI Strip completo (en pantallas <1280px)
  • Sección Activo completa (información estática poco frecuente)
  • Historial paginado (se carga más con botón explícito)
  • Fotos adicionales a la principal (galería expandible)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

### Lo que nunca debe ocultarse

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NUNCA OCULTAR — independientemente del estado,
                el rol, o el tamaño de pantalla
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  • Estado operacional actual (badge de estado)
  • Alertas activas (OTs abiertas, preventivos vencidos)
  • Barra de acciones primarias
  • El nombre del equipo
  • Indicador de criticidad en equipos críticos
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## IX. Wireframes conceptuales

### Desktop — Lista de equipos

```
┌─────────────────────────────────────────────────────────────────────┐
│  Equipos                                         [+ Nuevo equipo]   │
│  243 equipos · Mostrando: Mi área (Planta Norte)                    │
├─────────────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────────┐       │
│  │ 🔍  Buscar por nombre, código o número de serie...       │       │
│  └──────────────────────────────────────────────────────────┘       │
│                                                                      │
│  Estado: [Activos ▾]   Planta: [Planta Norte ▾]   Área: [Todas ▾]  │
│          Criticidad: [Todas ▾]   Categoría: [Todas ▾]               │
│          ☐ Solo con OTs activas   ☐ Solo con preventivos vencidos   │
│                                        [Vista lista ▾] [Guardadas ▾]│
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │ EQ-001      ★   │  │ EQ-002  ● 2 OTs │  │ EQ-003  ⚠ Venc. │  │
│  │ [Activo] [Crítico│  │ [En mto.] [Alto] │  │ [Activo] [Medio] │  │
│  │                  │  │                  │  │                  │  │
│  │ Bomba hidráulica │  │ Compresor L2     │  │ Motor eléctrico  │  │
│  │ principal        │  │                  │  │ aux. planta 2    │  │
│  │                  │  │                  │  │                  │  │
│  │ 📍 Planta N · L3 │  │ 📍 Planta N · L2 │  │ 📍 Planta N · L2 │  │
│  │ ▓▓▓▓░ 87.3%     │  │ ■ Intervención   │  │ ⚠ Prev. vencido  │  │
│  │   últimos 90d    │  │   activa 4h      │  │   hace 3 días    │  │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘  │
│                                                                      │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │ EQ-004           │  │ EQ-005           │  │ EQ-006           │  │
│  │ [Activo] [Bajo]  │  │ [Inactivo] [Bajo]│  │ [Activo] [Alto]  │  │
│  │ ...              │  │ ...              │  │ ...              │  │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘  │
│                                                                      │
│               [ Cargar más equipos ]                                 │
└─────────────────────────────────────────────────────────────────────┘
```

**Leyenda de señales en card:**
- `● 2 OTs` — badge azul: hay OTs activas en este equipo
- `⚠ Venc.` — badge amber: hay un preventivo vencido
- `▓▓▓▓░ 87.3%` — barra mini de disponibilidad (solo en equipos activos sin OT activa)
- `■ Intervención activa 4h` — en equipos "en mantenimiento"

---

### Desktop — Ficha de equipo (sticky header)

```
┌─────────────────────────────────────────────────────────────────────┐
│ STICKY HEADER (siempre visible al scrollear)                        │
│─────────────────────────────────────────────────────────────────────│
│ Equipos › Planta Norte › Línea 3 › EQ-001                          │
│                                                                      │
│ ┌────────┐   EQ-001  [Activo] [Crítico] [Bombas]                   │
│ │        │   Bomba hidráulica principal · Planta Norte · Línea 3    │
│ │  foto  │                                               ★  [PDF ▾] │
│ │        │   ┌───────┬───────┬───────┬───────┬───────┐            │
│ └────────┘   │ 87.3% │ 142h  │  4.1h │   2   │  12h  │            │
│              │ Disp. │ MTBF  │ MTTR  │ Fallas│ Downtime           │
│              │ ↑2.1pp│       │       │  ↓1   │       │            │
│              └───────┴───────┴───────┴───────┴───────┘            │
│              Período: últimos 90 días ▾                             │
│                                                                      │
│  [🔧 Crear OT ▾]   [⚠ Reportar problema]   [📊 Registrar lectura] │
│─────────────────────────────────────────────────────────────────────│
│ [Operación]  [Mantenimiento]  [Activo]  [Docs & Fotos]  [Historial] │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Desktop — Ficha de equipo (sección Operación)

```
┌─────────────────────────────────────────────────────────────────────┐
│ OPERACIÓN                                                           │
│─────────────────────────────────────────────────────────────────────│
│                                                                      │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ COMPONENTE DE                                        [Indigo]   │ │
│ │ Turbocompresor de alta presión · TC-001               [Ver →]  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  Última intervención: 23 nov 2025 · hace 14 días · OT-2025-184     │
│                                                                      │
│  OTs ACTIVAS (1)                                                    │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ OT-2026-001-000234  [En ejecución]  [Alta]                  │   │
│  │ Falla en sello mecánico del eje                             │   │
│  │ Asignado a: Juan Torres · Inicio: hace 4 horas              │   │
│  │                                              [Ver OT →]     │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  SUB-EQUIPOS CON ALERTAS                                            │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │ SEL-001 · Selector de presión · ⚠ Preventivo vencido       │    │
│  │                                      [Ver componente →]     │    │
│  └────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

---

### Tablet — Ficha de equipo (breakpoint ~768px)

```
┌─────────────────────────────────────────────┐
│ STICKY HEADER (compacto)                    │
│─────────────────────────────────────────────│
│ ← Equipos                                   │
│ ┌──────┐  EQ-001 [Activo] [Crítico]         │
│ │ foto │  Bomba hidráulica principal        │
│ └──────┘  Planta Norte · Línea 3    ★ [PDF]│
│                                             │
│  Disp: 87.3% ↑  MTBF: 142h  MTTR: 4.1h   │
│  [últimos 90d ▾]                            │
│                                             │
│  [Crear OT ▾]  [Reportar]  [Lectura]       │
│─────────────────────────────────────────────│
│ [Operación] [Mantenimiento] [Activo] [···]  │
└─────────────────────────────────────────────┘

Contenido en una columna, mismo orden de secciones que desktop.
El anchor nav colapsa los ítems 4 y 5 en un menú "···".
```

---

### Mobile PWA — Ficha de equipo (QR entry point)

```
┌───────────────────────────┐
│ ←  EQ-001                 │  ← Header: código del equipo
│                           │
├───────────────────────────┤
│ [En mantenimiento] [Crit] │  ← Badges de estado y criticidad
│ Bomba hidráulica          │
│ principal                 │
│ Planta Norte · Línea 3    │
├───────────────────────────┤
│                           │
│ ┌───────────────────────┐ │
│ │ 🔧 En intervención    │ │  ← ALERTA PRIMARIA (si aplica)
│ │    OT-2026-234        │ │    fondo amber/red según urgencia
│ │    Juan Torres · 4h   │ │
│ │               [Ver →] │ │
│ └───────────────────────┘ │
│                           │
│ ┌───────────────────────┐ │
│ │ ⚠ Prev. vencido       │ │  ← ALERTA SECUNDARIA (si aplica)
│ │    Revisión mensual   │ │
│ │    3 días de retraso  │ │
│ └───────────────────────┘ │
│                           │
│ ACCIONES                  │
│ ┌───────────────────────┐ │
│ │ + Crear OT            │ │  ← Botón primario (indigo sólido)
│ └───────────────────────┘ │
│ ┌───────────────────────┐ │
│ │ ⚠ Reportar problema   │ │  ← Botón secundario (outlined)
│ └───────────────────────┘ │
│ ┌───────────────────────┐ │
│ │ 📊 Registrar lectura  │ │  ← Botón terciario (outlined)
│ └───────────────────────┘ │
│                           │
│ DOCUMENTOS TÉCNICOS (3)   │
│ ┌───────────────────────┐ │
│ │ 📄 Manual operación   │ │
│ └───────────────────────┘ │
│ ┌───────────────────────┐ │
│ │ 📐 Esquema eléctrico  │ │
│ └───────────────────────┘ │
│ ┌───────────────────────┐ │
│ │ 📋 Proc. mantenimiento│ │
│ └───────────────────────┘ │
│                           │
│ ÚLTIMA INTERVENCIÓN       │
│ 23 nov 2025 · hace 14d    │
│ OT: Mantenimiento mensual │
│                           │
│ DATOS DEL EQUIPO ▾        │  ← Colapsado por defecto
│ (código, serie, fabricante│
│  fechas, ubicación)       │
│                           │
├───────────────────────────┤
│  🏠      📋      🔧      🔔│  ← Bottom nav
└───────────────────────────┘
```

**Lógica de la ficha mobile:**

1. **Si hay OTs activas:** El panel de alerta "En intervención" es lo primero, con fondo amber.
2. **Si hay preventivos vencidos y no hay OT activa:** El panel de alerta preventiva es lo primero.
3. **Si todo está normal:** La primera información visible son las acciones disponibles.
4. **Documentos siempre visibles** (no colapsados) porque el técnico los necesita en campo.
5. **Datos del equipo colapsados** por defecto — el técnico ya sabe qué equipo es.

---

### Mobile PWA — Lista de equipos

```
┌───────────────────────────┐
│ 🔍 Buscar equipos...      │
├───────────────────────────┤
│ [Mis equipos] [Con alertas│
│  ] [Críticos] [Todos]     │
├───────────────────────────┤
│                           │
│ ┌───────────────────────┐ │
│ │ EQ-001  ● 1 OT activa │ │
│ │ [Activo] [Crítico]    │ │
│ │ Bomba hidráulica...   │ │
│ │ Planta Norte · Línea 3│ │
│ └───────────────────────┘ │
│                           │
│ ┌───────────────────────┐ │
│ │ EQ-003  ⚠ Vencido    │ │
│ │ [Activo] [Medio]      │ │
│ │ Motor eléctrico aux.  │ │
│ │ Planta Norte · Línea 2│ │
│ └───────────────────────┘ │
│                           │
│ ┌───────────────────────┐ │
│ │ EQ-007                │ │
│ │ [Activo] [Alto]       │ │
│ │ Válvula de control L1 │ │
│ │ Planta Norte · Línea 1│ │
│ └───────────────────────┘ │
│                           │
├───────────────────────────┤
│  🏠      📋      🔧      🔔│
└───────────────────────────┘
```

El filtro "Mis equipos" muestra los equipos donde el técnico tiene OTs asignadas. Es la vista por defecto para el rol técnico.

---

## X. Microinteracciones

### Hover states (Desktop)

**Cards de la lista:**
```
Normal: bg-white border-gray-100 shadow-sm
Hover:  bg-white border-gray-200 shadow-md (transición 150ms)
        La señal operacional (barra de disponibilidad) mantiene su color
```

**Botones de acción en header:**
```
[Crear OT] normal:  bg-indigo-600 text-white
           hover:   bg-indigo-700 (150ms transition-colors)
           active:  bg-indigo-800

[Reportar] normal:  border-gray-200 text-gray-700
           hover:   border-gray-300 bg-gray-50 (150ms)
```

**Links de navegación entre entidades (Context Banners):**
```
[Ver OT →] normal:  text-indigo-600
           hover:   text-indigo-800 (150ms)
```

---

### Loading states

**Lista de equipos — skeleton:**
```
┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│ ░░░░░░   ░░░░░  │  │ ░░░░░░   ░░░░░  │  │ ░░░░░░   ░░░░░  │
│ ░░░░░░░░░░░░░░  │  │ ░░░░░░░░░░░░░░  │  │ ░░░░░░░░░░░░░░  │
│ ░░░░░   ░░░░    │  │ ░░░░░   ░░░░    │  │ ░░░░░   ░░░░    │
│ ░░░░░░░░░░░░    │  │ ░░░░░░░░░░░░    │  │ ░░░░░░░░░░░░    │
│ ░░░░░░░░░       │  │ ░░░░░░░░░       │  │ ░░░░░░░░░       │
└──────────────────┘  └──────────────────┘  └──────────────────┘
```
El skeleton de card tiene las mismas proporciones que la card real:
línea de código (ancha), línea de nombre (más ancha), dos badges (pequeños), ubicación.

**Ficha de equipo — skeleton:**
El skeleton del header replica: foto cuadrada 72px, breadcrumbs (línea estrecha), título (línea ancha), 2 badges (mini), KPI strip (5 celdas iguales), 3 botones de acción.

**Secciones del cuerpo — carga lazy:**
Las secciones que no son "Operación" (la primera) cargan cuando el usuario navega a ellas. Cada sección muestra su propio skeleton mientras carga, no un spinner global.

---

### Transiciones de estado

**Cuando un equipo cambia de "Activo" a "En mantenimiento":**
El badge en el header hace un crossfade suave (150ms). La sección Operación actualiza su contenido con una transición de fade-in del nuevo contenido (200ms). No hay animación de "cambio dramático" — el sistema es confiable, no teatral.

**Cuando se crea una OT desde la ficha:**
```
1. Click en [Crear OT] → modal/panel deslizable (250ms, slide-in desde la derecha)
2. Usuario completa el formulario mínimo
3. Submit → loading state en el botón de confirmar
4. Éxito → modal se cierra (200ms) → la sección Operación actualiza sin recargar la página
           → toast verde: "OT-2026-EQ001-000235 creada"
5. La nueva OT aparece en la sección Operación con fade-in
```

**Cuando el historial carga más:**
"Cargar más" transiciona a un spinner inline. Los nuevos eventos aparecen con un fade-in suave desde abajo. Sin salto de scroll (el nuevo contenido se añade, no reemplaza).

---

### Estados vacíos

Cada sección tiene su propio empty state que orienta hacia la acción:

**Sección Operación — sin OTs:**
```
┌──────────────────────────────────────────────────────────┐
│          Sin intervenciones activas                      │
│          Última intervención: hace 14 días               │
│          [+ Crear OT preventiva]                         │
└──────────────────────────────────────────────────────────┘
```

**Sección Mantenimiento — sin planes preventivos:**
```
┌──────────────────────────────────────────────────────────┐
│     Este equipo no tiene planes preventivos activos      │
│     Los equipos sin plan preventivo tienen mayor riesgo  │
│     de falla no planificada.                             │
│     [+ Crear plan preventivo]                            │
└──────────────────────────────────────────────────────────┘
```

**Sección Docs & Fotos — sin documentos:**
```
┌──────────────────────────────────────────────────────────┐
│          Sin documentación registrada                    │
│          Agrega manuales, esquemas o procedimientos      │
│          para que el equipo de campo pueda consultarlos. │
│          [+ Agregar documento]                           │
└──────────────────────────────────────────────────────────┘
```

**Sección Historial — sin eventos:**
```
┌──────────────────────────────────────────────────────────┐
│          Sin actividad registrada                        │
│          El historial se construye automáticamente       │
│          cuando se crean OTs, preventivos y lecturas     │
│          para este equipo.                               │
└──────────────────────────────────────────────────────────┘
```

---

### Estados de error

Los errores en Fronda siempre: (1) dicen qué pasó, (2) dicen qué puede hacer el usuario.

**Error de carga de la ficha:**
```
No pudimos cargar la información de este equipo.
Verifica tu conexión e intenta de nuevo.
[Reintentar]  [Volver a la lista]
```

**Error al crear OT (inline, no modal):**
```
No se pudo crear la OT. El equipo ya tiene una OT activa del mismo tipo.
¿Deseas ver la OT existente?  [Ver OT →]  [Crear igualmente]
```

**Error de red en mobile (offline):**
```
┌──────────────────────────────┐
│ Sin conexión                 │  ← OfflineBar permanente
│ Mostrando datos guardados    │
│ Última sync: hace 2 horas    │
└──────────────────────────────┘
```

---

### Microinteracción de selección en la lista

Al activar la selección múltiple (checkbox):
- El checkbox de cada card hace un fade-in desde opacity 0 a 1 (150ms)
- La BulkActionBar desliza desde abajo (250ms, cubic-bezier(0.4, 0, 0.2, 1))
- Al deseleccionar todo, la BulkActionBar desliza de vuelta hacia abajo

---

## XI. Roadmap interno — sub-sprints

El Sprint UX-3 se divide en cinco sub-sprints independientes, ordenados por impacto en el usuario.

---

### UX-3.1 — La mobile que sirve al técnico

**Prioridad:** 🔴 Urgente · **Dependencias:** Ninguna

**Problema que resuelve:** La ficha mobile actual es un cascarón sin información operacional ni acciones. Un técnico que escanea el QR llega a una pantalla que no responde ninguna pregunta de trabajo.

**Alcance exacto:**
1. Rediseño completo de `mobile/views/EquipmentDetailView.vue`
2. Nueva estructura: alertas activas → acciones → documentos técnicos → última intervención → datos básicos (colapsados)
3. Conectar endpoint existente de OTs del equipo (`/api/v1/work-orders?equipment_id=X&status=active`)
4. Mostrar documentos del equipo (ya existe en la API de la ficha desktop)
5. Botón "Reportar problema" → flujo de crear Solicitud de Mantenimiento con equipo preseleccionado
6. Botón "Crear OT" → formulario mínimo con equipo preseleccionado
7. Nueva vista de lista mobile: filtro "Mis equipos" como primera tab

**Qué NO incluye este sprint:**
- Registro de lectura de medidor (UX-3.3)
- Offline caching (UX-3.5)
- Fotos desde campo (ya existe en el flujo de OTs)

**Criterios de aceptación:**
- Un técnico puede llegar al equipo por QR y saber en ≤5 segundos si hay una OT activa
- Un técnico puede crear una OT desde la ficha en ≤3 taps
- Un técnico puede abrir un PDF de documentación sin salir de Fronda

---

### UX-3.2 — Acciones desde la ficha desktop

**Prioridad:** 🔴 Urgente · **Dependencias:** Ninguna

**Problema que resuelve:** La ficha desktop es de solo lectura. La acción más frecuente (crear OT para el equipo) requiere navegar fuera de la ficha y reconstruir el contexto manualmente.

**Alcance exacto:**
1. Barra de acciones primarias en el sticky header: `[Crear OT ▾]` `[Reportar problema]` `[Registrar lectura]`
2. El menú desplegable de "Crear OT" ofrece: Correctiva / Preventiva / Predictiva / Inspección
3. La OT creada nace con el equipo preseleccionado y no editable (el contexto viaja)
4. Corrección crítica: "Ver todas las OTs" → lista de OTs filtrada por `equipment_id`, no lista global
5. Edición inline de campos operacionales: estado, criticidad, notas, ubicación_notes (campos que cambian con frecuencia)
6. Eliminar la sección duplicada "Estado del activo" → mover Costo acumulado y Última intervención a la sección Mantenimiento

**Qué NO incluye:**
- Edición de campos maestros (Filament sigue siendo la autoridad para código, modelo, serie)
- Nueva estructura de secciones completa (eso es UX-3.4)

---

### UX-3.3 — Lista con inteligencia

**Prioridad:** 🟠 Alto · **Dependencias:** Ninguna

**Problema que resuelve:** La lista de equipos no ayuda al supervisor a priorizar. Con 200 equipos y solo 4 filtros de estado, el supervisor hace scroll manual para encontrar lo que necesita.

**Alcance exacto:**
1. Filtros adicionales: Planta (select), Área (select dependiente de Planta), Criticidad (select), Categoría (select)
2. Señales operacionales en las cards:
   - Badge "● N OTs" (azul) si hay OTs activas
   - Badge "⚠ Prev. vencido" (amber) si hay preventivos vencidos en las últimas 72h o más
   - Barra mini de disponibilidad (solo si el KPI está calculado y el equipo está activo sin OT activa)
3. Checkboxes de contexto: "Solo con OTs activas" / "Solo con preventivos vencidos"
4. Vista de tabla (toggle en la lista): columnas código, nombre, estado, criticidad, disponibilidad, fallas (período), último preventivo, próximo preventivo; ordenable por cualquier columna
5. La búsqueda se extiende para incluir búsqueda por nombre de área y planta

**Qué NO incluye:**
- Vista de mapa de planta (scope futuro)
- Exportación a Excel (scope separado)

---

### UX-3.4 — Ficha 360° rediseñada

**Prioridad:** 🟡 Medio · **Dependencias:** UX-3.2 completado

**Problema que resuelve:** El orden de secciones de la ficha fue construido incrementalmente. No fue diseñado. La sección de mayor relevancia operacional (estado actual, OTs) aparece segunda, no primera.

**Alcance exacto:**
1. Nueva estructura de secciones: Operación → Mantenimiento → Activo → Docs & Fotos → Historial
2. KPI Strip con selector de período (90d / 6m / 12m / Todo) y comparativa con período anterior
3. Filtros en el Historial: por tipo de evento (OTs / Preventivos / Paradas / Lecturas / Fallas)
4. Repuestos utilizados se integra en la sección Mantenimiento (eliminado como sección independiente)
5. Sección Operación como primera sección, con Context Banners remodelados
6. Ajuste del anchor nav: 5 ítems en lugar de 7

**Qué NO incluye:**
- Edición inline de campos (ya en UX-3.2)
- Nuevos campos de datos (scope de Filament)

---

### UX-3.5 — Offline y resiliencia

**Prioridad:** 🟢 Bajo · **Dependencias:** UX-3.1 completado

**Problema que resuelve:** Los técnicos trabajan en entornos con conectividad intermitente. Sin offline, Fronda no funciona en algunas plantas.

**Alcance exacto:**
1. Cache de las últimas 10 fichas de equipo visitadas en mobile (service worker)
2. Cache de los documentos técnicos descargados recientemente (hasta 100MB)
3. Creación de reportes de problema en modo offline (se encola y sincroniza)
4. Indicador de "datos en caché / sin conexión" visible en la ficha mobile
5. Indicador de "última sincronización: hace X minutos" en el header mobile

**Qué NO incluye:**
- Creación de OTs completas en offline (requiere confirmación de servidor)
- Sincronización bidireccional de OTs (scope mayor, fuera del sprint)

---

## Apéndice — Decisiones de diseño que este Blueprint toma

Las siguientes decisiones son definitivas en este Blueprint. Requieren un nuevo PDR para ser revertidas.

| Decisión | Razón |
|---|---|
| La ficha mobile muestra OTs activas primero | El técnico necesita saber si ya hay trabajo registrado antes de iniciar cualquier acción |
| Los datos básicos del equipo están colapsados en mobile | El técnico llega con el QR del equipo — ya sabe qué equipo es |
| "Ver todas las OTs" filtra por equipo, no lista global | Preserva el contexto; la lista global existe en el menú lateral |
| El KPI strip no se repite en el cuerpo de la página | Un dato en dos lugares genera desconfianza sobre si son los mismos datos |
| La sección Operación es siempre la primera en desktop | Responde la pregunta más frecuente primero |
| Los equipos retirados no muestran KPI strip | MTBF y disponibilidad de un equipo fuera de servicio no son indicadores útiles |
| Crear OT desde la ficha preselecciona el equipo y no lo permite cambiar | El contexto es el equipo que se está viendo; si el usuario quiere otra cosa, crea la OT desde el módulo de OTs |

---

*Blueprint preparado para revisión de producto. Los sub-sprints UX-3.1 y UX-3.2 no tienen dependencias entre sí y pueden ejecutarse en paralelo.*

*Próximo paso: aprobación de este Blueprint → inicio de desarrollo UX-3.1 (mobile) y UX-3.2 (acciones desktop) en paralelo.*
