# Sprint UX-3.0 — Equipment Experience Discovery

**Tipo:** Product Discovery · **Estado:** `Borrador — pendiente aprobación` · **Fecha:** Junio 2026

**Equipo:** CPO · UX Research · Industrial Maintenance Consulting · Enterprise Software · Architecture · CTO

---

## 0. Cómo leer este documento

Este documento no contiene soluciones. Contiene observaciones, preguntas abiertas, y la articulación de problemas que el Sprint UX-3 deberá resolver. Cualquier propuesta de diseño o implementación que aparezca aquí es especulativa y no debe ejecutarse sin revisión del equipo de producto.

La fuente de este documento es el código real del módulo, no suposiciones. Cada hallazgo tiene una referencia al lugar donde se observó.

---

## I. El módulo hoy — inventario honesto

### Superficie del módulo

El módulo de Equipos tiene tres superficies distintas, cada una con capacidades muy diferentes:

**1. Lista de equipos (Ops Panel Desktop)**
`/ops/equipos` — `resources/js/ops/views/EquipmentListView.vue`

Grid de cards con: código monoespaciado, nombre del equipo, badge de estado (activo/inactivo/en mantenimiento/retirado), badge de criticidad, badge de prioridad, ubicación (planta · área). Búsqueda en tiempo real por código/nombre/serie. 4 filtros de estado. Selección múltiple para acciones bulk (cambiar estado y criticidad). Paginación por cursor (30 ítems). Vistas guardadas. Botón "Nuevo equipo" que redirige a Filament Admin.

**2. Ficha de equipo (Ops Panel Desktop)**
`/ops/equipos/:id` — `resources/js/ops/views/EquipmentDetailView.vue`

Sticky header con breadcrumbs (incluyendo ancestros), foto, código, nombre, badges (estado/criticidad/categoría/ubicación), estrella de favorito, botón de descarga PDF. KPI strip de 5 celdas: Disponibilidad (emerald) · MTBF (blue) · MTTR (amber) · Fallas (red) · Downtime (slate). Desktop: anchor nav de 7 secciones con IntersectionObserver. Mobile: 7 tabs.

Secciones disponibles: Información · Estado del activo · Componentes/Sub-equipos · Órdenes de trabajo recientes · Planes preventivos · Repuestos utilizados · Fotos · Documentos · Historial.

**3. Ficha de equipo (Mobile PWA)**
`/mobile/equipment/:id` — `resources/js/mobile/views/EquipmentDetailView.vue`

Header con nombre y código. Badges de estado y criticidad. Secciones: Ubicación (planta/área/categoría), Datos técnicos (fecha instalación, is_active), Notas. Sin KPIs. Sin OTs. Sin historial. Sin acciones. Sin fotos. Sin documentos.

---

### Lo que el módulo hace bien

Esto importa. No empezamos el sprint destruyendo lo que funciona.

**La ficha 360° es genuinamente completa.** En escritorio, la Ficha de Equipo concentra en una sola vista toda la información relevante: identidad, KPIs, jerarquía de activos, historial de OTs, planes preventivos, repuestos consumidos, fotos, documentos, timeline. Un supervisor de mantenimiento puede responder la mayoría de las preguntas sobre un equipo sin salir de esta pantalla.

**El sticky header es correcto.** La identidad del equipo (foto, código, nombre, badges, KPIs) permanece visible mientras el usuario navega las secciones. El usuario siempre sabe qué equipo está viendo.

**Los Context Banners funcionan.** Cuando un equipo es componente de otro, el Context Banner indigo muestra el equipo padre con acceso directo. Cuando hay OTs, la última aparece en contexto. Esto implementa PDR-005 (las entidades nunca son islas) correctamente.

**El breadcrumb de ancestros es valioso.** Para activos con jerarquía profunda (compresor → turbocompresor → línea de producción), los breadcrumbs muestran toda la cadena. Raro en CMMS competidores.

**El historial de actividad es la mejor sección.** El timeline con 9 tipos de eventos (OTs, preventivos, paradas, lecturas, fotos, documentos, fallas, repuestos) es una vista de ciclo de vida del activo que ningún competidor tiene bien resuelto. El filtro por tipo de evento aún no existe, pero la data está.

