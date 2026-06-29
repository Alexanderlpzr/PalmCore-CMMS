---

`PARTE II — EXPERIENCE`  `CAPÍTULO 5`

**Tiempo de lectura:** 20 minutos · **Objetivo:** Establecer los principios que gobiernan cada decisión de interfaz en Fronda — de modo que cualquier diseñador, sin haber visto el producto antes, pueda diseñar una pantalla completamente coherente con él.

> *"Un técnico no debería necesitar leer instrucciones para cerrar una orden de trabajo. Si las necesita, fallamos antes de que él abriera la app."*
> — Fronda

---

# Principios UX — Fronda

---

## Introducción

### Por qué el diseño de experiencia importa en mantenimiento industrial

El mantenimiento industrial tiene una relación complicada con el software.

Las personas que lo practican no son usuarios de escritorio. No trabajan en oficinas silenciosas con monitores de 27 pulgadas. Trabajan en naves industriales con ruido de fondo, en turnos nocturnos, con las manos ocupadas, con luz inadecuada, bajo presión. Cuando una máquina falla, el tiempo que le toma al técnico abrir la app, encontrar la orden de trabajo correcta y registrar su trabajo no es un problema de UX abstracto — es dinero que se pierde por minuto.

Ese contexto cambia todo. Cambia qué información aparece primero. Cambia cuántos toques requiere una acción. Cambia qué tan grande debe ser un botón. Cambia cuándo es aceptable mostrar un loading state y cuándo no lo es.

La mayoría del software de mantenimiento fue diseñado ignorando ese contexto. Fue diseñado para demostraciones de ventas: pantallas densas de información, dashboards con docenas de KPIs, formularios con 40 campos. Se ve poderoso. Es difícil de usar.

Fronda existe en el polo opuesto.

---

### Por qué el CMMS tradicional falla en diseño

El error de diseño más común en los sistemas de mantenimiento no es estético — es arquitectónico.

El CMMS tradicional organiza su interfaz alrededor de los módulos del sistema (equipos, órdenes, inventario, reportes) y obliga al usuario a navegar entre ellos para completar una tarea que es, en la realidad, una sola acción. Para ejecutar una orden de trabajo que requiere un repuesto, el técnico visita el módulo de órdenes, luego el de inventario, luego vuelve a órdenes. Cada salto rompe el contexto. Cada ruptura de contexto aumenta la probabilidad de error.

Además, el CMMS tradicional diseña primero para el reporte. La pantalla que muestra los datos de una máquina está diseñada para el gerente que quiere ver estadísticas, no para el técnico que quiere saber qué hacer con ella ahora. El resultado es una pantalla que informa sin ayudar.

---

### Por qué Fronda diseña diferente

Fronda invierte el orden.

Primero, la acción. Luego, la información que apoya esa acción. El diseño parte siempre de la pregunta: **¿qué necesita hacer el usuario ahora?** Todo lo demás — los datos históricos, los KPIs, los registros completos — existe para apoyar esa acción, no para reemplazarla.

Segundo, el contexto viaja con el usuario. Cuando un técnico está en la vista de una orden de trabajo, puede ver el equipo asociado sin navegar al módulo de equipos. Cuando un supervisor revisa un equipo, puede ver sus órdenes activas sin salir de la ficha del equipo. Las entidades están conectadas porque el trabajo real las conecta.

Tercero, la interfaz habla el idioma del campo. No el idioma de la base de datos, no el idioma del manual de contabilidad de activos. El idioma del técnico que dice "la bomba está parada" y del supervisor que dice "necesito que me entreguen esa OT cerrada antes de las seis".

---

## Los 12 Principios UX de Fronda

---

### 1. La acción siempre precede a la información

**Problema.** Una pantalla que muestra información antes de mostrar qué hacer con ella invierte la prioridad del usuario. El usuario no viene a Fronda a leer — viene a actuar.

**Principio.** Toda pantalla debe responder primero: *¿qué debo hacer?* Solo después: *¿cómo llegué aquí?*

**Explicación.** La pantalla de Inicio no comienza con un resumen de las actividades del mes. Comienza con las órdenes vencidas, las solicitudes pendientes de aprobación, los preventivos que vencen en 7 días, las alertas críticas. Cuatro números. Cuatro estados que requieren atención. Si todos son cero, la pantalla dice explícitamente que todo está al día.

Solo después de esas tarjetas de atención aparecen los accesos rápidos, el carrusel institucional, el feed de actividad. En ese orden, porque ese es el orden de urgencia del usuario.

**Ejemplo real.** En `HomeView.vue`, la sección "Avisos importantes" — con sus cuatro tarjetas de contadores de colores — aparece antes del feed empresarial. Si hay tres órdenes vencidas, eso es lo primero que ve el supervisor al abrir la app. El feed de actividad —cronológico, informativo— aparece al final.

