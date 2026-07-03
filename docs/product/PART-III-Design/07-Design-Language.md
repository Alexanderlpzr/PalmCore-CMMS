---

`PARTE III — DESIGN`  `CAPÍTULO 7`

**Tiempo de lectura:** 35 minutos · **Objetivo:** Establecer la identidad visual oficial de Fronda — su personalidad, sus principios, su lenguaje cromático, tipográfico, compositivo y de movimiento — de modo que cualquier persona que diseñe para Fronda, sin haber visto una sola pantalla anterior, produzca algo que parezca inequívocamente Fronda.

> *"El diseño industrial no es diseño menos hermoso. Es diseño más honesto."*
> — Fronda

---

# The Fronda Design Language

---

## I. ¿Qué es un Design Language?

Un sistema de componentes te dice qué piezas existen. Un design language te dice qué eres.

La diferencia es fundamental. Un catálogo de componentes es una lista de materiales de construcción — ladrillos, vigas, ventanas. Un design language es la arquitectura que decide qué tipo de edificio construyes con esos materiales, por qué, y qué sensación debe producir quien lo habita.

Fronda tiene un design language porque el diseño de una pantalla industrial no puede reducirse a elegir los componentes correctos. Tiene que transmitir algo: que el sistema entiende dónde está el usuario, que lo respeta, que no le exige más de lo necesario. Esa transmisión no ocurre accidentalmente. Ocurre cuando hay decisiones visuales deliberadas, consistentes, y documentadas.

Este capítulo es esa documentación.

---

### Por qué el software industrial también puede ser hermoso

Existe una asunción implícita en el desarrollo de software industrial: que la complejidad del dominio justifica la complejidad de la interfaz. Que un sistema que gestiona equipos con treinta parámetros necesita mostrar los treinta al mismo tiempo. Que los usuarios industriales, acostumbrados a operar maquinaria compleja, toleran —y tal vez prefieren— interfaces densas.

Es una asunción equivocada.

El técnico que opera una turbina de alta presión no quiere una pantalla con la misma densidad informativa que el panel de la turbina. Quiere una pantalla que le diga, en el menor tiempo posible, qué tiene que hacer. La complejidad del dominio es razón para mayor claridad en la interfaz, no para menor.

Lo que llamamos "belleza" en una interfaz de trabajo no es decoración. Es precisión: la sensación de que nada sobra y nada falta. De que la pantalla fue diseñada por alguien que entendió el problema, no por alguien que incluyó todas las opciones posibles para cubrirse las espaldas.

Esa precisión es alcanzable. Es exigente, pero alcanzable. Y cuando se logra, el usuario lo percibe aunque no sepa nombrarlo: la app "funciona bien", "es fácil", "se entiende sola". Ese es el estándar de Fronda.

---

### Las emociones que Fronda debe transmitir

Cuando alguien abre Fronda por primera vez —en escritorio o en el campo—, hay emociones que deberían surgir naturalmente. No después de recibir formación. No después de leer un manual. Inmediatamente, en los primeros tres segundos.

**Calma.** No urgencia, no alarma, no sobrecarga. Incluso cuando hay alertas críticas activas, la interfaz no grita. Organiza. El usuario que llega bajo presión necesita que la pantalla lo ayude a pensar, no que compita con el caos que ya tiene.

**Competencia.** La sensación de que el sistema sabe lo que hace. Que las decisiones de diseño fueron tomadas por alguien que entiende el problema. Que no habrá sorpresas desagradables escondidas a dos clicks de distancia.

**Control.** El usuario sabe dónde está, sabe adónde puede ir, sabe qué pasará si hace click. Nada ocurre sin que él lo haya iniciado. La interfaz no actúa de manera autónoma sobre su trabajo.

**Respeto.** El sistema asume que el usuario sabe lo que hace. No lo sobre-explica. No le pide confirmación de cosas que son obvias. No le muestra información que no necesita con la esperanza de que algún día la necesite.

Estas cuatro emociones son el criterio emocional del design language de Fronda. Si una decisión visual las fortalece, es correcta. Si las debilita, está mal.

---

## II. Personalidad del producto

La personalidad de Fronda es un conjunto de rasgos que se manifiestan en cada decisión visual. No son aspiraciones abstractas — son restricciones concretas con consecuencias visibles en la interfaz.

---

### Rasgos que definen a Fronda

**Confiable**

Confiable significa predecible. El usuario que aprendió cómo funciona la pantalla de una orden de trabajo puede predecir cómo funciona la pantalla de un equipo, porque los patrones son los mismos. Confiable significa también que el sistema no miente: si algo está cargando, lo dice; si algo falló, lo dice; si no hay datos, lo dice. Nunca muestra datos desactualizados como si fueran actuales.

En la interfaz se manifiesta como: consistencia estructural entre pantallas similares, estados de carga explícitos (skeleton loaders que replican la forma del contenido real), mensajes de error directos, y navegación predecible.

Lo que evita: estados ambiguos, pantallas que cambian su estructura entre visitas, mensajes de éxito cuando la operación está pendiente de confirmación del servidor.

---

**Calmo**

Calmo no significa inerte. Significa que la interfaz no genera ansiedad. El color rojo existe en Fronda, pero no aparece a menos que algo realmente requiera atención inmediata. Las transiciones ocurren, pero no son llamativas. El espacio en blanco existe y no se llena por inercia.

En la interfaz se manifiesta como: jerarquía visual clara que guía el ojo sin empujarlo, uso contenido del color semántico (el rojo existe pero es infrecuente), espacio entre elementos que permite a la vista descansar entre grupos de información.

Lo que evita: badges de notificación en elementos que no tienen notificaciones relevantes, bordes decorativos, sombras que compiten entre sí, múltiples colores vivos en la misma sección.

