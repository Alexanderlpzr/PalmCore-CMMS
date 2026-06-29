---

`PARTE II — EXPERIENCE`  `CAPÍTULO 6`

**Tiempo de lectura:** 30 minutos · **Objetivo:** Documentar los 15 patrones oficiales de interacción de Fronda — las soluciones reutilizables que resuelven los problemas de diseño más frecuentes del producto. Todo nuevo diseño se construye combinando estos patrones.

> *"Un componente te dice qué construir. Un patrón te dice cómo resolver un problema. La diferencia entre los dos es la diferencia entre un catálogo y un criterio."*
> — Fronda

---

# The Fronda Pattern Library

---

## Introducción

### Por qué Fronda trabaja con patrones

Un componente resuelve un problema visual: cómo se ve un botón, cuál es la altura de un campo de texto, qué radio tienen las esquinas de una tarjeta.

Un patrón resuelve un problema de interacción: cómo el usuario entiende el estado de una entidad sin necesidad de instrucciones, cómo se confirma una acción sin fricción innecesaria, cómo se navega por información jerárquica sin perder orientación.

La diferencia es el nivel de abstracción. Un componente puede cambiar — los botones de Fronda pueden volverse más pequeños, más grandes, de otro tono, con otra tipografía — sin que el sistema pierda coherencia, siempre que el patrón que los usa permanezca intacto. Un patrón, en cambio, no cambia porque es una solución a un problema humano que no cambia.

El técnico de campo que necesita cerrar una orden de trabajo en treinta segundos tendrá ese mismo problema en 2026 y lo tendrá en 2031. La solución — acción primaria prominente en el encabezado fijo, campos mínimos, confirmación inmediata — tampoco cambia.

---

### Por qué un patrón es más importante que un componente

Cuando un diseñador nuevo llega a Fronda y ve el catálogo de componentes, puede construir pantallas que visualmente pertenecen al sistema. Pero sin los patrones, no sabe cuándo usar un Context Banner versus cuándo abrir un modal, no sabe si una pantalla de lista debe tener o no un header prominente, no sabe por qué la pantalla de Inicio tiene una estructura específica y en qué orden aparecen sus secciones.

Los patrones son el criterio. Los componentes son el material. Sin criterio, el material puede ensamblarse de infinitas maneras — la mayoría incorrectas.

---

### Por qué esta biblioteca garantiza consistencia durante años

Los patrones de este documento están documentados con su problema de origen, su contexto de uso, sus restricciones, y su estado de evolución. Esa información no es decorativa — es la razón por la que el patrón existe y el límite hasta donde puede cambiarse sin que deje de ser ese patrón.

Cuando Fronda incorpore IoT, cuando agregue pantallas de mantenimiento predictivo, cuando construya una app de TV para sala de control, los patrones de esta biblioteca seguirán siendo válidos porque resuelven problemas que no dependen de ninguna tecnología específica.

Los implementadores cambian. Los patrones permanecen.

---

## Los 15 Patrones Oficiales

---

### 01 — Workspace Hero

**Estado:** `FROZEN`

**Problema que resuelve.** El usuario llega a la pantalla principal del sistema sin saber de inmediato quién es, en qué empresa está, qué hora es ni qué tiene pendiente. El software lo recibe como si fuera la primera vez, sin reconocimiento.

**Contexto de uso.** La pantalla de Inicio (`HomeView`) del panel operativo. Es el primer elemento que el usuario ve al iniciar sesión o al navegar al home desde cualquier parte del sistema.

**Cuándo utilizarlo.** Únicamente en la pantalla de Inicio del workspace. No en dashboards analíticos, no en listas, no en vistas de entidad.

**Cuándo NO utilizarlo.** En cualquier pantalla que no sea el punto de entrada principal del rol del usuario. Un dashboard ejecutivo tiene su propio encabezado, pero no es un Workspace Hero.

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│  [Saludo + Nombre]    [Nombre del tenant]                │
│  Bienvenido, Juan     OleoIngeniería S.A.               │
│  [Fecha larga]        [Hora en tiempo real]             │
│  lunes, 29 de junio   08:23                             │
│                       [Placeholder clima]               │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** El ojo entra por el nombre del usuario (parte izquierda, tipografía grande y color emerald), desciende al nombre del tenant (orientación organizacional), y migra a la derecha donde el reloj en tiempo real actúa como anclaje temporal. El usuario sabe en segundos: quién soy, dónde estoy, qué momento es.

**Beneficio para el usuario.** Orientación inmediata. El usuario no tiene que inferir nada — la pantalla lo recibe por nombre, en su organización, con el tiempo actual. Reduce la carga cognitiva del primer segundo de uso.

**Ejemplo real.** `HomeView.vue` líneas 2–25. El header muestra `auth.userName` en emerald, `auth.tenantName` en gris suave, la fecha en formato largo (`lunes, 29 de junio de 2026`) y el reloj reactivo actualizado cada segundo. El placeholder de clima (`Clima próximamente`) ya reserva el espacio para una futura integración sin romper el layout.

**Errores comunes.**
- Mostrar el saludo sin el nombre del tenant. En una plataforma multi-tenant, el tenant es parte de la identidad.
- Usar el nombre completo del usuario cuando el nombre es largo. Solo el primer nombre.
- Hacer el header estático cuando hay un reloj en tiempo real. El reloj requiere actualización reactiva cada segundo.

**Evolución futura.** El placeholder de clima se convertirá en una integración real con datos meteorológicos de la ubicación de la planta. El header también podría mostrar el estado operacional general (línea de producción activa/detenida) cuando Fronda integre IoT.

---

### 02 — Attention Cards

**Estado:** `STABLE`

**Problema que resuelve.** El usuario llega al sistema sin saber qué necesita su atención ahora. La información disponible es mucha, pero no está jerarquizada por urgencia. El usuario tiene que explorar para encontrar lo que le urge.

**Contexto de uso.** La sección "Avisos importantes" en `HomeView`, inmediatamente después del Workspace Hero. También puede aparecer en dashboards de supervisores como el elemento de mayor jerarquía visual.

**Cuándo utilizarlo.** Cuando existe información operacional que requiere atención activa del usuario — no información que informa, sino información que demanda acción. El criterio es: si este número sube, hay un problema que el usuario debe resolver hoy.

**Cuándo NO utilizarlo.** Para métricas retrospectivas (porcentaje de cumplimiento del mes, tendencia de MTBF), para información informativa sin consecuencia inmediata, para notificaciones de baja urgencia.

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│  Avisos importantes                                      │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌──────┐  │
│  │ [3]   [!] │ │ [7]   [!] │ │ [2]   [!] │ │ [1]  │  │
│  │           │ │           │ │           │ │ [!]  │  │
│  │ OT        │ │ Solicitud  │ │ Preventiv │ │Alert │  │
│  │ vencidas  │ │ pendientes │ │ próximos  │ │críti │  │
│  └────────────┘ └────────────┘ └────────────┘ └──────┘  │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** El ojo lee el número (grande, en el tono del aviso) antes que la etiqueta. El número es el mensaje principal — la etiqueta lo contextualiza. Si todos los números son cero, la sección muestra el Empty State de calma activa.

