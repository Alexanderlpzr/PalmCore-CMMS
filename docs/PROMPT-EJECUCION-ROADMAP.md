# Prompt Maestro de Ejecución — Roadmap Fronda CMMS / Extractora El Pajuil

> **Cómo usar este documento.**
> 1. Pega la sección **CONTEXTO** al inicio de cualquier sesión nueva de Claude Code.
> 2. Elige el entregable que vas a atacar (C1…B6) y pega su **PROMPT** completo.
> 3. Los **AGENTES** son roles: pégalos como preámbulo cuando quieras que Claude adopte esa mentalidad, o úsalos con la herramienta Agent si quieres paralelizar.
> 4. Nada se da por terminado sin cumplir la **DEFINICIÓN DE HECHO**.
>
> Auditoría completa que sustenta todo esto: [AUDITORIA-FRONDA-EL-PAJUIL.md](AUDITORIA-FRONDA-EL-PAJUIL.md)

---

## CONTEXTO (pegar siempre al inicio de sesión)

```
Eres el equipo de ingeniería de Fronda CMMS, un sistema de gestión de mantenimiento
multi-tenant (Laravel 13 + Filament 5 + Livewire 4 + Vue 3 SPA + PWA móvil + PostgreSQL).

El cliente es EXTRACTORA EL PAJUIL, una planta extractora de aceite de palma en Colombia.
Hoy operan el mantenimiento con 4 archivos Excel/PDF:

1. REGISTRO DE HOROMETROS 2026.xlsx
   - 11 equipos críticos con lectura DIARIA, ~90 equipos con lectura SEMANAL.
   - Hoja "Control de Equipos": ~350 TAREAS preventivas (no equipos) con:
     Frecuencia(h) | Horómetro Último Mtto | Horómetro Actual | Próximo Mtto | Horas Faltantes | Días Faltantes
   - Fórmulas: Próximo = Último + Frecuencia ; Horas Faltantes = Próximo − Actual ;
     Días Faltantes = Horas Faltantes / Promedio horas-día del último mes.
   - Un mismo medidor alimenta N tareas con N frecuencias (Esterilizador: 22h, 44h, 88h, 340h…).
   - Backlog preventivo masivo (cientos de tareas con Horas Faltantes negativas).
   - Anomalías reales: retrocesos de horómetro (10.452 → 158), valores basura, medidores en 0.

2. Historial de Mantenimiento El Pajuil.xlsx (~700 registros)
   Proceso | Equipo | Fecha | OT | Descripción | Horómetro | Área de Mtto | Tipo General | Ejecutante
   - Área de Mtto: Mecánico, Eléctrico, Electrónico, Civil, Operaciones.
   - Tipo General: Correctivo, Preventivo, Predictivo, Periódico, Mejoras.
   - Ejecutante: 12 técnicos propios + "Contratista" + "Operadores" + "proceso".
   - Contratistas reales: Disam, AIC, Servimontajes, Montajes Industriales HF, Ingenimec,
     Conford, Indutronic, Inzinier.

3. INDICADORES DE MANTENIMIENTO — JUNIO.xlsx (~700 paros/año)
   Fecha | Hora inicio | Hora fin | Tiempo paro (h) | Tipo I | Tipo II | Sección | Equipo | Causa
   Taxonomía Tipo I × Tipo II:
     Programada   → Arranque planta, Apagado planta, Reunión, Capacitaciones,
                    Mantenimiento programado, Montajes
     Mantenimiento→ Falla eléctrica, Falla mecánica, Mantenimiento programado
     Operativa    → Falla operativa, Atascamiento, Sobrecarga, Falta de fruta esterilizada
     Externa      → Falta de fruta fresca, Falta energía red, Eventos naturales,
                    Eventos orden público
   El 70% de los paros NO genera orden de trabajo.
   Indicadores que hoy entregan a gerencia:
     Eficiencia de planta = Horas efectivas / Horas programadas (junio: 413.4/452 = 91.46%)
     MTBF y MTTR de PLANTA (no de equipo)
     % de paradas por Tipo I (junio: Programada 68.7% | Mantenimiento 23.0% | Operativa 8.3%)
     Pareto de horas de paro por equipo
     Meta: paradas de mtto + operativas ≤ 10%
   La hoja MTTR trae una columna PLAN DE ACCIÓN por cada falla.

4. PROGRAMACION DE MTTO LUNES 15-06-2026.pdf (programa diario impreso)
   Sección | Equipo | Hallazgo(tipo) | Plan de acción | Responsable | Personal de apoyo |
   Hora programada (06:00–18:00) | Fecha ejecución | EJECUTADO SÍ/NO | Nivel prioridad
   - La planta para en ventanas de 6–12 h y en esa ventana se ejecutan N órdenes.
   - El Responsable puede ser un grupo de técnicos O un contratista.

ESTADO ACTUAL DEL SISTEMA (verificado en código):
✅ Existe y funciona: tenants, plants, areas, equipment (jerárquico) + components + histories,
   documents, photos, QR, issue_reports, maintenance_requests, work_orders (8 estados, técnicos
   con hourly_rate congelado, time_logs, parts con inventario, comments, attachments, firmas),
   maintenance_plans (calendar/meter/hybrid, cadencia fixed/floating, grace días+horas, pausa si
   inactivo) + schedules, meter_readings, downtime_events, equipment_kpis (MTBF/MTTR/availability),
   production_logs + OEE, spare_parts + warehouses + inventory_transactions, purchase_orders,
   alerts, automation_rules, notifications + push, webhooks + API con idempotencia, audit_logs,
   13 widgets de dashboard, reportes PDF/Excel, PWA móvil offline con QR y firma.

❌ NO existe (los 7 bloqueadores):
   1. Ejecución de checklists — maintenance_plan_tasks y maintenance_checklist_items son
      TABLAS HUÉRFANAS: no hay work_order_tasks ni work_order_checklist_results.
   2. Generación automática de OT preventivas — MaintenancePlanService tiene la lógica pero
      ningún job la invoca.
   3. Registro de paros independiente de OT — downtime_events tiene unique(work_order_id),
      equipment_id NOT NULL, y solo 5 causas genéricas.
   4. KPIs de planta y de sección — solo hay KPIs por equipo. No hay production_calendar.
   5. Horómetros: proyección (días faltantes), tasa de consumo, ruta de lectura masiva,
      validación de retrocesos, múltiples medidores por equipo.
   6. Contratistas como entidad.
   7. Permisos de trabajo, LOTO y ATS.

REGLA DE ORO DEL PROYECTO:
Si el cliente termina usando Fronda ADEMÁS de Excel, el proyecto fracasó.
Cada entregable se mide por cuánto Excel elimina.
```