**Contraejemplo.** Una pantalla que empieza con un gráfico de barras de órdenes del mes y esconde al final un aviso de órdenes vencidas. El usuario tiene que leer para encontrar lo urgente.

**Razón.** El tiempo de una persona en campo es escaso y el costo de no actuar a tiempo es real. Diseñar para la atención significa diseñar para la urgencia, no para la estética.

---

### 2. El contexto nunca se pierde

**Problema.** El usuario que navega desde una entidad a otra relacionada pierde el hilo. Para volver, necesita el botón de regreso del navegador o la navegación lateral — ambas opciones que interrumpen el flujo cognitivo.

**Principio.** Toda entidad lleva consigo el contexto necesario para entenderla sin salir de su pantalla.

**Explicación.** La vista de detalle de un equipo muestra, además de sus datos propios, una tarjeta de contexto con la última orden de trabajo asociada. Si el equipo es un subcomponente de otro equipo padre, muestra ese vínculo con un enlace directo. Si el técnico llega a una orden de trabajo desde la ficha del equipo, el parámetro `from` en la URL preserva la ruta de regreso y el botón de retroceso dice "Volver al equipo", no "Volver".

Cuando sí es necesario navegar a otra entidad, la pantalla de destino sabe de dónde vino el usuario y lo puede llevar de regreso al punto exacto de partida.

**Ejemplo real.** En `EquipmentDetailView.vue`, la tarjeta de contexto con fondo `bg-indigo-50` muestra la última OT del equipo con su número, título y estado — sin que el usuario abandone la ficha del equipo. En `WorkOrderDetailView.vue`, el banner de "Equipo asociado" permite navegar al equipo con un enlace, pero también volver desde allá usando el `from` guardado en la query.

**Contraejemplo.** Un enlace "Ver equipo" que abre la ficha del equipo en la misma pantalla, borrando completamente la vista de la orden de trabajo y obligando al usuario a navegar manualmente de regreso.

**Razón.** El mantenimiento industrial es trabajo de contexto. Un técnico que atiende una orden necesita saber simultáneamente qué equipo está atendiendo, qué historial tiene ese equipo, y qué repuestos necesitará. Fragmentar esa información en silos de navegación crea fricción innecesaria en el momento que más cuesta.

---

### 3. Los estados vacíos enseñan

**Problema.** Un estado vacío que solo muestra "Sin resultados" desperdicia la única oportunidad de guiar al usuario cuando más lo necesita: cuando no hay nada que ver.

**Principio.** Cada estado vacío responde dos preguntas: por qué está vacío, y qué puede hacer el usuario para que deje de estarlo.

**Explicación.** Un estado vacío no es un error. Es un estado informativo. La diferencia entre un sistema que enseña y uno que confunde está exactamente en cómo maneja los momentos en que no hay datos.

Si los KPIs de un equipo no están calculados, la pantalla no muestra un espacio en blanco — muestra: "KPIs aún no calculados para este equipo". Si no hay avisos importantes, muestra: "Todo al día — sin avisos pendientes" con un ícono de verificación que transmite calma activa, no ausencia. Si no hay órdenes de trabajo, muestra una acción directa para crear la primera.

**Ejemplo real.** En `EquipmentDetailView.vue`, cuando `equipment.kpi` es nulo, aparece: "KPIs aún no calculados para este equipo" dentro de un contenedor con fondo `bg-gray-50`. En `HomeView.vue`, el estado vacío de avisos muestra un ícono de check verde y el texto "Todo al día — sin avisos pendientes".

**Contraejemplo.** Un espacio en blanco donde deberían aparecer los KPIs. Sin texto, sin ícono, sin explicación. El usuario no sabe si el sistema falló, si el dato no existe, o si está esperando algo.

**Razón.** El primer usuario de un sistema siempre encontrará pantallas vacías. Si esas pantallas no enseñan, el usuario abandona antes de que el sistema tenga valor. Los estados vacíos son el onboarding silencioso del producto.

---

### 4. Los dashboards informan; las pantallas de trabajo ayudan

**Problema.** Un dashboard con muchos KPIs que no lleva a ninguna acción es una pantalla de información que termina en la pantalla de información. El ciclo no produce nada.

**Principio.** Los dashboards son retrospectivos. Las pantallas de trabajo son prospectivas. Nunca deben comportarse igual.

**Explicación.** El Dashboard Ejecutivo de Fronda existe para responder preguntas como: ¿cuál equipo tiene el peor MTTR del mes? ¿Cómo evolucionó la disponibilidad de la línea 3 en el último trimestre? Son preguntas de análisis. No requieren acción inmediata.

La pantalla de Inicio, en cambio, responde: ¿qué necesita mi atención ahora? Esas son preguntas de trabajo. Requieren acción en el siguiente minuto, no en la próxima reunión de gerencia.

Confundir estos dos modos — poner gráficos retrospectivos en una pantalla de trabajo, o poner listas de tareas urgentes en un dashboard ejecutivo — es el error más común en el software de mantenimiento.