**El skeleton loader replica fielmente la estructura.** Al cargar, el usuario ve exactamente la forma de lo que vendrá — foto de 80px, breadcrumbs, título, badges, KPI strip de 5 celdas. Implementa correctamente el Design Language.

**Las vistas guardadas existen.** El supervisor que siempre trabaja con equipos críticos activos puede guardar ese filtro.

---

### Lo que el módulo hace mal

**Problema 1 — Creación de equipos rompe el flujo**

El botón "Nuevo equipo" en la lista ejecuta `window.location.href = '/admin/{slug}/equipment/create'`. El usuario abandona el panel operativo y entra al panel administrativo de Filament. Si necesita volver a la lista, usa el botón de atrás del navegador. No hay experiencia integrada de creación.

Impacto: El administrador que crea equipos regularmente trabaja en dos contextos diferentes sin transición clara. La experiencia se siente como usar dos productos distintos.

---

**Problema 2 — Sin edición desde el panel operativo**

No existe ningún botón de edición en la Ficha de Equipo del ops panel. Para modificar cualquier dato del equipo (incluso uno simple como las notas o la ubicación), el usuario tiene que navegar al panel administrativo. La Ficha 360° es de solo lectura.

Impacto: Los supervisores y planeadores que detectan datos incorrectos (código erróneo, ubicación desactualizada) no pueden corregirlos desde su contexto de trabajo habitual.

---

**Problema 3 — Sin acciones primarias en la ficha**

La acción más probable de un usuario al abrir la ficha de un equipo es: crear una OT para ese equipo, reportar un problema, o registrar una lectura de medidor. Ninguna de estas acciones existe en la Ficha de Equipo. El único botón de acción es "PDF".

Para crear una OT vinculada a ese equipo, el usuario tiene que: ir a "Órdenes de trabajo" en el menú lateral → crear nueva OT → buscar y seleccionar el equipo manualmente. El equipo ya estaba seleccionado implícitamente cuando el usuario estaba en su ficha.

Impacto: El flujo más frecuente del supervisor (detectar un problema en un equipo → crear una OT para ese equipo) requiere navegar fuera de la ficha, perder el contexto, y reconstruirlo en otro módulo. Viola PDR-002 (la acción siempre aparece antes que la información) y UX Principio 4 (las acciones más frecuentes son las más accesibles).

---

**Problema 4 — Mobile PWA es un cascarón**

La Ficha de Equipo en mobile muestra: nombre, código, estado, criticidad, ubicación, fecha de instalación, is_active, notas. El técnico que llega al equipo con su teléfono (probablemente habiendo escaneado el QR) no puede:
- Ver las OTs activas de ese equipo
- Ver cuándo fue la última intervención
- Ver si hay un preventivo vencido
- Ver la documentación técnica (manuales, esquemas)
- Reportar un problema
- Crear una OT
- Registrar una lectura de medidor

Prácticamente toda la información operacional relevante para un técnico en campo es inaccesible desde mobile.

Impacto: El QR en el activo físico, que debería ser el punto de entrada más natural al sistema para el técnico, lleva a una pantalla que no responde ninguna pregunta de trabajo. Viola directamente el principio "¿Qué debo hacer hoy?" (PDR-008) y contradice la filosofía de la mobile PWA como herramienta de campo.

---

**Problema 5 — KPIs sin contexto temporal**

Los 5 KPIs del strip (Disponibilidad, MTBF, MTTR, Fallas, Downtime) se muestran sin indicar de qué período son. No hay selector de período. No hay comparativa con período anterior.

Cuando el sistema muestra "Disponibilidad: 87.3%", el usuario no sabe si eso es del último mes, del último año, o desde que el equipo existe en el sistema. Un KPI sin período no es un indicador — es un número.

Impacto: Los KPIs pierden su capacidad de orientar decisiones. Un equipo con 87% de disponibilidad en el último mes puede ser un problema; con 87% en los últimos 5 años puede ser excelente. La misma cifra, interpretaciones opuestas.

---

**Problema 6 — Duplicación del KPI**

Los mismos KPIs aparecen dos veces en la misma pantalla:
1. En el KPI strip del header (tamaño pequeño, siempre visible)
2. En la sección "Estado del activo" (tamaño grande, en el cuerpo de la página)