---

## GOALS — Objetivos del programa

### Meta primaria
**Eliminar los 4 archivos.** Al final del roadmap crítico + alto, El Pajuil no debe abrir ningún Excel de mantenimiento.

| Goal | Métrica de éxito | Bloqueado por |
|---|---|---|
| **G1 — Preventivo operativo** | El planificador no crea ninguna OT preventiva a mano. Las ~350 tareas del Excel viven en Fronda y generan OT solas. | C1, C2, C4 |
| **G2 — Ejecución con evidencia** | El 100 % de los preventivos se cierra con checklist ejecutado y valores medidos, no con texto libre. | C1 |
| **G3 — Indicadores nativos** | El informe mensual a gerencia (Eficiencia de planta, MTBF/MTTR de planta, % paradas por Tipo I, Pareto) sale de Fronda con un clic. | C3, C5 |
| **G4 — Planificación semanal real** | El planificador ve "qué vence en los próximos 14 días" ordenado por días faltantes, y arma la ventana de parada del lunes. | C4, A2, A4 |
| **G5 — Programa del día** | El PDF de programación se imprime desde Fronda, con responsable (técnico o contratista), personal de apoyo, hora programada y marca de ejecutado. | A2, A1 |
| **G6 — Costo real** | Se conoce el costo de mantenimiento por sección, mes, técnico y contratista, y el costo de indisponibilidad. | A1, A9 |
| **G7 — Trazabilidad de vida útil** | Se sabe cuántas horas duró el tornillo de la prensa raquis reconstruido por Disam vs. el nuevo de AIC. | A1, A6 |
| **G8 — Seguridad** | Ninguna OT sobre caldera, turbina o espacio confinado puede pasar a `InProgress` sin permiso de trabajo y LOTO firmados. | A3 |
| **G9 — Confianza en el dato** | El sistema arranca con el histórico migrado (350 tareas, 700 intervenciones, 700 paros, series de horómetros) → los KPIs son creíbles desde el día 1. | C6 |

### Restricciones no negociables
1. **Snapshot histórico:** todo dato copiado de una plantilla a una OT (tarea, checklist, tolerancia, tarifa, costo) se **congela**. Cambiar el plan nunca reescribe el historial. Ya se hace con `hourly_rate` y `unit_cost_snapshot`: extender el principio.
2. **Multi-tenant:** cada tabla nueva lleva `tenant_id` con FK y scope global.
3. **Offline-first en campo:** todo lo que hace un técnico o un operador debe funcionar sin señal (ya hay cola offline en la PWA).
4. **Español operativo:** los labels de UI usan la jerga del cliente (hallazgo, paro, sección, ejecutante, personal de apoyo). Los enums pueden seguir en inglés en código.
5. **Test obligatorio:** cada cambio lleva test Pest. `php artisan test --compact --filter=...` verde antes de dar por hecho nada.
6. **Pint:** `vendor/bin/pint --dirty --format agent` antes de cerrar.

---

## AGENTES — Roles especializados

Úsalos como preámbulo de rol, o con la herramienta `Agent` para paralelizar entregables independientes.

### 🏗️ `arquitecto-datos`
```
Eres Arquitecto de Datos senior especializado en CMMS/EAM sobre PostgreSQL + Laravel.
Diseñas migraciones, modelos Eloquent, relaciones e índices.

Principios que aplicas sin excepción:
- UUID v7 como PK. tenant_id + FK + índice compuesto (tenant_id, ...) en TODA tabla.
- Soft deletes en entidades de negocio; inmutabilidad en registros de auditoría/transacción.
- Snapshot de valores históricos (nunca FK viva a una plantilla mutable).
- Índices parciales para los query paths calientes.
- Columnas generadas para cálculos derivados deterministas (duration_minutes, is_out_of_range).
- Nada de enums PHP hardcodeados para catálogos que el cliente debe poder editar.

Antes de escribir una migración: lee las migraciones vecinas y copia su estilo exacto
(timestampsTz(0), softDeletesTz, foreignUuid, comentarios de sección).
Entregas: migración + modelo + factory + test de esquema.
```

### ⚙️ `ingeniero-dominio`
```
Eres Ingeniero de Backend senior en Laravel 13 con arquitectura de dominio (app/Domain/*).
Implementas servicios, jobs, enums y reglas de negocio.

Convenciones del repo que respetas:
- Cada dominio tiene Enums/, Services/, DTOs/, Data/.
- Servicios sin estado, inyectados, con transacciones explícitas (DB::transaction).
- Jobs idempotentes, ShouldBeUnique cuando aplica, dispatch por tenant.
- Tipos de retorno explícitos, PHPDoc con array shapes, constructor property promotion.
- Observers para efectos secundarios (ya existen: WorkOrderObserver, AlertObserver…).

Nunca introduces lógica de negocio en un Resource de Filament ni en un controlador.
Entregas: servicio + job + tests Pest de feature.
```

### 🔬 `ingeniero-confiabilidad`
```
Eres Ingeniero de Confiabilidad (RCM/RCA/CBM) con experiencia en plantas extractoras de palma.
Defines las FÓRMULAS y la SEMÁNTICA de los indicadores antes de que nadie escriba código.

Tu trabajo: para cada KPI entregas
  - definición formal
  - fórmula exacta con su denominador
  - fuente de datos en el modelo
  - nivel de agregación (equipo / sección / planta)
  - ventana temporal
  - casos borde (equipo sin fallas, denominador cero, paros solapados, paro que cruza medianoche)

Conoces la realidad del cliente: Eficiencia de planta = Horas efectivas / Horas programadas.
MTBF y MTTR de PLANTA, no solo de equipo. MTTR debe separar tiempo de intervención de
tiempo de espera (repuesto, torno externo, planta ocupada).

No aceptas un KPI sin denominador definido.
```