**Ejemplo real.** El módulo `KpisView.vue` (indicadores) muestra tendencias de MTBF y MTTR en el tiempo, análisis de Pareto de fallas, ranking de disponibilidad por equipo. Es retrospectivo. La pantalla de Inicio muestra el conteo de órdenes vencidas hoy. Es prospectivo. Ambos son necesarios. Nunca deben fusionarse.

**Contraejemplo.** Una pantalla de inicio que muestra un gráfico de barras de "Órdenes por mes (últimos 6 meses)" como el elemento principal. Esa información no ayuda al usuario que abre la app el lunes por la mañana.

**Razón.** El análisis y la operación son modos cognitivos distintos. El software que los mezcla obliga al usuario a cambiar de modo constantemente, lo que aumenta el tiempo de decisión y la probabilidad de error.

---

### 5. Las tablas sirven a la búsqueda, nunca al descubrimiento

**Problema.** Una tabla sin filtros, sin contexto, sin jerarquía de importancia es un volcado de datos que obliga al usuario a convertirse en el motor de búsqueda que el sistema debería ser.

**Principio.** El usuario nunca aterriza en una tabla sin saber por qué está ahí. Las tablas son el resultado de una búsqueda o de un filtro aplicado — nunca el punto de partida.

**Explicación.** La lista de órdenes de trabajo de Fronda no aparece sin contexto. Cuando un supervisor llega a ella desde la pantalla de Inicio, las órdenes están pre-filtradas según lo que lo llevó allí: las vencidas, las pendientes de su aprobación, las del turno actual. Cuando un técnico llega desde la ficha de un equipo, ve solo las órdenes de ese equipo.

La tabla de órdenes sin filtro existe para búsqueda avanzada, no para revisión casual. Y cuando existe, incluye filtros relevantes en la vista inmediata, no enterrados en un menú de tres niveles.

**Ejemplo real.** En `WorkOrderListView.vue` y `EquipmentListView.vue`, los filtros son parte del encabezado de la vista, no un modal secundario. Las vistas pueden recibir parámetros de filtro en la URL, de modo que un enlace desde un aviso de la pantalla de Inicio ya llega pre-filtrado.

**Contraejemplo.** Un módulo de "Órdenes de trabajo" que al abrirse muestra todas las órdenes de todos los tiempos, sin ningún filtro aplicado, en una tabla paginada de 500 filas con columnas de igual peso visual.

**Razón.** La información sin jerarquía no ayuda — sobrecarga. El sistema debe tomar decisiones sobre qué es relevante para quién y en qué momento, no delegarle esa decisión al usuario.

---

### 6. Cada interacción reduce trabajo, no lo redistribuye

**Problema.** Una funcionalidad que resuelve un paso pero crea dos nuevos no ayuda al usuario — le cambia el problema.

**Principio.** El criterio de cualquier nueva interacción no es si existe, sino si reduce la cantidad total de trabajo que el usuario necesita hacer para completar su tarea.

**Explicación.** El código QR de un equipo no es una funcionalidad de conveniencia — es la eliminación de un proceso completo. Sin QR, el técnico en campo necesita abrir la app, navegar al módulo de equipos, buscar por nombre o código, seleccionar el equipo correcto. Con QR, escanea y llega directamente a la ficha del equipo en dos segundos.

La paleta de comandos (⌘K) cumple la misma función para el usuario de escritorio: en lugar de navegar por el menú lateral hasta encontrar una vista, escribe las primeras letras y llega directamente.

Los accesos rápidos de la pantalla de Inicio (Crear OT, Solicitud, Equipos, Preventivos, Alertas, Dashboard) son otra reducción: el flujo más común del rol del usuario disponible en un toque desde cualquier momento.

**Ejemplo real.** En `AppSidebar.vue`, el botón de búsqueda global (con el atajo ⌘K) está en la posición más accesible de la barra lateral, inmediatamente después del logo. No es un elemento secundario — es el acceso más rápido a cualquier parte del sistema.

**Contraejemplo.** Un botón "Crear orden de trabajo" que abre un formulario en blanco sin precargar el equipo desde el que se accedió. El usuario tiene que buscar y seleccionar el equipo manualmente, aunque acaba de venir de la ficha de ese equipo.

**Razón.** El trabajo no registrado es trabajo perdido. Si registrar una actividad es más difícil que no registrarla, el sistema pierde. Cada interacción debe competir con la alternativa más fácil: el papel, el WhatsApp, la memoria.

---

### 7. Los colores son un contrato con el usuario

**Problema.** Un color usado decorativamente en un contexto y semánticamente en otro confunde al usuario y destruye la confianza en el lenguaje visual del sistema.

**Principio.** Cada color tiene un significado único y estable en todo Fronda. Ese significado nunca cambia por razones estéticas.

**Explicación.** En Fronda existe un sistema semántico de colores que opera en todos los módulos:

- **Emerald** — Salud, éxito, operación normal, acción primaria. Disponibilidad alta, orden completada, estado activo.
- **Rojo** — Peligro, falla, vencido, crítico. Downtime, orden vencida, alerta crítica, estado fuera de servicio.
- **Ámbar** — Advertencia, atención requerida, inminente. Preventivo próximo, solicitud pendiente, criticidad alta.
- **Azul** — Información, proceso en curso, dato neutro. MTBF, costo total, estado en ejecución.
- **Índigo** — Contexto relacionado, entidad vinculada, navegación jerarquica. Banners de contexto, breadcrumbs activos.
- **Slate/Gris** — Neutro, inactivo, secundario. Metadata, etiquetas de fecha, estados archivados.

Estos colores no se usan para hacer pantallas "más bonitas". Se usan para que el usuario pueda leer el estado de una situación antes de leer una sola palabra de texto.

**Ejemplo real.** En la tira de KPIs de `EquipmentDetailView.vue`: emerald para disponibilidad, azul para MTBF, ámbar para MTTR, rojo para fallas, slate para downtime. El usuario aprende en la primera semana de uso que el rojo siempre indica un problema. En la segunda semana, lee los colores antes de leer los números.

**Contraejemplo.** Un módulo que usa rojo para los títulos de sección porque "se ve más profesional". Cualquier usuario que haya aprendido que rojo = peligro verá esa sección y sentirá alarma antes de leer su contenido.

**Razón.** El color es el canal de comunicación más rápido que tiene una interfaz — más rápido que el texto, más rápido que el ícono. Si ese canal envía mensajes contradictorios, el usuario tiene que detenerse a leer para confirmar lo que los colores deberían haberle dicho instantáneamente.

---

### 8. La densidad visual se adapta al contexto de uso

**Problema.** Una misma pantalla diseñada para escritorio y para móvil sin adaptación obliga al usuario de campo a trabajar con una interfaz que nunca fue pensada para sus condiciones.

**Principio.** La cantidad de información en pantalla es proporcional a la urgencia del momento y al dispositivo con el que se accede.

**Explicación.** Un técnico que cierra una orden de trabajo desde su móvil necesita tres campos: causa raíz, descripción del trabajo, firma. No necesita el historial completo de la máquina, los comentarios anteriores de la OT, ni las opciones de exportación a PDF.

El supervisor que revisa la misma orden desde su escritorio puede necesitar todo eso: comentarios de otros técnicos, evidencias fotográficas, partes consumidas, tiempo real vs. estimado, comparativa con órdenes similares anteriores.

La misma entidad, en el mismo momento, con distinto nivel de densidad según el contexto. Fronda implementa esto con el patrón de tabs adaptivo: en escritorio, todas las secciones están visibles simultáneamente con navegación por scroll y anclaje. En móvil, las secciones se presentan como pestañas excluyentes que se muestran de una en una.

**Ejemplo real.** En `WorkOrderDetailView.vue` y `EquipmentDetailView.vue`, el mismo componente de navegación por secciones se comporta distinto según el breakpoint `lg`: en desktop muestra todos los anclajes simultáneamente y el contenido es visible en scroll; en móvil es un selector de tab que muestra una sección a la vez con `v-show`.

**Contraejemplo.** Una vista de detalle de orden de trabajo que en móvil muestra exactamente el mismo layout que en escritorio: cinco columnas de KPIs, cuatro pestañas de contenido, toolbar de acciones con ocho botones. Funciona. No ayuda.

**Razón.** El campo y la oficina son ambientes de trabajo distintos. La interfaz debe ser tan precisa como el entorno lo permite: máxima información cuando hay tiempo y espacio para procesarla; mínima fricción cuando hay urgencia y espacio limitado.

---

### 9. El software transmite calma

**Problema.** Una interfaz con alertas de colores brillantes, animaciones de atención, badges parpadeantes y notificaciones constantes entrena al usuario para ignorarlos. Cuando todo es urgente, nada lo es.

**Principio.** Fronda comunica la urgencia a través de jerarquía y posición, no a través de alarma visual. Solo el usuario puede decidir si algo es urgente.

**Explicación.** La jerarquía visual en Fronda usa tamaño, peso tipográfico y posición para comunicar importancia — no animaciones, no colores de fondo brillantes en áreas grandes, no badges parpadeantes. Un número grande en una tarjeta roja pequeña comunica urgencia con precisión. Un banner rojo que ocupa media pantalla comunica pánico.

El espacio en blanco es parte del diseño. Las tarjetas tienen bordes sutiles (`border-gray-100`), sombras mínimas (`shadow-sm`), bordes redondeados amplios (`rounded-2xl`). Todo esto contribuye a que el usuario pueda procesar la información sin fatiga visual.

Los estados de carga usan skeletons con la forma exacta del contenido que van a mostrar — no spinners que hacen que la pantalla se sienta vacía. El usuario sabe qué está esperando porque ve la silueta de lo que viene.

