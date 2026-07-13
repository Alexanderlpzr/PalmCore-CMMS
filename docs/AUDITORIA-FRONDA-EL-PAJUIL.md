# Auditoría Integral — Fronda CMMS vs. Operación Real de Extractora El Pajuil

**Fecha:** 12 de julio de 2026
**Alcance:** modelo de datos, módulos funcionales, flujo de trabajo, KPIs, dashboard, UX, comparación con los 4 archivos operativos reales del cliente y benchmark contra CMMS enterprise.
**Postura:** crítica. Se asume que nada del sistema actual es suficiente hasta demostrarlo contra la operación real.

---

## 0. Resumen ejecutivo — los 7 hallazgos que bloquean producción

| # | Hallazgo | Impacto |
|---|---|---|
| 1 | **Los checklists se definen pero nunca se ejecutan.** `maintenance_plan_tasks` y `maintenance_checklist_items` son plantillas huérfanas: no existe ninguna tabla que capture su resultado en la OT. | El 100% de las inspecciones preventivas del cliente (con valores medidos, tolerancias min/max) no se puede registrar. El módulo preventivo es decorativo. |
| 2 | **No hay generación automática de OT preventivas.** Solo existe `SendOverdueMaintenanceNotificationsCommand` (una notificación diaria). | El Excel de horómetros tiene **~350 tareas preventivas activas**, la mayoría vencidas. Crear OTs a mano es inviable. |
| 3 | **El registro de paros de planta no existe.** `equipment_downtime_events` solo nace de una OT y su enum de causa tiene 5 valores genéricos. | El Excel de Indicadores registra **~700 paros/año** cuyo 70% NO genera OT (atascamientos, falta de fruta, cambio de energía, falla operativa). Sin esto **no se puede reproducir ni un solo indicador que hoy usa la gerencia**. |
| 4 | **KPIs solo a nivel equipo.** No existe MTBF/MTTR/disponibilidad/eficiencia a nivel **planta** ni **sección**. | El reporte mensual que hoy entrega mantenimiento (Eficiencia de Planta 91.46%, MTBF, MTTR, % paradas por tipo) no es producible. |
| 5 | **Horómetros sin proyección ni carga masiva.** No hay tasa de consumo (h/día), no hay “días faltantes”, no hay ruta de lectura, no hay validación de retrocesos. | El Excel calcula “Días Faltantes” para priorizar la semana. Fronda no puede decir *cuándo* vence un preventivo por horómetro, solo si ya venció. La captura diaria de ~90 equipos no tiene UI viable. |
| 6 | **Contratistas no existen como entidad.** El Excel los usa constantemente (Disam, AIC, Servimontajes, Montajes Industriales HF, “Contratista”, “Operadores”, “proceso”). | `actual_cost_external` es un decimal suelto sin trazabilidad. No hay KPI de contratistas ni evaluación de proveedores — crítico dado el historial de reconstrucciones fallidas de tornillos. |
| 7 | **Sin permisos de trabajo / LOTO / análisis de riesgo.** | Planta con caldera, turbina de vapor, esterilizador presurizado y prensas. Es exposición legal y de seguridad, no una feature “nice to have”. |

---

## 1. Reconstrucción del negocio a partir de los archivos reales

Antes de auditar hay que entender cómo trabaja El Pajuil. Los 4 archivos no son 4 reportes: son **4 subsistemas de un CMMS manual** que se comunican por copiar-pegar.

### 1.1 `REGISTRO DE HOROMETROS 2026.xlsx` — el motor de planificación preventiva

Es el archivo más importante y el que Fronda peor cubre.

**Estructura real:**
- Hoja `Registro Diario`: ~11 equipos **críticos** (Esterilizador, Prensa P15, Digestor, Tricanter, Turbina, Planta 1250 kVA, Planta 72 kVA, Compresor FSN, Prensa raquis, Caldera, Centrífuga Alfa Laval) con lectura **diaria** desde 2025-06 hasta 2026-07. Cada fecha guarda **lectura acumulada + delta del día**. Se calcula un **PROMEDIO mensual de horas/día** por equipo.
- Hoja `Registro Cada 7 Días`: ~90 equipos **no críticos** con lectura **semanal** y su delta.
- Hoja `Último Horómetro`: consolidado (Máquina, Horómetro, Medidor=CCM, Sección, Promedio Día Último Mes).
- Hoja `Control de Equipos`: **el corazón**. ~350 filas, cada una es una **tarea preventiva** (no un equipo):
  `Código Medidor | Equipo+Tarea | Frecuencia (h) | Horómetro Último Mtto | Horómetro Actual | Horómetro Próximo Mtto | Horas Faltantes | Días Faltantes | Observaciones por día del mes (1..31)`

**Lógica de negocio extraída:**
```
Horómetro Próximo Mtto = Horómetro Último Mtto + Frecuencia
Horas Faltantes        = Horómetro Próximo Mtto − Horómetro Actual     (negativo = VENCIDO)
Días Faltantes         = Horas Faltantes / Promedio Día Último Mes      (proyección)
```
Y la clave: **un mismo medidor alimenta N tareas con N frecuencias distintas.** Ej. `A02STR.03.01` (Esterilizador) tiene 10 tareas: tapa superior (22h), tapa inferior (22h), válvulas Bray (44h), sinfín y acople (22h), motorreductor (88h), válvulas de globo (88h), sistema eléctrico (44h), camisa (22h), cambio de camisa (340h), estructura (88h).

**Estado real hoy:** de ~350 tareas, la mayoría con `Horas Faltantes` **negativas** (backlog preventivo masivo: Tambor Pulidor −11.290 h, Elevador de Almendras −11.552 h, Molino Ripple −11.315 h). Esto no es un dato menor: **el cliente entra a Fronda con un backlog preventivo de años**. El sistema debe poder representarlo y ayudar a saldarlo, no ocultarlo.

**Anomalías de datos que el sistema debe tolerar/detectar:**
- Retrocesos de horómetro: `Caldera bomba hidráulica #1` pasa de 10.452 → 158 (reemplazo de equipo/medidor). `Esclusa Cascaras Húmedas` 329 → 324 → 329.
- Valores basura: `Planta eléctrica inspección mecánica` con Último Mtto = **54.666** cuando el horómetro actual es 5.506 → “Horas Faltantes 49.660”. Fórmula que nadie validó.
- Tareas con `Horómetro Último Mtto = 0` (nunca se hizo) → aparece como vencida desde el día 1.
- Equipos con horómetro que no avanza (Turbina = 0, Caldera = 0) → **el equipo crítico “Caldera” no tiene horómetro funcional**, pero sí ~30 subequipos con horómetro propio.

### 1.2 `Historial de Mantenimiento El Pajuil.xlsx` — la bitácora

Hoja `BASE-HV_EQUIPOS`: ~700 registros con columnas:
`Proceso | Equipo | Fecha | OT | Descripción | Horómetro | Área de Mtto | Tipo General | Ejecutante`

- **Proceso** (14): Recepción, Esterilización, Desfrutado, Raquis, Extracción, Clarificación, Desfibrado, Palmistería, Generación_Vapor, Generación_Eléctrica, Almacenamiento, Caldera, PTAI, Estructura_Planta, ZONA DE CARGUE, Despachos, Planta General.
- **Área de Mtto** (5): Mecánico, Eléctrico, Electrónico, Civil, **Operaciones**.
- **Tipo General** (5): Correctivo, Preventivo, **Predictivo**, Periódico, **Mejoras**.
- **Ejecutante** (~12): Jhon Jardis S., Daniel Florez, Jolman D., Andres Rosas, Jorge M., Sebastian S., **Contratista**, **Operadores**, Niver Avila, Fernando A., Andrey M., Wilmer V., Yeison V., **proceso**.
- **OT**: columna existe pero está **vacía en el 99%** → hoy no hay numeración de OT. Fronda ya lo resuelve.