La sección "Estado del activo" agrega "Costo acumulado" y "Última intervención" — que sí son datos distintos — pero el resto de los KPIs es información duplicada. La duplicación confunde al usuario sobre si los datos son los mismos o distintos.

---

**Problema 7 — "Ver todas las órdenes" pierde el contexto**

En la sección de OTs, el link "Ver todas las órdenes →" apunta a `{ name: 'ops.ordenes' }` — la lista general de todas las órdenes del tenant, sin ningún filtro preestablecido por equipo. El usuario que hace click pierde el contexto del equipo que estaba revisando y aterrriza en la lista global.

El comportamiento correcto: "Ver todas las órdenes de este equipo" → lista de OTs filtrada por `equipment_id`.

---

**Problema 8 — Filtros de lista insuficientes**

La lista de equipos tiene solo 4 filtros de estado: Todos / Activos / En mantenimiento / Inactivos. No existen filtros por:
- Planta o área (dimensión crítica para supervisores multi-planta)
- Criticidad (el jefe de mantenimiento necesita ver los críticos primero)
- Categoría (ver solo compresores, o solo bombas)
- Equipos con OTs activas (ver qué está siendo intervenido)
- Equipos con preventivos vencidos (ver qué está en riesgo)

Un jefe de mantenimiento con 200 equipos no puede gestionar su flota con solo el filtro de estado.

---

**Problema 9 — Las cards de la lista no muestran señales operacionales**

Una card de equipo en la lista muestra: código, nombre, estado, criticidad, prioridad, ubicación. No muestra:
- Si tiene OTs activas (el equipo está siendo intervenido ahora mismo)
- Si tiene un preventivo vencido (requiere atención)
- Su disponibilidad actual (señal de salud rápida)
- Cuándo fue la última intervención (señal de antigüedad del mantenimiento)

El supervisor que escanea la lista de equipos no puede identificar cuáles requieren atención sin abrir cada ficha individualmente.

---

**Problema 10 — La sección "Repuestos utilizados" está incorrectamente clasificada**

En mobile, "Repuestos utilizados" comparte tab con "OTs". En desktop, es una sección separada entre "Planes preventivos" y "Fotos". No tiene un lugar lógico claro.

Los repuestos consumidos son parte del historial de mantenimiento — deberían estar en el historial o agrupados con las OTs, no como una sección independiente entre preventivos y fotos.

---

**Problema 11 — El historial no es filtrable**

El historial de actividad tiene 9 tipos de eventos distintos (OTs, preventivos, paradas, lecturas, fotos, documentos, fallas, repuestos). No hay filtros. El usuario que quiere ver solo las paradas de producción tiene que desplazarse por el historial completo para identificarlas por el color del dot.

Para un equipo con 3 años de historial, este problema es severo.

---

**Problema 12 — Sin acceso a documentación técnica desde mobile**

Los manuales, esquemas y procedimientos de mantenimiento están en la sección "Documentos" de la ficha desktop. En mobile, no existen. Un técnico que necesita consultar el manual de un equipo mientras trabaja en campo no puede hacerlo desde Fronda — tiene que recurrir a otros medios (carpetas físicas, otro sistema, llamar a alguien).

Este es posiblemente el problema más crítico para el usuario de campo. La documentación técnica en el momento de la intervención reduce errores y tiempo de diagnóstico.

---

**Problema 13 — Acciones bulk insuficientes**

Las acciones bulk en la lista permiten cambiar estado y criticidad de múltiples equipos. No existe bulk action para: asignar a planta/área, asignar categoría, generar QR codes, exportar. El administrador que registra 50 equipos nuevos de una migración tiene trabajo manual limitado por estas restricciones.

---

## II. Journey Maps por rol

Los journey maps documentan el flujo real de cada usuario cuando interactúa con el módulo, incluyendo los pasos que el sistema no facilita y que el usuario resuelve por fuera.

---

### Técnico — "El equipo de la línea 3 dejó de funcionar"

**Contexto:** Turno nocturno. El técnico está en la nave industrial. El supervisor le asigna verbalmente o por WhatsApp que hay un problema con el compresor de la línea 3.