### 🏭 `consultor-cmms`
```
Eres Consultor CMMS/EAM con implementaciones en IBM Maximo, SAP PM e Infor EAM.
Tu función es CUESTIONAR el diseño funcional antes de construirlo.

Preguntas que siempre haces:
- ¿Esto elimina un Excel del cliente o solo agrega una pantalla?
- ¿Cómo lo modela Maximo/SAP y por qué?
- ¿Qué pasa cuando el dato viene sucio (retroceso de horómetro, medidor en 0, paro sin equipo)?
- ¿Quién lo llena en campo, con qué dispositivo, en cuántos segundos?
- ¿Qué reporte sale de esto y quién lo lee?

Vetas cualquier feature que el planificador o el técnico no vayan a usar el lunes.
```

### 📱 `ux-industrial`
```
Eres diseñador UX de software industrial. Diseñas para tres usuarios muy distintos:

TÉCNICO (móvil, guantes, sin señal, de pie): máximo 3 taps por acción. Botones grandes.
  Teclado numérico para valores. Cámara siempre a un tap. Todo funciona offline.
SUPERVISOR/PLANIFICADOR (desktop, planea la semana): densidad de información alta,
  filtros, acciones masivas, tablero de asignación, impresión.
GERENTE (tablet, 2 minutos al día): un solo tablero, semáforos, tendencia, sin detalle.

Regla: el dashboard responde "¿qué hago hoy?", no solo "¿qué pasó?".
Stack: Vue 3 + Tailwind (SPA ops y PWA móvil), Filament 5 solo para administración.
Reutilizas los componentes existentes (mission/*, SlidePanel, BottomSheet, CommandPalette).
```

### 📊 `ingeniero-datos-migracion`
```
Eres Ingeniero de Datos. Migras los 4 Excel/PDF de El Pajuil al modelo de Fronda.

Tu método:
1. Perfilar: contar filas, detectar nulos, duplicados, outliers, retrocesos, tipos mixtos.
2. Mapear: columna Excel → campo Fronda, con regla de transformación explícita.
3. Limpiar: documentar CADA corrección (no silenciar datos sucios; marcarlos con is_anomaly).
4. Cargar: comandos artisan idempotentes, en transacción, con --dry-run y reporte.
5. Validar: reconciliación (totales, sumas de horas, conteos) Excel vs. Fronda.

Datos sucios conocidos que DEBES manejar:
- Horómetros que retroceden (reemplazo de medidor) → marcar is_reset, no calcular delta negativo.
- "Horómetro Último Mtto = 54666" con actual 5506 → outlier, marcar y no usar en cálculos.
- Tareas con Último Mtto = 0 → nunca ejecutadas, no vencidas desde el día 1.
- Nombres de equipo inconsistentes entre archivos ("Prensa de doble tornillo" vs "Prensa P15"
  vs "prensa de doble tornillo") → tabla de alias/normalización.
- Fechas Excel serializadas como 1900-01-01 (hora fin que cruzó medianoche).
```

### 🛡️ `especialista-hse`
```
Eres especialista en Seguridad Industrial para plantas de proceso en Colombia.
Diseñas el módulo de permisos de trabajo, LOTO y ATS.

Contexto de riesgo real de El Pajuil: caldera de vapor, turbina, esterilizador presurizado,
unidades hidráulicas, tableros de media tensión (CCM), trabajo en altura (plataforma del
esterilizador, ciclones), espacios confinados (tanques, hogar de caldera, precipitador ESP).

Marco: Res. 0312/2019, Res. 4272 (alturas), Res. 0491 (espacios confinados).
Regla dura que implementas: una OT sobre equipo crítico NO puede pasar a InProgress
sin permiso vigente y LOTO verificado, con firmas.
```

### 🧪 `qa-pest`
```
Eres QA Engineer con Pest 4 sobre Laravel.
Escribes tests de feature (no unitarios triviales) que prueban la REGLA DE NEGOCIO.

Para cada entregable produces:
- Camino feliz.
- Casos borde reales del cliente (retroceso de horómetro, paro sin equipo, plan sin tareas,
  OT sin técnico, denominador cero, paro que cruza medianoche, tenant cruzado).
- Aislamiento multi-tenant (un tenant NUNCA ve datos de otro).
- Regresión de los KPIs contra los valores REALES de junio 2026 del Excel:
  Eficiencia de planta 91.46 %, MTTR 2.37 h, 9 fallas, Programada 68.7 %.

Usas factories y estados existentes. Comando: php artisan test --compact --filter=...
```

---

## PROMPTS POR ENTREGABLE

> Cada prompt es autónomo: pega CONTEXTO + el prompt del entregable.

---

### 🔴 C1 — Tareas y checklist ejecutables en la OT

**Agentes:** `arquitecto-datos` → `ingeniero-dominio` → `ux-industrial` → `qa-pest`

```
OBJETIVO
Hoy maintenance_plan_tasks y maintenance_checklist_items son tablas HUÉRFANAS: se definen
tareas con tolerancias (expected_min/expected_max, unidad, tipo boolean/numeric/text) y nunca
se ejecutan. Una OT preventiva se cierra con texto libre. Hay que cerrar ese hueco.

CONSTRUYE
1. Migración work_order_tasks:
   id, tenant_id, work_order_id, maintenance_plan_task_id (nullable, solo referencia),
   sort_order, title, description, estimated_minutes,
   status (pending|in_progress|done|skipped), skipped_reason,
   assigned_to (users, nullable), started_at, completed_at, completed_by.
   → title/description/estimated_minutes son SNAPSHOT del plan, no FK viva.

2. Migración work_order_checklist_results:
   id, tenant_id, work_order_task_id, maintenance_checklist_item_id (nullable),
   sort_order, label, item_type, unit, expected_min, expected_max, is_required,
   value_boolean, value_numeric, value_text,
   is_out_of_range (columna GENERADA: numeric fuera de [min,max]),
   photo_path, notes, recorded_at, recorded_by.
   → label/unit/expected_* son SNAPSHOT.

3. Modelos + relaciones: WorkOrder::tasks(), WorkOrderTask::checklistResults(),
   WorkOrder::checklistResults() (hasManyThrough).

4. Servicio WorkOrderTaskService:
   - copyFromPlan(WorkOrder $wo, MaintenancePlan $plan): copia tareas + items congelando valores.
   - completeTask(), skipTask(reason), recordChecklistResult().
   - Al completar una OT: validar que TODAS las tareas con checklist is_required estén respondidas.
     Si falta alguna, bloquear la transición a Completed con excepción de dominio.

5. Efecto: un resultado con is_out_of_range = true dispara un Alert (AlertService ya existe,
   categoría condición/severidad según desviación) y sugiere crear OT correctiva.

6. UI:
   - PWA móvil (resources/js/mobile/views/WorkOrderDetailView.vue): lista de tareas colapsables,
     cada una con sus items. Numérico → teclado numérico + validación visual contra rango.
     Boolean → switch. Foto por item a un tap. Funciona OFFLINE (usar la cola existente).
   - SPA ops (resources/js/ops/views/WorkOrderDetailView.vue): vista de solo lectura del progreso
     + valores fuera de rango resaltados en rojo.
   - Filament WorkOrderResource: RelationManager de solo lectura.

TESTS (Pest)
- copyFromPlan congela los valores: cambiar el plan después NO altera la OT ya generada.
- No se puede completar una OT con items requeridos sin responder.
- Un valor numérico fuera de rango marca is_out_of_range y genera un Alert.
- Skip de tarea exige razón.
- Aislamiento multi-tenant.

DEFINICIÓN DE HECHO
Un preventivo del Esterilizador con 10 tareas y valores medidos se ejecuta completo desde el
móvil, offline, y queda registrado con evidencia y alertas por desviación.
```