**Inteligencia enterrada en las descripciones** (esto es oro para RCM y hoy se pierde):
> *Prensa raquis, tornillo #1: 1.056 h → reconstruido Disam (7018+durowell 650) → 1.976 h fractura de eje → 2.201 h rotura de canasta → 2.859 h desgaste → 3.639 h fractura total de eje+tornillo+canasta → 4.457 h fisura radial (UTP 630/65 agrietado) → 5.349 h.*

Eso es un **análisis de vida útil de componente por proveedor y por procedimiento de soldadura** que hoy vive en texto libre. Es exactamente lo que un módulo de componentes rotables + RCA debe capturar.

Lo mismo con la Prensa P15: 8 roturas de eje corto/largo documentadas entre 2024-04 y 2026-06. Y la nota final del MTTR de junio: *“A finales de julio se va a cambiar el sistema de sujeción entre tornillos y ejes de cuadrado a circular”* → **eso es una acción correctiva de RCA que necesita seguimiento y verificación de eficacia**. Fronda no tiene dónde ponerla.

### 1.3 `INDICADORES DE MANTENIMIENTO — JUNIO.xlsx` — el reporte a gerencia

Hoja `Registrio Paros`: ~700 paros con:
`Fecha | Día | Mes | Hora inicio | Hora fin | Tiempo paro (h) | Tipo I | Tipo II | Sección | Equipo | Causa/Observación | Responsable diligenciamiento`

**Taxonomía de paros (Tipo I × Tipo II)** — esta es la que Fronda NO tiene:

| Tipo I | Tipo II |
|---|---|
| **Programada** | Arranque planta, Apagado planta, Reunión, Capacitaciones, Mantenimiento programado, Montajes |
| **Mantenimiento** | Falla eléctrica, Falla mecánica, Mantenimiento programado |
| **Operativa** | Falla operativa, Atascamiento, Sobrecarga, Falta de fruta esterilizada |
| **Externa** | Falta de fruta fresca, Falta energía red, Eventos naturales, Eventos orden público, Capacitaciones |

**Indicadores calculados (hojas `Resumen general`, `MTBF`, `MTTR`, `Análisis PNP`):**
```
Horas totales programadas       = 452 h/mes
Horas efectivas de proceso      = 413.4 h
Horas no procesadas             = 38.6 h
Eficiencia de planta            = 413.4 / 452 = 91.46 %
Cantidad de fallas              = 9
MTBF (planta)                   = tiempo funcionamiento / n° fallas
MTTR (planta)                   = horas de reparación / n° fallas   → 2.37 h en junio
Horómetro inicial/final prensa  = 10 923 → 11 293  (horas efectivas de la prensa = 370.11 h)
% paradas por tipo              = Programada 68.7 % | Mantenimiento 23.0 % | Operativa 8.3 %
Pareto por equipo (PNP)         = Prensa doble tornillo 8.4 h (28.9 %), Prensa raquis 5.6 h, Sinfín 5.35 h…
Meta                            = paradas mtto+operativas ≤ 10 %
```
Y la hoja **MTTR trae una columna `PLAN DE ACCION` por cada falla** → cierre de ciclo. Fronda no tiene plan de acción por falla.

### 1.4 `PROGRAMACION DE MTTO LUNES 15-06-2026.pdf` — la orden del día

Documento impreso, una fila por trabajo:
`SECCIÓN | EQUIPO | HALLAZGO (tipo mtto) | PLAN DE ACCIÓN | RESPONSABLE | PERSONAL DE APOYO | HORA PROGRAMADA (06:00–18:00) | FECHA DE EJECUCIÓN | EJECUTADO SI/NO | NIVEL PRIORIDAD`

Observaciones:
- **RESPONSABLE puede ser varias personas** (Franker Mariño + Diego Jiménez + Daniel Florez) **o un contratista** (Montajes Industriales HF).
- **PERSONAL DE APOYO** es un rol distinto (N/A o “Javier Delgado”).
- **HALLAZGO** es el tipo: Mantenimiento correctivo / preventivo / **MEJORA**.
- Se imprime y se marca a mano “EJECUTADO SÍ/NO”.

Esto es una **programación diaria por parada de planta** (la planta para lunes 6:00–18:00 = 12 h). Fronda tiene un calendario Filament pero **no tiene el concepto de “Parada Programada / Ventana de mantenimiento”** que agrupa N órdenes de trabajo, tiene una duración de planta y se imprime como programa del día.

---

## 2. Auditoría funcional módulo por módulo

Leyenda: ✅ Existe y es suficiente · ⚠️ Existe pero incompleto · ❌ No existe · 🔀 Debe dividirse/fusionarse

