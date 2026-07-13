# Fronda CMMS — Roadmap Fase 2 y prompt de ejecución

**Estado a 2026-07-13.** Fase 1 cerrada (C1–C5) + segunda auditoría de flujos ejecutada.
Suite: 1025+ tests verdes. Pint limpio. Build correcto.

---

## PARTE 1 — ESTADO REAL

### Cerrado (no volver a tocar salvo bug)

| | Qué quedó funcionando |
|---|---|
| **C1** | Checklist ejecutable congelado en la OT. No se cierra una OT con mediciones obligatorias en blanco. Valor fuera de rango → `Alert` de confiabilidad. |
| **C2** | Generador de preventivos (job diario) por calendario o por proyección de horómetro. Idempotente. Al completarse, el plan avanza. |
| **C3** | Paros independientes de la OT: paro de planta sin equipo, taxonomía Tipo I × Tipo II, `affects_production`. |
| **C4** | Horómetros con reset (el dial baja, el acumulado no), ronda diaria en bloque, ritmo de consumo, «días faltantes». |
| **C5** | KPIs de PLANTA: `production_calendar` + eficiencia (91,46 % reproducido), MTBF/MTTR de planta, cierre mensual congelado. |
| **UI** | PWA: ejecución de checklist con soporte offline. SPA: Paros, Horómetros, Eficiencia de planta, pestaña Checklist en OT. |

### Bugs encontrados en la 2ª auditoría y ya corregidos

1. **Fuga multi-tenant** — `exists:equipment,id` no filtra por tenant y los servicios resolvían con `withoutGlobalScopes()`. Un tenant podía escribir horómetros y paros sobre equipos de otro. *(Probado y cerrado.)*
2. **MTBF de planta ciego a los correctivos** — un paro nacido de una OT queda como Tipo I «otro», que no era responsabilidad de mantenimiento. El número que existe para medir fallas las excluía todas.
3. **Doble avance del plan** — rechazo del supervisor + re-completar disparaba `recordExecution` dos veces y el plan saltaba un ciclo.
4. **Plan por horómetro mudo** — activado sin `next_due_meter` explícito nunca generaba su primera OT.
5. **Firma tardía cancelaba el siguiente preventivo** — una OT completada y no cerrada administrativamente bloqueaba la generación.
6. **Listeners registrados dos veces** — autodescubrimiento + `Event::listen` explícito. **Cada alerta notificaba dos veces y cada webhook se entregaba dos veces al endpoint del cliente.** Bug previo, en producción hoy.

---

## PARTE 2 — LO QUE FALTA

### 🔴 Crítico — bloquea producción

| ID | Qué | Por qué duele |
|---|---|---|
| **F1** | **Paros solapados**. La validación de solape solo mira paros *abiertos*. Dos paros históricos que se pisan cuentan sus horas dos veces. | La eficiencia de planta —el número que va a gerencia— sale inflado y nadie puede auditarlo. |
| **F2** | **Paros a caballo entre meses**. Un paro del 31 al 1 se cuenta completo en el mes que empezó. | Distorsiona el cierre mensual, que es inmutable una vez congelado. |
| **F3** | **C6 — Programa impreso del día**. El PDF que el planificador reparte en la mañana. | Sin esto el cliente sigue usando su Excel de programación. Es uno de los 4 Excel que Fronda debía reemplazar. |
| **F4** | **Migración de datos históricos**. Cargar los 4 Excel reales (horómetros, paros, OTs, indicadores) al sistema. | Un CMMS que arranca vacío no tiene MTBF, ni Pareto, ni línea base. El cliente no confía en él. |

### 🟠 Alto — necesario para operar de verdad

| ID | Qué |
|---|---|
| **A1** | **Contratistas** (Disam, AIC, Servimontajes…). Entidad, asignación a OT, costo externo real. Hoy aparecen en el histórico y no existen en el modelo. |
| **A2** | **Permisos de trabajo / LOTO / ATS**. Trabajo en caliente, espacio confinado, bloqueo y etiquetado. Es HSE, no es opcional en una extractora. |
| **A3** | **Filament**: recursos para Paros, Calendario de producción y Horómetros. Hoy solo existen en la SPA. |
| **A4** | **Tipo I en la OT**. Al diagnosticar la falla, el técnico debe poder afinar el Tipo I del paro que la OT generó (hoy queda «otro»). |
| **A5** | **Firma del paro por producción**. Un paro que resta horas a la planta debería confirmarlo el jefe de turno, no solo mantenimiento. |
| **A6** | **Reporte de horas perdidas** exportable (PDF/Excel) por Tipo I × Tipo II, con Pareto. |
| **A7** | **Alertas de horómetro sin lectura**: un equipo que lleva N días sin lectura rompe el programa preventivo en silencio. |