---

**Preciso**

Preciso significa que cada elemento tiene una razón para estar donde está. Los labels son exactos, no aproximados. Los números muestran la unidad. Las fechas muestran el contexto necesario (el día de la semana si importa, solo la hora si es reciente). Los íconos corresponden sin ambigüedad al concepto que representan.

En la interfaz se manifiesta como: etiquetas en mayúsculas para categorías (nunca en minúsculas sin capitalizar para identificadores técnicos), fechas con el nivel de detalle apropiado al contexto, valores numéricos con sus unidades explícitas, íconos que no requieren tooltip para entenderse.

Lo que evita: labels genéricos ("Info", "Datos", "Ver más"), valores sin unidades, fechas sin contexto ("hace 3 días" no es preciso cuando importa la fecha exacta).

---

**Profesional**

Profesional no es sinónimo de frío. Significa que la interfaz tiene la madurez de un producto construido con cuidado para personas que hacen trabajo serio. El saludo del Home usa el nombre del usuario y es genuinamente cálido. Pero ese calor tiene límites: la interfaz no bromea, no usa lenguaje coloquial donde se esperaría formalidad, no introduce elementos decorativos que distraigan.

En la interfaz se manifiesta como: tipografía consistente con la jerarquía (nunca tres tamaños de texto del mismo nivel de importancia), espaciado que da dignidad a la información, terminología que respeta el lenguaje del dominio (se dice "Orden de Trabajo", no "ticket").

Lo que evita: emojis en la interfaz principal, tonos de voz inconsistentes entre pantallas, diseño que parece una app de consumo cuando debería parecer una herramienta de trabajo.

---

**Humano**

Humano significa que detrás de la interfaz hay alguien que pensó en el usuario como persona, no como operador de un sistema. El saludo del Home sabe tu nombre y te dice buenos días. El estado vacío de una lista no dice "No se encontraron registros." — dice algo que reconoce el contexto. Los mensajes de error no tienen código de HTTP.

En la interfaz se manifiesta como: lenguaje en primera persona cuando el contexto es personal, mensajes de estado vacío que orientan en lugar de solo informar, feedback de carga que sugiere que el sistema está trabajando para el usuario (no que el usuario está esperando al sistema).

Lo que evita: mensajes técnicos en la UI ("Error 422: Unprocessable Entity"), etiquetas en inglés técnico en una interfaz en español, interacciones que tratan al usuario como si no necesitara saber qué está pasando.

---

### Lo que Fronda no es

Estos rasgos son el lado positivo de la personalidad. Pero la personalidad también se define por lo que excluye.

**Fronda nunca es agresivo.** La urgencia no se comunica haciendo la interfaz ruidosa — se comunica organizando la información para que lo urgente aparezca primero. Nunca hay elementos parpadeantes, colores saturados compitiendo entre sí, o botones que parecen urgentes aunque la acción no lo sea.

**Fronda nunca es ruidoso.** Cada elemento que no añade información es ruido. Los separadores decorativos, los gradientes sin función, los íconos repetidos en el mismo contexto, los labels que dicen lo que el contexto ya hace evidente — todo es ruido que se elimina.

**Fronda nunca es infantil.** La claridad no requiere simplificación patronizante. Fronda puede ser simple y aun así tratarte como adulto que sabe lo que hace.

**Fronda nunca es decorativo.** La interfaz no tiene elementos cuyo propósito sea "verse bien". Todo tiene propósito funcional o no está.

**Fronda nunca es sobrecargado.** Si una pantalla requiere scroll para ver la información más importante del flujo, la pantalla está mal diseñada. Lo importante va primero, siempre.

---

## III. Principios visuales

Los principios visuales de Fronda son las reglas que convierten la personalidad del producto en decisiones de diseño concretas.

---

### 1. La información respira

Las pantallas de Fronda nunca llenan todo el espacio disponible. El espacio en blanco no es espacio desperdiciado — es el elemento que permite al usuario procesar la información sin fatiga.

**Ejemplo:** La página de detalle de un equipo agrupa la información en bloques distintos con espacio entre ellos: primero la identidad (foto, código, nombre, badges), luego las métricas (KPI strip), luego las secciones de contenido (tabs). Cada grupo puede leerse de manera independiente.

**Contraejemplo:** Una tabla que muestra veinticinco columnas, donde el usuario tiene que hacer scroll horizontal para ver la información más importante. La información está, pero no respira — está comprimida hasta volverse ilegible.

---

### 2. La acción destaca

En toda pantalla de trabajo, la acción principal es inmediatamente visible sin necesidad de buscarla. No compite con la información — la información existe para contextualizar la acción. La acción es el punto de llegada.

**Ejemplo:** En la pantalla de una Orden de Trabajo, el botón de transición de estado (el paso siguiente en el flujo) es el único elemento con color de fondo sólido en esa zona. Todo lo demás es gris, blanco, o outlined. El ojo no puede ignorarlo.

**Contraejemplo:** Una barra de herramientas con ocho botones del mismo peso visual, donde el usuario tiene que leer todos los labels para encontrar la acción que necesita.

---

### 3. El color informa

Los colores en Fronda no son estéticos. Son semánticos. Cada color tiene un significado específico y ese significado es consistente en toda la interfaz. Un usuario que aprende que el verde significa "operativo" no tendrá que reaprender ese significado en ninguna otra pantalla del sistema.

**Ejemplo:** El badge de estado de un equipo usa emerald cuando el equipo está activo. El badge de una orden completada usa emerald. El indicador de tiempo real en el KPI strip usa emerald. El usuario sabe, sin leer el label, que verde = bien.

**Contraejemplo:** Usar azul como color de estado activo en un módulo y verde en otro, porque "el azul se veía mejor ahí". El color pierde su significado semántico.