---

### 🔴 C2 — Generación automática de OT preventivas

**Agentes:** `ingeniero-dominio` → `consultor-cmms` → `qa-pest`
**Depende de:** C1

```
OBJETIVO
MaintenancePlanService ya tiene initializeSchedule, calculateNextDue, isOverdue, recordExecution.
NADIE los llama. El único job programado es SendOverdueMaintenanceNotificationsCommand (notifica,
no crea). Con ~350 tareas preventivas, crear OTs a mano es inviable.

CONSTRUYE
1. Job GeneratePreventiveWorkOrdersJob (por tenant, ShouldBeUnique):
   Recorre maintenance_schedules donde:
     - trigger calendar/hybrid: next_due_at <= now() + tenant.pm_lookahead_days (default 14)
     - trigger meter/hybrid:   next_due_meter <= equipment.current_meter + lookahead_meter
       (lookahead_meter derivado de avg_daily_consumption × lookahead_days — ver C4;
        mientras C4 no exista, usar un umbral fijo configurable)
   Reglas:
     - Respeta plan.is_active, plan.pause_when_equipment_inactive (equipo no activo → saltar).
     - Respeta grace_period_days / grace_meter_hours.
     - ANTI-DUPLICADO: no genera si ya existe una OT abierta (no Closed/Cancelled) para ese plan.
     - Crea la OT en estado Planned, type=Preventive, con planned_start_at = next_due_at,
       priority derivada de equipment.criticality.
     - Llama WorkOrderTaskService::copyFromPlan (C1).
     - Asigna responsible_user_id del plan como assigned_supervisor.
     - Actualiza schedule.last_work_order_id.
   Cadencia:
     - fixed: si se pasaron ciclos sin ejecutar, avanza next_due_* al siguiente ciclo teórico
       e incrementa times_skipped (NO genera una OT por cada ciclo perdido).
     - floating: next_due_* se recalcula solo al completar (recordExecution ya lo hace).

2. Programación en routes/console.php:
   - Diario 05:00 (calendar).
   - Horario (meter) — barato porque solo mira schedules con trigger meter/hybrid.

3. Config por tenant: pm_lookahead_days, pm_auto_generate (on/off), pm_default_priority_map.

4. Observability: log estructurado + contador de OT generadas/saltadas por razón.

TESTS (Pest)
- Genera OT cuando next_due_at entra en el lookahead; no la duplica en la corrida siguiente.
- No genera si el equipo está inactivo y pause_when_equipment_inactive = true.
- Respeta el periodo de gracia.
- Cadencia fixed con 3 ciclos perdidos → 1 OT + times_skipped = 3.
- La OT generada trae las tareas y el checklist copiados (integración con C1).
- Aislamiento multi-tenant.

DEFINICIÓN DE HECHO
Con las 350 tareas cargadas, el job corre y el planificador abre el lunes con su semana ya
armada, sin haber creado una sola OT a mano.
```

---

### 🔴 C3 — Registro de paros independiente de la OT

**Agentes:** `consultor-cmms` → `arquitecto-datos` → `ingeniero-dominio` → `ux-industrial` → `qa-pest`

```
OBJETIVO
equipment_downtime_events solo nace de una OT (unique(work_order_id)), exige equipment_id y tiene
5 causas genéricas. La realidad: ~700 paros/año y el 70% NO genera OT (atascamientos, falta de
fruta, cambio de energía, falla operativa). Sin esto NO se puede producir ningún indicador que
hoy mira la gerencia.

CONSTRUYE
1. Catálogo configurable por tenant:
   downtime_types (id, tenant_id, type_i, code, name, counts_as_downtime bool, sort_order)
   type_i ∈ {programada, mantenimiento, operativa, externa}
   Seeder con la taxonomía EXACTA del cliente (ver CONTEXTO, archivo 3).

2. Refactor de downtime_events (migración de cambio, preservando datos):
   - work_order_id nullable, QUITAR el unique (una OT puede tener N eventos: la Prensa P15 tuvo
     un paro de 3 días partido en 3 registros).
   - equipment_id NULLABLE (existe "Planta general").
   - AÑADIR: plant_id (NOT NULL), area_id (nullable), downtime_type_id (FK al catálogo),
     reported_by, cause_description, action_plan, action_plan_status
     (pending|in_progress|done|verified), action_plan_owner, action_plan_due_at.
   - duration_minutes como columna GENERADA desde started_at/ended_at.
   - Mantener was_planned como derivado de type_i (programada|mantenimiento programado).
   - Migrar los datos existentes mapeando el cause_type viejo al nuevo catálogo.

3. Servicio DowntimeService: open(), close(), escalateToRequest() (un paro puede convertirse en
   solicitud → OT manteniendo el vínculo), linkToWorkOrder().

4. UI:
   - PWA móvil: botón "REPORTAR PARO" en 3 taps → equipo/planta, tipo (chips grandes por Tipo I,
     luego Tipo II), hora inicio (default: ahora). Cerrar el paro es 1 tap. OFFLINE.
   - SPA ops: tabla de paros con filtros por Tipo I/II, sección, equipo, rango; edición; cierre
     masivo; exportación.
   - Widget "Paros abiertos ahora" en el dashboard.

5. Invalidar equipment_kpis (markStale) al abrir/cerrar/editar un paro. El observer ya existe.

TESTS (Pest)
- Un paro sin equipo (Planta general) es válido.
- Una OT puede tener 3 eventos de paro consecutivos.
- Paro que cruza medianoche calcula bien duration_minutes.
- Paros solapados del mismo equipo → warning (no doble-contar horas).
- Cerrar un paro marca los KPIs del equipo como stale.
- La suma de horas de paro de junio 2026 reproduce el total del Excel.

DEFINICIÓN DE HECHO
El operador reporta un atascamiento del elevador de fruto desde el celular en 10 segundos, sin
crear una OT, y ese paro aparece en el Pareto y en la eficiencia de planta del mes.
```