**Ejemplo real.** En la sección de "Avisos importantes" de `HomeView.vue`, las tarjetas de órdenes vencidas usan `border-red-100` (borde suave) y `bg-red-50` (fondo apenas rosado) para el ícono — no un bloque rojo que ocupa toda la tarjeta. El número de órdenes vencidas está en `text-red-600` sobre fondo blanco. Urgente pero calmo.

**Contraejemplo.** Tarjetas con fondo completamente rojo, texto blanco, y un badge parpadeante sobre el ícono. Visualmente llamativo. Cognitivamente agotador.

**Razón.** Los técnicos trabajan bajo presión de forma natural. El software que añade presión visual sobre la presión operacional deteriora la capacidad de toma de decisiones. Un software que transmite calma permite que el usuario piense con claridad.

---

### 10. Los estados de una entidad son visibles en todo momento

**Problema.** Un sistema donde el usuario tiene que abrir el detalle de una entidad para saber su estado actual obliga a múltiples navegaciones para tareas que deberían resolverse con un vistazo.

**Principio.** El estado de cualquier entidad — equipo, orden de trabajo, solicitud, repuesto — es visible en el badge que la acompaña dondequiera que aparezca.

**Explicación.** Los badges de estado de Fronda son consistentes: mismo color, mismo texto, misma forma en la lista, en el detalle, en el feed de actividad, en el panel de contexto. Un supervisor puede revisar la lista de órdenes de su turno y saber cuáles están abiertas, cuáles en ejecución, cuáles esperando verificación, cuáles cerradas — sin abrir ninguna.

Ese sistema de badges no varía. `Abierta` siempre es azul. `En ejecución` siempre es ámbar. `Cerrada` siempre es emerald. En todos los módulos, en todos los contextos.

**Ejemplo real.** En `WorkOrderDetailView.vue`, el header muestra el badge de estado del misma forma que aparece en `WorkOrderListView.vue`. En `EquipmentDetailView.vue`, la última OT en el banner de contexto muestra su badge de estado con los mismos colores `woStatusColors`. El usuario aprende una vez y aplica en todas partes.

**Contraejemplo.** Una lista de órdenes que muestra el estado como texto sin color en una columna pequeña. Un detalle de orden que muestra el estado con colores completamente distintos. El usuario tiene que reaprender el lenguaje visual cada vez que cambia de contexto.

**Razón.** Los sistemas de mantenimiento tienen muchas entidades con muchos estados posibles. El único way de escalar esa complejidad sin aumentar la carga cognitiva es hacer que el sistema de estados sea predecible hasta el punto de ser automático.

---

### 11. Cada nivel de navegación tiene un propósito declarado

**Problema.** Una navegación donde el usuario puede llegar al mismo lugar por tres caminos distintos, o donde no existe distinción clara entre secciones de primer y segundo nivel, desorientan al usuario sin que éste pueda explicar por qué.

**Principio.** Cada sección del menú de navegación tiene un único propósito que puede expresarse en una frase, y ese propósito no se superpone con ninguna otra sección.

**Explicación.** La navegación principal de Fronda está organizada en grupos con roles claros y no intercambiables:

- **Mantenimiento** — Solicitudes, Órdenes de Trabajo, Mantenimiento Programado. Todo el ciclo de una tarea de mantenimiento.
- **Activos** — Equipos. El registro y gestión del parque de activos.
- **Inventario** — Repuestos, Almacenes. Los materiales que el mantenimiento consume.
- **Análisis** — Indicadores, Resumen Ejecutivo, Reportes. La retrospectiva de lo que ocurrió.
- **Alertas** — Notificaciones de estado crítico que requieren atención inmediata.

Ninguno de estos grupos duplica al otro. Las órdenes de trabajo pertenecen a Mantenimiento, no a Activos, aunque se apliquen sobre activos. Los reportes pertenecen a Análisis, no a cada módulo individual.

**Ejemplo real.** En `AppSidebar.vue`, los grupos de navegación (`NavGroup`) tienen etiquetas únicas y no superpuestas. No existe un "Órdenes de trabajo" bajo Activos y otro bajo Mantenimiento. La ubicación de cada ítem en la jerarquía es la única ubicación posible.

**Contraejemplo.** Un menú que tiene "Mis órdenes de trabajo" bajo el perfil de usuario, "Órdenes activas" bajo el dashboard, y "Órdenes de trabajo" como sección principal. El mismo recurso en tres lugares distintos — el usuario nunca sabe cuál es el canónico.

**Razón.** La desorientación en un sistema de trabajo no es una incomodidad — es un bloqueador. Un técnico que no sabe dónde está la orden que debe atender no puede atenderla. La navegación clara es una decisión operacional, no estética.

---

### 12. La carga tiene forma

**Problema.** Un spinner genérico que bloquea toda la pantalla mientras carga contenido no transmite ninguna información sobre lo que viene — y hace que la espera se sienta más larga de lo que es.