**Beneficio para el usuario.** En un solo vistazo, el supervisor sabe si la operación está bajo control. Cuatro tarjetas, cuatro números. Si todos son cero, sin acción requerida. Si alguno tiene número, ese número es un enlace que lleva directamente a la lista filtrada de los elementos que componen ese aviso.

**Ejemplo real.** `HomeView.vue` líneas 143–173. Cuatro tarjetas de color codificado:
- Rojo: órdenes vencidas → ruta `ops.ordenes` pre-filtrada
- Ámbar: solicitudes pendientes → ruta `ops.solicitudes`
- Azul: preventivos próximos (7 días)
- Naranja: alertas críticas → ruta `ops.alertas`

El estado vacío (líneas 168–173) muestra un ícono de check en emerald y el texto "Todo al día — sin avisos pendientes".

**Errores comunes.**
- Mostrar más de 4 tarjetas. Cuatro es el máximo. Si hay cinco tipos de aviso urgente, dos de ellos deben consolidarse o uno de ellos no es un aviso urgente.
- Usar el mismo tono de color para dos avisos distintos. Cada aviso tiene un color único y estable.
- Hacer las tarjetas no interactivas. Cada tarjeta es un enlace al recurso que representa.

**Evolución futura.** Con IoT, las Attention Cards podrán incluir avisos de sensores (temperatura fuera de rango, vibración anómala). El patrón no cambia — se agregan nuevos tipos de aviso al mismo contenedor.

---

### 03 — Quick Actions

**Estado:** `STABLE`

**Problema que resuelve.** Las acciones más frecuentes del rol del usuario requieren demasiada navegación para iniciarse: ir al menú lateral, seleccionar el módulo, llegar a la lista, hacer clic en "Nuevo". Cuatro pasos para una acción que ocurre decenas de veces por turno.

**Contexto de uso.** La sección "Accesos rápidos" en `HomeView`, después de las Attention Cards. También puede aparecer en el footer de una Entity Detail Page como acciones contextuales.

**Cuándo utilizarlo.** Para las acciones que el rol del usuario realiza con mayor frecuencia — máximo 6. Si el análisis de uso muestra que hay más de 6 acciones frecuentes, el diseño de los módulos subyacentes necesita revisión.

**Cuándo NO utilizarlo.** Para acciones de configuración, acciones destructivas, o acciones que requieren contexto previo (crear una OT desde Quick Actions no pre-selecciona ningún equipo — eso es aceptable para la creación libre, pero no para la creación contextual).

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│  Accesos rápidos                                         │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌───────┐  │
│  │  [+]  │ │  [💬] │ │  [⚙] │ │  [📅] │ │  [🔔] │  │
│  │       │ │       │ │       │ │       │ │       │  │
│  │Crear  │ │Solici-│ │Equip- │ │Preven-│ │Alert- │  │
│  │  OT   │ │  tud  │ │  os   │ │ tivos │ │  as   │  │
│  └────────┘ └────────┘ └────────┘ └────────┘ └───────┘  │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** La grilla de 3 (móvil) o 6 (desktop) elementos en columnas iguales permite una lectura de izquierda a derecha sin jerarquía entre acciones. El ícono lleva el peso visual; la etiqueta (máximo 2 palabras) confirma el destino.

**Beneficio para el usuario.** La acción más frecuente está siempre a un toque de distancia desde la pantalla de inicio, sin importar qué tan profundo se haya navegado previamente.

**Ejemplo real.** `HomeView.vue` líneas 177–189. Seis acciones con íconos de heroicons, fondo de color semántico (`bg-indigo-50` para crear OT, `bg-emerald-50` para solicitud, etc.) y etiqueta corta. Cada una es un `RouterLink` a la ruta correspondiente.

**Errores comunes.**
- Etiquetar una Quick Action con el nombre completo del módulo ("Órdenes de trabajo" en lugar de "Crear OT"). La Quick Action es una acción, no una sección.
- Incluir más de 6 elementos. La grilla de 6 es el límite de reconocimiento instantáneo.
- Usar el mismo color de fondo para dos acciones distintas. El color es parte de la identidad de la acción.

**Evolución futura.** Las Quick Actions podrían volverse personalizables por rol o por usuario. El patrón permanece — solo el contenido configurable varía.

---

### 04 — Institutional Carousel

**Estado:** `STABLE`

**Problema que resuelve.** La empresa cliente necesita comunicar a sus empleados información institucional (campañas, noticias, avisos de seguridad, fotos del evento de la semana) dentro del sistema operativo que ya usan — sin que esa comunicación interfiera con el flujo de trabajo.

**Contexto de uso.** La sección del carrusel en `HomeView`, después de las Attention Cards y Quick Actions. Es contenido gestionado por el administrador del tenant (o la plataforma) a través del panel de administración.

**Cuándo utilizarlo.** Cuando el tenant ha configurado al menos un slide. Si no hay slides configurados, la sección no aparece — el espacio no queda vacío esperando contenido.

**Cuándo NO utilizarlo.** Para mostrar información operacional (el estado de la planta no va en un carrusel). Para contenido generado automáticamente por el sistema. El carrusel es editorial, no algorítmico.

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│   [    Imagen de fondo del slide    ]                    │
│                                                          │
│   [SUBTÍTULO EN MAYÚSCULAS]                             │
│   Título principal del slide                            │
│   Descripción breve hasta 2 líneas                      │
│   [Botón CTA opcional]        [● ○ ○] ← / →            │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** La imagen ocupa todo el fondo. Un gradiente de abajo hacia arriba (`from-black/60`) asegura legibilidad del texto sin importar qué imagen se use. El texto y el CTA están siempre en la parte inferior izquierda. Los controles de navegación (dots + flechas) están en la parte inferior derecha para no obstruir el texto.

**Beneficio para el usuario.** La empresa tiene un canal de comunicación interno dentro de la herramienta que ya usan a diario. La información institucional llega sin depender de correo, WhatsApp o reuniones.

**Ejemplo real.** `HomeView.vue` líneas 30–73. El carrusel avanza automáticamente cada 5 segundos si hay más de un slide. Las flechas y los dots son interactivos. El modelo `CarouselSlide` alimenta el endpoint `home/carousel`.