| Módulo | Estado | Evidencia en el código | Qué falta contra la operación real |
|---|---|---|---|
| **Plantas** | ✅ | `plants` | — |
| **Áreas / Secciones** | ⚠️ | `areas` (plano, sin jerarquía) | El Excel usa “Proceso” (14) + “Sección” (en paros usa también “Planta general”). Falta un nivel o un tipo. Falta poder colgar KPIs de área. |
| **Equipos / Activos** | ✅ | `equipment` con `parent_equipment_id`, criticidad, prioridad, specs JSONB, ciclo de vida, costos | Falta `is_rotating` (rotables), falta ubicación funcional vs. activo físico (Maximo separa Location de Asset — aquí un tornillo reconstruido “rota” entre prensas). |
| **Componentes** | ✅ | `equipment_components` + `component_histories` (horas, part_number, status, unit_cost) | Bien diseñado. Falta ligar componente ↔ proveedor que lo reconstruyó y ↔ vida útil esperada (para el caso Disam/AIC). |
| **Documentación técnica** | ✅ | `equipment_documents` | — |
| **Fotos / evidencias** | ✅ | `equipment_photos`, `work_order_attachments` (before/after/evidence) | — |
| **QR** | ✅ | `equipment_qr_codes` + `ScanQrView.vue` | — |
| **Reportes de novedad (operador)** | ✅ | `equipment_issue_reports` (open → acknowledged → converted_to_mr) | Mapea con el operador que reporta. Bien. |
| **Solicitudes de mantenimiento** | ✅ | `maintenance_requests` (+ comments, attachments, técnico preliminar) | — |
| **Órdenes de trabajo** | ⚠️ | `work_orders` (8 estados, tipos, prioridad, costos, firmas, tiempos, causa raíz texto, failure_mode) | Ver §4. Faltan: tareas/checklist ejecutable, permiso de trabajo, LOTO, ATS, contratista, herramientas, hora programada, “ejecutado sí/no”. |
| **Técnicos en OT** | ✅ | `work_order_technicians` (lead/technician/helper + hourly_rate congelado) | “Personal de apoyo” = helper. OK. |
| **Registro de tiempo** | ✅ | `work_order_time_logs` | Falta separar tiempo efectivo vs. tiempo de espera/logística (MTTR real vs. MTTR administrativo). |
| **Firmas** | ✅ | `work_order_signatures` + `image_path` + `SignatureCanvas.vue` | — |
| **Preventivo (planes)** | ⚠️ | `maintenance_plans` (calendar/meter/hybrid/manual, cadencia fija/flotante, gracia días+horas, pausa si inactivo) + `maintenance_schedules` (next_due_at / next_due_meter) | **Modelo excelente, ejecución ausente**: no genera OT automáticamente y no ejecuta las tareas. Ver §5. |
| **Tareas y checklist** | ❌ (huérfano) | `maintenance_plan_tasks` + `maintenance_checklist_items` (boolean/numeric/text, unit, expected_min/max, is_required) existen… | …y **no hay ninguna tabla que guarde su resultado**. No existe `work_order_tasks` ni `work_order_checklist_results`. Ver §5.1. |
| **Correctivo** | ✅ | `WorkOrderType::Corrective` | — |
| **Predictivo** | ⚠️ | Solo el enum `WorkOrderType::Predictive` | No hay **mediciones de condición**: vibración, termografía, megger, análisis de aceite, alineación. El Excel documenta balanceo de ventiladores (13→2.0 g de desbalance), megger a motores, alineación con comparador de carátula (0.50 mm). No hay dónde guardarlo ni tendenciarlo. |
| **Mejoras** | ⚠️ | `WorkOrderType::Improvement` | El Excel las trata como categoría de primera clase (columna HALLAZGO=MEJORA). Falta flujo de aprobación/CAPEX. |
| **Lubricación** | ❌ | — | Decenas de tareas “cambio aceite reductor”, “lubricación chumaceras”, “cambio grasa”. Modelable como plan, pero falta catálogo de lubricantes, cantidad, y **rutas de lubricación** (una OT que recorre 20 puntos). |
| **Inspecciones / rondas** | ❌ | — | Falta “ruta de inspección” multi-equipo. Hoy 1 plan = 1 equipo. |
| **Horómetros** | ⚠️ | `equipment_meter_readings` (hours/cycles/km) + `equipment.current_meter_reading` | **1 solo medidor por equipo**. Sin tasa de consumo, sin proyección de días, sin carga masiva, sin validación de retroceso. Ver §6. |
| **Paros / downtime** | ⚠️ | `equipment_downtime_events` (was_planned, cause_type × 5, failure_mode) | Solo nace de OT (`unique(work_order_id)`), enum de causa insuficiente. **No cubre el 70 % de los paros reales.** Ver §7. |
| **Repuestos / Inventario** | ✅ | `spare_parts` (ABC, min/max, reorder point, lead time), `warehouses`, `warehouse_spare_parts`, `inventory_transactions` | Sólido. Falta reserva contra OT planificada y kardex valorado (PEPS/promedio). |
| **Compras** | ⚠️ | `purchase_orders` + `purchase_order_lines` (draft→ordered→received) | Falta requisición, cotización comparativa, recepción parcial, factura, y **OC de servicio** (contratista). |
| **Proveedores** | ⚠️ | `suppliers` | No hay evaluación de proveedor ni ligado a componentes reconstruidos. |
| **Contratistas** | ❌ | — | Ver §3. |
| **Costos** | ⚠️ | Labor + partes + externo en la OT | Sin agregación por sección/mes/técnico/contratista, sin presupuesto, sin costo de indisponibilidad (lucro cesante). Inconsistencia de moneda (equipment=USD, WO/PO=COP). |
| **KPIs** | ⚠️ | `equipment_kpis` (MTBF, MTTR, availability, unplanned availability) + `EquipmentKpiService` | **Solo por equipo.** No hay KPI de planta ni de sección. No hay snapshot histórico mensual. Ver §8. |
| **OEE** | ✅ | `equipment_production_logs` + `OeeService` | Bien. Pero no está alimentado por la realidad del cliente (ellos miden “Eficiencia de planta” = horas efectivas/programadas, no OEE clásico). |
| **Dashboard** | ⚠️ | 13 widgets Filament + `DashboardView.vue` + `ExecutiveDashboardView.vue` | Ver §9. |
| **Alertas** | ✅ | `alerts` (categoría/severidad/estado) + `AlertService` | — |
| **Automatizaciones** | ✅ | `automation_rules` + `EvaluateAutomationRulesJob` (horario) | Palanca clave para cerrar los gaps 2 y 5. |
| **Notificaciones** | ✅ | `notifications` + `push_subscriptions` + WebPush | — |
| **Webhooks / API** | ✅ | `webhook_subscriptions`, `api_request_logs`, `idempotency_keys`, Sanctum | Más maduro que el core de mantenimiento. |
| **Auditoría** | ✅ | `audit_logs`, `impersonation_logs` | — |
| **Reportes** | ⚠️ | 6 PDF + 5 Excel jobs | Falta el **reporte mensual de indicadores** que hoy usa gerencia y el **programa diario de mantenimiento** (el PDF de la programación). |
| **RCM / FMEA / RCA / Weibull / criticidad** | ❌ | `root_cause` (texto) + `failure_mode` (enum PHP fijo) | Ver §10. |
| **Permisos de trabajo / LOTO / HSE** | ❌ | — | Ver §11. |
| **Móvil** | ✅ | PWA Vue con QR, firma, cola offline | Falta captura masiva de horómetros y ejecución de checklist. |

---

## 3. Contratistas — el actor invisible

**Problema.** El Excel de historial atribuye ~30 intervenciones críticas a terceros: **Disam** (reconstrucción de tornillos y ejes), **AIC** (fabricante, canastas y camisas), **Servimontajes**, **Montajes Industriales HF** (montaje de sistema de vapor), **Ingenimec**, **Conford** (balanceo de ventiladores), **Indutronic** (SCADA), **Inzinier** (variadores). En Fronda solo hay `users` (empleados) y `suppliers` (para compras/repuestos). El costo externo es un `decimal` sin dueño.

**Impacto.** El caso más caro del cliente es exactamente un problema de contratistas: los tornillos de la prensa raquis reconstruidos por Disam duraban 322–892 h (contra >1000 h esperadas) por soldadura inadecuada, lo que derivó en fracturas totales de eje. **El sistema no puede ni detectar ese patrón ni facturárselo a nadie.**

**Riesgo.** Imposible calcular costo real de mantenimiento, imposible evaluar proveedores, imposible sustentar reclamos de garantía.

**Solución propuesta.**
- Tabla `contractors` (o extender `suppliers` con `type: material | service | both`).
- `work_order_contractors` (contractor_id, scope, cotización, costo real, fecha_inicio/fin, calificación 1–5, work_order_id).
- Ligar `equipment_components.rebuilt_by_supplier_id` + `expected_life_hours` + `actual_life_hours` → KPI **“vida útil real vs. esperada por proveedor”**.
- Permitir que `RESPONSABLE` de una OT sea un contratista, no solo un `user`.

**Prioridad:** Alta · **Complejidad:** Media · **Beneficio:** trazabilidad de costo externo + evaluación objetiva de proveedores + soporte a reclamos de garantía.

---

## 4. Órdenes de trabajo — qué falta

El modelo actual es bueno (8 estados, firmas, costos, tiempos). Contra el PDF de programación y la operación real faltan:

| Campo / concepto | ¿Existe? | Justificación desde los archivos |
|---|---|---|
| Tareas ejecutables con checklist | ❌ | §5.1 — bloqueante |
| Hora programada (06:00–18:00) | ❌ | El PDF lo trae en cada fila. `planned_start_at` existe pero no hay ventana de parada. |
| Personal de apoyo | ✅ (`helper`) | — |
| Responsable = contratista | ❌ | §3 |
| Ejecutado SÍ/NO (cierre rápido en campo) | ⚠️ | Hay `completed_at`, pero no un “no ejecutado + motivo” (se reprograma). Necesario: `not_executed_reason`. |
| Nivel de prioridad | ✅ | `WorkOrderPriority` P1..P5 vs. el “1” del PDF. Mapear. |
| Permiso de trabajo / LOTO / ATS | ❌ | §11 |
| Herramientas requeridas | ❌ | Común en Maximo/SAP. Media prioridad. |
| Repuestos usados | ✅ | `work_order_parts` con `spare_part_id`, reserva, emisión y devolución. |
| Tiempo muerto vs. efectivo | ⚠️ | `time_logs` no distingue espera de repuesto / espera de planta / trabajo efectivo. Distorsiona MTTR. |
| Causa raíz | ⚠️ | Texto libre. Sin estructura → no se puede hacer Pareto de causas. |
| Modo de falla | ⚠️ | Enum PHP hardcodeado de 15 valores. Debe ser **catálogo por tenant** y jerárquico (Clase de falla → Problema → Causa → Remedio, modelo Maximo). |
| Acción correctiva / preventiva de seguimiento | ❌ | La hoja MTTR trae `PLAN DE ACCION` por falla. Sin esto no se cierra el ciclo de mejora. |
| Geolocalización | ✅ | `activity_locations` |
| QR | ✅ | — |
| Firma técnico + supervisor | ✅ | — |

---

## 5. El agujero del preventivo

### 5.1 Los checklists no se ejecutan — **CRÍTICO**

**Problema.** `maintenance_plan_tasks` (título, orden, minutos estimados) y `maintenance_checklist_items` (label, tipo boolean/numeric/text, unidad, `expected_min`, `expected_max`, `is_required`) están perfectamente modelados… y no hay ninguna tabla, servicio ni relación que copie esas tareas a la OT ni que guarde el resultado. `WorkOrder` no tiene relación `tasks()`. Verificado: `grep -rl checklist database/migrations app/Models` devuelve solo la definición.

**Impacto.** Un preventivo de “Esterilizador — mtto e inspección válvulas Bray” se cierra hoy en Fronda con un campo de texto libre. El valor medido (presión, temperatura, espesor de camisa 2.5 mm de desgaste, alineación 0.50 mm) **no se guarda de forma estructurada**, por tanto:
- No hay evidencia de cumplimiento del preventivo (auditoría/ISO).
- No hay tendencia de variables → **el mantenimiento predictivo es imposible**.
- La inversión en modelar `expected_min/max` es 100 % desperdiciada.

**Solución.**
```sql
work_order_tasks (id, tenant_id, work_order_id, maintenance_plan_task_id NULL,
                  sort_order, title, description, estimated_minutes,
                  status ENUM(pending, in_progress, done, skipped),
                  skipped_reason, assigned_to, completed_at, completed_by)

work_order_checklist_results (id, tenant_id, work_order_task_id,
                  maintenance_checklist_item_id NULL, label, item_type,
                  value_boolean, value_numeric, value_text, unit,
                  expected_min, expected_max, is_out_of_range (generated),
                  photo_path, notes, recorded_at, recorded_by)
```
- Snapshot de `title/label/expected_*` en la OT (no FK viva) para que cambiar el plan no reescriba el historial. **Este punto es crítico y hoy no está contemplado.**
- `is_out_of_range` → dispara alerta automática + sugiere OT correctiva.
- Ejecución en la PWA móvil (ya existe `WorkOrderDetailView.vue` mobile — ahí va).

**Prioridad:** Crítica · **Complejidad:** Media · **Beneficio:** habilita preventivo real, predictivo, evidencia de cumplimiento y tendencia de variables.

### 5.2 No hay generación automática de OT preventivas — **CRÍTICO**

**Problema.** `MaintenancePlanService` tiene `initializeSchedule`, `calculateNextDue`, `isOverdue`, `recordExecution` — pero **nadie los llama en un job**. El único job programado relacionado es `SendOverdueMaintenanceNotificationsCommand` (notifica, no crea). No existe `GenerateDueWorkOrdersJob`.

**Impacto.** Con ~350 tareas preventivas activas, el planificador tendría que crear OTs a mano todos los días. El módulo preventivo no se usará.

**Solución.**
- `GeneratePreventiveWorkOrdersJob` diario (y horario para trigger=meter): recorre `maintenance_schedules` con `next_due_at <= now + lookahead_days` o `next_due_meter <= current_meter + lookahead_hours`, crea la OT en estado `Planned`, copia tareas + checklist, respeta `pause_when_equipment_inactive` y `grace_period_days`, evita duplicados (una OT abierta por plan).
- Configurable por tenant: `lookahead_days` (ej. 14) para que aparezcan en el horizonte de planificación, no el día que vencen.
- `times_skipped` ya existe en `maintenance_schedules` → usarlo cuando la cadencia es `fixed` y se saltó un ciclo.

**Prioridad:** Crítica · **Complejidad:** Media · **Beneficio:** el módulo preventivo pasa de decorativo a operativo.

### 5.3 Falta el concepto de “Parada Programada / Ventana de mantenimiento”

**Problema.** El cliente para la planta bloques de 6–12 h (lunes 06:00–18:00) y en esa ventana ejecuta N OTs, con priorización y personal asignado. En los indicadores esas horas se contabilizan como `Programada / Mantenimiento programado` (68.7 % de las paradas de junio). Fronda no tiene esa entidad.

**Solución.** Tabla `maintenance_windows` (fecha, hora_inicio, hora_fin, plantas/áreas afectadas, estado) con N:M a `work_orders`, capacidad de horas-hombre disponibles vs. requeridas, e impresión del **“Programa de Mantenimiento del día”** idéntico al PDF actual (con SÍ/NO ejecutado).

**Prioridad:** Alta · **Complejidad:** Media · **Beneficio:** reemplaza literalmente el PDF que hoy imprimen; conecta paradas programadas con el indicador de disponibilidad.

---

## 6. Horómetros — el módulo más subdimensionado

| Necesidad real (Excel) | Estado Fronda | Gap |
|---|---|---|
| Lectura acumulada + delta | ⚠️ | Se guarda acumulado; el delta se puede derivar, pero no se persiste ni valida. |
| **Múltiples medidores por equipo** | ❌ | `equipment.current_meter_reading` es UNO. Un motor podría tener horas + ciclos + kWh. |
| **Promedio de consumo (h/día)** | ❌ | El Excel lo calcula por mes y por equipo. **Sin esto no hay proyección.** |
| **“Días Faltantes” (proyección de vencimiento)** | ❌ | Fronda solo sabe si ya venció (`isOverdue`). No sabe *cuándo* vencerá. |
| **Carga masiva / ruta de lectura** | ❌ | 11 equipos diarios + 90 semanales. Hoy habría que abrir 90 formularios. |
| **Validación de retroceso / reset de medidor** | ❌ | El Excel tiene retrocesos reales (10.452 → 158). Fronda aceptaría el dato y destruiría el cálculo de MTBF. |
| **Detección de outliers** | ❌ | Delta de 116 h en un día (Planta eléctrica, 2025-09-03) es imposible → debe alertar. |
| Alertas por horómetro | ⚠️ | Vía `automation_rules`, pero sin proyección solo alerta cuando ya venció. |
| Lectura automática (PLC/SCADA) | ❌ | Existe API + webhooks → viable como integración futura. La planta tiene SCADA (Siemens/Indutronic) y CCM. |