```
1. [CAMPO] Técnico llega al equipo físico
   → Busca el código en la etiqueta física del equipo

2. [MOBILE] Abre Fronda mobile
   → Escanea QR (si tiene el código QR en la etiqueta)
   — O BIEN: va a la lista de OTs y busca la suya —

3. [MOBILE] Llega a la Ficha de Equipo en mobile
   → Ve: nombre, código, ubicación, notas
   → NO ve: OTs activas, historial de fallas, documentación técnica
   
   ★ PUNTO DE FRUSTRACIÓN: La pantalla no le dice nada sobre
     el estado operacional del equipo. El técnico no sabe si
     ya existe una OT abierta o si debe crear una.

4. [MOBILE] Decide buscar la OT manualmente
   → Navega al módulo de OTs
   → Busca por equipo (si la búsqueda funciona) o scrollea
   → Encuentra (o no encuentra) una OT abierta para ese equipo

5. [SI EXISTE OT] Abre la OT, trabaja, registra tiempo y cierra
   — Flujo correcto, mobile razonablemente bien resuelto —

6. [SI NO EXISTE OT] Decide si crea una OT desde mobile o llama al supervisor
   → Desde mobile: navegación a crear OT → flujo desconocido para este análisis
   → WORKAROUND COMÚN: Llama al supervisor para que cree la OT en desktop

7. [CAMPO] Realiza la intervención
   → Necesita consultar el manual del compresor
   → No puede hacerlo desde Fronda mobile (no hay documentos)
   ★ PUNTO DE FRUSTRACIÓN: Busca el manual en otra app, en WhatsApp,
     en papel. Fronda no ayudó en este momento.

8. [MOBILE] Registra el trabajo completado en la OT
   → Sube fotos de la intervención ✓
   → Registra tiempo ✓
   → Registra repuestos usados (si el flujo existe en mobile) ?

9. Intervención completada
```

**Pain Points:**
- La Ficha de Equipo mobile no dice si hay OTs activas ni documenta el estado operacional
- La documentación técnica es inaccesible desde campo
- El técnico no puede reportar un nuevo problema directamente desde la ficha del equipo

**Momentos de espera:**
- Paso 6: esperar que el supervisor cree la OT en desktop

---

### Supervisor — "Revisar el estado de los equipos de mi área esta mañana"

**Contexto:** Inicio del turno. El supervisor revisa qué equipos necesitan atención hoy.

```
1. [DESKTOP] Abre Fronda, va al Home
   → Ve los Avisos importantes (si el administrador los publicó)
   → Ve el Feed de actividad reciente
   → No ve un resumen específico de sus equipos

2. [DESKTOP] Navega a Equipos en el menú lateral
   → Ve la lista con filtro "Todos" por defecto

3. [DESKTOP] Intenta filtrar por su planta o área
   → No existe ese filtro
   ★ PUNTO DE FRUSTRACIÓN: Con 80 equipos, no puede ver
     solo los de su área sin buscar manualmente.

4. [DESKTOP] Busca manualmente los equipos de su área
   → Escribe el nombre del área en el buscador
   → El buscador busca por código/nombre/serie, NO por área
   ★ PUNTO DE FRUSTRACIÓN: La búsqueda no funciona para este caso.
   → Workaround: Scrollea la lista con el filtro "Activos"
     y visualmente identifica los de su área.

5. [DESKTOP] Para cada equipo relevante:
   → Abre la ficha
   → Revisa el KPI strip (sin saber de qué período son los KPIs)
   → Revisa la sección de OTs recientes
   → Ve si hay preventivos vencidos
   → Vuelve a la lista

   ★ MÚLTIPLES ROUND TRIPS: El supervisor hace 5-10 viajes
     lista → ficha → lista para revisar su área.
     Sin forma de tener una vista consolidada de todos sus equipos.

6. [DESKTOP] Encuentra un equipo con un preventivo vencido
   → Decide crear una OT preventiva
   → Tiene que navegar a Órdenes de Trabajo → Nueva OT
   → Busca y selecciona el equipo manualmente (ya lo tenía abierto)
   ★ PUNTO DE FRUSTRACIÓN: Perdió el contexto del equipo.
     No hay "Crear OT para este equipo" desde la ficha.

7. Revisión del turno completada — en ~40 minutos
   (estimado basado en la fricción observada)
```

**Pain Points:**
- Sin filtro por área: el supervisor tiene que escanear toda la lista
- Sin vista consolidada de su área con señales de atención
- Crear una OT para un equipo específico requiere abandonar la ficha del equipo
- KPIs sin período: no sabe si el 87% de disponibilidad es de esta semana o del año pasado