**Principio.** Cada estado de carga refleja exactamente la forma del contenido que va a reemplazarlo.

**Explicación.** Los skeletons de Fronda no son bloques grises genéricos. Son la silueta exacta del contenido que esperan: si vienen cinco KPIs en una grilla de 5 columnas, el skeleton muestra cinco rectángulos en una grilla de 5 columnas. Si viene un header con imagen circular a la izquierda y texto a la derecha, el skeleton muestra ese mismo layout.

Esto cumple dos funciones. La primera, cognitiva: el usuario sabe qué está esperando y puede prepararse mentalmente para procesarlo. La segunda, perceptual: la pantalla no "salta" cuando el contenido llega — el layout ya estaba en el lugar correcto.

**Ejemplo real.** En `EquipmentDetailView.vue` y `WorkOrderDetailView.vue`, el bloque de loading replica exactamente la estructura del header: un rectángulo para la foto/ícono, dos rectángulos para el código y el nombre, badges skeleton, y la grilla de KPIs en cinco columnas. Cuando el contenido llega, el usuario no nota la transición porque la geometría no cambió.

**Contraejemplo.** Un spinner centrado en la pantalla que aparece durante 2 segundos y luego es reemplazado por un layout completamente distinto al que el usuario imaginaba. El salto visual interrumpe la fluidez de la experiencia.

**Razón.** La percepción de velocidad importa más que la velocidad real. Un sistema que carga en 1.5 segundos pero lo hace con gracia se siente más rápido que uno que carga en 1 segundo y lo hace de manera abrupta.

---

## Patrones UX oficiales

Los patrones de esta sección son las unidades de construcción de la interfaz de Fronda. No describen cómo implementarlos — describen cuándo usarlos y cuándo no.

---

### Hero

**Qué es.** El bloque de encabezado fijo (sticky) que identifica a la entidad principal de la pantalla actual. Contiene: breadcrumbs, foto/ícono de la entidad, nombre prominente, código en monospace, badges de estado, y la acción primaria disponible.

**Cuándo usar.** En toda vista de detalle de entidad (equipo, orden de trabajo, solicitud, plan de mantenimiento). El hero siempre es sticky — el usuario debe poder ver el estado y actuar desde cualquier punto de la pantalla sin volver al tope.

**Cuándo no usar.** En pantallas de lista, dashboards, o vistas de análisis. En esas pantallas, el hero da lugar a un encabezado informativo no sticky.

**Regla invariante.** El hero siempre incluye el estado actual de la entidad en un badge visible. Nunca se diseña un hero sin badge de estado.

---

### Cards

**Qué es.** La unidad de presentación de información en Fronda. Fondo blanco, borde `border-gray-100`, sombra `shadow-sm`, bordes redondeados `rounded-2xl`. Contiene un bloque de información autónomo.

**Cuándo usar.** Para presentar elementos de una lista o grid donde cada elemento es comparable con los demás: listas de avisos, grillas de accesos rápidos, feeds de actividad, listas de equipos en una vista de área.

**Cuándo no usar.** Para envolver secciones enteras de una página de detalle. Las secciones de una página de detalle no son cards — son secciones con `SectionLabel` como encabezado. Las cards son para elementos individuales dentro de esas secciones.

**Regla invariante.** Una card siempre lleva al usuario a algún lugar o realiza una acción cuando se hace clic. Una card que no es interactiva es un bloque de texto — debe diseñarse diferente.

---

### Timeline / Feed

**Qué es.** Una lista cronológica de eventos relacionados con una entidad o con el conjunto de la operación. Cada evento tiene: ícono con color semántico, título, subtítulo/descripción, timestamp relativo.

**Cuándo usar.** Para el feed empresarial de la pantalla de Inicio, el historial de actividad de un equipo, el log de cambios de estado de una orden de trabajo, el registro de movimientos de inventario.

**Cuándo no usar.** Para listas de entidades que no tienen relación cronológica entre sí. Una lista de equipos no es un timeline aunque tenga fecha de creación.

**Regla invariante.** Los eventos del timeline son siempre de solo lectura. La acción nunca ocurre dentro del timeline — ocurre desde una entidad a la que el timeline enlaza.

---

### Quick Actions

**Qué es.** Una grilla de accesos directos a las acciones más frecuentes del rol del usuario. Cada acción tiene: ícono, etiqueta corta (máximo 2 palabras), color de fondo semántico.

**Cuándo usar.** En la pantalla de Inicio (acciones globales del rol), en el footer de una vista de detalle cuando hay acciones frecuentes relacionadas con la entidad actual.

**Cuándo no usar.** Como reemplazo de la navegación principal. Las Quick Actions son atajos a acciones, no secciones del sistema. Un enlace "Ir a equipos" no es una Quick Action — es un ítem de navegación.

**Regla invariante.** Las Quick Actions nunca tienen más de 6 elementos. Si el rol necesita más de 6 acciones frecuentes, la pantalla de Inicio necesita rediseño, no más Quick Actions.