**Errores comunes.**
- Mostrar el carrusel vacío o con un placeholder cuando no hay slides. El carrusel debe invisibilizarse cuando no tiene contenido.
- Autoplay sin pausa al hacer hover. El usuario que está leyendo un slide no debe verlo cambiar mientras lo lee.
- CTA que abre contenido dentro del sistema. Los CTAs del carrusel abren URLs externas — no son navegación interna.

**Evolución futura.** Posible integración con un módulo de comunicaciones más amplio (intranet básica). El patrón del carrusel como contenedor visual permanece — el origen del contenido puede evolucionar.

---

### 05 — Activity Timeline

**Estado:** `BETA`

**Problema que resuelve.** El usuario necesita entender qué ocurrió en la operación en las últimas horas sin tener que navegar por cada módulo individualmente. El historial de actividad está disperso entre OTs, solicitudes, equipos y planes — no existe una vista consolidada.

**Contexto de uso.** La sección "Feed empresarial" en `HomeView`. También aparece como sección "Historial" en Entity Detail Pages de equipos y órdenes de trabajo.

**Cuándo utilizarlo.** Para presentar eventos cronológicos de naturaleza heterogénea (diferentes tipos de entidad, diferentes tipos de acción) en un orden temporal unificado. Para historial de una entidad específica.

**Cuándo NO utilizarlo.** Para listas de entidades del mismo tipo (las órdenes de trabajo no son un timeline — son una lista). Para notificaciones activas que requieren acción (esas son Attention Cards).

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│  Feed empresarial                                        │
│  [Todo] [OT] [Equipos] [Solicitudes] [Mantenimiento]     │
│                                                          │
│  ┌──────────────────────────────────────────────────┐    │
│  │ [🔧]  OT #1234 fue marcada como completada       │    │
│  │       Compresor Línea 3 · hace 2h    Ver OT →   │    │
│  └──────────────────────────────────────────────────┘    │
│  ┌──────────────────────────────────────────────────┐    │
│  │ [⚙]  Equipo EQ-089 fue creado                   │    │
│  │       Bomba centrífuga · hace 4h                │    │
│  └──────────────────────────────────────────────────┘    │
│                        [cargando más…]                   │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** Los filtros de tipo (chips en la parte superior) permiten al usuario reducir el ruido sin salir del feed. Cada ítem tiene: ícono con color semántico, título del evento, subtítulo con el nombre de la entidad afectada, timestamp relativo. Si el ítem enlaza a una entidad navegable, toda la tarjeta es clicable con un chevron derecho. Si no, muestra un enlace de texto.

**Beneficio para el usuario.** Una vista única del estado reciente de la operación, filtrable por tipo de evento. El supervisor puede revisar "qué pasó en las últimas 4 horas" sin abrir ningún módulo específico.

**Ejemplo real.** `HomeView.vue` líneas 240–317. El endpoint `home/feed` devuelve eventos paginados con `filter` y `page`. El `IntersectionObserver` detecta cuando el usuario llega al final de la lista y carga más eventos automáticamente (infinite scroll). Los tipos de evento tienen colores específicos en `feedIconColor`: OT creada (índigo), OT completada (emerald), OT cancelada (gris), comentario (azul), evidencia (púrpura), equipo creado (slate), solicitud (ámbar), mantenimiento completado (teal).

**Errores comunes.**
- Mostrar todos los tipos de evento sin filtros. En una operación activa, el feed puede tener cientos de eventos diarios — los filtros son obligatorios.
- Timestamps absolutos en lugar de relativos. "hace 2h" es más útil que "29/06/2026 10:34" en un feed operacional.
- Incluir eventos del sistema (logins, cambios de configuración) en el feed operacional. El feed es de trabajo, no de auditoría.

**Evolución futura.** Con IoT, el feed incluirá eventos generados por sensores (temperatura fuera de rango, contador de ciclos alcanzado). Con mantenimiento predictivo, incluirá alertas de modelo ("Riesgo de falla detectado"). El patrón escala — los tipos de evento son extensibles.

---

### 06 — Entity Detail Page

**Estado:** `STABLE`

**Problema que resuelve.** El usuario llega a la ficha de una entidad compleja (equipo, orden de trabajo, solicitud) y necesita procesar información de múltiples categorías — datos de identidad, estado actual, métricas, historial, relaciones — sin perder el contexto de quién es esa entidad y qué puede hacer con ella.

**Contexto de uso.** Toda vista de perfil completo de una entidad: `EquipmentDetailView`, `WorkOrderDetailView`, `MaintenanceRequestDetailView`.

**Cuándo utilizarlo.** Cuando una entidad tiene suficiente información de múltiples categorías para justificar su propia pantalla. El criterio mínimo: al menos 3 categorías de información distintas.