**Solución propuesta.**
```sql
equipment_meters (id, tenant_id, equipment_id, code, name, unit,
                  is_primary, current_value, rollover_max,
                  avg_daily_consumption, avg_calculated_at, last_reading_at)
-- equipment_meter_readings gana meter_id, delta_value, is_reset, is_anomaly
meter_reading_routes (id, name, frequency)  -- "Diario críticos", "Semanal C/7días"
meter_reading_route_meters (route_id, meter_id, sort_order)
```
- Job diario: recalcula `avg_daily_consumption` (media móvil 30 días, excluyendo días de paro).
- `maintenance_schedules` gana `projected_due_at` = `next_due_meter` − `current` ÷ `avg_daily_consumption`.
- Pantalla móvil **“Ruta de lectura”**: lista secuencial, teclado numérico, valida delta contra el promedio (±3σ) y contra retroceso; funciona offline (ya hay cola offline en la PWA).

**Prioridad:** Crítica (proyección + carga masiva) / Alta (multi-medidor) · **Complejidad:** Media-Alta · **Beneficio:** habilita la planificación semanal real y elimina el Excel de horómetros.

---

## 7. Registro de paros — el indicador que la gerencia mira

**Problema.** `equipment_downtime_events` está atado al ciclo de la OT: se crea desde la OT y tiene `unique(work_order_id)`. Su `cause_type` es un enum de 5 valores (corrective, preventive, emergency, external, other). La realidad del cliente:

- ~700 paros/año, de los cuales la mayoría son **Operativa/Atascamiento** o **Externa/Falta de fruta** — **no generan OT**.
- Se registra `Hora inicio` / `Hora fin` con precisión de minutos, por un “Responsable de diligenciamiento”.
- Los mismos paros alimentan el % de paradas, la eficiencia de planta y el Pareto por equipo.

**Impacto.** Sin esto, Fronda no puede producir el informe mensual. El cliente seguiría llevando el Excel en paralelo → **el CMMS fracasa por adopción**.

**Riesgo adicional (bug latente).** `unique(work_order_id)` impide que una OT larga (Prensa P15, paro de 3 días partido en 3 registros diarios en el Excel) tenga varios eventos, y también impide que un mismo paro se atienda con 2 OTs.

**Solución.**
- Convertir `equipment_downtime_events` en **`downtime_events` de primera clase**:
  - `work_order_id` nullable, **sin unique** (una OT ↔ N eventos; un evento ↔ N OTs vía tabla pivote si hace falta).
  - `plant_id` obligatorio, `area_id`, `equipment_id` nullable (existe “Planta general”).
  - `type_i` (programada | mantenimiento | operativa | externa) y `type_ii` (catálogo configurable por tenant, ver taxonomía §1.3).
  - `started_at` / `ended_at` / `duration_minutes` (generado).
  - `reported_by`, `cause_description`, `action_plan`, `action_plan_status`.
- Registro rápido desde móvil (el operador reporta paro en 3 taps) y desde `equipment_issue_reports`.
- Un paro puede **escalar** a solicitud → OT, manteniendo el vínculo.

**Prioridad:** Crítica · **Complejidad:** Media · **Beneficio:** reemplaza el Excel de indicadores, habilita todos los KPIs de planta.

---

## 8. KPIs — lo que hay y lo que falta

### 8.1 Lo que hay
`EquipmentKpiService` (MTBF, MTTR, disponibilidad total, disponibilidad no planificada, failure_count, downtime_hours, ventana rodante de 12 meses, flag `is_stale`, recálculo nocturno).
`AnalyticsService`: failuresByMonth, downtimeTrend, mtbfTrend, mttrTrend, costByEquipment, paretoFailures, paretoFailuresByMode, reliabilityRanking, preventiveCompliance, plannedVsCorrective.
`OeeService`: plantSummary, oeeByEquipment.

Es una base **respetable**. Los problemas son de nivel de agregación y de histórico.

### 8.2 Problemas
1. **`equipment_kpis` guarda UNA fila por equipo con ventana fija de 12 meses.** No hay snapshot mensual → no hay histórico auditable ni comparación mes a mes. Si se corrigen datos viejos, el histórico cambia retroactivamente.
   → **Solución:** tabla `kpi_snapshots (scope: plant|area|equipment, scope_id, period: YYYY-MM, metric, value)` congelada al cierre de mes.
2. **No hay KPIs de planta ni de sección.** El indicador principal del cliente (Eficiencia de planta 91.46 %) no existe.
3. **Falta el denominador.** No hay “Horas Programadas de Proceso” (452 h/mes). Sin calendario de producción, la disponibilidad no es calculable como la calcula el cliente.
   → **Solución:** tabla `production_calendar (plant_id, date, planned_hours, shift)`.
4. **MTTR no distingue tiempo de espera** (repuesto en Bogotá, torno en Maní) del tiempo de intervención. El MTTR de 2.37 h del cliente es sospechosamente bajo precisamente por eso.

### 8.3 Catálogo de KPIs propuesto

**Confiabilidad y disponibilidad**
- MTBF / MTTR / MTTF — por equipo, área **y planta** ← falta planta/área
- MTBM (entre mantenimientos, incluye preventivos)
- Disponibilidad operacional = (Horas programadas − Paros) / Horas programadas ← **falta**
- Disponibilidad inherente (solo paros por falla)
- **Eficiencia de planta** (métrica nativa del cliente) ← **falta**
- Confiabilidad R(t) = e^(−t/MTBF) ← falta
- Tasa de fallas λ, índice de averías, **índice de reincidencia** (mismo equipo+modo <30 días) ← falta

**Gestión de mantenimiento**
- Cumplimiento PM (% preventivos ejecutados en ventana + gracia) — existe parcialmente
- Cumplimiento de OT / % OT cerradas a tiempo ← falta
- **Backlog** (horas-hombre pendientes ÷ capacidad semanal) ← **falta y es crítico** dado el backlog preventivo real
- Ratio Planificado / No planificado (existe: `plannedVsCorrective`)
- **Preventivo vs. Correctivo vs. Predictivo vs. Mejora** (los 4 tipos del cliente) — parcial
- Tiempo medio de respuesta (creación de solicitud → inicio de OT) ← falta
- Schedule compliance (OT ejecutadas en la ventana programada) ← falta
- OT reabiertas / retrabajos ← falta

**Costos**
- Costo por equipo (existe) / por sección / por técnico / por contratista / mensual ← faltan 4 de 5
- Costo mtto ÷ valor de reposición del activo (RAV %) ← falta
- Costo de indisponibilidad (t paro × ton/h × precio aceite) ← falta, **es el KPI que le importa al gerente**
- Presupuesto vs. real ← falta
- Costo de repuestos inmovilizados / rotación de inventario ← falta

**Personas**
- Horas-hombre por tipo de mtto, wrench time (% tiempo llave), horas extras, carga por técnico ← faltan

**Análisis**
- Pareto de paros por equipo (existe) / por modo de falla (existe) / **por Tipo II de paro** ← falta
- Pareto de costos, de repuestos consumidos ← faltan
- Tendencia de fallas y costos (existe parcialmente)
- **Matriz de criticidad** (probabilidad × consecuencia) ← falta
- **Mapa de calor de averías** (equipo × mes) ← falta
- Semáforo de salud del activo (score compuesto: MTBF + backlog + edad + criticidad) ← falta

---

## 9. Dashboard — auditoría y rediseño

**Estado.** 13 widgets Filament + `DashboardView.vue` + `ExecutiveDashboardView.vue`. Los widgets existentes (Pareto de fallas, Pareto de modos, tendencias MTBF/MTTR, downtime, costo por equipo, ranking de confiabilidad, cumplimiento, peor disponibilidad, alertas críticas) son **buenos pero orientados al analista, no al operador ni al gerente**.