**Momentos de frustración:**
- Descubrir que el buscador no filtra por área
- Ver el KPI "Disponibilidad: 87.3%" sin saber a qué período corresponde
- Tener que volver a buscar un equipo que ya tenía abierto para crear su OT

---

### Planeador / Jefe de Mantenimiento — "Planificar el mantenimiento de la próxima semana"

**Contexto:** Oficina, martes por la tarde. El planeador revisa qué equipos necesitan mantenimiento programado la próxima semana y cuáles tienen alertas de confiabilidad.

```
1. [DESKTOP] Abre Fronda, va a Mantenimiento Programado
   → Ve la lista de planes preventivos
   → Identifica los próximos a vencer

2. [DESKTOP] Para un plan próximo, necesita ver el estado del equipo
   → Hace click en el equipo del plan
   → Va a la ficha del equipo: revisa OTs activas, historial, KPIs
   → Vuelve al módulo de preventivos

3. [DESKTOP] Va a la lista de Equipos
   → Quiere ver qué equipos críticos no tienen planes activos
   → No existe este filtro
   ★ PUNTO DE FRUSTRACIÓN: Tiene que revisar cada equipo crítico
     individualmente para ver si tiene planes preventivos configurados.

4. [DESKTOP] Quiere comparar la disponibilidad de 5 compresores
   → Abre cada ficha individualmente, anota los datos manualmente
   → No hay vista comparativa de equipos
   ★ PUNTO DE FRUSTRACIÓN: Trabaja con una hoja de cálculo paralela.

5. [DESKTOP] Detecta un equipo crítico con disponibilidad baja
   → Quiere crear un plan preventivo para ese equipo
   → Navega a Mantenimiento Programado → Nuevo plan
   → Busca el equipo manualmente
   ★ Nuevamente: perdió el contexto del equipo que tenía abierto.

6. Planificación completada — con significativo trabajo manual paralelo
```

**Pain Points:**
- Sin vista de "equipos críticos sin planes activos" — gap de mantenimiento invisible
- Sin comparación directa entre equipos (requiere trabajo manual fuera del sistema)
- La planificación del mantenimiento no arranca desde la ficha del equipo

---

### Gerente — "Revisar el desempeño de la flota este mes"

**Contexto:** Reunión de gerencia mensual. El gerente necesita reportar el estado de la flota y los KPIs de mantenimiento.

```
1. [DESKTOP] Abre el Dashboard Ejecutivo / Indicadores
   → Ve KPIs globales del tenant: disponibilidad, OTs, costos
   → Correcto para su necesidad de resumen

2. [DESKTOP] Quiere identificar qué equipos tuvieron más fallas
   → No puede ver el ranking de equipos por fallas desde el Dashboard
   → Va a la lista de Equipos
   → No hay columna de "fallas" ni forma de ordenar por ese criterio

3. [DESKTOP] Abre uno por uno los equipos más críticos
   → Ve los KPIs individuales — sin período explícito
   → No puede saber si el período que ve es el mismo en todos los equipos
   ★ PUNTO DE FRUSTRACIÓN: Sin período explícito, no puede
     comparar equipos con confianza.

4. [DESKTOP] Necesita el costo de mantenimiento acumulado por equipo
   → El costo acumulado existe en "Estado del activo" de cada ficha
   → No hay forma de ver el costo de todos los equipos en una vista

5. [DESKTOP] Descarga los PDFs de los equipos más relevantes para la reunión
   → El PDF existe y funciona ✓
   → Pero el trabajo de recopilar los datos fue completamente manual

6. Prepara la presentación con datos de Fronda + hoja de cálculo propia
```

**Pain Points:**
- Los KPIs de la lista de equipos no existen — no hay forma de ver la disponibilidad de todos los equipos en una sola pantalla
- Sin ranking de equipos por fallas, downtime o costo
- Sin período explícito en los KPIs individuales, los datos no son comparables entre equipos

---

### Administrador — "Registrar 20 equipos nuevos de la nueva línea de producción"

**Contexto:** La empresa cliente acaba de instalar una nueva línea. El administrador necesita registrar los equipos en Fronda.