**Cuándo NO utilizarlo.** Para entidades simples con un solo bloque de información (un proveedor con nombre, dirección y teléfono no necesita tabs). Para formularios de creación (los formularios tienen su propio patrón).

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐ sticky
│  Equipos › Planta Norte › Bomba centrífuga               │
│  ┌────┐  EQ-089                                          │
│  │foto│  Bomba centrífuga BCP-200                        │
│  └────┘  [Activo ●] [Alta criticidad] [Mecánico]         │
│          Planta Norte · Área de procesos                 │
│                                    [★] [PDF]            │
│                                                          │
│  [92.4%] [1240h] [4.2h] [3 fallas] [12.5h downtime]     │
│  Disponi. MTBF   MTTR   Fallas     Downtime             │
│                                                          │
│  [Info] [OTs] [Historial] [Componentes] [Documentos]    │
└──────────────────────────────────────────────────────────┘
│                                                          │
│  [Contenido de la tab/sección activa]                   │
│                                                          │
│  [Context Banner si aplica]                             │
│                                                          │
```

**Flujo visual.** El sticky header permite que el usuario siempre sepa de qué entidad está hablando, cuál es su estado actual, y cuál es la acción disponible — sin importar cuánto haya scrolleado. Los KPIs en la tira inmediatamente bajo la identidad dan el contexto operacional sin requerir click. Las tabs organizan el contenido adicional por categoría.

**Beneficio para el usuario.** Todo lo que el usuario necesita saber sobre una entidad está en una sola pantalla. No tiene que navegar a tres módulos distintos para entender si un equipo está disponible, cuándo fue su última OT, y qué técnico la atendió.

**Ejemplo real.** `EquipmentDetailView.vue`: breadcrumbs de jerarquía (Equipos → ancestores → nombre actual), foto clicable con lightbox, código en monospace, nombre en `text-2xl font-bold`, badges de status/criticidad/categoría, tira de 5 KPIs en color semántico, tab nav que en desktop es scroll-anchor y en móvil es selector exclusivo.

**Errores comunes.**
- Header no sticky. Si el header se scrollea fuera de vista, el usuario pierde el contexto de qué entidad está viendo y dónde están los botones de acción.
- KPIs sin color semántico. Una tira de KPIs donde todos los fondos son blancos pierde toda la capacidad de comunicación inmediata.
- Más de 7 tabs. Si hay más de 7 categorías de información, algunas deben consolidarse.

**Evolución futura.** Con IoT, la tira de KPIs incluirá datos en tiempo real de sensores. La tab de "Condición" mostrará gráficas de tendencia de los últimos 30 días. El patrón de Hero + KPI Strip + Tabs soporta esa expansión sin cambios estructurales.

---

### 07 — Context Banner

**Estado:** `STABLE`

**Problema que resuelve.** El usuario necesita información de una entidad relacionada con la que está viendo — el equipo de una OT, el plan de mantenimiento del que nació una OT, el equipo padre de un subcomponente — pero navegar a esa entidad implica perder el contexto actual.

**Contexto de uso.** Dentro de la sección "Resumen" o "Información" de una Entity Detail Page, inmediatamente antes o después del contenido principal de esa sección.

**Cuándo utilizarlo.** Cuando existe una entidad relacionada que el usuario debería poder ver y navegar sin salir de la pantalla actual. Una sola entidad, la más relevante para el contexto actual.

**Cuándo NO utilizarlo.** Para mostrar múltiples entidades relacionadas (eso requiere una lista dentro de una tab). Para información que no necesita navegación (eso es texto informativo plano). Para alertas o advertencias (eso requiere un estilo diferente — el Context Banner no es para urgencia).

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│  [fondo indigo-50, borde indigo-100, rounded-2xl]        │
│                                                          │
│  EQUIPO ASOCIADO                    [Ver perfil →]       │
│  Bomba centrífuga BCP-200                               │
│  EQ-089                                                 │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** La etiqueta en mayúsculas y color indigo-400 identifica el tipo de relación. El nombre de la entidad relacionada en negra prominente es el contenido principal. El enlace "Ver →" está siempre a la derecha y siempre es la única acción del banner.

**Beneficio para el usuario.** El usuario puede ver información clave de una entidad relacionada y navegarla si necesita más detalle — sin perder su posición actual. Al volver, está en el mismo lugar.

**Ejemplo real.** `EquipmentDetailView.vue` líneas 150–161 (banner de equipo padre con `bg-indigo-50`) y líneas 164–178 (banner de última OT). `WorkOrderDetailView.vue` líneas 157–170 (banner de equipo asociado con `bg-indigo-50 border-indigo-100`).

**Errores comunes.**
- Dos Context Banners del mismo tipo en la misma vista. Un banner muestra una sola entidad. Si hay dos candidatas, se elige la más relevante.
- Usar el Context Banner para mostrar un aviso o un error. El color índigo es para contexto relacional, no para urgencia.
- Ocultar el banner cuando la entidad relacionada no existe. Si la relación no existe (una OT sin equipo asociado), el banner simplemente no aparece — no muestra un estado vacío.

**Evolución futura.** Posible variante para mostrar el estado del subcomponente más crítico de un equipo. El patrón es estable — solo el tipo de relación que muestra puede variar.

---

### 08 — Master-Detail

**Estado:** `FROZEN`

**Problema que resuelve.** El usuario necesita explorar una lista de entidades y examinar el detalle de elementos específicos sin perder el contexto de la lista — los filtros aplicados, la posición de scroll, los elementos seleccionados.

**Contexto de uso.** Todas las vistas de lista principal: `WorkOrderListView`, `EquipmentListView`, `MaintenanceRequestListView`, `RepuestosView`, `AlmacenesView`.

**Cuándo utilizarlo.** Para cualquier colección de entidades donde el usuario necesita comparar elementos de la lista antes de seleccionar uno para examinar en detalle.

**Cuándo NO utilizarlo.** Para entidades que solo tienen un estado, no un perfil completo. Para selecciones dentro de formularios (eso usa un dropdown o un modal de selección).

**Anatomía.**

```
Vista Master (Lista):
┌──────────────────────────────────────────────────────────┐
│  [Título] [Contador]                    [+ Nueva OT]     │
│  [🔍 Buscar...]                                          │
│  [Todos] [Abierta] [En ejecución] [Verificada] [Cerrada] │
│                                                          │
│  ┌──────────────────────────────────────────────────┐    │
│  │ [✓] [⚙] OT-2025-0043 · Bomba hidráulica         │    │
│  │        [En ejecución] [Alta] · hace 2h           │    │
│  └──────────────────────────────────────────────────┘    │
│  ┌──────────────────────────────────────────────────┐    │
│  │ [✓] [📋] OT-2025-0042 · Compresor línea 3       │    │
│  │        [Abierta] [Media] · hace 4h               │    │
│  └──────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────┘

Vista Detail (navega preservando from + posición):
→ Entity Detail Page del elemento seleccionado
← Regreso al Master en la misma posición de scroll
```

**Flujo visual.** La lista muestra los elementos comparables en cards uniformes. El badge de estado es el elemento de mayor información — permite al usuario leer el estado de toda la lista antes de hacer clic en ningún elemento. La acción primaria (crear nuevo) está siempre en el header de la lista, no dentro de ningún elemento.

**Beneficio para el usuario.** Puede explorar muchas entidades en la vista de lista y profundizar en las que le interesan, siempre pudiendo volver al punto exacto donde estaba.

**Ejemplo real.** `WorkOrderListView.vue`: búsqueda inline, chips de filtro por estado (Todos/Abierta/En ejecución/Verificada/Cerrada), selección múltiple para acciones masivas, `SavedViews` para guardar configuraciones de filtro. El parámetro `from` en la URL del detalle preserva la vista de origen para el regreso.

**Errores comunes.**
- Abrir el detalle en un modal sobre la lista. El Master-Detail de Fronda usa navegación de ruta completa, no modales superpuestos.
- Perder los filtros al regresar del detalle. Los filtros y la posición de scroll deben persistirse durante la sesión.
- Listas sin filtros de estado. Sin filtros, una lista de 200 OTs activas e históricas es inmanejable.

**Evolución futura.** Posible panel lateral (split view) en resoluciones anchas donde el detalle aparece a la derecha de la lista sin navegación completa. El patrón conceptual permanece — solo la implementación visual evolucionaría.

---

### 09 — Tabbed Workspace

**Estado:** `STABLE`

**Problema que resuelve.** Una Entity Detail Page tiene múltiples categorías de información que no pueden mostrarse todas simultáneamente sin crear una página infinita. El usuario necesita acceder a cualquier categoría de manera directa, sin scrollear por todo el contenido.

**Contexto de uso.** Dentro de la Entity Detail Page, como sistema de navegación entre secciones. En desktop: anclajes de scroll con indicador de posición. En móvil: selector de sección exclusivo.

**Cuándo utilizarlo.** Cuando hay entre 3 y 7 categorías de información distintas para una misma entidad. Menos de 3: el scroll simple es preferible. Más de 7: las categorías deben consolidarse.

**Cuándo NO utilizarlo.** Para separar pasos de un formulario (wizard). Para filtrar contenido de una lista (filter chips). Para cambiar entre entidades distintas (navegación).

**Anatomía.**

```
Desktop (scroll anchors):
┌─────────────────────────────────────────────────────────┐ sticky
│  [Resumen] [Componentes] [OTs] [Historial] [Documentos] │
│      ↑ activo (borde emerald inferior)                  │
└─────────────────────────────────────────────────────────┘
   ↓ scroll