---

### 🔴 C4 — Horómetros: proyección, ruta de lectura y validación

**Agentes:** `arquitecto-datos` → `ingeniero-dominio` → `ux-industrial` → `qa-pest`

```
OBJETIVO
Hoy: 1 solo medidor por equipo (equipment.current_meter_reading), sin tasa de consumo, sin
proyección, sin carga masiva, sin validación. El Excel calcula "Días Faltantes" = Horas Faltantes
/ Promedio horas-día del último mes — ESA es la columna con la que el planificador arma la semana.

CONSTRUYE
1. Migración equipment_meters (1 equipo : N medidores):
   id, tenant_id, equipment_id, code, name, unit (hours|cycles|km|kwh),
   is_primary, current_value, rollover_max (nullable),
   avg_daily_consumption, avg_calculated_at, last_reading_at, is_active.
   Migrar equipment.current_meter_reading al medidor primario de cada equipo.

2. equipment_meter_readings gana: meter_id (FK), delta_value, is_reset (bool), is_anomaly (bool),
   anomaly_reason. Mantener compatibilidad hacia atrás.

3. Validación en EquipmentMeterReadingService::record():
   - Lectura < última → NO es error: es un RESET de medidor (caso real: 10.452 → 158).
     Marcar is_reset = true, delta = 0, NO romper el MTBF.
   - delta > media + 3σ (o > 24 h/día si unit = hours) → is_anomaly = true, alerta al supervisor,
     pero se guarda (el dato sucio se marca, no se silencia).
   - Lectura duplicada mismo medidor/mismo día → upsert, no duplicar.

4. Job diario RecalculateMeterConsumptionJob:
   avg_daily_consumption = media móvil 30 días de (delta / días transcurridos),
   EXCLUYENDO días en que el equipo estuvo parado (usar downtime_events de C3).

5. maintenance_schedules gana projected_due_at:
     horas_faltantes = next_due_meter − meter.current_value
     projected_due_at = now() + (horas_faltantes / avg_daily_consumption) días
   Job que lo recalcula tras cada lectura y tras el recálculo de consumo.
   Si avg_daily_consumption = 0 → projected_due_at = null (no proyectable).

6. Rutas de lectura:
   meter_reading_routes (id, tenant_id, name, frequency: daily|weekly|monthly, is_active)
   meter_reading_route_meters (route_id, meter_id, sort_order)
   Seeder: "Críticos — Diario" (11 medidores) y "General — Cada 7 días" (~90 medidores),
   copiando el orden exacto del Excel.

7. UI móvil "RUTA DE LECTURA":
   - Lista secuencial, un medidor a la vez, teclado numérico grande.
   - Muestra la última lectura y el delta esperado; si el valor entra fuera de rango, pide
     confirmación explícita ("¿Se reemplazó el medidor?").
   - Barra de progreso (23/90). Guarda parcial. OFFLINE con la cola existente.

8. UI ops: tabla "Próximos vencimientos por horómetro", ordenada por días faltantes ASC,
   con las columnas EXACTAS del Excel: Equipo+Tarea | Frecuencia | Último Mtto | Actual |
   Próximo | Horas Faltantes | Días Faltantes. Colores: vencido (rojo), <7 días (ámbar), resto.

TESTS (Pest)
- Un reset de medidor (10.452 → 158) no produce delta negativo ni destruye el MTBF.
- Un delta imposible (116 h en un día) se marca is_anomaly y alerta.
- avg_daily_consumption excluye los días de paro.
- projected_due_at reproduce el "Días Faltantes" del Excel para ≥5 casos reales.
- avg = 0 → projected_due_at null, sin división por cero.
- La ruta guarda parcialmente y sincroniza al recuperar señal.

DEFINICIÓN DE HECHO
El operador recorre los 90 equipos con el celular en 15 minutos y el planificador ve al instante
qué preventivos vencen en los próximos 14 días, ordenados por urgencia real.
```

---

### 🔴 C5 — KPIs de planta y sección + calendario de producción

**Agentes:** `ingeniero-confiabilidad` (PRIMERO, define fórmulas) → `arquitecto-datos` → `ingeniero-dominio` → `qa-pest`
**Depende de:** C3

```
OBJETIVO
Hoy solo hay KPIs por equipo (equipment_kpis) con ventana fija de 12 meses y sin histórico.
El indicador principal del cliente NO existe: Eficiencia de planta = Horas efectivas / Horas
programadas (junio 2026: 413.4 / 452 = 91.46 %). Tampoco hay MTBF/MTTR de PLANTA.

PASO 0 — el ingeniero-confiabilidad entrega PRIMERO, en un documento, la definición formal de
cada KPI (fórmula, denominador, fuente, agregación, ventana, casos borde). Nadie escribe código
antes de aprobar ese documento.

CONSTRUYE
1. Migración production_calendar:
   id, tenant_id, plant_id, date, planned_hours, shift, notes.
   → Es el DENOMINADOR. Sin esto no hay disponibilidad. Seeder con junio 2026 = 452 h.

2. Migración kpi_snapshots (histórico congelado, no recalculable):
   id, tenant_id, scope (plant|area|equipment), scope_id, period (YYYY-MM),
   metric (código), value, numerator, denominator, calculated_at.
   Único por (tenant, scope, scope_id, period, metric).

3. Servicio PlantKpiService con, como mínimo:
   - horas_programadas (production_calendar)
   - horas_no_procesadas (Σ downtime_events donde counts_as_downtime)
   - horas_efectivas = programadas − no_procesadas
   - eficiencia_planta = efectivas / programadas
   - mtbf_planta = horas_efectivas / n_fallas   (fallas = paros Tipo I ∈ {mantenimiento} con
     Tipo II ∈ {falla mecánica, falla eléctrica})
   - mttr_planta = Σ horas de reparación / n_fallas
   - distribucion_paradas por Tipo I (%) y por Tipo II
   - pareto_paros_por_equipo (horas + % acumulado)
   - cumplimiento_meta (paradas mtto + operativas ≤ 10 %)
   Mismo servicio con scope = area.

4. Job MonthlyKpiSnapshotJob (día 1 de cada mes, 04:00): congela los snapshots del mes cerrado
   para plant, area y equipment. Idempotente.

5. Endpoints API + widgets (ver A5).

TESTS (Pest) — REGRESIÓN CONTRA DATOS REALES
Cargar los paros reales de junio 2026 y verificar que el servicio devuelve EXACTAMENTE:
  horas programadas = 452
  horas efectivas   = 413.4
  horas no procesadas = 38.6
  eficiencia planta = 91.46 %
  n° fallas = 9
  MTTR = 2.37 h
  Programada 68.7 % | Mantenimiento 23.0 % | Operativa 8.3 %
  Pareto: Prensa doble tornillo 8.4 h (28.9 %) en primer lugar
Más casos borde: mes sin producción (denominador 0), paros solapados, paro que cruza mes.

DEFINICIÓN DE HECHO
El jefe de mantenimiento genera el informe mensual de gerencia desde Fronda y los números son
idénticos a los que hoy calcula a mano en Excel.
```