---

### 4. La jerarquía guía

La jerarquía visual de una pantalla debe poder leerse en tres segundos. Qué es el título, qué son los datos secundarios, qué es la información de apoyo — debe ser evidente por tamaño, peso y posición, sin necesidad de color.

**Ejemplo:** El nombre de un equipo (`text-lg lg:text-2xl font-bold text-gray-900`) es claramente el elemento principal. El código de identificación (`text-xs font-mono font-bold text-gray-500 uppercase tracking-widest`) es secundario por tamaño y color. La planta y el área son terciarios.

**Contraejemplo:** Un formulario donde los labels y los valores tienen el mismo tamaño, el mismo peso y el mismo color. El usuario tiene que leer todo para entender qué es campo y qué es dato.

---

### 5. El espacio comunica

El espacio entre elementos no es neutral — comunica relación. Los elementos que están juntos pertenecen al mismo grupo. Los grupos que están separados son distintos. Fronda usa el espacio de manera deliberada para organizar la información antes de que el usuario lea una sola palabra.

**Ejemplo:** Los cinco KPIs de una orden de trabajo aparecen como un strip horizontal continuo — separados apenas lo suficiente para distinguirse, pero agrupados como una unidad. El usuario sabe que esos cinco datos forman un conjunto antes de leer cuáles son.

**Contraejemplo:** Datos relacionados distribuidos en distintas partes de la pantalla sin agrupación visual, que obliga al usuario a navegar la pantalla completa para reunir la información que necesita para tomar una decisión.

---

### 6. La consistencia genera confianza

La confianza en un sistema de trabajo se construye por repetición. El usuario que sabe qué esperar de una pantalla puede enfocarse en el trabajo, no en descifrar la interfaz. La consistencia entre pantallas es la fuente de esa confianza.

**Ejemplo:** El patrón de la página de detalle (sticky header → KPI strip → anchor nav → contenido en tabs) se repite en equipos, órdenes de trabajo y solicitudes de mantenimiento. El usuario que aprendió a navegar una ficha puede navegar todas.

**Contraejemplo:** Cada módulo con su propia convención de navegación y estructura de página. El usuario nunca puede transferir lo que aprendió en un módulo a otro.

---

## IV. Sistema cromático

El sistema de color de Fronda tiene seis roles semánticos. No son seis opciones de paleta — son seis significados. Cada pantalla, cada componente, cada estado usa estos roles de manera consistente. No existen colores adicionales para "dar variedad" o "mejorar el diseño". Si un color no corresponde a uno de estos seis roles, no tiene lugar en la interfaz de Fronda.

---

### Los seis roles

**Brand — La identidad de Fronda**

El verde Fronda (emerald, en la misma familia que `success`) es el color que identifica al producto. Aparece en el saludo del nombre del usuario en el Home, en el indicador activo de la navegación, en los links de acción secundaria, en el borde activo del sidebar.

Es el color de "esto es Fronda". No es el color de "esto está bien" (ese es el papel de `success`, aunque comparten familia). La distinción es sutil pero importante: `brand` identifica el producto, `success` informa sobre el estado de una entidad.

Cuándo usarlo: elementos de identidad del producto, acciones primarias en pantallas no operacionales, estado activo de navegación.

Cuándo no usarlo: estados de datos (usar `success`), alertas, acciones destructivas, contexto donde podría confundirse con "completado".

---

**Success — Salud y operatividad**

Emerald. El color que el sistema muestra cuando algo funciona como debe. Un equipo activo, una orden completada, una solicitud aprobada — todos usan el tono emerald. No hay ambigüedad sobre qué significa este color.

El tono de fondo en el panel de escritorio es `bg-emerald-50` con texto `text-emerald-700`. En mobile PWA, el fondo es translúcido: `bg-emerald-500/15` con texto `text-emerald-400`. El mismo significado, adaptado al contexto de luminosidad.

Cuándo usarlo: estado operativo de entidades, confirmación de acciones completadas, indicadores de cumplimiento, métricas positivas.

Cuándo no usarlo: acciones (para eso existe `brand`/`indigo`), contextos donde el usuario podría interpretar "aprobado por el sistema" cuando significa "operativo".

---

**Warning — Atención sin urgencia crítica**

Amber. El color que dice "mira esto, pero no pares todo". Un equipo en mantenimiento, una orden en ejecución, una solicitud en revisión — todos usan amber. También los equipos de alta criticidad que no están en estado de falla activa.

Warning no es peligro. Warning es "este proceso está en curso y requiere atención". Es el color de la transición, del trabajo en progreso.

Cuándo usarlo: estados intermedios (en progreso, en revisión, en espera), criticidad alta sin falla activa, advertencias que no requieren acción inmediata.

Cuándo no usarlo: estados completados (aunque sea una transición importante), elementos que no tienen un estado de proceso activo.

---

**Danger — Crítico y urgente**

Rojo. El color que aparece con moderación y siempre con razón. Un equipo retirado, una orden cancelada, una falla crítica, un equipo de criticidad máxima — todos usan rojo. El rojo en Fronda siempre significa que algo requiere atención inmediata o que algo definitivamente salió mal.

Por eso el rojo es infrecuente. Cuando aparece, tiene peso. Si apareciera en cada pantalla, perdería ese peso. La contención del color rojo es una decisión de diseño deliberada: el sistema lo reserva para cuando realmente importa.

Cuándo usarlo: fallos activos, estados cancelados, criticidad máxima, errores de validación que bloquean el flujo, métricas de downtime.

Cuándo no usarlo: advertencias generales (usar `warning`), información que no requiere acción urgente, elementos decorativos.

---

**Info — Contexto y proceso planificado**