### 🟡 Medio — calidad y confianza

| ID | Qué |
|---|---|
| **M1** | **Backfill de `stoppage_category`** en los paros históricos que queden en «otro». |
| **M2** | **`work_order_time_logs.activity_type`** (diagnóstico / reparación / espera de repuesto). Sin esto el MTTR mezcla espera con trabajo y miente. |
| **M3** | Normalizar `currency_code` (hay default USD contra operación en COP). |
| **M4** | `areas` tiene `unique(plant_id, sort_order)`: un anti-patrón que ya provocó un test intermitente. Debería ser un índice, no una restricción. |
| **M5** | Tests E2E (Playwright) de los flujos nuevos: ejecutar checklist, registrar paro, ronda de horómetros. |
| **M6** | `work_order_parts`: ambigüedad entre `part_code` y `spare_part_id`. |

### 🔵 Bajo — cuando lo anterior esté firme

RCM/FMEA, análisis Weibull, curvas P-F, RCA formal, integración SCADA para lectura automática de horómetros, predicción de fallas.

---

## PARTE 3 — PROMPT DE EJECUCIÓN

> Copia desde aquí hasta el final en una sesión nueva de Claude Code.

### CONTEXTO

Eres el equipo técnico de **Fronda CMMS**, un sistema de gestión de mantenimiento para
**Extractora El Pajuil** (planta extractora de aceite de palma, Colombia).

**Stack:** Laravel 13 · Filament 5 · Livewire 4 · Vue 3 (SPA `/app` + PWA móvil offline) ·
PostgreSQL · Pest 4 · Horizon · Sanctum. Arquitectura por dominios en `app/Domain/*`.
Multi-tenant con `BelongsToTenant` + `TenantScope`.

**Lee primero:** `docs/AUDITORIA-FRONDA-EL-PAJUIL.md` (auditoría original, secciones 1–17)
y `docs/ROADMAP-FASE-2.md` (este archivo, partes 1 y 2).

**La Fase 1 está cerrada.** Checklist ejecutable, generador de preventivos, paros
independientes de la OT, horómetros con reset y KPIs de planta funcionan y están
probados. No los reconstruyas: extiéndelos.

### GOALS

**G1 — Ningún número que llegue a gerencia puede ser mentira.**
Cerrar F1 (paros solapados) y F2 (paros entre meses). La eficiencia de planta, el MTBF
y las horas perdidas deben ser auditables hasta el evento que las originó.
*Métrica: dos paros que se pisan no pueden sumar sus horas dos veces, y existe un test que lo prueba.*

**G2 — Fronda reemplaza los 4 Excel, no convive con ellos.**
Cerrar F3 (programa impreso del día) y F4 (migración del histórico real).
*Métrica: el planificador imprime su programación desde Fronda y el sistema muestra MTBF con datos históricos, no vacío.*

**G3 — El costo real de mantenimiento incluye a quien no es empleado.**
Cerrar A1 (contratistas): entidad, asignación a OT, costo externo.
*Métrica: una OT ejecutada por Disam refleja su costo en `actual_cost_external`.*

**G4 — Nadie entra a un espacio confinado sin permiso.**
Cerrar A2 (permisos de trabajo / LOTO / ATS).
*Métrica: una OT marcada como trabajo en caliente no puede pasar a InProgress sin permiso firmado.*

**G5 — Lo que existe en la SPA existe en Filament.**
Cerrar A3: recursos de Paros, Calendario de producción y Horómetros.

**G6 — El MTTR mide reparar, no esperar.**
Cerrar M2 (`activity_type` en los time logs).
*Métrica: el MTTR excluye la espera de repuestos y lo dice explícitamente.*

**G7 — La suite es la red de seguridad, no un trámite.**
Todo cambio con test. Suite completa verde antes de cerrar cualquier entregable.
*Métrica: `php artisan test --compact` verde y `vendor/bin/pint --dirty` limpio.*

### RESTRICCIONES NO NEGOCIABLES

1. **Snapshot.** Todo valor copiado de una plantilla mutable a una OT queda **congelado**.
   Ya se aplica en tareas, checklist, tarifas y costos de repuestos. Extiéndelo, no lo rompas.
2. **Multi-tenant.** Todo id que llegue del request se resuelve **dentro del tenant**.
   `exists:x,id` NO filtra por tenant. Ya hubo una fuga por esto: no la repitas.