---

### 🔴 C6 — Migración de datos históricos

**Agentes:** `ingeniero-datos-migracion` → `qa-pest`
**Depende de:** C1, C3, C4 (los esquemas destino deben existir)

```
OBJETIVO
Sin histórico, Fronda arranca con MTBF/MTTR vacíos y nadie le cree. Hay que cargar:
- ~100 equipos + su jerarquía por sección/proceso
- Series de horómetros (11 diarios × 13 meses, 90 semanales × 10 meses)
- ~350 tareas preventivas como maintenance_plans + tasks (con su Último Mtto y Frecuencia)
- ~700 intervenciones históricas como work_orders cerradas
- ~700 paros como downtime_events

CONSTRUYE
Comandos artisan idempotentes con --dry-run y reporte de reconciliación:
  fronda:import:equipment      {file}
  fronda:import:meters         {file}   (series + cálculo de avg_daily_consumption inicial)
  fronda:import:pm-plans       {file}   (Control de Equipos → plans + schedules)
  fronda:import:history        {file}   (BASE-HV_EQUIPOS → work_orders Closed)
  fronda:import:downtime       {file}   (Registrio Paros → downtime_events)

REGLAS DE LIMPIEZA (documentar cada decisión en un reporte de migración):
- Normalización de nombres de equipo: tabla de alias.
  "Prensa de doble tornillo" = "prensa de doble tornillo" = "Prensa P15" = "Prensa #1 A05EXT.05.01"
  "Sinfín para Fruto Suelto" = "sinfín bajo desfrutador"
  → construir el mapa de alias y REVISARLO con el cliente antes de cargar.
- Horómetros que retroceden → is_reset = true, no delta negativo.
- "Horómetro Último Mtto = 54666" con actual 5506 → is_anomaly, excluir del cálculo de vencimiento.
- Tareas con Último Mtto = 0 → NO se marcan como vencidas; se marcan como "sin línea base",
  requieren que el planificador fije el punto de partida.
- Fechas 1900-01-01 en "Hora fin" → el paro cruzó medianoche: reconstruir con la fecha siguiente.
- Ejecutante "Contratista" / "Operadores" / "proceso" → NO son users: mapear a contractors (A1)
  o a un usuario genérico marcado como tal.
- Registros del historial sin fecha o sin equipo → cuarentena, reporte aparte, no se cargan.

VALIDACIÓN (obligatoria antes de dar por cargado)
- Conteo de filas origen vs. destino vs. cuarentena (debe cuadrar).
- Suma de horas de paro por mes: Excel vs. Fronda, diferencia = 0.
- Los KPIs de junio 2026 calculados por C5 reproducen los del Excel.
- Muestreo manual de 20 registros por archivo.

DEFINICIÓN DE HECHO
Fronda arranca el día 1 con 2 años de historia, KPIs poblados y el backlog preventivo real
visible. El cliente reconoce sus propios números.
```

---

### 🟠 A1 — Contratistas + vida útil de componentes por proveedor

**Agentes:** `consultor-cmms` → `arquitecto-datos` → `ingeniero-dominio`

```
OBJETIVO
El Excel atribuye ~30 intervenciones críticas a terceros (Disam, AIC, Servimontajes, Montajes
Industriales HF, Ingenimec, Conford, Indutronic, Inzinier). En Fronda solo hay users y suppliers;
actual_cost_external es un decimal sin dueño.

El caso de negocio: los tornillos de la prensa raquis reconstruidos por Disam duraban 322–892 h
(contra >1000 h esperadas) por soldadura inadecuada, lo que derivó en fracturas TOTALES de eje.
El sistema no puede detectar ese patrón ni sustentar un reclamo.

CONSTRUYE
1. Extender suppliers con type (material|service|both) + campos de contratista
   (NIT, contacto, ARL vigente, póliza, calificación promedio).
2. work_order_contractors: work_order_id, supplier_id, scope, quoted_cost, actual_cost,
   started_at, ended_at, rating (1–5), rating_notes.
   → actual_cost_external de la OT pasa a ser la SUMA de esta tabla.
3. equipment_components gana: rebuilt_by_supplier_id, rebuild_procedure (texto: "UTP 630 + ledurit 65"),
   expected_life_hours, installed_at_meter, removed_at_meter,
   actual_life_hours (columna generada: removed − installed).
4. La OT puede tener como responsable un contratista, no solo un user (el PDF de programación lo exige).
5. KPI: "Vida útil real vs. esperada por proveedor y procedimiento" — tabla + gráfico.
   Ranking de contratistas por reincidencia de falla post-intervención (<30 días).

TESTS
- Un componente reconstruido por Disam con 322 h de vida aparece en el ranking bajo la media.
- La suma de work_order_contractors.actual_cost cuadra con actual_cost_external.
```

---

### 🟠 A2 — Ventanas de parada + Programa del día imprimible

**Agentes:** `consultor-cmms` → `arquitecto-datos` → `ux-industrial`
**Depende de:** C2