Azul. El color de lo que está planificado, informado, en espera de inicio. Una orden planificada, una solicitud enviada pendiente de revisión, información contextual relevante pero no urgente — todos usan azul.

Info es también el color del contexto relacional en la interfaz: los Context Banners que muestran la entidad padre de lo que el usuario está viendo usan un fondo `bg-indigo-50` con texto índigo — una variación fría del mismo polo semántico.

Cuándo usarlo: estados de planificación, información contextual relevante, procesos en cola, datos informativos que no requieren acción.

Cuándo no usarlo: acciones (para eso existe `indigo` sólido en los botones primarios), estados activos (usar `warning`), estados completados (usar `success`).

---

**Neutral — Inactivo y archivado**

Gris. El color de lo que ya no tiene actividad o relevancia operativa inmediata. Un equipo inactivo, una orden cerrada, un registro archivado — todos usan gris. El gris comunica "existe pero no requiere atención".

Neutral es también el color de la infraestructura visual: separadores, fondos de página, bordes de cards, labels secundarios. El gris es el fondo sobre el que los colores semánticos tienen significado.

Cuándo usarlo: estados terminales sin urgencia (cerrado, archivado, dado de baja), elementos de la infraestructura visual, texto de apoyo y metadata.

Cuándo no usarlo: estados que aún tienen relevancia operativa, información que el usuario necesita destacar para actuar.

---

### Una nota sobre el Índigo

El índigo ocupa una posición especial en el sistema cromático de Fronda. No es uno de los cinco roles semánticos de datos, pero tampoco es un color decorativo.

El índigo es el color de la acción primaria en pantallas operacionales. Los botones de transición de estado de una orden de trabajo — el "Iniciar", el "Completar", el "Verificar" — son `bg-indigo-600`. Esta elección es deliberada: usar el color `brand`/`success` (emerald) en botones de acción crearía confusión semántica con los estados "operativo" y "completado". El índigo es neutro respecto a los significados semánticos, pero tiene suficiente presencia para ser el punto focal de la acción.

El índigo también aparece en los Context Banners (`bg-indigo-50`) como marcador de contexto relacional — no de un estado, sino de una relación entre entidades.

---

### Cómo se combinan

La combinación correcta siempre tiene un color semántico sobre un fondo neutral. Las pantallas de Fronda usan `bg-gray-50` como superficie base, `bg-white` para las cards, y dentro de las cards aparecen los chips de color semántico.

Lo que nunca sucede: dos colores semánticos vivos en el mismo área visual sin jerarquía. Un KPI strip puede tener un chip emerald y un chip rojo — pero sobre fondo blanco, con espacio entre ellos, y cada uno con su label que refuerza el significado.

---

## V. Tipografía

Fronda usa **Instrument Sans** como única familia tipográfica. Una familia, sin excepciones. La variedad se consigue con peso, tamaño y color — no con fuentes distintas.

La elección de Instrument Sans no es arbitraria. Es una fuente diseñada para interfaces: legible en tamaños pequeños, con proporciones que funcionan en densidades de información alta, con un carácter levemente geométrico que comunica precisión sin frialdad.

---

### Jerarquía tipográfica

La jerarquía tipográfica de Fronda tiene cuatro niveles, no más:

**Nivel 1 — Títulos de entidad**
El nombre del equipo, el título de la orden, el saludo del Home. Siempre `font-bold`, en tamaño `text-xl` o `text-2xl`. Color `text-gray-900`. Leading ajustado (`leading-tight`). Nunca en mayúsculas, nunca en un color semántico (la identidad no tiene estado).

**Nivel 2 — Labels de sección y categorías**
Los nombres de las tabs, los encabezados de grupos en pantallas de lista. `font-semibold`, `text-sm` o `text-base`. Color `text-gray-700` o `text-gray-800`. Cuando son identificadores técnicos (códigos, números de OT), usan `font-mono uppercase tracking-widest text-xs text-gray-500`.

**Nivel 3 — Valores y datos**
El contenido de los campos, los datos dentro de las cards, los valores de los KPIs. `text-sm` para la mayoría, `text-lg font-bold` cuando el valor es el punto focal de la pantalla (como en el KPI strip). Color `text-gray-900` para el dato primario, `text-gray-600` para datos secundarios.

**Nivel 4 — Metadata y texto de apoyo**
Fechas relativas, créditos, subtítulos del workspace hero, labels de campos en formularios. `text-xs`. Color `text-gray-400` o `text-gray-500`. Nunca compite con los niveles superiores.

---

### Ritmo y densidad

El ritmo tipográfico de Fronda es deliberadamente espaciado. Las pantallas de trabajo no son artículos de lectura larga — son interfaces de acción rápida. Por eso:

- Los párrafos de descripción usan `leading-relaxed` para ser legibles a poca luz o en pantallas pequeñas.
- Los datos y valores usan `leading-none` o `leading-tight` — son escaneos, no lecturas.
- El espaciado entre grupos de información (`space-y-4`, `space-y-8`) marca las pausas en el ritmo, guiando al ojo de grupo en grupo.

La longitud de línea máxima en el panel de escritorio es `max-w-5xl` — aproximadamente 80-90 caracteres en pantallas grandes. No porque sea la regla tipográfica canónica, sino porque es el ancho que permite leer datos sin que los ojos viajen distancias largas entre el inicio y el fin de una fila.

---

## VI. Composición

La composición en Fronda no se describe en términos de cuadrículas abstractas. Se describe en términos de las situaciones de uso que debe resolver.

---

### La anatomía de la pantalla de trabajo

Toda pantalla de trabajo en Fronda (equipo, orden, solicitud) tiene la misma anatomía en cuatro zonas:

**Zona 1 — Sticky header**
Fondo blanco, sombra suave (`shadow-sm`), posición fija (`sticky top-0 z-20`). Contiene: breadcrumbs (contexto de navegación), identity row (identificación de la entidad), KPI strip (estado en cifras), y anchor nav (acceso a las secciones de la pantalla). Esta zona nunca hace scroll — permanece visible siempre.

**Zona 2 — Context Banner**
Aparece cuando la entidad tiene una entidad padre relevante (el componente hijo de un equipo, la orden de trabajo de un equipo). Fondo `bg-indigo-50`, con la información clave de la entidad relacionada y un enlace "Ver →". Comunica relación sin que el usuario tenga que navegar a buscarla.

**Zona 3 — Contenido en secciones**
El cuerpo de la pantalla, organizado en tabs o secciones ancladas. Cada sección es un grupo temático de información (detalles, historial, componentes relacionados, documentos). Las secciones se cargan bajo demanda — el usuario que no hace click en una tab no paga el costo de cargar su contenido.

**Zona 4 — Estado vacío**
Cuando una sección no tiene datos, no muestra una área en blanco. Muestra un estado vacío: un ícono centrado en un contenedor redondeado `rounded-2xl bg-gray-100`, un título que describe la situación, y ocasionalmente una acción de primer paso. El estado vacío es parte del diseño, no una excepción.

---

### Cards y superficies

Las cards de Fronda tienen una anatomía consistente:

```
bg-white
rounded-2xl
border border-gray-100
shadow-sm
```

No existe `shadow-lg` en la UI de Fronda. La elevación es suave y única — no hay jerarquía de elevación con múltiples niveles de sombra compitiendo. La distinción entre la card y su fondo (`bg-gray-50`) proviene del color de fondo y el borde, no de la sombra.

Los chips de KPI son una variante: `rounded-xl` (ligeramente menos redondeados que las cards principales), sin borde, con fondo de color semántico suave (`bg-emerald-50`, `bg-red-50`, `bg-blue-50`).

---

### Grids y columnas

El sistema de layout de Fronda usa dos anchos:

- **Contenido principal:** `max-w-5xl mx-auto` — usado en todas las pantallas de trabajo para mantener la línea de lectura en un ancho razonable independientemente del tamaño de pantalla.
- **Padding lateral:** `px-4 lg:px-8` — 16px en móvil, 32px en desktop. Nunca menos, para evitar que el contenido toque los bordes en pantallas pequeñas.

Los grids dentro del contenido son adaptativos: `grid-cols-2 lg:grid-cols-5` para el KPI strip, `grid-cols-2 lg:grid-cols-3` para acciones rápidas. El punto de corte no es el breakpoint: es cuántas columnas caben antes de que las celdas sean demasiado estrechas para ser legibles.

---

### Quick Actions

Las Quick Actions son el único lugar en la interfaz donde la estética de la card cede ligeramente ante la necesidad de acción. Son cuadrículas de íconos con label, diseñadas para ser accionadas con el pulgar. En desktop, el hover state les da un fondo suave. En mobile, el active state da feedback táctil inmediato.

Nunca más de 6 Quick Actions visibles sin scroll. Si hay más de 6, se reorganizan por frecuencia de uso.

---

### Timeline y feeds

La línea de tiempo (Activity Timeline en el Home, historial en fichas de entidad) tiene una estructura propia: una línea vertical como eje, bullets como marcadores de eventos, y texto que sigue el ritmo del tiempo (descendente, del más reciente al más antiguo).

La densidad del timeline es intencionalmente baja: cada evento ocupa espacio generoso. No es una tabla de logs — es una narrativa del estado de la operación.

---

## VII. Motion Language

El movimiento en Fronda no es ornamental. Todo movimiento tiene una función: orientar al usuario, confirmar una acción, o comunicar que algo está ocurriendo. Sin función, no hay movimiento.

---

### Las curvas de movimiento

Fronda usa dos curvas de aceleración:

**Standard easing (`cubic-bezier(0.4, 0, 0.2, 1)`):** Para transiciones de elementos que entran y salen del viewport. Es la curva del sidebar en mobile — entra con energía y decelera suavemente. El usuario siente que el sistema respondió a su gesto, no que algo apareció por arte de magia.

**Linear con fade (`ease-in-out`):** Para transiciones de estado de un mismo elemento. El carrusel del Home transiciona con `ease-in-out duration-500` — no está entrando al viewport, está cambiando de contenido.

No existen curvas de rebote (`spring`), curvas de anticipación, o efectos elásticos. Fronda no hace malabares visuales.

---

### Duraciones

- **Transiciones de micro-estado** (hover, focus, active): 150ms implícito de Tailwind. No se anula. Es el tiempo que el usuario necesita para percibir feedback sin sentir retraso.
- **Transiciones de panel y sidebar:** 250ms. Lo suficientemente rápido para sentirse responsivo, lo suficientemente lento para que el usuario pueda seguir el movimiento.
- **Transiciones de contenido** (carrusel, slides): 500ms. El contenido que cambia necesita tiempo suficiente para que el usuario entienda qué cambió.

No hay animaciones superiores a 500ms en la interfaz principal. Las animaciones largas en un contexto de trabajo interrumpen el flujo.

---

### Skeleton loaders

Los skeleton loaders son el momento más honesto del design language: la interfaz admite que no tiene datos todavía y muestra una silueta de lo que vendrá.

La regla de los skeleton loaders en Fronda: **la silueta debe coincidir exactamente con la estructura del contenido que reemplaza**. Un skeleton de una ficha de equipo tiene un bloque del tamaño de la foto, un bloque del tamaño del código, un bloque del tamaño del título, y bloques del tamaño de los badges. Cuando el contenido real aparece, el usuario no tiene que reorientar la vista.

La animación del skeleton es `skeleton-pulse` — opacidad que va de 1 a 0.4 a 1 en 1.5 segundos, con `ease-in-out`. Es suave y lenta. No es distracción — es indicación silenciosa de que algo está sucediendo.