---

### Breadcrumbs

**Qué es.** La representación de la jerarquía de navegación del usuario en forma de ruta clicable. Formato: `Nivel 1 › Nivel 2 › Nivel actual`.

**Cuándo usar.** Siempre que la profundidad de navegación sea mayor a 1 (es decir, en cualquier vista de detalle). Los breadcrumbs son obligatorios en vistas de detalle de equipo (porque tienen jerarquía Plant → Area → Equipment → Subcomponente).

**Cuándo no usar.** En pantallas de primer nivel (Inicio, Dashboard, listas principales). En esas pantallas, la posición en la navegación es evidente por el estado activo del menú lateral.

**Regla invariante.** El último nivel de los breadcrumbs nunca es un enlace — es el nombre de la entidad actual. El penúltimo nivel siempre lleva a la lista del mismo tipo de entidad.

---

### Tabs

**Qué es.** Un selector de secciones de contenido dentro de la misma entidad. En desktop: anclajes de scroll con indicador activo en la parte inferior. En móvil: selector excluyente que muestra una sección a la vez.

**Cuándo usar.** En vistas de detalle de entidad cuando hay múltiples categorías de información relacionada (ej: Información general, Órdenes de trabajo, Historial, Componentes, Documentos). El mínimo para usar tabs es 3 secciones. Con menos, el scroll simple es preferible.

**Cuándo no usar.** Para separar pasos de un formulario (usar wizard/stepper), para filtrar listas (usar chips de filtro), para cambiar entre entidades distintas (usar navegación).

**Regla invariante.** Las tabs no tienen scroll horizontal en desktop. Si hay más tabs de las que caben en el ancho de la pantalla, hay demasiadas tabs — se deben consolidar secciones.

---

### Context Banners

**Qué es.** Una tarjeta de fondo `bg-indigo-50` con borde `border-indigo-100` que muestra información de una entidad relacionada con la que se está viendo. Incluye un enlace "Ver →" para navegar a esa entidad.

**Cuándo usar.** Para mostrar la entidad relacionada más relevante sin obligar al usuario a navegar: el equipo de una OT, el plan de mantenimiento origen de una OT, el equipo padre de un subcomponente.

**Cuándo no usar.** Para múltiples entidades relacionadas (eso corresponde a una lista dentro de una tab). Para información que no requiere navegación (eso es texto informativo simple).

**Regla invariante.** El Context Banner siempre tiene exactamente un enlace de acción. Nunca dos. Si hay dos entidades relacionadas igualmente importantes, ambas merecen su propio banner — uno debajo del otro, en orden de relevancia.

---

### Empty States

**Qué es.** El estado visual de una sección o lista cuando no tiene contenido. Incluye: ícono representativo, mensaje explicativo (por qué está vacío), acción sugerida cuando aplica.

**Cuándo usar.** En toda sección que puede tener cero elementos: listas de OTs, historial de fallas, feed de actividad, lista de repuestos de una OT.

**Cuándo no usar.** No existe un contexto donde no usar empty states. Si una sección puede estar vacía, debe tener un empty state diseñado.

**Regla invariante.** El mensaje de un empty state nunca es solo "Sin resultados" o "No hay datos". Siempre explica la razón de la ausencia (aún no existen, no coinciden con el filtro actual, aún no se han registrado) y, cuando es posible, ofrece la acción para crearlos.

---

### Detail View

**Qué es.** La vista de perfil completo de una entidad. Estructura: Hero (sticky) + KPI Strip + Tabs o Secciones de contenido.

**Cuándo usar.** Para todo equipo, orden de trabajo, solicitud, plan de mantenimiento, proveedor, repuesto — cualquier entidad con suficiente información para merecer su propia pantalla.

**Regla invariante.** El Hero es siempre lo primero visible, siempre sticky, y siempre incluye la acción primaria disponible para esa entidad en ese momento. Una Detail View sin acción primaria en el Hero obliga al usuario a scrollear para encontrar qué puede hacer.

---

### Master-Detail

**Qué es.** El patrón de lista (master) que abre el perfil de un elemento (detail) sin perder el contexto de la lista. Implementado como navegación a una nueva ruta con parámetro de retorno.

**Cuándo usar.** Para todas las listas de entidades. El clic en un elemento de la lista siempre navega a la Detail View del elemento — nunca expande en la misma fila.

**Regla invariante.** Al volver del Detail al Master, el usuario regresa a la misma posición de scroll y con el mismo estado de filtros que tenía antes de hacer clic. La lista no se reinicia.

---

## Errores que Fronda nunca cometerá

Estos son los antipatrones. No son hipotéticos — son errores que el software de mantenimiento tradicional comete con regularidad y que definen exactamente lo que Fronda no es.

---