```
OBJETIVO
Replicar y superar el PDF "PROGRAMACION DE MTTO LUNES 15-06-2026". La planta para en ventanas de
6–12 h y en esa ventana ejecuta N OTs. Hoy Fronda no tiene esa entidad.

CONSTRUYE
1. maintenance_windows: id, tenant_id, plant_id, name, date, start_time, end_time, status
   (draft|published|in_progress|closed), notes.
   maintenance_window_work_orders (pivote): window_id, work_order_id, scheduled_time,
   sequence, executed (bool), not_executed_reason.
2. work_orders gana: scheduled_time (hora del día), not_executed_reason.
3. Planificador: arrastra OTs a la ventana; ve horas-hombre requeridas vs. disponibles
   (capacidad = técnicos × horas de la ventana) y avisa si sobrepasa.
4. Impresión PDF "Programa de Mantenimiento del día", con las MISMAS columnas del PDF actual:
   Sección | Equipo | Hallazgo | Plan de acción | Responsable | Personal de apoyo |
   Hora programada | Fecha ejecución | ☐ SÍ ☐ NO | Nivel prioridad
   (usar el patrón de app/Domain/Reports/Services/*PdfService.php).
5. Al cerrar la ventana: genera automáticamente el downtime_event de tipo
   "Programada / Mantenimiento programado" con las horas reales de parada (alimenta C5).
```

---

### 🟠 A3 — Permisos de trabajo, LOTO y ATS

**Agentes:** `especialista-hse` → `arquitecto-datos` → `ingeniero-dominio` → `ux-industrial`

```
OBJETIVO
Cero artefactos de seguridad en un sistema que emite órdenes para entrar al hogar de una caldera.

CONSTRUYE
1. work_permits: id, tenant_id, work_order_id, permit_type (caliente|altura|espacio_confinado|
   electrico|izaje|excavacion), valid_from, valid_to, issued_by, approved_by, approved_at,
   status (draft|approved|active|closed|revoked), precautions (jsonb), ppe_required (jsonb).
2. equipment_loto_points: equipment_id, energy_type (electrica|vapor|hidraulica|neumatica|
   mecanica|termica|quimica), location, isolation_method, verification_method, sort_order.
3. work_order_loto_verifications: work_order_id, loto_point_id, blocked_at, blocked_by,
   verified_at, verified_by, released_at, released_by, photo_path.
4. ATS/AST como checklist previo obligatorio (reutilizar el motor de checklist de C1).
5. REGLA DURA en WorkOrderService::start(): si el equipo tiene criticality Critical|High o
   tiene loto_points definidos, NO se permite pasar a InProgress sin:
     - permiso aprobado y vigente
     - todos los loto_points bloqueados y verificados
     - ATS firmado
   Lanzar excepción de dominio con mensaje claro.
6. Móvil: flujo de bloqueo con foto obligatoria por punto.

TESTS
- Una OT sobre la caldera no puede iniciar sin permiso → excepción.
- Permiso vencido → no permite iniciar.
- Liberación de LOTO exige que todos los puntos estén verificados.
```

---

### 🟠 A4 — Backlog y planificación de recursos

**Agentes:** `consultor-cmms` → `ingeniero-dominio` → `ux-industrial`

```
OBJETIVO
El cliente entra con un backlog preventivo de años y no tiene forma de dimensionarlo ni de saldarlo.

CONSTRUYE
1. KPI Backlog = Σ planned_labor_hours de OTs abiertas ÷ capacidad semanal (horas-hombre).
   Desglosado por prioridad, por sección, por tipo (preventivo/correctivo).
   Semáforo: <2 semanas verde, 2–4 ámbar, >4 rojo.
2. Capacidad: user_profiles gana weekly_capacity_hours (default 44 h Colombia).
3. Tablero de planificación semanal: Gantt simple (OT × día × técnico), drag & drop,
   detección de sobrecarga.
4. Widget "Backlog" en el dashboard del planificador.
```

---

### 🟠 A5 — Dashboards por rol

**Agentes:** `ux-industrial` + `ingeniero-confiabilidad`
**Depende de:** C3, C5

```
Construir tres dashboards, cada uno responde una pregunta distinta.

PLANIFICADOR — "¿qué hago hoy?"
  Tiles de acción: OT vencidas · OT de hoy · Preventivos que vencen en 7 días ·
  Solicitudes sin revisar · Paros abiertos ahora · Repuestos bajo mínimo
  Backlog en horas-hombre vs. capacidad (barra apilada por prioridad)
  Próximos vencimientos por horómetro ordenados por días faltantes  ← el corazón
  Programa de la semana (Gantt)
  Equipos parados ahora (semáforo en vivo)

GERENTE — "¿vamos bien?" (2 minutos)
  Gauge Eficiencia de planta del mes vs. meta + tendencia 12 meses
  Dona % paradas por Tipo I vs. meta ≤10% (mtto + operativas)
  Costo de mantenimiento del mes vs. presupuesto (mano de obra / repuestos / contratistas)
  Costo de indisponibilidad (horas de paro × pérdida de producción)
  Top 10 equipos por horas de paro (Pareto acumulado)
  MTBF / MTTR de planta con tendencia y semáforo
  Cumplimiento PM + Ratio Preventivo/Correctivo
  Mapa de calor sección × mes de horas de paro
  Top contratistas por costo y por reincidencia

TÉCNICO (móvil)
  Mis OT de hoy · Escanear QR · Reportar paro · Reportar novedad ·
  Ruta de horómetros · Mis horas de la semana

Antes de programar: usar la skill `dataviz`. Reutilizar HorizontalBarChart y MultiLineChart.
```

---

### 🟠 A6 — Catálogo jerárquico de fallas + RCA estructurado

**Agentes:** `ingeniero-confiabilidad` → `arquitecto-datos` → `ingeniero-dominio`

```
OBJETIVO
FailureMode es un enum PHP hardcodeado de 15 valores. root_cause es texto libre → imposible
hacer Pareto de causas. La hoja MTTR del cliente ya trae PLAN DE ACCIÓN por falla, sin seguimiento.

CONSTRUYE
1. failure_catalog jerárquico por tenant (modelo Maximo):
   failure_classes → problems → causes → remedies
   Migrar el enum actual como seed inicial; permitir edición.
2. work_orders: reemplazar failure_mode (string) por failure_class_id / problem_id / cause_id /
   remedy_id (mantener compatibilidad con los datos existentes).
3. root_cause_analyses: id, tenant_id, work_order_id / downtime_event_id, method (5_whys|ishikawa),
   findings (jsonb), root_cause_id, created_by, reviewed_by, reviewed_at.
4. corrective_actions: rca_id, description, owner_id, due_at, status
   (pending|in_progress|done|verified), verified_at, verified_by, effectiveness_notes.
   → Ej. real: "cambiar sujeción tornillo-eje de cuadrado a circular" (Prensa P15, julio 2026).
   Alerta si vence sin ejecutar. KPI de eficacia: ¿reincidió la falla tras la acción?
5. Pareto por clase / problema / causa.
```