**Crítica principal:** el dashboard responde “¿qué pasó?” pero no “¿qué hago hoy?”. Un CMMS enterprise abre con **acción**, no con gráficos.

### 9.1 Dashboard del Planificador / Supervisor (el que falta)
- **Fila de acción (tiles):** OT vencidas · OT de hoy · Preventivos que vencen en 7 días · Solicitudes sin revisar · Paros abiertos ahora · Repuestos bajo mínimo
- **Backlog** en horas-hombre vs. capacidad semanal (barra apilada por prioridad) ← el KPI que le falta al cliente
- **Programa de la semana** (Gantt simple: OT × día × técnico) — hoy no hay planificación de recursos
- **Próximos vencimientos por horómetro** ordenados por **“días faltantes”** ← copia directa del Excel, es lo que piden
- **Equipos parados ahora** (semáforo en vivo)

### 9.2 Dashboard Gerencial / Ejecutivo
- **Eficiencia de planta del mes** (gauge, vs. meta) + tendencia 12 meses
- **% de paradas por Tipo I** (dona: Programada / Mantenimiento / Operativa / Externa) vs. meta ≤ 10 % mtto+operativa ← su meta declarada
- **Costo de mantenimiento del mes** vs. presupuesto, desagregado en mano de obra / repuestos / contratistas
- **Costo de indisponibilidad** (horas de paro × pérdida de producción)
- **Top 10 equipos por horas de paro** (Pareto acumulado) ← ya existe, mantener
- **MTBF / MTTR de planta** con tendencia y semáforo
- **Cumplimiento PM** + **Ratio Preventivo/Correctivo** (el norte estratégico: hoy están 70/30 en modo bombero)
- **Mapa de calor** sección × mes de horas de paro
- **Top contratistas** por costo y por reincidencia de falla post-intervención

### 9.3 Dashboard del Técnico (móvil)
- Mis OT de hoy · Escanear QR · Reportar novedad · Registrar horómetro · Mis horas de la semana

**Prioridad:** Alta · **Complejidad:** Media (los datos existen para el 60 %; el resto depende de §7 y §8).

---

## 10. Ingeniería de confiabilidad — módulos a crear

El cliente **ya hace confiabilidad sin saberlo**: rastrea vida útil de tornillos por proveedor, hace Pareto de paros, balancea ventiladores, mide aislamiento con megger, calcula MTBF/MTTR. Fronda no tiene dónde soportarlo.

| Módulo | Justificación desde los archivos | Prioridad |
|---|---|---|
| **Matriz de criticidad** (probabilidad × consecuencia sobre seguridad, producción, costo, ambiente) | Hoy `criticality` es un enum manual sin metodología. La planta necesita saber que Prensa P15 > Bomba de florentino. | Alta |
| **Catálogo jerárquico de fallas** (Clase → Problema → Causa → Remedio) | `failure_mode` es un enum PHP fijo de 15 valores. Debe ser configurable y jerárquico para Pareto útil. | Alta |
| **RCA estructurado** (5 porqués / Ishikawa) con **plan de acción y verificación de eficacia** | La hoja MTTR ya trae `PLAN DE ACCION`. Ej: “cambiar sujeción tornillo-eje de cuadrado a circular”. Sin seguimiento, la falla vuelve. | Alta |
| **Mediciones de condición (CBM)** — vibración, termografía, análisis de aceite, megger, alineación, espesores | El Excel documenta balanceo (13 g → 2.0 g), megger, alineación 0.50 mm, desgaste de placas 2.5 mm. Hoy se pierde en texto libre. Tabla `condition_measurements` + límites de alarma + tendencia. | Alta |
| **FMEA** por equipo crítico | Formaliza los modos de falla recurrentes (eje corto/largo P15, canasta, tornillo, cadena elevador). | Media |
| **Vida útil de componentes rotables por proveedor** | El caso Disam/AIC. `expected_life_hours` vs. `actual_life_hours` por proveedor y procedimiento. | Alta |
| **Análisis Weibull** | Con 8 roturas de eje de P15 documentadas ya hay muestra suficiente para estimar β y η. | Baja |
| **Curvas P-F** | Requiere CBM primero. | Baja |

---

## 11. HSE — Permisos de trabajo, LOTO, ATS

**Problema.** No existe ningún artefacto de seguridad. La planta opera: caldera de vapor, turbina, esterilizador presurizado, unidades hidráulicas, tableros de media tensión, trabajos en altura (plataforma del esterilizador, ciclones), espacios confinados (tanques, hogar de la caldera, precipitador). El historial documenta trabajos en el hogar de la caldera y limpieza interna del precipitador.

**Riesgo.** Regulatorio (Res. 0312, trabajo en alturas, espacios confinados) y de vida. Un CMMS que emite una OT para entrar al hogar de la caldera sin permiso ni LOTO es un pasivo, no un activo.

**Solución mínima viable.**
- `work_permits` (tipo: caliente / altura / espacio confinado / eléctrico / izaje; vigencia; emisor; aprobador; firmas).
- `loto_points` por equipo (fuente de energía, punto de bloqueo) → checklist de bloqueo/desbloqueo obligatorio en OT de equipo crítico.
- ATS/AST embebido como checklist previo obligatorio (bloquea el paso `InProgress` sin firmar).

**Prioridad:** Alta (Crítica si hay auditoría HSE inminente) · **Complejidad:** Media · **Beneficio:** cumplimiento legal + protección real.

---

## 12. Auditoría de base de datos

### 12.1 Defectos concretos

| # | Hallazgo | Severidad | Solución |
|---|---|---|---|
| D1 | `maintenance_plan_tasks` y `maintenance_checklist_items` **son tablas huérfanas** — sin consumidor. | Crítica | §5.1 |
| D2 | `equipment_downtime_events.unique(work_order_id)` impide N eventos por OT. Además el evento no puede existir sin equipo (`equipment_id` NOT NULL) → no cubre “Planta general”. | Crítica | §7 |
| D3 | `equipment.current_meter_reading` = un solo medidor. Sin tabla `equipment_meters`. | Alta | §6 |
| D4 | **Inconsistencia de moneda:** `equipment.currency_code` default `USD`; `work_orders` y `purchase_orders` default `COP`. | Alta | Unificar a moneda del tenant (`tenants.currency_code`) y quitar defaults duros. |
| D5 | `equipment_kpis`: una fila por equipo, ventana fija 12 m, sin histórico. `is_stale` + recálculo nocturno es correcto, pero **no hay snapshot congelado**. | Alta | Añadir `kpi_snapshots` mensual §8.2. |
| D6 | `FailureMode` es un **enum PHP hardcodeado** guardado como string. No configurable por tenant, no jerárquico. | Alta | Tabla `failure_modes` jerárquica por tenant. |
| D7 | `work_order_parts` tiene `part_code` (texto libre, del diseño pre-inventario) **y** `spare_part_id` (agregado después). Duplicidad y ambigüedad. | Media | Deprecar `part_code` (o marcarlo explícitamente como “ítem no catalogado”) y validar que uno de los dos exista. |
| D8 | `areas` es plano y con `unique(plant_id, sort_order)` — reordenar áreas requiere transacción compleja o falla. | Media | Quitar el unique de `sort_order` (es un anti-patrón). Evaluar `parent_area_id`. |
| D9 | `root_cause` como `text` en la OT → imposible hacer Pareto de causas. | Media | §10 (RCA estructurado). |
| D10 | `work_order_time_logs` sin `activity_type` (efectivo / espera repuesto / espera planta / traslado). | Media | Añadir enum → MTTR real. |
| D11 | No hay `production_calendar` → el denominador de disponibilidad no existe. | Alta | §8.2 |
| D12 | `purchase_orders` sin recepción parcial (solo `received_at`) ni líneas recibidas. | Media | `purchase_order_lines.received_quantity` + `goods_receipts`. |
| D13 | `equipment_components` sin `supplier_id` de reconstrucción ni `expected_life_hours`. | Alta | §3 |
| D14 | `technical_specs` JSONB sin esquema por categoría → riesgo de basura. | Baja | Definir schema JSON por `equipment_category`. |