[Contenido de cada sección visible en el mismo scroll]

Móvil (exclusivo):
┌──────────────────────────────────────────────┐ sticky
│  [Resumen] [OTs] [Historial] [Docs]          │
│      ↑ activo                                │
└──────────────────────────────────────────────┘
[Solo el contenido de la sección activa es visible]
```

**Flujo visual.** En desktop, el tab activo tiene un borde inferior en emerald y texto emerald-700 en negrita. Los inactivos son gris-500, con hover a gris-800. En móvil, el comportamiento es idéntico pero el contenido de secciones no activas está oculto con `v-show`. El scroll en desktop avanza automáticamente el tab activo según la sección en viewport.

**Beneficio para el usuario.** Acceso directo a cualquier categoría de información sin scrollear por todo el contenido. En móvil, reduce la carga cognitiva al mostrar solo la sección activa.

**Ejemplo real.** `WorkOrderDetailView.vue` líneas 126–145: tabs de desktop con `onTabClick` → `scrollToSection`, tabs de móvil con `onMobileTab` → `v-show`. Los tabs muestran contadores de elementos cuando aplica (`sec.count`). `EquipmentDetailView.vue` tiene la misma implementación con secciones Información, Componentes, OTs, Historial, KPIs, Documentos.

**Errores comunes.**
- El mismo Tabbed Workspace en el Hero y en el contenido. Solo debe haber un nivel de tabs por página.
- Tabs que cargan su contenido siempre al inicio. El contenido de cada tab debe cargarse solo cuando se activa (lazy loading por tab).
- Tabs de un solo elemento. Si hay solo una sección de contenido, no hay tabs.

**Evolución futura.** La configuración de tabs podría volverse configurable por rol (un técnico ve Resumen y OTs; un gerente ve además KPIs y Documentos). El patrón soporta esa variación.

---

### 10 — Dashboard Analytics

**Estado:** `BETA`

**Problema que resuelve.** El gerente o el ingeniero de mantenimiento necesitan entender tendencias, comparar rendimiento entre equipos, y detectar patrones de falla — información que no es operacional (no requiere acción inmediata) sino analítica (informa decisiones de mediano plazo).

**Contexto de uso.** Los módulos `KpisView`, `DashboardView`, `ExecutiveDashboardView`, y la vista `PlatformDashboardView` para super admins.

**Cuándo utilizarlo.** Cuando el usuario viene a responder una pregunta de análisis ("¿cuál equipo tiene el peor MTTR del mes?", "¿cómo evolucionó la disponibilidad?") — no a tomar una acción inmediata.

**Cuándo NO utilizarlo.** En pantallas operacionales que requieren acción. No combinar con Attention Cards ni Quick Actions en la misma vista — los modos analítico y operacional no se mezclan.

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│  [Encabezado: Período] [Filtro de planta] [Filtro área]  │
│                                                          │
│  ┌────────────────────┐  ┌─────────────────────────────┐ │
│  │  Gráfica de línea  │  │  Ranking / tabla comparativa│ │
│  │  (tendencia MTBF)  │  │  (equipos por disponib.)    │ │
│  └────────────────────┘  └─────────────────────────────┘ │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │  Pareto de fallas (barras horizontales)            │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** Los filtros de período y alcance (planta, área) están siempre en la parte superior. Las visualizaciones más importantes (las que responden la pregunta principal del rol) ocupan el primer viewport. Las visualizaciones de soporte aparecen al scrollear. Toda visualización enlaza al conjunto de datos que la genera.

**Beneficio para el usuario.** El gerente puede responder preguntas de negocio sin exportar datos a Excel. Las tendencias son visibles. Los outliers son identificables. Las decisiones de inversión pueden basarse en evidencia.

**Ejemplo real.** `KpisView.vue` con widgets de `MttrTrendWidget`, `MtbfTrendWidget`, `DowntimeTrendWidget`, `FailuresByMonthWidget`, `ParetoFailuresWidget`, `ReliabilityRankingWidget`, `CostByEquipmentWidget`. Los widgets están en `app/Filament/Widgets/Analytics/`.

**Errores comunes.**
- Mezclar un Dashboard Analytics con Attention Cards en la misma pantalla. Son modos distintos.
- Gráficas sin enlace a los datos subyacentes. Cada número en un dashboard debe poder "abrirse" para ver qué lo compone.
- Gráficas sin período declarado. Un MTBF sin período de referencia es un número sin significado.

**Evolución futura.** Con mantenimiento predictivo, el Dashboard Analytics incluirá proyecciones ("probabilidad de falla en los próximos 30 días"). El patrón de visualización + filtros + drill-down permanece.

---

### 11 — Empty State

**Estado:** `STABLE`

**Problema que resuelve.** Una sección que puede tener cero elementos muestra un espacio en blanco cuando está vacía — sin explicación, sin orientación, sin diferenciación entre "no hay datos" y "ocurrió un error".

**Contexto de uso.** Toda lista, feed, sección de historial, o cualquier contenedor de elementos que puede estar vacío.

**Cuándo utilizarlo.** Siempre que una sección pueda tener cero elementos. No existe excepción a esta regla.

**Cuándo NO utilizarlo.** No aplica como excepción. Toda sección vacía tiene su Empty State.

**Anatomía.**

```
┌──────────────────────────────────────────────────────────┐
│                                                          │
│                   [Ícono representativo]                 │
│                                                          │
│            [Mensaje principal]                           │
│            [Mensaje secundario / causa]                  │
│                                                          │
│                 [Acción opcional]                        │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** Ícono centrado en tono suave (no de alerta), mensaje en gris, acción (cuando existe) en tono primario. La jerarquía es ícono → mensaje → acción. El Empty State nunca usa colores de alerta — la ausencia de datos no es una emergencia.

**Beneficio para el usuario.** Sabe exactamente por qué la sección está vacía y qué puede hacer al respecto. La ausencia de datos se convierte en una invitación, no en una pared.