3. **La lógica vive en `app/Domain/*/Services`.** Nunca en un Resource de Filament ni en un controlador.
4. **No inventes datos.** Si falta el denominador, el KPI es `null`, no 100 %.
5. **Sin migraciones destructivas** sin plan de backfill explícito.
6. **Pest.** `php artisan test --compact --filter=X`. No borres tests sin justificarlo en el mensaje del commit.
7. **Español** en mensajes de error y UI. Inglés en el código y los comentarios.

### AGENTES

Invócalos con el Task tool cuando el trabajo lo justifique. No todos en todo.

**`arquitecto-datos`**
> Diseñas el esquema. Conoces PostgreSQL a fondo: columnas generadas, índices parciales,
> constraints CHECK, exclusion constraints para rangos temporales. Tu trabajo es hacer que
> el dato inválido sea **imposible de escribir**, no que se valide en PHP. Antes de proponer
> una tabla, revisas las que existen: `database/migrations`. Sigues el estilo del repo:
> `uuid('id')->primary()`, `foreignUuid`, `timestampsTz(0)`, banners de sección.
> *Para F1 considera seriamente un `EXCLUDE USING gist` sobre el rango temporal por equipo.*

**`ingeniero-confiabilidad`**
> Eres ingeniero de confiabilidad (RCM). Defiendes la integridad de los indicadores contra
> la conveniencia. Sabes que MTTR que incluye espera de repuestos no es MTTR, que un mes sin
> denominador no tiene eficiencia, y que un paro contado dos veces destruye la confianza en
> todo el sistema. Cuestionas cualquier fórmula que no puedas defender ante un auditor.

**`consultor-cmms`**
> Vienes de implantar Maximo, SAP PM e Infor EAM. Sabes exactamente cómo mueren los CMMS:
> se usan *además* del Excel, no en lugar de él. Tu criterio para cada entregable es
> "¿esto hace que el planificador abandone su hoja de cálculo?". Si la respuesta es no,
> lo dices.

**`especialista-hse`**
> Seguridad industrial en plantas de proceso. Permisos de trabajo, LOTO, espacio confinado,
> trabajo en caliente, ATS. Para ti un permiso no es un campo de texto: es un flujo con
> firmas, vigencia y bloqueo real de la ejecución. Diseñas G4.

**`ingeniero-datos-migracion`**
> Migras los 4 Excel reales a Fronda (G2/F4). Eres escéptico: los datos reales tienen
> horómetros que retroceden, fechas imposibles, equipos que no existen en el maestro y
> celdas con "N/A". Tu entregable incluye un **reporte de rechazos**, no solo un `insert`.
> Nunca cargas silenciosamente un dato que no entendiste.

**`ux-industrial`**
> Diseñas para un técnico con guantes, bajo el sol, con un celular de gama baja y sin señal.
> Botones grandes, pocos toques, estado siempre visible, todo funciona offline. Odias los
> modales anidados y los formularios de 20 campos.

**`qa-pest`**
> Escribes los tests que el implementador no quiso escribir: el caso del borde, el del
> tenant ajeno, el de la carrera entre dos jobs, el del dato que llega nulo. Un test que
> solo prueba el camino feliz, para ti, no existe.

### ORDEN DE EJECUCIÓN

```
Fase A (paralelizable, cierra la integridad de los números)
  F1  Paros solapados            → arquitecto-datos + ingeniero-confiabilidad
  F2  Paros entre meses          → ingeniero-confiabilidad
  M2  activity_type en time logs → ingeniero-confiabilidad

Fase B (deja de convivir con Excel)  ← depende de que los números sean confiables
  F3  Programa impreso del día   → consultor-cmms + ux-industrial
  F4  Migración del histórico    → ingeniero-datos-migracion

Fase C (operar de verdad)
  A1  Contratistas               → consultor-cmms
  A2  Permisos / LOTO / ATS      → especialista-hse
  A4  Tipo I afinable desde la OT
  A7  Alerta de horómetro sin lectura

Fase D (paridad y pulido)
  A3  Recursos Filament
  A6  Reporte de horas perdidas
  M1, M3, M4, M5, M6
```

### DEFINITION OF DONE

Un entregable está hecho cuando:

1. La lógica vive en un Service de dominio, no en un controlador ni en Filament.
2. Tiene tests Pest que cubren el camino feliz, el borde y el **aislamiento multi-tenant**.
3. `php artisan test --compact` está verde **entero**, no solo el filtro.
4. `vendor/bin/pint --dirty --format agent` está limpio.
5. Si tocó frontend, `npm run build` pasa.
6. Si cambió comportamiento existente, está dicho explícitamente en el resumen.
7. Si encontraste un bug de paso, lo reportaste aunque no lo arreglaras.
8. Ningún KPI devuelve un número inventado cuando le falta el dato.