### 12.2 Lo que está bien (no tocar)
- UUID v7 + multi-tenant con FK a `tenants` en cada tabla + soft deletes consistentes.
- Índices parciales bien pensados (`equipment_active_idx`, `equipment_kpis_stale_idx`).
- Snapshot de `hourly_rate` en `work_order_technicians` y de `unit_cost_snapshot` en `work_order_parts` → correcto (inmutabilidad de costos históricos). **Aplicar el mismo principio a las tareas/checklist copiadas del plan.**
- `maintenance_plans` con `cadence_mode fixed|floating`, `grace_period_days`, `grace_meter_hours`, `pause_when_equipment_inactive` → **diseño superior a muchos CMMS comerciales**. Solo falta usarlo.
- `inventory_transactions` inmutable, `idempotency_keys`, `audit_logs`.

---

## 13. Auditoría UX

**Arquitectura actual:** 3 frontends — Filament (admin), Vue SPA `/app` (ops, 20 vistas), Vue PWA móvil (7 vistas, offline, QR, firma).

| Pregunta | Respuesta |
|---|---|
| ¿Demasiados clics? | **Sí en el flujo crítico.** Registrar el horómetro de 90 equipos = 90 formularios. Registrar un paro operativo = no se puede. Reportar una novedad sí es rápido (`QuickReportPanel`, `ScanQrView`). |
| ¿Formularios muy largos? | `EquipmentResource` tiene ~30 campos. Debe usar wizard/tabs y ocultar lo financiero tras un rol. La OT es larga y va a crecer (§4). |
| ¿Faltan accesos rápidos? | Existen (`CommandPalette`, `FavoritesPanel`, `QuickCreateWoPanel`, `SavedViews`). **Bien.** Falta: “Registrar paro”, “Ruta de horómetros”, “Programa de hoy”. |
| ¿Se puede trabajar desde celular? | Sí — PWA con cola offline es un acierto grande (la planta tiene zonas sin señal). Falta ejecutar checklist y capturar horómetros ahí. |
| ¿Cómodo para el técnico? | Parcial. `mission/` (MissionHero, MissionProgress, EvidenceZone, CompletionExperience) muestra buen criterio. Pero sin checklist ejecutable, el técnico no tiene qué hacer paso a paso. |
| ¿Cómodo para el supervisor? | No: le falta el tablero de asignación/backlog y el programa del día imprimible. |
| ¿Cómodo para el gerente? | No: el `ExecutiveDashboardView` no tiene los indicadores que él ya usa (§9.2). |
| ¿Intuitivo? | El vocabulario del sistema está en inglés en los enums (`in_progress`, `p1_critical`, `mechanical_wear`) y la planta habla español con su propia jerga (“hallazgo”, “paro”, “sección”, “ejecutante”, “personal de apoyo”). **Alinear el lenguaje al del cliente es adopción.** |

**Riesgo de arquitectura:** mantener Filament + SPA ops + PWA móvil = tres implementaciones de las mismas reglas. Ya se ve divergencia (Filament tiene MaintenanceCalendar; el SPA tiene PreventivosView). **Recomendación:** Filament solo para configuración/administración; SPA ops como producto real; móvil como ejecución. Definirlo explícitamente antes de que crezca.

---

## 14. Benchmark contra CMMS enterprise

| Capacidad | Maximo | SAP PM | Fiix | UpKeep | MaintainX | Limble | **Fronda** |
|---|---|---|---|---|---|---|---|
| Job plans con **tareas ejecutables + checklist** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Auto-generación de PM** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Múltiples medidores por activo** | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ❌ |
| **Proyección de vencimiento por medidor** | ✅ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ❌ |
| **Registro de paros independiente de OT** | ✅ | ✅ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ❌ |
| **Jerarquía de fallas (clase/problema/causa/remedio)** | ✅ | ✅ | ⚠️ | ❌ | ❌ | ⚠️ | ❌ |
| **Permisos de trabajo / LOTO / safety plans** | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ⚠️ | ❌ |
| **Contratistas / OC de servicio** | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ❌ |
| **Planificación de recursos (capacity / Gantt)** | ✅ | ✅ | ⚠️ | ❌ | ❌ | ⚠️ | ❌ |
| **Backlog en horas-hombre** | ✅ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ❌ |
| **Condition monitoring / CBM** | ✅ | ✅ | ✅ | ⚠️ | ❌ | ⚠️ | ❌ |
| **Presupuesto y control de costos** | ✅ | ✅ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ⚠️ |
| **Calibración de instrumentos** | ✅ | ✅ | ⚠️ | ❌ | ❌ | ❌ | ❌ |
| Inventario multi-almacén + ABC + reorder | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Móvil offline + QR + firma | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ✅ | ✅ |
| API pública + webhooks + idempotencia | ✅ | ✅ | ✅ | ⚠️ | ⚠️ | ⚠️ | ✅ |
| Multi-tenant nativo | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Automatizaciones no-code | ⚠️ | ❌ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ |

**Lectura:** Fronda es **superior a la media del mercado en plataforma** (multi-tenant, API, webhooks, auditoría, automatizaciones, PWA offline) y **está por debajo del mínimo del mercado en el núcleo de mantenimiento** (ejecución de PM, medidores, paros, seguridad, contratistas). Se construyó el chasis y el sistema eléctrico antes que el motor.

---

## 15. Roadmap priorizado

### 🔴 Prioridad Crítica — bloquea producción (~6–9 semanas)

| # | Entregable | Complejidad | Beneficio |
|---|---|---|---|
| C1 | **Tareas y checklist ejecutables en la OT** (`work_order_tasks`, `work_order_checklist_results`, con snapshot y `is_out_of_range`), ejecución en PWA móvil | Media | Habilita preventivo real, predictivo y evidencia de cumplimiento |
| C2 | **Generación automática de OT preventivas** (`GeneratePreventiveWorkOrdersJob` con lookahead, gracia, anti-duplicado) | Media | Vuelve operativo el módulo preventivo con 350 tareas |
| C3 | **Registro de paros independiente de OT** con taxonomía Tipo I/Tipo II del cliente; captura móvil rápida | Media | Reemplaza el Excel de indicadores; base de todos los KPIs |
| C4 | **Horómetros: proyección + ruta de lectura + validación** (`avg_daily_consumption`, `projected_due_at`, carga masiva móvil, detección de reset/outlier) | Media-Alta | Permite planificar la semana; elimina el Excel de horómetros |
| C5 | **KPIs de planta y sección** + `production_calendar` + Eficiencia de planta + snapshots mensuales | Media | Produce el informe mensual que hoy hace Excel |
| C6 | **Migración de datos históricos** de los 4 Excel (equipos, horómetros, 350 tareas preventivas, 700 registros de historial, 700 paros) | Alta | Sin histórico el sistema arranca con MTBF/MTTR vacíos → nadie lo cree |