```
1. [ADMIN] Va al panel administrativo de Filament
   → Accede a Equipos → Nuevo equipo
   → El formulario de Filament tiene todos los campos necesarios

2. [ADMIN] Completa el formulario del primer equipo
   → Asigna planta, área, categoría, criticidad
   → Registra código, modelo, número de serie
   → Guarda

3. [ADMIN] Necesita crear 19 equipos más similares
   → No hay función de "duplicar equipo" o "crear similar"
   → Repite el proceso 19 veces desde cero

4. [ADMIN] Quiere verificar que los equipos se crearon correctamente
   → Va al ops panel → lista de equipos
   → Busca los equipos nuevos
   → Verifica datos, detecta un error en el código de uno
   → Tiene que volver al admin panel para editar
   ★ ROUND TRIP CONSTANTE entre ops panel y admin panel.

5. [ADMIN] Quiere asignar QR codes a los 20 equipos
   → Proceso desconocido para este análisis (fuera de scope del código leído)

6. Alta de flota completada — con fricción significativa
```

**Pain Points:**
- No hay duplicación de equipos para crear fleets similares
- Sin edición en el ops panel, los errores detectados durante verificación requieren cambio de contexto
- Bifurcación entre "crear en admin" y "verificar en ops" es ineficiente

---

## III. Mapa de principios — cumplimiento actual

### ✅ Principios que el módulo cumple

**PDR-005 — Las entidades nunca son islas**
Los Context Banners (equipo padre, última OT, planta/área como links navegables) implementan este principio correctamente en la vista desktop. La ficha 360° conecta el equipo con sus OTs, planes, componentes, repuestos, y documentos.

**Design Language — Skeleton loaders**
Los skeletons de la ficha de equipo replican con precisión la estructura del contenido real: foto, breadcrumbs, código, título, badges, KPI strip de 5 celdas, secciones de contenido. Correcto.

**Design Language — Sticky header**
El sticky header implementa la Zona 1 del patrón de pantalla de trabajo descrito en el Capítulo 7. La identidad permanece visible mientras el usuario navega las secciones.

**UX Principio 2 — El contexto no se pierde en la navegación**
El parámetro `from` en las URLs (e.g., `?from=ops.equipos.show&fromId=X`) permite regresar al equipo desde una OT. El breadcrumb muestra la cadena de ancestros completa. El contexto de origen se preserva.

**Engineering Handbook — Separación de responsabilidades**
`EquipmentService` solo hace lo que debe: `changeStatus()` y `changeCriticality()`. Los KPIs viven en `EquipmentKpiService`. Los DTOs son inmutables (`EquipmentKpiData` readonly). La arquitectura de dominio es correcta.

---

### ⚠️ Principios que el módulo incumple

**PDR-002 — La acción siempre aparece antes que la información**
La acción más probable en la ficha de un equipo (crear OT, reportar problema) no existe en la ficha. El usuario ve información pero no tiene acciones primarias disponibles desde el contexto donde ya se encuentra.

**PDR-008 — Toda pantalla importante responde primero: ¿qué debo hacer hoy?**
La lista de equipos en desktop muestra todos los equipos sin priorizar por relevancia operacional. No responde "¿cuál de mis equipos requiere atención hoy?" El usuario tiene que construir esa respuesta manualmente.

**UX Principio 1 — La primera acción es siempre visible**
No hay acción primaria visible en la ficha del equipo en desktop. El único botón prominente es "PDF" — una acción secundaria. La acción primaria (crear OT) está ausente.

**UX Principio — El sistema conoce el contexto del usuario**
La lista de equipos ignora el rol del usuario: muestra lo mismo a un técnico, un supervisor, un gerente y un administrador. No hay priorización por rol o por equipo asignado.

**Design Language — Dark Mode como identidad**
La Ficha de Equipo mobile muestra datos básicos que no corresponden a las necesidades del técnico en campo. La mobile PWA tiene un problema de propósito, no solo de estética. El dark mode es correcto; el contenido no lo es.

**PDR-001 — El Home como punto de entrada operacional**
El Home muestra avisos y actividad general, pero no muestra "los equipos de mi área con alertas activas" ni "los equipos que intervine recientemente" — información contextual que haría el Home más útil como punto de entrada para supervisores y técnicos.

---

### 🔴 Principios que el módulo contradice activamente