---

### 🟠 A7 — Mediciones de condición (CBM)

**Agentes:** `ingeniero-confiabilidad` → `arquitecto-datos` → `ux-industrial`

```
OBJETIVO
El cliente ya hace predictivo y lo pierde en texto libre: balanceo de ventiladores (13 g → 2.0 g
de desbalance residual), megger a motores, alineación con comparador de carátula (0.50 mm),
desgaste de placas (2.5 mm), análisis de aceite, termografía.

CONSTRUYE
1. condition_measurement_points: equipment_id, code, name, technique
   (vibracion|termografia|ultrasonido|aceite|megger|alineacion|espesor),
   unit, alarm_warning, alarm_critical, direction (higher_is_worse|lower_is_worse), frequency.
2. condition_measurements: point_id, measured_at, value, status (ok|warning|critical) [generada],
   measured_by, work_order_id, notes, attachment_path.
3. Alerta automática al superar warning/critical (AlertService).
4. Gráfico de tendencia por punto con las bandas de alarma.
5. Un valor en critical sugiere generar OT correctiva (un tap).
6. Integrable con el checklist de C1 (un item numérico puede ser un punto de medición).
```

---

### 🟠 A10 — Deuda técnica de base de datos

**Agentes:** `arquitecto-datos` → `qa-pest`

```
Resolver, cada uno con su migración y su test de regresión:

D2  downtime_events: quitar unique(work_order_id), equipment_id nullable → cubierto por C3.
D4  Moneda: equipment default USD vs. work_orders/purchase_orders default COP.
    → tenants.currency_code como fuente única; quitar defaults duros; migrar datos existentes.
D5  equipment_kpis sin snapshot histórico → cubierto por C5 (kpi_snapshots).
D6  FailureMode enum hardcodeado → cubierto por A6.
D7  work_order_parts tiene part_code (texto libre) Y spare_part_id → ambigüedad.
    → Regla: uno de los dos es obligatorio. part_code solo para ítems NO catalogados.
      Constraint CHECK + validación en el servicio.
D8  areas con unique(plant_id, sort_order) → anti-patrón: reordenar rompe.
    → Quitar el unique. Evaluar parent_area_id para la jerarquía Proceso > Sección.
D10 work_order_time_logs sin activity_type → el MTTR mezcla trabajo y espera.
    → Enum: efectivo | espera_repuesto | espera_planta | traslado | diagnostico.
      Recalcular MTTR usando solo tiempo efectivo + reportar el administrativo aparte.
D11 production_calendar → cubierto por C5.
D12 purchase_orders sin recepción parcial → purchase_order_lines.received_quantity + goods_receipts.
D13 equipment_components sin proveedor de reconstrucción → cubierto por A1.
D14 technical_specs JSONB sin esquema → definir JSON Schema por equipment_category y validar.
```

---

### 🟠 A11 — Español operativo

**Agente:** `ux-industrial`

```
Toda la UI habla el idioma del cliente. Los enums pueden seguir en inglés en el CÓDIGO,
pero jamás se muestran crudos.

Glosario obligatorio (lang/es):
  work order        → Orden de Trabajo (OT)
  finding / type    → Hallazgo
  downtime event    → Paro
  area              → Sección
  assignee          → Ejecutante
  helper            → Personal de apoyo
  meter reading     → Horómetro
  preventive plan   → Plan de mantenimiento preventivo
  in_progress       → En ejecución
  p1_critical       → Prioridad 1 — Crítica
  mechanical_wear   → Desgaste mecánico

Auditar: labels de Filament, enums con ->label(), vistas Vue, PDFs, notificaciones, emails.
```

---

## ORDEN DE EJECUCIÓN

```
Fase 1 — Fundaciones (paralelizable)
  ├─ C1  Checklist ejecutable        [arquitecto-datos → ingeniero-dominio → ux-industrial]
  ├─ C3  Registro de paros           [consultor-cmms → arquitecto-datos → ingeniero-dominio]
  └─ C4  Horómetros                  [arquitecto-datos → ingeniero-dominio → ux-industrial]

Fase 2 — Automatización (depende de Fase 1)
  ├─ C2  Auto-generación de PM       ← necesita C1 y C4
  └─ C5  KPIs de planta              ← necesita C3   [ingeniero-confiabilidad define PRIMERO]

Fase 3 — Datos reales
  └─ C6  Migración histórica         ← necesita C1, C3, C4  [ingeniero-datos-migracion]
     🚦 HITO: aquí Fronda ya reemplaza los 4 Excel. Validar con el cliente antes de seguir.

Fase 4 — Valor alto (paralelizable)
  ├─ A1  Contratistas
  ├─ A2  Ventanas de parada + programa del día   ← necesita C2
  ├─ A3  HSE (permisos, LOTO, ATS)
  ├─ A4  Backlog y planificación
  ├─ A5  Dashboards por rol          ← necesita C3, C5
  ├─ A6  Catálogo de fallas + RCA
  ├─ A7  CBM
  ├─ A9  Costos por sección/técnico/contratista
  ├─ A10 Deuda técnica DB
  └─ A11 Español operativo

Fase 5 — Media: M1…M8    Fase 6 — Baja: B1…B6  (ver auditoría §15)
```

---

## DEFINICIÓN DE HECHO (aplica a TODO entregable)

- [ ] Migración + modelo + factory, siguiendo el estilo de las migraciones vecinas.
- [ ] Lógica en `app/Domain/*/Services`, nunca en Resources ni controladores.
- [ ] `tenant_id` + scope global + índice compuesto en toda tabla nueva.
- [ ] Snapshot congelado de todo dato copiado desde una plantilla.
- [ ] Tests Pest: camino feliz + casos borde reales del cliente + aislamiento multi-tenant.
- [ ] `php artisan test --compact --filter=<Feature>` en verde.
- [ ] `vendor/bin/pint --dirty --format agent` sin hallazgos.
- [ ] UI en español con la jerga del cliente.
- [ ] Si toca el flujo de campo: funciona offline en la PWA.
- [ ] Responde a la pregunta: **¿qué Excel elimina o qué columna del Excel reemplaza?**
```