**Ejemplo real.** `HomeView.vue` líneas 168–173: "Todo al día — sin avisos pendientes" con ícono de check en emerald-200. El `check` verde (no rojo) comunica que el vacío es positivo — no hay nada que atender. Líneas 315–317: "Sin actividad reciente" para el feed vacío — texto neutral, sin acción, porque el feed simplemente está vacío, no hay nada que crear.

**Errores comunes.**
- "Sin resultados" como único mensaje. ¿Por qué no hay resultados? ¿El filtro activo excluyó todo? ¿Nunca se han creado registros? La causa importa.
- Ícono de error (X, triángulo de advertencia) en un Empty State normal. Los estados vacíos son neutros o positivos, no errores.
- Empty State sin distinción entre "vacío por filtro" y "vacío porque no hay datos". El mensaje debe ser diferente en cada caso.

**Evolución futura.** Los Empty States de primer uso (onboarding) podrían ser más elaborados, con pasos guiados para crear el primer registro. Los Empty States de filtros activos siempre mostrarán el botón "Limpiar filtros".

---

### 12 — Loading Experience

**Estado:** `STABLE`

**Problema que resuelve.** El usuario espera contenido sin ninguna indicación de qué va a aparecer, durante cuánto tiempo, o si el sistema está funcionando. Cuando el contenido aparece, el layout "salta" porque la estructura de lo que cargó es diferente a la del contenedor que lo esperaba.

**Contexto de uso.** Todo contenido que se carga asincrónicamente: Entity Detail Pages, listas, widgets de dashboard, secciones de tabs.

**Cuándo utilizarlo.** Siempre que haya una operación de carga que tome más de 200ms. Para operaciones más cortas, la transición directa es preferible a mostrar y ocultar un skeleton.

**Cuándo NO utilizarlo.** Para acciones del usuario cuyo resultado aparece en la misma pantalla (guardar un formulario, marcar una tarea). Para esas acciones, el feedback es el estado del botón (texto en "…" durante la operación) y el resultado en el elemento modificado.

**Anatomía.**