En mobile (dark theme), los skeletons usan `bg-zinc-800 animate-pulse` — el equivalente oscuro que respeta la identidad del tema sin revelar los colores de la superficie de destino.

---

### Microinteracciones

Los hover states de Fronda son sutiles y consistentes:

- Buttons: `transition-colors` con el estado hover definido por una versión ligeramente más oscura del color de fondo.
- Links de navegación: `text-gray-500 hover:text-gray-700` — el texto oscurece, no cambia de color.
- Cards clicables en mobile: `hover:bg-zinc-800/60` (translúcido, no sólido) — el feedback es visible sin ser brusco.

Los active states en mobile son más pronunciados que los hover: `active:bg-zinc-800` (sólido). En un dispositivo táctil, el feedback de "presión" requiere mayor contraste que el de "hover".

---

### Reduced Motion

Toda animación en Fronda debe respetar `prefers-reduced-motion`. Los skeleton loaders, las transiciones de carrusel y las animaciones de sidebar deben desactivarse o reducirse drásticamente cuando el usuario indica preferencia por menos movimiento.

No es un requerimiento de accesibilidad — es un requerimiento de respeto. Hay usuarios para quienes el movimiento en pantalla causa malestar físico. La interfaz no puede ignorarlo.

---

## VIII. Fotografía e ilustraciones

---

### Fotografía de equipo industrial

Las fotografías en Fronda son fotografías de trabajo, no fotografías de campaña. Una foto de un equipo debe mostrar el equipo como es: con su número de inventario visible si lo tiene, en su entorno real, con la iluminación real de la planta.

Las fotografías de equipo nunca deben ser:
- Fotografías de stock de equipo genérico (un compresor de catálogo cuando el sistema registra un compresor específico de esa planta).
- Fotografías retocadas con fondos artificiales.
- Fotografías tomadas para "verse bien" en lugar de para identificar el equipo.

La función de la fotografía de equipo es la identificación. Un técnico nuevo que llega a la planta debe poder mirar la foto en el sistema y reconocer físicamente el equipo. Si la foto no cumple esa función, no cumple ninguna.

Cuando no hay foto, el placeholder es un ícono de herramienta sobre fondo `bg-slate-100`, con `stroke-width 1.5`. No es un mensaje de error — es simplemente la ausencia neutral de una foto.

---

### Iconografía

Fronda usa una única librería de íconos (Lucide-style, líneas, sin relleno). La consistencia de la librería es más importante que cualquier ícono individual.

Los pesos de trazo tienen roles definidos:

- **`stroke-width: 1.5`** — Íconos decorativos y de estado. El ícono de cámara en el placeholder de foto, el ícono de clima en el workspace hero. Presencia suave, sin énfasis.
- **`stroke-width: 2`** — Íconos de acción secundaria y de navegación. Los íconos de los botones del top bar en mobile, los íconos de los chips de Quick Action. Presencia normal.
- **`stroke-width: 2.5`** — Íconos de navegación pura (las flechas de los breadcrumbs, las chevrons de navegación). El mayor peso corresponde a las guías de movimiento.

No hay íconos con relleno (`fill`) excepto en el logo. La UI usa exclusivamente trazos.

---

### Carruseles y contenido visual

Las imágenes en el carrusel institucional del Home siguen un patrón consistente: la imagen ocupa todo el contenedor (`bg-cover bg-center`), con un gradiente sobre ella (`bg-gradient-to-t from-black/60 via-black/10 to-transparent`) que asegura legibilidad del texto en la parte inferior independientemente de la imagen.

El contraste del texto sobre el carrusel nunca depende de la imagen específica — depende del gradiente. Por eso el carrusel siempre funciona con cualquier imagen, sin necesidad de revisar el contraste para cada slide.

---

### Gráficos y visualizaciones de datos

Los gráficos de Fronda (barras, líneas, áreas en el Dashboard Ejecutivo) siguen el sistema cromático semántico. Un gráfico de disponibilidad usa emerald para períodos de operación normal y rojo para períodos de falla. El usuario no necesita leer la leyenda para entender el gráfico — el color lo dice.

No existen gráficos de torta en Fronda. No porque sean estéticamente incorrectos, sino porque son cognitivamente ineficientes para comparaciones de más de tres categorías — que es exactamente el tipo de comparación que el Dashboard requiere.

---

### QR y código de equipos

El QR es el puente entre el mundo físico y Fronda. En la mobile PWA, escanear un QR lleva directamente a la ficha del equipo. El QR en la etiqueta física del equipo es tan parte del design language de Fronda como cualquier elemento de la interfaz digital.

La etiqueta QR de un equipo debe incluir siempre: el código de identificación (`font-mono uppercase`) y el nombre corto del equipo, además del QR. Nunca solo el QR — la etiqueta debe ser legible para un técnico que no tiene el teléfono en mano.

---

## IX. Responsive Philosophy

Fronda no diseña para pantallas. Diseña para situaciones de trabajo.

---

### Las dos situaciones de Fronda

Hay dos situaciones de uso fundamentalmente distintas en el producto:

**Situación 1 — Escritorio, espacio, tiempo**
El supervisor de mantenimiento en su oficina, el gerente revisando el dashboard, el planner programando órdenes para la semana. Tienen una pantalla grande, un teclado, tiempo para leer. La densidad de información es un activo: pueden procesar más datos a la vez.

**Situación 2 — Campo, movimiento, prisa**
El técnico con el teléfono en la mano frente a un equipo fallado. La luz puede ser mala. Tiene los guantes puestos. Necesita llegar a la orden, registrar su trabajo y continuar. La densidad de información es un obstáculo: cada dato innecesario es un segundo que pierde.