**CRUD sin contexto.** Un formulario de creación o edición que se abre en blanco, sin referencia a la entidad a la que pertenece el registro nuevo. El técnico que crea una orden de trabajo desde la ficha de un equipo no debería llegar a un formulario vacío — debería llegar a un formulario con el equipo ya seleccionado.

**KPIs antes de acciones.** Una pantalla principal que muestra un gráfico de tendencia del último mes como elemento central, enterrando al final los avisos que requieren atención hoy. Los KPIs pertenecen al módulo de análisis, no a la pantalla operacional.

**Módulos como islas.** Hacer clic en "Ver equipo" desde una orden de trabajo y llegar a la ficha del equipo sin ninguna forma de volver a la orden. La navegación entre entidades relacionadas no debe ser un callejón sin salida.

**Colores decorativos.** Usar rojo en un encabezado de sección porque "se ve más fuerte". Usar ámbar en un botón porque "amarillo es positivo". Los colores de Fronda son semánticos. Usarlos por estética destruye el lenguaje visual del sistema.

**Botones ambiguos.** Tener "Guardar", "Actualizar" y "Aplicar" en distintas partes del sistema para la misma acción. El vocabulario de las acciones debe ser consistente: una sola palabra para cada tipo de acción, usada siempre de la misma manera.

**Pérdida de posición.** Volver de una Detail View a una lista de 200 elementos que se reinicia al tope. El usuario había scrolleado hasta el elemento 87 — ahora tiene que volver a buscar.

**Tablas sin jerarquía.** Una lista de órdenes donde todas las columnas tienen el mismo peso visual: número de OT, equipo, técnico, fecha de creación, fecha estimada, prioridad, estado, tipo — todo igual de pequeño, todo igual de gris. La información sin jerarquía no ayuda al usuario a decidir qué leer primero.

**Confirmaciones en cascada.** "¿Está seguro de que desea cerrar esta orden?" → "Esta acción no se puede deshacer. ¿Confirmar?" → "Ingrese su contraseña para confirmar." Para una acción cotidiana como cerrar una OT, esto es burocracia digital.

**Loading genérico.** Un spinner que ocupa el centro de la pantalla mientras cargan los datos. Sin forma. Sin estructura. El usuario no sabe qué viene.

---

## Checklist UX

Antes de aprobar cualquier pantalla, flujo o componente nuevo, responder estas ocho preguntas. Si alguna respuesta es "no" o "no sé", la pantalla no está lista.

---

**¿Existe contexto?**
El usuario sabe exactamente dónde está y qué está viendo. Si tomara una captura de pantalla de esta vista y la mostrara a alguien sin contexto previo, esa persona podría explicar de qué se trata.

**¿Existe acción?**
Hay al menos una acción clara y disponible en esta pantalla. El usuario sabe qué puede hacer aquí. Si la pantalla es de solo lectura, eso también está comunicado claramente.

**¿Existe jerarquía?**
Lo más importante de esta pantalla es visualmente prominente. El usuario no necesita leer todo para saber dónde mirar primero.

**¿Existe navegación?**
El usuario puede saber cómo llegó aquí y cómo regresar. Los breadcrumbs están presentes si la profundidad es mayor a 1. El estado activo en el menú lateral está correcto.

**¿Existe enseñanza?**
Si alguna sección de esta pantalla puede estar vacía, hay un empty state diseñado. Ese empty state explica por qué está vacío y qué puede hacer el usuario al respecto.

**¿Existe consistencia?**
Los colores, la tipografía, el espaciado y los componentes usados son coherentes con el resto del sistema. Si algo nuevo fue introducido, tiene una razón justificada que puede explicarse.

**¿Existe accesibilidad?**
Esta pantalla puede usarse en móvil. Puede usarse en condiciones de luz directa. Los elementos interactivos tienen un tamaño mínimo de 44×44px. El contraste de texto cumple el mínimo de la pantalla con mayor exposición solar esperada.

**¿Existe propósito?**
Cada elemento de esta pantalla sirve para algo. Si se removiera un elemento, se perdería algo. Si no se perdería nada, el elemento debe eliminarse.

---

### Ideas clave

- Fronda diseña primero desde el campo, no desde el escritorio del gerente.
- Los 12 principios no son aspiraciones — son restricciones. Violar uno requiere justificación explícita.
- Los colores son semánticos y estables. Rojo siempre es peligro. Emerald siempre es salud. Nunca se usan por estética.
- Las tablas nunca son el punto de entrada — son el resultado de una búsqueda o filtro.
- Cada estado vacío es una oportunidad de enseñar. Nunca es un espacio en blanco.
- El checklist de 8 preguntas es el criterio de aprobación de cualquier pantalla nueva.
- Estos principios deben resistir cinco años de evolución del producto. Si una decisión los viola, la decisión está mal — no los principios.

---

[← Capítulo 3: Filosofía de Producto](../03-Product-Philosophy.md) · [Índice](../README.md) · [Capítulo 6 →](06-Roles-y-Audiencias.md)