```
Skeleton del header de Entity Detail:
┌──────────────────────────────────────────────────────────┐
│  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ (breadcrumbs — 160px)                 │
│  ┌──────┐  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ (código — 64px)            │
│  │      │  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ (nombre — 66%)    │
│  │ foto │  ▓▓▓▓▓▓ ▓▓▓▓▓▓▓▓▓▓ (badges)                  │
│  └──────┘                                               │
│  ▓▓▓▓▓▓ ▓▓▓▓▓ ▓▓▓▓▓ ▓▓▓▓▓ ▓▓▓▓▓ (KPI strip — 5 celdas)│
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** Los skeletons tienen exactamente la misma geometría del contenido que van a reemplazar. El fondo es gris claro con animación de pulso suave. Cuando el contenido llega, los skeletons se reemplazan sin saltos de layout porque los contenedores ya tienen el tamaño correcto.

**Beneficio para el usuario.** La espera tiene forma — el usuario sabe qué está por aparecer. La transición al contenido real es invisible porque el layout no cambia. La percepción de velocidad mejora aunque el tiempo real de carga sea el mismo.

**Ejemplo real.** `EquipmentDetailView.vue` líneas 2–28: el bloque de loading replica exactamente la estructura del header (skeleton de breadcrumbs, skeleton de foto cuadrada, skeleton de código y nombre, skeleton de 5 KPIs en grilla). `WorkOrderListView.vue` líneas 55–64: skeleton de lista donde cada ítem tiene un ícono cuadrado a la izquierda, dos líneas de texto, y un badge a la derecha — exactamente la estructura de cada OT en la lista.

**Errores comunes.**
- Spinner centrado en la pantalla. Un spinner no tiene forma — no informa sobre qué viene.
- Skeleton de tamaño diferente al contenido real. Si el skeleton tiene 3 líneas y el contenido tiene 6, hay un salto al cargar.
- No mostrar nada durante la carga (pantalla en blanco). La pantalla en blanco es el peor estado de carga — sugiere que el sistema no está funcionando.

**Evolución futura.** Posible transición suavizada (cross-fade) entre skeleton y contenido real, en lugar de un reemplazo brusco. El principio de "la carga tiene forma" no cambia.

---

### 13 — Confirmation Experience

**Estado:** `BETA`

**Problema que resuelve.** El usuario necesita ejecutar una acción que tiene consecuencias (cambiar el estado de una OT, eliminar un registro, aprobar una solicitud) sin fricción innecesaria, pero con suficiente señal para que no ocurra por accidente.

**Contexto de uso.** Acciones de transición de estado en Entity Detail Pages. Acciones de eliminación en listas. Acciones de aprobación/rechazo en flujos de solicitud.

**Cuándo utilizarlo.** Para cualquier acción que modifica estado de manera no trivial o que no es fácilmente reversible.

**Cuándo NO utilizarlo.** Para acciones de lectura. Para acciones frecuentes de bajo impacto (agregar un comentario no necesita confirmación). Para acciones que ya tienen un estado de loading visible como feedback.

**Anatomía (acciones de transición de estado).**

```
┌──────────────────────────────────────────────────────────┐
│  [Iniciar ejecución]  [Marcar verificada]  [Rechazar]    │
│   botón filled        botón outlined       botón outline │
│   (primaria)          (secundaria)         (destructiva) │
└──────────────────────────────────────────────────────────┘
```

**Flujo visual.** La acción primaria disponible (la que sigue en el flujo natural) es el botón prominente (indigo, filled). Las acciones secundarias son outlined con borde suave. Las acciones destructivas son outlined con tono rojo. Durante la transición, el texto del botón activo muestra "…" y todos los botones se deshabilitan (`opacity-60`). Si la transición falla, aparece un mensaje de error en rojo bajo los botones — no un modal de error.

**Beneficio para el usuario.** La acción más lógica es la más visible. No hay diálogos de "¿Está seguro?" para acciones reversibles. El feedback inmediato (botón en estado loading) confirma que el sistema recibió la acción. Los errores aparecen en contexto, no en un modal disruptivo.

**Ejemplo real.** `WorkOrderDetailView.vue` líneas 104–123. `primaryTransition` es el botón filled en indigo. `secondaryTransitions` es el array de botones outlined. El error `transitionError` aparece como `text-xs text-red-600` bajo los botones, sin modal.

**Errores comunes.**
- Dialog "¿Está seguro?" para acciones reversibles como cambiar el estado de Abierta a En ejecución.
- Botones de acción sin estado de loading. El usuario hace clic y no recibe ningún feedback hasta que la operación termina — que puede tomar 2-3 segundos.
- Mismo peso visual para acción primaria y destructiva. La acción de eliminar no debe verse igual que la de avanzar en el flujo.

**Evolución futura.** Acciones con confirmación de mayor impacto (eliminar un equipo con historial) tendrán un modal con campo de confirmación tipada ("escribe el nombre del equipo para confirmar"). Ese es el único caso donde un modal de confirmación es apropiado.

---

### 14 — Navigation Experience

**Estado:** `FROZEN`

**Problema que resuelve.** El usuario no sabe dónde está dentro del sistema, cómo llegó ahí, ni cómo volver al lugar donde estaba antes. La navegación lateral tiene grupos y nombres que no corresponden al modelo mental del usuario.

**Contexto de uso.** El `AppSidebar.vue` y los breadcrumbs en Entity Detail Pages.

**Cuándo utilizarlo.** El sidebar es permanente en todas las vistas del panel operativo. Los breadcrumbs aparecen en toda vista con profundidad de navegación mayor a 1.

**Cuándo NO utilizarlo.** El sidebar no aparece en la aplicación móvil de técnicos (que usa un BottomNav). No aparece en el panel de administración de plataforma.

**Anatomía del sidebar.**

```
┌───────────────────────────────────────────┐
│  ● FRONDA         [nombre tenant]         │
│  ─────────────────────────────────────    │
│  [🔍] Buscar…                     ⌘K     │
│  ─────────────────────────────────────    │
│  Inicio                                   │
│  Dashboard                                │
│                                           │
│  MANTENIMIENTO                            │
│    Solicitudes                            │
│  ● Órdenes de trabajo   ← activo         │
│    Mantenimiento Programado               │
│                                           │
│  ACTIVOS                                  │
│    Equipos                                │
│                                           │
│  INVENTARIO                               │
│    Repuestos                              │
│    Almacenes                              │
│                                           │
│  ANÁLISIS                                 │
│    Indicadores                            │
│    Resumen Ejecutivo                      │
│    Reportes                               │
│  ─────────────────────────────────────    │
│    Alertas                                │
│  ─────────────────────────────────────    │
│  [●] Juan García                          │
│      juan@empresa.com     [→ salir]       │
└───────────────────────────────────────────┘
```

**Flujo visual.** El sidebar usa `bg-slate-900` como fondo — completamente distinto al fondo blanco/gris del contenido principal. Esta diferencia de tono separa visualmente el sistema de navegación del contenido de trabajo. Los grupos de navegación tienen etiquetas en mayúsculas y tamaño pequeño. El ítem activo tiene un fondo sutil diferenciado. La búsqueda global está en posición alta y prominente con el atajo ⌘K.

**Beneficio para el usuario.** Puede navegar a cualquier parte del sistema en 2 pasos: clic en el grupo → clic en el ítem. La búsqueda global con ⌘K permite ir directamente a cualquier entidad específica sin navegar por el menú.

**Ejemplo real.** `AppSidebar.vue`: fondo `bg-slate-900`, logo en verde emerald, búsqueda con `@click="palette.open()"` y atajo `⌘K`, `NavGroup` components con etiquetas de sección, `NavItem` con estado activo automático por `RouterLink`. El footer del usuario con nombre, email e ícono de logout.

**Errores comunes.**
- Sidebar que colapsa en desktop para "ganar espacio". En Fronda, el sidebar es siempre visible en desktop. La navegación no se oculta.
- Grupos de navegación con nombres técnicos en lugar de nombres del dominio del usuario. "Módulo de gestión de activos físicos" vs. "Activos". El segundo siempre gana.
- Agregar ítems de primer nivel sin grupo. Todo ítem de navegación pertenece a un grupo, excepto Inicio, Dashboard y Alertas.

**Evolución futura.** El sidebar podría tener un modo colapsado (iconos solamente) en resoluciones intermedias. La jerarquía de grupos permanece — solo la representación visual varía.

---

### 15 — Search Experience

**Estado:** `BETA`

**Problema que resuelve.** El usuario necesita llegar a una entidad específica (un equipo por código, una OT por número, un repuesto por nombre) sin recordar en qué módulo está o sin navegar por listas paginadas.

**Contexto de uso.** La Command Palette (`CommandPalette.vue`) activada con ⌘K desde cualquier parte del panel. La búsqueda inline en vistas de lista (`WorkOrderListView`, `EquipmentListView`). Los filter chips de estado en listas.

**Cuándo utilizarlo.** La Command Palette es el mecanismo de búsqueda global — cualquier entidad de cualquier tipo. La búsqueda inline es para filtrar una lista específica. Los filter chips son para filtrar por estado o categoría.

**Cuándo NO utilizarlo.** La búsqueda global no reemplaza la navegación por módulos — es un atajo para usuarios que ya saben qué buscan. Un usuario explorando el sistema usa el sidebar. Un usuario que sabe exactamente qué quiere usa ⌘K.

**Anatomía de la Command Palette.**

```
┌──────────────────────────────────────────┐  overlay con backdrop
│  [🔍] Buscar equipos, OT, solicitudes…  │
│  ─────────────────────────────────────   │
│                                          │
│  (estado inicial: "Empieza a escribir")  │
│                                          │
│  EQUIPOS                                 │
│  ┌────────────────────────────────────┐  │
│  │ [⚙] EQ-089 · Bomba centrífuga BCP │  │
│  │     EQ-089          [Activo ●]     │  │
│  └────────────────────────────────────┘  │
│                                          │
│  ÓRDENES DE TRABAJO                      │
│  ┌────────────────────────────────────┐  │
│  │ [📋] OT-2025-0043 · Bomba hidrául │  │
│  │      OT-2025-0043  [En ejecución] │  │
│  └────────────────────────────────────┘  │
│                                          │
│  ↑↓ navegar  ↵ abrir  Esc cerrar        │
└──────────────────────────────────────────┘
```

**Flujo visual.** El overlay aparece con backdrop blur sobre el contenido actual. El input tiene foco automático. Los resultados se agrupan por tipo de entidad. El resultado activo (teclado) tiene fondo emerald-50. Cada resultado muestra ícono del tipo + título + subtitle en monospace (código/número) + badge de estado. El footer muestra los atajos de teclado disponibles.

**Beneficio para el usuario.** Llega a cualquier entidad del sistema en menos de 5 segundos: ⌘K → primeras letras → Enter. Sin navegar por módulos, sin recordar en qué planta está el equipo, sin buscar el número exacto de la OT.

**Ejemplo real.** `CommandPalette.vue`: `Teleport to="body"` para salir del contexto de scroll del sidebar, `backdrop-blur-sm` sobre `bg-slate-900/40`, input con debounce de búsqueda, grupos por `group.type` (equipos/ordenes/solicitudes/repuestos/preventivos), navegación por teclado con `activeIndex`, keyboard hints en el footer.

**Errores comunes.**
- Command Palette que muestra resultados globales mezclados sin agrupar. El usuario no puede distinguir qué tipo de entidad es cada resultado.
- Búsqueda que empieza después de 1 carácter. Con 1 carácter hay demasiados resultados y el sistema se sobrecarga. El mínimo es 2-3 caracteres.
- Resultados sin badge de estado. El usuario necesita saber el estado de la OT o el equipo antes de hacer clic.

**Evolución futura.** La búsqueda global incorporará búsqueda semántica cuando Fronda integre IA ("órdenes de la semana pasada del turno de noche"). El patrón de Command Palette permanece — el motor de búsqueda subyacente evoluciona.

---

## Pattern Relationship

Los patrones no existen en aislamiento. Cada pantalla de Fronda es una composición de patrones. Las composiciones permitidas están definidas aquí.

---

### Composiciones canónicas

Estas son las secuencias de patrones que componen las pantallas principales de Fronda. Representan el orden correcto de los patrones en cada tipo de pantalla.

**Pantalla de Inicio (Home)**
```
Workspace Hero
     ↓