El panel de operaciones (ops) fue diseñado para la Situación 1. La mobile PWA fue diseñada para la Situación 2. No son el mismo producto con estilos distintos — son dos interfaces con filosofías distintas que comparten el mismo sistema de datos.

---

### El mismo contenido, diferente prioridad

Cuando un elemento existe en ambas interfaces, la pregunta no es "cómo se ve en mobile" — es "qué importa en este contexto":

En desktop, la lista de órdenes de trabajo muestra: número, título, equipo asignado, técnico, estado, prioridad, fecha planificada, botones de acción. La densidad es manejable con el espacio disponible.

En mobile, la misma lista muestra: título, estado, prioridad. El técnico que llega a su lista de órdenes del día solo necesita identificar la suya y acceder a ella. El resto puede verse dentro de la ficha.

No se diseña "mobile-first" ni "desktop-first". Se diseña "situación-first": qué información necesita este usuario en esta situación para completar su tarea.

---

### Qué permanece y qué desaparece

Permanece siempre: la acción principal, el estado de la entidad, la identidad (nombre, código), la navegación de retorno.

Desaparece en mobile: las columnas secundarias de las listas, las tabs ancladas (se convierten en un scroll con secciones), los botones de acción terciarios (quedan en la ficha, no en la lista), el KPI strip completo de 5 celdas (en mobile se reduce o se omite).

El test: ¿puede el técnico completar su tarea principal —ver sus órdenes del día, abrir la que le corresponde, registrar tiempo y cerrarla— sin tocar ningún elemento que no sea necesario para eso? Si la respuesta es sí, el diseño mobile está bien. Si tiene que navegar a través de información que no necesita, está mal.

---

## X. Dark Mode

La mobile PWA de Fronda opera en dark mode permanente. No es un "tema oscuro opcional" — es la identidad visual de la interfaz de campo.

---

### Por qué dark mode en campo

Los técnicos trabajan en entornos de iluminación variable: talleres bien iluminados, naves industriales con luz mixta, exteriores bajo el sol directo, espacios confinados con poca luz.

En condiciones de alta luminosidad ambiental, una pantalla clara pierde contraste. En condiciones de baja luminosidad, una pantalla clara fatiga más. El dark mode no resuelve ambas situaciones perfectamente, pero reduce la diferencia de contraste entre pantalla y entorno, lo que se traduce en menos fatiga visual durante turnos largos.

Hay además una razón pragmática: el consumo de batería. Los displays OLED muestran negro auténtico apagando píxeles. Una interfaz predominantemente oscura en un OLED consume significativamente menos batería que una interfaz clara — y el técnico en campo no siempre tiene acceso a un cargador.

---

### La paleta oscura de Fronda

La paleta del dark mode usa la escala zinc, no la escala gray:

- Superficie raíz: `bg-zinc-950`
- Header y nav: `bg-zinc-900`
- Cards e información: `bg-zinc-900 border border-zinc-800`
- Divisores: `divide-zinc-800`
- Texto principal: `text-zinc-100`
- Texto secundario: `text-zinc-400`

Zinc tiene un tono levemente más frío (menos azul-morado) que gray, lo que lo hace más legible sobre pantallas con temperatura de color variable. El resultado es una interfaz que se ve calibrada, no simplemente "dark".

---

### Los colores semánticos en dark mode

Los colores semánticos en dark mode son translúcidos sobre fondo zinc, no sólidos:

- `bg-emerald-500/15 text-emerald-400` (no `bg-emerald-100 text-emerald-700`)
- `bg-amber-500/15 text-amber-400`
- `bg-red-500/15 text-red-400`
- `bg-blue-500/15 text-blue-400`

La translucidez mantiene el fondo zinc visible a través del tinte semántico. El efecto es más sutil que el light mode — y debe serlo, porque la atención del técnico en campo no puede ser interrumpida por colores saturados en momentos que no lo requieren.

---

### Lo que nunca sucede en dark mode

Fondos blancos dentro de la interfaz oscura. Mezcla de zinc y gray en la misma pantalla. Colores semánticos sólidos en superficie oscura (el contraste es excesivo). Gradientes de color (solo gradientes de oscuridad, como `from-black/60 to-transparent` en los carruseles). Texto `text-white` puro — siempre `text-zinc-100`.

---

## XI. Accesibilidad

La accesibilidad en Fronda no es cumplimiento. Es la consecuencia natural de diseñar bien.

Un diseño con jerarquía tipográfica clara, colores con contraste suficiente, targets táctiles adecuados, y feedback explícito de estado es accesible no porque cumplió una lista de verificación, sino porque fue diseñado para personas reales en condiciones reales.

---

### Contraste

El contraste en Fronda sigue el criterio de la peor condición esperada, no la condición ideal. Un técnico con el teléfono bajo el sol directo experimenta pérdida de contraste efectivo. El texto `text-zinc-100` sobre `bg-zinc-900` tiene un ratio de contraste que soporta esa pérdida.

En el panel de escritorio, `text-gray-900` sobre `bg-white` y `text-gray-700` sobre `bg-gray-50` cumplen el mínimo WCAG AA para texto normal. El texto de metadata (`text-gray-400` sobre `bg-white`) no siempre cumple para tamaños pequeños — se acepta porque es información de apoyo no crítica, y el texto principal siempre cumple.

---

### Focus y teclado

Todos los elementos interactivos de Fronda tienen un estado focus visible. El focus ring usa `ring-2 ring-accent ring-offset-2`, definido en los design tokens de Flux UI. El usuario que navega con teclado nunca pierde la visibilidad del elemento activo.