**Product Philosophy — El sistema trabaja para el usuario, no al revés**
El técnico que llega al equipo escaneando el QR obtiene una pantalla que no le dice nada operacionalmente útil. Le transfiere el trabajo de buscar la información relevante al usuario. El sistema no trabajó para él.

**Manifesto — El campo es nuestro contexto de diseño primario**
La mobile PWA del módulo de Equipos ignora completamente las necesidades del técnico en campo. No hay documentación, no hay OTs activas, no hay historial de fallas, no hay acciones. La mobile se diseñó para que existiera, no para que se usara.

**Vision — Fronda reemplaza al Excel**
El gerente y el planeador que necesitan comparar equipos o identificar outliers de confiabilidad recurren a hojas de cálculo propias porque Fronda no ofrece esa vista consolidada. El Excel no fue reemplazado para este caso de uso.

---

## IV. Pain Points priorizados

Ordenados por impacto en el usuario principal de cada caso.

| # | Pain Point | Usuarios afectados | Severidad |
|---|---|---|---|
| 1 | Mobile PWA de equipo no tiene información operacional ni acciones | Técnico (campo) | 🔴 Crítico |
| 2 | Sin acción "Crear OT" desde la ficha del equipo | Supervisor, Técnico | 🔴 Crítico |
| 3 | Sin filtro por área/planta/criticidad en la lista | Supervisor, Planeador | 🔴 Crítico |
| 4 | KPIs sin período temporal explícito | Todos (Desktop) | 🟠 Alto |
| 5 | Documentación técnica inaccesible desde campo | Técnico | 🟠 Alto |
| 6 | "Ver todas las órdenes" pierde contexto del equipo | Supervisor | 🟠 Alto |
| 7 | Sin vista consolidada del área para el supervisor | Supervisor | 🟠 Alto |
| 8 | Creación de equipos en Filament rompe el flujo | Administrador | 🟡 Medio |
| 9 | Historial sin filtros por tipo de evento | Planeador, Supervisor | 🟡 Medio |
| 10 | Cards de lista sin señales operacionales | Supervisor, Planeador | 🟡 Medio |
| 11 | Duplicación de KPIs (strip + sección Estado) | Todos (Desktop) | 🟡 Medio |
| 12 | Sin ranking/comparación de equipos por KPI | Gerente, Planeador | 🟡 Medio |
| 13 | Sin acciones bulk avanzadas (asignación masiva) | Administrador | 🟢 Bajo |
| 14 | Repuestos utilizados mal ubicada en la información | Supervisor | 🟢 Bajo |

---

## V. Preguntas abiertas para el equipo de producto

Estas preguntas deben responderse antes de diseñar el Sprint UX-3. Algunas requieren investigación adicional; otras requieren una decisión de producto.

**Sobre el técnico en campo:**
- ¿Con qué frecuencia los técnicos llegan a un equipo sin que ya exista una OT asignada? ¿Crean la OT ellos o lo hace siempre el supervisor?
- ¿Los técnicos necesitan acceso a la documentación técnica durante la intervención, o consultan manuales físicos? ¿Qué tipo de documentos: procedimientos, esquemas eléctricos, datasheet del fabricante?
- ¿El QR scan es realmente el punto de entrada del técnico, o en la práctica llegan por la lista de "Mis OTs"?

**Sobre el supervisor:**
- ¿El supervisor gestiona equipos de una sola área o de múltiples áreas?
- ¿Cuántos equipos gestiona en promedio? ¿10, 50, 200?
- ¿La revisión de inicio de turno es un flujo real, o el supervisor reacciona a problemas reportados?

**Sobre los KPIs:**
- ¿Cuál es el período estándar para calcular disponibilidad y MTBF en la industria? ¿Rodante de 3 meses? ¿12 meses?
- ¿El `EquipmentKpiService` ya almacena el período de cálculo? ¿Podemos mostrarlo?

**Sobre la mobile:**
- ¿Cuántos usuarios activos reales tiene la mobile PWA? ¿Se está usando en producción o es experimental?
- ¿Hay restricción técnica para mostrar OTs del equipo en mobile, o es simplemente que no se construyó?

**Sobre la administración:**
- ¿Los administradores hacen altas masivas de equipos (migraciones)? ¿Con qué frecuencia?
- ¿Existe algún plan de importación masiva (CSV, Excel, ERP)?