Attention Cards
     ↓
Quick Actions
     ↓
Institutional Carousel (condicional — solo si hay slides)
     ↓
Activity Timeline
```

**Vista de detalle de entidad (Equipment / Work Order / Request)**
```
[Navigation Experience: Breadcrumbs en el Hero]
     ↓
Entity Detail Page (Hero + KPI Strip + Tabs)
     ├── Context Banner (dentro de tab/sección)
     ├── Tabbed Workspace
     ├── Activity Timeline (sección Historial)
     └── Empty State (cuando una sección está vacía)
```

**Vista de lista (Master)**
```
[Navigation Experience: Sidebar activo]
     ↓
Master-Detail (lista)
     ├── Search Experience (búsqueda inline + filter chips)
     ├── Loading Experience (skeleton de lista)
     └── Empty State (cuando la lista está vacía)
```

**Dashboard analítico**
```
[Navigation Experience: Sidebar activo]
     ↓
Dashboard Analytics
     ├── Loading Experience (skeleton de widgets)
     └── Empty State (cuando no hay datos para el período)
```

---

### Combinaciones prohibidas

Algunos patrones son mutuamente excluyentes porque sirven a modos cognitivos distintos. Combinarlos en la misma vista crea confusión de propósito.

| Combinación prohibida | Razón |
|---|---|
| `Dashboard Analytics` + `Attention Cards` | Analítico y operacional son modos distintos. Una vista no puede ser ambas cosas. |
| `Dashboard Analytics` + `Quick Actions` | Las acciones rápidas pertenecen al contexto operacional, no al analítico. |
| `Workspace Hero` + `Entity Detail Page` | El Workspace Hero es para el home. Una entidad tiene su propio hero dentro de la Entity Detail Page. |
| `Institutional Carousel` + `Entity Detail Page` | El carrusel es contenido editorial del home. No aparece en vistas de entidad. |
| `Confirmation Experience (modal)` + `Tabbed Workspace` | Las confirmaciones que abren modales rompen el contexto de la tab activa. Los errores de transición se muestran inline, no en modales. |
| `Activity Timeline` como pantalla principal | El Timeline es siempre secundario. Nunca es el único contenido de una pantalla principal. |

---

### Regla de oro de composición

> **Un patrón de acción y un patrón de análisis nunca comparten la misma pantalla.**

Las pantallas de Fronda son de uno de dos tipos:
1. **Operacionales** — Ayudan al usuario a hacer algo ahora. (Workspace Hero, Attention Cards, Quick Actions, Master-Detail, Entity Detail Page)
2. **Analíticas** — Ayudan al usuario a entender qué ocurrió. (Dashboard Analytics, Activity Timeline cuando es el foco principal)

La pantalla de Inicio tiene Activity Timeline, pero es el último elemento — subordinado a las Attention Cards y Quick Actions. En ese contexto, el timeline es informativo, no analítico.

---

## Registro de Evolución de Patrones

| Patrón | Estado | Implementado | Parcialmente | Pendiente |
|---|---|---|---|---|
| 01 — Workspace Hero | `FROZEN` | ✓ | | |
| 02 — Attention Cards | `STABLE` | ✓ | | |
| 03 — Quick Actions | `STABLE` | ✓ | | |
| 04 — Institutional Carousel | `STABLE` | ✓ | | |
| 05 — Activity Timeline | `BETA` | ✓ | Feed filters | Eventos IoT |
| 06 — Entity Detail Page | `STABLE` | ✓ | | |
| 07 — Context Banner | `STABLE` | ✓ | | |
| 08 — Master-Detail | `FROZEN` | ✓ | | |
| 09 — Tabbed Workspace | `STABLE` | ✓ | | |
| 10 — Dashboard Analytics | `BETA` | ✓ | Drill-down | Proyecciones |
| 11 — Empty State | `STABLE` | ✓ | | Onboarding states |
| 12 — Loading Experience | `STABLE` | ✓ | | Cross-fade |
| 13 — Confirmation Experience | `BETA` | ✓ | | Confirmación tipada |
| 14 — Navigation Experience | `FROZEN` | ✓ | | Modo colapsado |
| 15 — Search Experience | `BETA` | ✓ | | Búsqueda semántica |

**Definición de estados:**

- `FROZEN` — El patrón es definitivo. No se acepta ninguna propuesta de cambio estructural. Solo se permiten variantes de contenido.
- `STABLE` — El patrón está establecido y validado. Puede tener evoluciones menores pero su estructura central no cambia.
- `BETA` — El patrón está implementado pero puede cambiar significativamente con nueva evidencia de uso.
- `EXPERIMENTAL` — El patrón está en prueba. No debe usarse como base para nuevas pantallas hasta que alcance `BETA`.

---

### Ideas clave

- Un patrón resuelve un problema de interacción humano. Un componente resuelve un problema visual. Los patrones son más fundamentales porque los problemas humanos no cambian cuando cambia la tecnología.
- Los 15 patrones de esta biblioteca cubren el 95% de los casos de diseño de Fronda. Toda nueva pantalla debe construirse con ellos antes de considerar introducir un patrón nuevo.
- Las composiciones canónicas son la forma correcta de ensamblar patrones. Las combinaciones prohibidas existen porque algunos patrones sirven a modos cognitivos incompatibles.
- Los patrones `FROZEN` no se debaten en design reviews. Los `BETA` sí pueden evolucionar con evidencia de uso.
- Esta biblioteca se actualiza cuando se implementa una pantalla que requiere un patrón que no existe. Nunca se actualiza por preferencia estética.

---

[← Capítulo 5: Principios UX](05-UX-Principles.md) · [Índice](../README.md) · [Capítulo 7 →](07-Roles-y-Audiencias.md)