La paleta de Quick Actions, la Command Palette, y la navegación del sidebar son completamente navegables con teclado. La Command Palette en particular fue diseñada con la navegación de teclado como caso de uso primario: `↑↓` para navegar, `Enter` para seleccionar, `Esc` para cerrar, `⌘K` para abrir.

---

### Touch targets

El tamaño mínimo de touch target en la mobile PWA es 44×44px. Los botones en el header (`p-2`, 8px de padding sobre un ícono de 20px = 36px más margin) se acercan a ese límite. En la próxima revisión de la PWA, este es uno de los puntos de mejora documentados.

Las filas de lista en mobile son `py-4` (16px de padding vertical cada lado), lo que con el contenido típico da entre 56px y 72px de altura — adecuado para navegación con el pulgar.

---

### Lectores de pantalla

Los labels `aria-label` están presentes en todos los botones que solo contienen íconos. Las secciones del Home usan `aria-label` para dar contexto al lector de pantalla. Los formularios usan etiquetas explícitas, nunca solo placeholder como única descripción del campo.

Las skeletons durante carga no tienen contenido semántico expuesto al lector de pantalla — son presentacionales. El contenido real se anuncia cuando carga.

---

### Colores y daltonismo

El sistema de color de Fronda usa emerald, amber, rojo, azul y gris. Ningún par de estos colores depende exclusivamente del hue para distinguirse — siempre hay diferencia de luminosidad o un elemento de texto/ícono que refuerza el significado. El usuario con deuteranopía (la forma más común de daltonismo, que afecta la distinción rojo-verde) puede distinguir los estados porque emerald es más claro que rojo en la mayoría de los contextos.

Sin embargo, esto no es suficiente. La regla es: el color nunca es el único indicador de estado. El badge de estado siempre tiene también un label de texto. El ícono de una alerta crítica tiene color rojo y forma distinta del ícono de una advertencia.

---

## XII. La prueba Fronda

Antes de que una pantalla sea aprobada para producción, debe pasar la Prueba Fronda: doce preguntas, sin excepciones.

---

**¿Parece Fronda?**
Si alguien que conoce el producto viera una captura de pantalla sin contexto, ¿reconocería que es Fronda? La pantalla usa los patrones establecidos, los colores semánticos correctos, la tipografía correcta, la composición correcta.

**¿Transmite calma?**
¿El primer segundo de ver la pantalla genera claridad o confusión? ¿Hay algún elemento que llame la atención sin necesitarlo? ¿El espacio es suficiente para que la información respire?

**¿La acción es evidente?**
Si el usuario llega a esta pantalla con una tarea específica, ¿puede encontrar la acción sin buscarla? ¿Hay un punto focal claro?

**¿Existe jerarquía?**
¿Puede leerme la pantalla en tres segundos sabiendo cuál es el título, cuáles son los datos principales, cuáles son los secundarios? ¿La jerarquía es visual (tamaño, peso, color) y no solo semántica (lo que dice el texto)?

**¿Respira?**
¿Hay espacio entre los grupos de información? ¿El contenido toca los bordes del contenedor? ¿Los elementos están comprimidos para caber más información de la necesaria?

**¿Usa los colores correctamente?**
¿Cada color semántico usado en esta pantalla corresponde a su rol? ¿El rojo aparece solo cuando algo es crítico? ¿El verde no está siendo usado por estética sino porque algo está operativo?

**¿Puede usarla un técnico cansado?**
Al final de un turno de doce horas, con cansancio visual y mental, ¿puede el técnico completar su tarea en esta pantalla sin equivocarse? ¿Los targets táctiles son suficientemente grandes? ¿El contraste es suficiente?

**¿Podría usarla un gerente durante una reunión?**
En modo distraído, con atención dividida, ¿puede el gerente extraer la información relevante en 10 segundos? ¿La pantalla es densa para él o puede escanearla?

**¿Los estados vacíos están diseñados?**
Si no hay datos, ¿qué ve el usuario? ¿Hay un estado vacío diseñado que oriente? ¿O solo hay un espacio en blanco?

**¿Los estados de carga están diseñados?**
Si los datos tardan en llegar, ¿hay un skeleton que replique la estructura real? ¿O hay un spinner genérico que no da contexto?

**¿Es accesible bajo las peores condiciones esperadas?**
¿El contraste funciona bajo luz solar directa? ¿Los touch targets tienen el tamaño mínimo? ¿Los elementos interactivos tienen `aria-label` si solo tienen ícono? ¿El focus ring es visible?

**¿Sobrevivirá dos años?**
¿Esta pantalla fue diseñada con los principios del libro o con los gustos del momento? ¿Usaría este diseño el mismo razonamiento visual si lo estuviera diseñando hoy por primera vez? ¿Seguirá pareciendo correcto cuando el producto tenga el doble de usuarios?

Si alguna respuesta es "No" — la pantalla no está terminada.

---

### Ideas clave

- El Design Language de Fronda define identidad, no solo apariencia. La apariencia puede cambiar; la identidad debe sobrevivir.
- La personalidad del producto (confiable, calmo, preciso, profesional, humano) no es marketing — es un conjunto de restricciones visuales con consecuencias concretas en cada pantalla.
- Seis roles cromáticos. Ni uno más. El color comunica significado o no comunica nada.
- Instrument Sans. Una familia. La variedad viene del peso, el tamaño y el color — no de las fuentes.
- Dark mode en mobile no es un tema: es la identidad visual del producto en campo.
- La accesibilidad es consecuencia del diseño correcto, no un requisito adicional.
- La Prueba Fronda es el criterio de aprobación de toda pantalla nueva. Doce preguntas. Sin excepciones.
- Este documento gobierna sobre el código. Si el código y el Design Language se contradicen, el código tiene un error de diseño.

---

[← Capítulo 6: Pattern Library](../PART-II-Experience/06-Pattern-Library.md) · [Índice](../README.md)