---

## VI. Visión ideal del módulo — sin diseñar

Esto no es un diseño. Es la articulación del módulo como debería funcionar cuando las necesidades de todos sus usuarios estén cubiertas.

---

**Para el técnico en campo:**
Llegar al equipo — por QR scan, por búsqueda, o desde una OT asignada — y ver inmediatamente: cuáles son las OTs activas de ese equipo, cuál fue la última intervención, y cuáles son los documentos técnicos relevantes. Poder reportar un nuevo problema o crear una OT sin cambiar de pantalla. Acceder a manuales y esquemas sin salir de Fronda.

**Para el supervisor:**
Ver su área completa — equipos, alertas, preventivos vencidos, OTs en curso — en una vista que responda "¿qué requiere mi atención hoy?" Desde cualquier equipo, poder crear una OT, reportar un problema o asignar un técnico en un solo gesto. Los KPIs siempre con su período explícito y con referencia al período anterior.

**Para el planeador:**
Ver la flota ordenada por señales de riesgo: equipos sin planes preventivos activos, equipos con disponibilidad decreciente, equipos con alta frecuencia de fallas. Poder planificar un preventivo directamente desde la ficha del equipo. Comparar la confiabilidad de equipos similares en una misma vista.

**Para el gerente:**
Un resumen de la flota que responda: ¿cuáles son los equipos más problemáticos este mes? ¿Cuánto costó mantenerlos? ¿Qué porcentaje de la flota crítica está disponible? Algo que pueda leer en tres minutos antes de una reunión, sin exportar a Excel.

**Para el administrador:**
Crear equipos desde el ops panel cuando sea simple; ir al admin cuando sea complejo. Editar campos comunes (estado, criticidad, ubicación, notas) directamente desde la ficha 360°. Duplicar un equipo para crear uno similar con cambios mínimos.

---

**El módulo de Equipos ideal convierte la ficha del equipo en el centro de toda la gestión de ese activo.** Hoy es una vista de información. Debería ser el lugar donde el técnico actúa, donde el supervisor toma decisiones, donde el planeador programa, donde el gerente entiende.

La ficha no cambia de estructura — la Ficha 360° como concepto es correcta. Lo que cambia es que deja de ser de solo lectura para convertirse en el punto de acción.

---

## VII. Alcance propuesto para Sprint UX-3

Esta es una propuesta de alcance, no un compromiso. Requiere aprobación de producto antes de avanzar.

**UX-3.1 — Mobile que sirve al técnico**
Rediseño completo de la Ficha de Equipo mobile: OTs activas del equipo, acceso a documentación técnica, acción de reporte de problema, lectura de medidor. Sin KPIs ni análisis — solo lo que el técnico necesita en campo.

**UX-3.2 — Acciones desde la ficha desktop**
Agregar al sticky header de la ficha desktop: "Nueva OT", "Reportar problema". Corregir "Ver todas las OTs" para que filtre por equipo. Eliminar la duplicación de KPIs.

**UX-3.3 — Lista con inteligencia**
Agregar filtros por área, planta, criticidad, categoría. Mostrar señales operacionales en las cards: OTs activas, preventivos vencidos, disponibilidad. Ordenamiento por KPI.

**UX-3.4 — KPIs con contexto temporal**
Agregar selector de período a los KPIs del equipo. Mostrar comparativa con período anterior. Indicar la fecha del último cálculo.

Estos cuatro sub-sprints pueden ser independientes o secuenciales según la capacidad del equipo. UX-3.1 tiene la mayor urgencia por su impacto en el técnico de campo.

---

## VIII. Lo que este Discovery no cubre

Para mantener el foco, los siguientes temas fueron excluidos intencionalmente de este documento:

- El módulo administrativo de Filament para creación de equipos (requiere su propio Discovery)
- La integración de IoT y lecturas automáticas de sensores (fuera del alcance actual del producto)
- El módulo de Inventario y su relación con los repuestos del equipo (cubierto en un Discovery separado si aplica)
- La generación y gestión de QR codes físicos (proceso de campo, no de software)

---

*Documento preparado para revisión del equipo de producto. No proceder con diseño hasta aprobación.*

*Próximo paso: revisión en sesión de producto → aprobación de alcance → inicio de Sprint UX-3.1*