### 🟠 Prioridad Alta — valor alto (~6–8 semanas)

| # | Entregable | Complejidad |
|---|---|---|
| A1 | **Contratistas** como entidad + OC de servicio + vida útil de componente por proveedor | Media |
| A2 | **Ventanas de mantenimiento / paradas programadas** + “Programa del día” imprimible (réplica del PDF) | Media |
| A3 | **Permisos de trabajo, LOTO y ATS** | Media |
| A4 | **Backlog en horas-hombre** + tablero de planificación/asignación (Gantt semanal) | Media-Alta |
| A5 | **Dashboards por rol** (Planificador / Gerente / Técnico) según §9 | Media |
| A6 | **Catálogo jerárquico de fallas** + **RCA estructurado con plan de acción y verificación de eficacia** | Media |
| A7 | **Mediciones de condición (CBM)**: vibración, termografía, megger, alineación, análisis de aceite, con límites y tendencia | Media |
| A8 | **Múltiples medidores por equipo** | Media |
| A9 | Costos por sección / técnico / contratista / mes + presupuesto + **costo de indisponibilidad** | Media |
| A10 | Deuda técnica DB: D2, D4, D5, D6, D7, D10, D11 | Baja-Media |
| A11 | Español operativo en toda la UI (enums, labels, jerga del cliente) | Baja |

### 🟡 Prioridad Media (~4–6 semanas)

| # | Entregable |
|---|---|
| M1 | Matriz de criticidad con metodología (probabilidad × consecuencia) |
| M2 | Rutas de lubricación e inspección multi-equipo + catálogo de lubricantes |
| M3 | Compras completas: requisición → cotización → OC → recepción parcial → factura |
| M4 | FMEA por equipo crítico |
| M5 | Evaluación de proveedores y contratistas (KPI de reincidencia post-intervención) |
| M6 | Reportes ejecutivos programados (PDF mensual automático a gerencia) |
| M7 | Herramientas y equipos de apoyo en OT; distinción tiempo efectivo vs. espera |
| M8 | Definir y documentar el reparto Filament ↔ SPA ops ↔ PWA (evitar triple mantenimiento) |

### 🟢 Prioridad Baja / futuro

| # | Entregable |
|---|---|
| B1 | Integración SCADA/CCM para lectura automática de horómetros (la planta ya tiene Siemens/Indutronic) |
| B2 | Análisis Weibull y curvas P-F |
| B3 | Calibración de instrumentos |
| B4 | Optimización de inventario (EOQ, stock de seguridad por criticidad × lead time) |
| B5 | Predicción de fallas con ML sobre las series de horómetros/CBM |
| B6 | Gestión de garantías y reclamos a proveedor |

---

## 16. Conclusión

Fronda tiene un **excelente esqueleto de plataforma** (multi-tenant, API con idempotencia, webhooks, auditoría, automatizaciones, PWA offline, reportes PDF/Excel, KPIs de confiabilidad por equipo) y un **modelo de planes preventivos mejor pensado que el de varios CMMS comerciales** (cadencia fija/flotante, gracia por días y por horas, pausa si el equipo está inactivo).

Pero hoy **no puede reemplazar ninguno de los 4 Excel que usa El Pajuil**:
- No ejecuta los checklists que define → no reemplaza el Excel de horómetros/preventivos.
- No genera OTs preventivas → el planificador seguiría en Excel.
- No registra los paros que la gerencia mide → no reemplaza el Excel de indicadores.
- No imprime el programa del día → no reemplaza el PDF de programación.

Los 6 ítems de prioridad crítica cierran exactamente esa brecha. **Con C1–C6 el sistema es adoptable; sin ellos, el cliente terminará usando Fronda *además* de Excel, que es la forma más común en que fracasa un CMMS.**

---

## 17. Estado de ejecución — Fase 1 (backend)

Ejecutado de forma autónoma el 2026-07-13. **981/981 tests verdes, Pint limpio.**

| Ítem | Estado | Entregado |
|---|---|---|
| **C1** — Checklist ejecutable en la OT | ✅ Hecho | Tablas `work_order_tasks` y `work_order_checklist_results` (con columna generada `is_out_of_range`), enum `WorkOrderTaskStatus`, `WorkOrderTaskService`, `ChecklistIncompleteException`. La OT nace desde el plan con tareas y tolerancias **congeladas**; no se puede completar con mediciones obligatorias sin responder; un valor fuera de rango genera un `Alert` de confiabilidad. 18 tests. |
| **C2** — Generación automática de preventivos | ✅ Hecho | `PreventiveWorkOrderGenerator` + `GeneratePreventiveWorkOrdersJob` (diario 05:00, uno por tenant). Genera con 7 días de anticipación, por calendario **o por proyección de horómetro**; idempotente (no duplica si el plan ya tiene OT abierta); prioriza por criticidad del equipo. `AdvanceMaintenancePlanScheduleListener` cierra el ciclo: al completar el preventivo, el plan avanza. 16 tests. |
| **C3** — Paros independientes de la OT | ✅ Hecho | `equipment_downtime_events` ahora admite paros **sin equipo** (paro de planta) y **sin OT**; taxonomía real Tipo I (`StoppageCategory`) × Tipo II (`stoppage_cause`); flag `affects_production`; `DowntimeService` con apertura/cierre, prevención de solapes y `lostHoursByCategory()`. 15 tests. |
| **C4** — Horómetros | ✅ Hecho | El cambio de horómetro **ya no lanza excepción**: se registra como `is_reset` y el consumo real se acumula en `accumulated_meter_reading`, que nunca retrocede y es la base contra la que se programa el preventivo. Ronda diaria en bloque (`recordBulk`), ritmo de consumo (`consumptionPerDay`) y proyección de **«días faltantes»** (`daysUntilDue`). 16 tests. |
| **C5** — KPIs de planta | ✅ Hecho | `production_calendar` (el denominador que no existía) y `plant_monthly_kpis` con `efficiency_percentage` como columna generada. `PlantKpiService` reproduce el número del cliente: **452 h programadas − 38,6 h perdidas = 413,4 h efectivas = 91,46 %**. MTBF/MTTR de planta calculados solo sobre las fallas que mantenimiento realmente posee. `SnapshotPlantKpisJob` congela el mes. 10 tests. |
| **C6** — Programa impreso del día | ⬜ Pendiente | No abordado en esta fase. |

### Cambios de comportamiento a tener en cuenta

1. **Una OT con checklist ya no se puede completar con mediciones obligatorias en blanco.** El guard está en `WorkOrderService::transition()`, la única puerta de salida. Las OT sin tareas (correctivos ad-hoc) no se ven afectadas.
2. **`EquipmentMeterReadingService::record()` ya no lanza `RuntimeException` ante una lectura hacia atrás.** El test que exigía ese comportamiento fue reemplazado por otro que exige el correcto; el motivo está documentado en el propio test.
3. **`equipment.meter_unit` ahora está casteado a `MeterReadingUnit`.** Se corrigió la comparación contra string en `EquipmentKpiService`.
4. **El listener de avance de plan se registra por autodescubrimiento** (`app/Listeners`), no en `AppServiceProvider`: registrarlo en ambos sitios duplicaba el avance del cronograma.

### Siguiente paso natural

Falta la **capa de UI** de todo lo anterior (Filament + Vue/PWA): pantalla de ejecución de checklist en móvil, registro de paros del supervisor, ronda de horómetros, calendario de producción y dashboard de eficiencia de planta. El backend ya expone todo lo que esas pantallas necesitan.
