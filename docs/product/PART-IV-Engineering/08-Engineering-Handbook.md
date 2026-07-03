---

`PARTE IV — ENGINEERING`  `CAPÍTULO 8`

**Tiempo de lectura:** 30 minutos · **Objetivo:** Establecer cómo se desarrolla software en Fronda — no qué herramientas se usan, sino cómo se piensa antes de escribir una línea. Cualquier ingeniero que lea este capítulo debe entender qué se espera de él antes de abrir su editor.

> *"Un ingeniero que no puede explicar por qué tomó una decisión está adivinando con confianza."*
> — Fronda

---

# The Fronda Engineering Handbook

---

## I. Desarrollar software en Fronda

En Fronda, la ingeniería no existe para impresionar a otros ingenieros. Existe para que un técnico de mantenimiento industrial pueda registrar el cierre de una orden de trabajo en treinta segundos, desde un teléfono con la pantalla sucia, al final de un turno de doce horas.

Eso no significa que la ingeniería sea simple. Significa que la complejidad que existe en el sistema está ahí porque resuelve un problema real — no porque el ingeniero que la escribió quería demostrar que podía.

Esta distinción es el primer principio de la cultura de ingeniería de Fronda: **el código sirve al producto, y el producto sirve al usuario**. Cuando esa cadena se rompe — cuando el código existe para servir a la arquitectura, o la arquitectura existe para servir a la teoría —, estamos haciendo ingeniería equivocada.

---

### Por qué la ingeniería existe para servir al producto

La pregunta que justifica cada decisión de arquitectura no es "¿es esto elegante?" Es "¿esto hace el producto mejor para alguien que lo usa?"

Hay una tensión permanente entre la ingeniería y el producto. La ingeniería quiere abstracción, generalización, patrones reutilizables. El producto quiere respuestas concretas a problemas concretos, entregadas rápido. Un equipo que resuelve esa tensión siempre a favor de la ingeniería construye sistemas técnicamente correctos que nadie adopta. Un equipo que la resuelve siempre a favor del producto construye soluciones rápidas que se vuelven imposibles de mantener.

La respuesta de Fronda: la ingeniería decide cómo se construye, pero el producto decide qué se construye y por qué. Un arquitecto puede rechazar un enfoque técnico si es insostenible. No puede rechazar una funcionalidad porque "ensambla mal" con la arquitectura ideal. La arquitectura se adapta al producto, no al revés.

---

### Por qué escribir menos código puede ser mejor ingeniería

El código tiene un costo que no aparece en el sprint en que se escribe. Aparece en todos los sprints siguientes: en el tiempo de lectura de quien lo mantiene, en los bugs que genera cuando el contexto cambia, en la fricción que crea cuando hay que modificarlo.

Un ingeniero que escribe cien líneas donde podían ser veinte no entregó más trabajo — entregó más deuda. Un ingeniero que elimina código que ya no hace falta entregó valor real sin escribir una línea nueva.

En Fronda, la métrica de productividad de un sprint no es líneas escritas. Es comportamiento entregado, comportamiento corregido, o deuda eliminada. Las tres tienen el mismo peso.

---

## II. Principios de ingeniería

Los principios de ingeniería de Fronda son las reglas que gobiernan cómo se toman decisiones técnicas. No son dogmas — son la destilación de qué ha funcionado y qué ha fallado en el contexto específico de este producto.

---

### 1. El producto gobierna la arquitectura

**Problema:** Un ingeniero diseña una arquitectura perfectamente simétrica para un dominio de problema que tiene simetría a medias. Cuando el producto evoluciona en direcciones asimétricas, la arquitectura resiste en lugar de adaptarse.

**Principio:** La arquitectura existe para servir al modelo de dominio. El modelo de dominio existe para reflejar la realidad del negocio. Si la realidad del negocio es asimétrica, la arquitectura puede serlo también.

**Ejemplo:** El dominio de Fronda tiene seis subdominios con estructuras distintas — Assets, Maintenance, Inventory, Reliability, Reports, Automation. Cada uno tiene sus propios services, DTOs, enums y contratos. No comparten una capa de abstracción forzada que los haga "parecer iguales". Son distintos porque el problema que resuelven es distinto.

**Anti-patrón:** Crear una clase base `BaseService` que todos los services hereden para "consistencia", cuando los services tienen comportamientos fundamentalmente distintos. La consistencia visual no justifica el acoplamiento real.

---

### 2. La simplicidad gana

**Problema:** Dos implementaciones resuelven el mismo problema. Una es más elegante, más general, más extensible para casos futuros que nadie ha pedido todavía. La otra es directa, legible, y resuelve exactamente el caso que existe hoy.

**Principio:** La implementación directa gana. La generalización prematura es una forma de deuda técnica disfrazada de buen diseño.

**Ejemplo:** El generador de números de Orden de Trabajo (`OT-{AÑO}-{CÓDIGO_EQUIPO}-{SECUENCIA}`) usa una query directa con `lockForUpdate()` y lógica secuencial explícita. No usa un generador de secuencias abstracto reutilizable. Es simple, es claro, resuelve el problema exacto, y tiene un comentario explicando el detalle no obvio del ordenamiento por VARCHAR.

**Anti-patrón:** Abstraer lógica en una clase `SequenceGenerator` porque "podríamos necesitar secuencias para otros modelos". Si el momento llega, se abstrae entonces — no antes.

---

### 3. La claridad es más importante que la inteligencia

**Problema:** Un ingeniero escribe código que demuestra su conocimiento del lenguaje: expresiones compactas, métodos encadenados de seis niveles, patrones avanzados que requieren conocer el framework en profundidad para entenderse. El código funciona. Es difícil de leer.

**Principio:** El código se escribe una vez y se lee docenas de veces. La persona que lo leerá puede no ser quien lo escribió, puede estar bajo presión, puede no conocer el contexto original. La inteligencia que no comunica es ruido.

**Ejemplo:** `WorkOrderStatus::Draft->canTransitionTo(WorkOrderStatus::Planned)` devuelve `true`. Un ingeniero junior puede leer esa línea y entender exactamente qué hace sin conocer la implementación. El nombre de cada case del enum, el nombre del método, y su resultado hablan por sí solos.

**Anti-patrón:** `$s->ct($t, false)`. Correcto e incomprensible.

---

### 4. Cada línea tiene un costo futuro

**Problema:** En la presión de un sprint, se agrega una línea de lógica directamente en un controlador para "resolver rápido". La línea queda. El siguiente sprint agrega otra. En seis meses, el controlador tiene responsabilidades que no debería tener y nadie recuerda por qué.

**Principio:** Antes de escribir una línea, hay una pregunta implícita: ¿en qué capa vive este código? El controlador orquesta. El service ejecuta la lógica de negocio. El model conoce su propia estructura. El DTO transfiere datos entre capas. Romper esas responsabilidades tiene un costo que se paga en el futuro, no en el presente.

**Ejemplo:** El cálculo de KPIs de equipos (MTBF, MTTR, disponibilidad) vive en `EquipmentKpiService`, que devuelve un `EquipmentKpiData` inmutable. El controlador de la API llama al service y devuelve el DTO serializado. Nada del cálculo vive en el controlador. Nada de la presentación vive en el service.

**Anti-patrón:** Un controlador que calcula MTBF directamente porque "solo era una fórmula y era más rápido hacerlo aquí".

---

### 5. La deuda técnica también es deuda de producto

**Problema:** El equipo acepta deuda técnica para cumplir una fecha. La deuda no se registra. En el próximo sprint, otra deuda. En seis meses, cada funcionalidad nueva tarda el doble porque el código resistente acumula fricción invisible.

**Principio:** La deuda técnica que no se registra no existe para el producto — y por eso nunca se paga. Toda deuda aceptada deliberadamente debe registrarse como un ítem explícito de trabajo futuro. No como "idealmente refactorizaríamos esto", sino como un ticket con una descripción del problema y su impacto.

**Ejemplo práctico:** Un workaround en la generación de números de OT tiene un comentario explicando exactamente qué problema resuelve y por qué existe la solución actual. El siguiente ingeniero que lea ese código entiende la situación sin tener que descubrirla.

**Anti-patrón:** Usar `// TODO: mejorar esto` sin contexto. ¿Mejorar qué? ¿Por qué? ¿Cuándo importa hacerlo?

---

### 6. La automatización es parte del desarrollo, no después

**Problema:** Los tests se escriben al final, "cuando hay tiempo". No hay tiempo. Los tests se omiten. El código llega a producción sin verificación de comportamiento. El equipo descubre bugs cuando los usuarios los reportan.

**Principio:** Los tests son parte del desarrollo, no una etapa después. Una funcionalidad sin tests no está terminada. Una funcionalidad con tests que prueban implementación (no comportamiento) tampoco está terminada. El test que vale prueba que el sistema hace lo que el usuario necesita.

**Ejemplo:** `it('returns 403 when a tenant admin tries to access the platform panel')`. El nombre del test describe el comportamiento esperado, no el mecanismo interno. Si el mecanismo cambia pero el comportamiento se preserva, el test sigue siendo válido.

**Anti-patrón:** `it('calls the middleware with the correct parameters')`. Un test que verifica la implementación interna no verifica el comportamiento. Si la implementación cambia, el test falla aunque el comportamiento sea correcto.

---

## III. Cómo nace una funcionalidad

Toda funcionalidad en Fronda sigue el mismo proceso, sin excepciones. No es burocracia — es la garantía de que lo que se implementa vale la pena implementar, y que se implementa bien.

---

**Paso 1 — Idea**

La idea puede venir de cualquier lugar: un usuario, un comercial, un ingeniero, una observación durante el uso. Las ideas no se descartan sin revisión. Pero tampoco se convierten automáticamente en trabajo planificado.

El filtro inicial: ¿esta idea resuelve un problema real para un usuario real? Si la respuesta requiere más de dos oraciones, la idea necesita más investigación antes de avanzar.

---

**Paso 2 — Product Review**

El responsable de producto evalúa la idea en el contexto del producto completo: ¿es coherente con la visión? ¿Sirve al usuario correcto? ¿Compite con algo que ya existe? ¿Es el momento correcto?

El resultado de la Product Review no es "aprobado" o "rechazado". Es "cuándo" y "cómo" — o "por qué no, con qué alternativa".

---

**Paso 3 — The Fronda Book**

Antes de diseñar la funcionalidad, se revisan los capítulos del libro relevantes para el trabajo que se va a hacer. La revisión es un paso formal que se registra en la descripción del sprint. No es opcional.

El libro puede revelar: que la funcionalidad ya tiene un patrón documentado que debe respetarse, que existe un principio UX que limita cómo puede implementarse, o que hay una decisión anterior registrada como PDR que aplica.

---

**Paso 4 — PDR (si aplica)**

Si la funcionalidad requiere tomar una decisión de producto que no tiene precedente — una decisión sobre cómo debe comportarse la interfaz, cuándo debe aparecer algo, qué semántica tiene un concepto — se crea un PDR antes de implementar.

Un PDR no es documentación post-facto. Es la decisión misma, tomada antes de que el código exista. Si el código se escribe antes del PDR, el orden está invertido.

---

**Paso 5 — Arquitectura**

Para funcionalidades que introducen nuevos dominios, nuevas capas, o decisiones estructurales significativas, se hace una revisión de arquitectura antes de implementar. El resultado es un acuerdo sobre la estructura — no una especificación detallada, sino un mapa de qué capa vive dónde y por qué.

Funcionalidades menores dentro de un dominio existente no requieren revisión de arquitectura formal. El dominio ya tiene su estructura — el ingeniero la sigue.

---

**Paso 6 — Implementación**

La implementación sigue la arquitectura acordada. El código sigue los principios de este handbook. Los nombres son descriptivos, las responsabilidades están en la capa correcta, los casos de error están considerados.

La implementación no es el lugar para improvisaciones de diseño de producto. Si durante la implementación surge una pregunta de diseño que no estaba resuelta, el trabajo se detiene y vuelve al Paso 2 — no se toma la decisión de producto en el pull request.

---

**Paso 7 — Testing**

Todo el comportamiento nuevo tiene tests. Los tests de comportamiento verifican que el sistema hace lo que el usuario necesita. Los tests de integración verifican que las capas se comunican correctamente. Los tests de seguridad verifican que el aislamiento de tenant se mantiene.

La regla: si el comportamiento no puede verificarse con un test, o bien el diseño tiene un problema de testabilidad (señal de arquitectura incorrecta), o el comportamiento es tan simple que el riesgo de no testearlo es aceptablemente bajo (excepcional).

---

**Paso 8 — Visual QA**

Toda funcionalidad que tiene superficie visual se revisa contra el Design Language antes de considerarse terminada. El revisor verifica la Prueba Fronda del Capítulo 7: los doce criterios de aprobación visual.

El Visual QA no es opcional para funcionalidades con UI. Un ingeniero que considera terminada una funcionalidad sin revisión visual no ha terminado.

---

**Paso 9 — Feature Freeze (si aplica)**

Cuando una funcionalidad alcanza una forma que el equipo considera definitiva — sin cambios previstos en su estructura o comportamiento fundamental — puede declararse Feature Frozen. La declaración se hace con un PDR.

Feature Frozen no significa que el código nunca cambia. Significa que la forma de la funcionalidad no cambia sin una decisión de producto documentada.

---

**Paso 10 — Release**

La funcionalidad llega a producción. El Fronda Book se actualiza si la funcionalidad cambió algo que el libro documenta. La deuda conocida queda registrada como tickets activos.

El release no es el fin del proceso — es el punto en que empieza la observación: ¿los usuarios la usan? ¿Resuelve el problema que pretendía resolver? ¿Produce comportamientos inesperados?

---

## IV. Cómo escribimos código

Este no es un manual de estilo. Es una descripción de cómo pensamos cuando escribimos.

---

### El código explícito es preferido al código inteligente

Explícito significa que el código dice lo que hace. No usa abreviaturas que el lector tiene que decodificar, no depende de efectos secundarios implícitos, no usa convenciones que solo el autor conoce.

`$order->transitionTo(WorkOrderStatus::Completed)` es explícito.
`$order->complete()` asume que el lector sabe que "completar" implica una transición de estado con validaciones.
`$order->s = 'comp'` requiere que el lector conozca la abreviatura y el enum completo.

Los tres funcionan. El primero no requiere ninguna suposición.

---

### Los nombres cuentan la historia

Un nombre de variable, método o clase debería describir lo que representa o hace, no cómo está implementado.

`$isRegisteredForDiscounts` (qué es) vs `$discount` (qué hace internamente).
`calculateAvailabilityPercentage()` (qué calcula) vs `calc()` (que calcule algo).
`EquipmentKpiData` (qué contiene) vs `KpiResult` (demasiado genérico) vs `EquipmentDto` (demasiado técnico).

Cuando nombrar algo es difícil, el problema suele ser que lo que se está nombrando tiene responsabilidades poco claras. El esfuerzo de encontrar un buen nombre es tiempo de diseño, no overhead.

---

### Los métodos son pequeños y tienen una sola razón de cambiar

Un método que hace dos cosas debería ser dos métodos. Un método que hace dos cosas y una de ellas es opcional según un parámetro debería ser reescrito.

La señal de alerta: un método que necesita un comentario para separar "secciones" de su lógica. Si el método tiene secciones, las secciones son métodos.

El tamaño no es el criterio — la responsabilidad sí. Un método puede tener diez líneas y hacer una sola cosa bien definida. Un método puede tener tres líneas y hacer dos cosas mezcladas.

---

### La lógica de dominio vive en el dominio

La máquina de estados de una Orden de Trabajo — qué transiciones son válidas, qué estados son terminales, qué estados permiten edición — vive en `WorkOrderStatus`, el enum del dominio. No en el controlador. No en el service. En el tipo que representa el estado.

Cuando alguien pregunta "¿puede una orden en estado Completed volver a InProgress?", la respuesta está en `WorkOrderStatus::Completed->canTransitionTo(WorkOrderStatus::InProgress)`. No en una condición enterrada en un método de un service. El dominio conoce sus propias reglas.

---

### Los DTOs son inmutables

Cuando los datos cruzan capas — del service al controlador, del controlador a la vista, del evento al listener — viajan en DTOs. Los DTOs son `readonly`: se crean una vez, con todos sus datos, y no cambian.

Un DTO mutable es una fuente de bugs invisibles: alguien modifica el objeto después de crearlo y el cambio afecta a quien lo usa más adelante. `readonly` elimina esa posibilidad por diseño.

---

### La duplicación tiene un umbral

La duplicación de código es un problema cuando dos piezas de código que representan el mismo concepto evolucionan de manera independiente. Cuando eso ocurre, un cambio en el concepto requiere dos modificaciones — y tarde o temprano alguien hace una y olvida la otra.

La duplicación no siempre representa el mismo concepto. Dos funciones que hacen lo mismo pueden estar en capas distintas por razones válidas. El criterio no es "¿es el código idéntico?" sino "¿si el concepto cambia, tengo que cambiar ambos?"

Si la respuesta es sí, la duplicación es un problema. Si no, puede ser código coincidentalmente similar.

---

### La optimización prematura es deuda disfrazada de rendimiento

No se optimiza código que no tiene un problema de rendimiento medido. Se escribe código claro que funciona correctamente, se mide si hay un problema, y si hay un problema se optimiza con evidencia.

La razón es simple: la optimización prematura hace el código más difícil de leer y modificar, y con frecuencia optimiza el lugar equivocado. El cuello de botella raramente está donde el ingeniero intuitivamente lo busca.

---

## V. Testing como diseño

Los tests en Fronda no son una verificación de que el código funciona. Son la especificación de cómo debe comportarse el sistema.

Un test que pasa con una implementación incorrecta es un test mal escrito. Un test que verifica la implementación interna en lugar del comportamiento externo es un test que creará trabajo innecesario cuando la implementación cambie. Un test que no puede ejecutarse de manera repetible y predecible es un test que no puede confiarse.

---

### Test de comportamiento (Feature Tests)

Los tests de comportamiento son la capa más importante de la pirámide de tests en Fronda. Verifican que el sistema hace lo que el usuario necesita, desde la perspectiva del usuario.

`it('returns 403 when a tenant admin tries to access the platform panel')` — Este test no sabe nada sobre cómo se implementa la verificación. Sabe que un usuario con el rol incorrecto debe recibir un 403. Si la implementación cambia de middleware a policy, el test sigue siendo válido.

El nombre del test en Fronda es la especificación de comportamiento. Si el nombre requiere conocer detalles de implementación para entenderse, el test está especificando la implementación, no el comportamiento.

---

### Tests de estado de dominio

Los comportamientos del dominio — máquinas de estado, reglas de negocio, validaciones — tienen sus propios tests que no pasan por HTTP ni por la base de datos cuando no es necesario.

`it('closed is terminal — no transitions allowed')` verifica el comportamiento del enum directamente. No necesita una request HTTP. No necesita una OT en la base de datos. Verifica que el tipo de dominio se comporta como se espera.

Estos tests son el seguro contra la evolución del dominio: cuando alguien agrega un nuevo estado al flujo de OTs, los tests de la máquina de estados fallan inmediatamente si la lógica de transiciones no se actualizó.

---

### Tests de seguridad y aislamiento de tenant

En Fronda, el aislamiento entre tenants es una propiedad de seguridad, no solo de correctitud. Un bug que permite a un usuario de Tenant A ver datos de Tenant B no es un bug de UX — es una violación de seguridad.

Por eso, los tests de seguridad de tenant son una categoría explícita. Verifican que el aislamiento se mantiene en los puntos de la API más críticos: queries de lista, acceso a detalle, operaciones de escritura.

`it('tenant A user cannot read tenant B work orders through the API')` no asume que el sistema es correcto porque el código se ve bien. Lo verifica.

---

### Tests E2E

Los tests end-to-end verifican los flujos completos del usuario en el navegador real: desde que abre la aplicación hasta que completa una tarea. Son más lentos que los tests de feature y más frágiles ante cambios de UI, pero son el único tipo de test que verifica que todos los componentes — backend, frontend, interacciones del usuario — funcionan juntos correctamente.

En Fronda, los tests E2E cubren los flujos críticos, no todos los flujos. Un test E2E del flujo de cierre de una Orden de Trabajo es esencial. Un test E2E de la visualización del perfil de usuario no lo es — ese comportamiento puede verificarse con tests más simples.

---

### Accesibilidad y rendimiento como requisitos testeables

La accesibilidad y el rendimiento no son propiedades que se evalúan "cuando hay tiempo". Son requisitos que pueden expresarse como tests:

- ¿Los elementos interactivos sin texto tienen `aria-label`?
- ¿El tiempo de respuesta del endpoint de lista de OTs bajo carga simulada es menor a 200ms?
- ¿La pantalla de campo de la mobile PWA pasa el score de accesibilidad mínimo?

Cuando un requisito puede expresarse como un test, su cumplimiento deja de depender de que alguien lo recuerde verificar manualmente.

---

## VI. Feature Freeze

Una funcionalidad alcanza Feature Freeze cuando el equipo ha acordado que su forma es definitiva — que los cambios futuros serán de configuración o contenido, no de estructura o comportamiento fundamental.

Feature Freeze existe porque no toda funcionalidad debe estar en evolución permanente. Las funcionalidades en el primer viewport del usuario, las que definen la identidad visual del producto, las que forman la base de la experiencia — estas necesitan estabilidad. Cada cambio a una funcionalidad central tiene un costo de adaptación para los usuarios existentes.

---

### Criterios para declarar Feature Freeze

Una funcionalidad puede declararse Feature Frozen cuando cumple todos los criterios:

**Revisión de The Fronda Book:** El comportamiento de la funcionalidad es consistente con los principios del libro. Si no lo es, no puede freezarse — está mal implementada.

**PDR existente (si aplica):** Si la funcionalidad es una decisión de producto significativa, tiene su PDR. El freeze formaliza esa decisión.

**Cobertura de tests:** El comportamiento principal está cubierto por tests de feature. Los casos de borde documentados tienen sus tests.

**Visual QA aprobado:** La funcionalidad pasó la Prueba Fronda del Capítulo 7.

**Seguridad verificada:** Si la funcionalidad involucra datos sensibles, acceso por roles, o comunicación externa, los tests de seguridad relevantes existen y pasan.

**Rendimiento aceptable:** Bajo la carga esperada para esta funcionalidad, el tiempo de respuesta está dentro de los parámetros aceptables.

---

### Lo que Feature Freeze no significa

Feature Frozen no es código muerto. Significa que la estructura no cambia sin una razón de producto documentada. Los bugs se corrigen. Los textos pueden actualizarse. Los datos que alimentan la funcionalidad pueden cambiar. Lo que no cambia sin un PDR es la forma — el número de elementos, su posición, su semántica, su comportamiento.

---

## VII. Errores que Fronda nunca cometerá

Hay errores que se cometen una vez en la historia de cualquier equipo de software. Los que sobreviven los aprenden y los documentan para que no se repitan.

---

**Programar primero y diseñar después**

Cuando la implementación precede al diseño de producto, el código asume decisiones de UX que no han sido tomadas. El resultado es una funcionalidad que "funciona" pero que resuelve el problema equivocado, o lo resuelve de una manera que no corresponde a cómo el usuario piensa sobre él. Deshacer eso es más caro que haberlo diseñado antes.

En Fronda, el diseño (o al menos la validación contra el Fronda Book) precede siempre a la implementación.

---

**Documentar al final**

La documentación que se escribe al final de un proyecto registra cómo quedó el sistema, no por qué quedó así. Las razones de las decisiones — los contextos, las alternativas consideradas, los trade-offs aceptados — se pierden. Lo que queda es una descripción de hechos sin contexto.

En Fronda, la documentación de decisiones (PDRs) se escribe cuando se toma la decisión, no cuando se termina el sprint.

---

**Aceptar deuda sin registrarla**

Toda deuda técnica aceptada con conciencia es una deuda negociada — se acepta porque el beneficio inmediato supera el costo futuro conocido. Eso es válido. Lo que no es válido es aceptar deuda sin reconocerla, porque entonces no se puede administrar.

En Fronda, la deuda técnica conocida se registra como trabajo futuro explícito, con una descripción del problema y su impacto real.

---

**Agregar funcionalidades por presión comercial sin revisión de producto**

La presión para agregar una funcionalidad "rápido para el cliente X" sin pasar por el proceso de producto produce funcionalidades que no encajan en el modelo, que crean inconsistencias en la experiencia, y que después no pueden removerse sin afectar al cliente que las pidió.

En Fronda, la urgencia comercial no es una razón para saltarse la revisión de producto. Si algo es urgente, se acelera el proceso — no se elimina.

---

**Duplicar lógica en capas distintas**

Cuando la misma regla de negocio vive en dos lugares — en el backend y en el frontend, en el service y en el controlador, en la vista y en el modelo —, eventualmente los dos lugares divergen. Uno se actualiza y el otro no.

En Fronda, la lógica de dominio tiene un lugar canónico. Los otros lugares que necesitan esa lógica la consultan — no la reimplementan.

---

**Ignorar el feedback de los usuarios cuando contradice la arquitectura**

Si los usuarios reportan consistentemente que algo es confuso, difícil de usar, o que no resuelve su problema, la respuesta correcta no es "el sistema es así por razones técnicas". La respuesta es investigar si la razón técnica justifica la fricción del usuario. Frecuentemente no.

En Fronda, el feedback de usuarios es un input de primer orden. La arquitectura se adapta cuando el usuario tiene razón — no al revés.

---

**Mezclar el concepto de "hecho" con "funciona en mi máquina"**

Una funcionalidad está hecha cuando tiene tests, pasó el visual QA, y está en producción. No cuando el código compila. No cuando el feature branch existe. No cuando el desarrollador dice que funciona.

---

## VIII. La ingeniería como cultura

La ingeniería de software no es, en su núcleo, un conjunto de herramientas o técnicas. Es una forma de pensar sobre problemas: con rigor, con evidencia, con conciencia del costo de las decisiones.

Esa forma de pensar es lo que Fronda busca en sus ingenieros. No la familiaridad con un framework específico, no el conocimiento de un patrón de arquitectura particular, no la velocidad de tipeo. La forma de pensar.

---

### Qué significa pensar como un ingeniero de Fronda

Significa preguntarse, antes de escribir código, si la funcionalidad que se va a implementar es la correcta para el usuario. No "¿puedo implementarlo?" sino "¿debería implementarse así?"

Significa rechazar la presión de entregar rápido a expensas de la correctitud. La velocidad que no incluye correctitud no es velocidad — es deuda acelerada.

Significa tener la honestidad de decir "no lo sé" cuando no se sabe, en lugar de tomar una decisión técnica con confianza injustificada. La confianza sin fundamento es la fuente de los bugs más difíciles.

Significa leer el código de otros con la intención de entenderlo, no de juzgarlo. El código que parece confuso tiene frecuentemente una razón — descubrirla antes de cambiarlo es parte del trabajo.

Significa escribir código como si el siguiente lector fuera alguien inteligente que no tiene el contexto que tú tienes ahora.

---

### Qué no es la ingeniería en Fronda

No es producir más código. El ingeniero que escribe más líneas no es más productivo — puede ser más problemático. El código es un costo, no un activo.

No es defender la primera solución que uno propone. Las mejores decisiones técnicas frecuentemente emergen del desacuerdo respetuoso. El ingeniero que cambia de posición ante un argumento mejor no está cediendo — está aprendiendo.

No es resolver problemas en abstracto. Fronda tiene usuarios reales con problemas reales. La ingeniería que no puede trazar una línea desde la decisión técnica hasta el beneficio al usuario está resolviendo el problema equivocado.

No es la acumulación de conocimiento técnico como fin en sí mismo. El conocimiento técnico es un medio para construir cosas que funcionen. Un ingeniero que sabe muchas cosas pero no puede explicar por qué tomó cada decisión está adivinando con vocabulario sofisticado.

---

### El estándar

El estándar en Fronda no es la perfección técnica. Es el criterio de que, en retrospectiva, la decisión tomada fue la más razonable dado el contexto disponible en ese momento.

Eso significa que los errores son aceptables cuando se aprende de ellos. Significa que la deuda es aceptable cuando se reconoce. Significa que el código imperfecto es aceptable cuando funciona correctamente y puede evolucionar.

Lo que no es aceptable es tomar decisiones sin pensar, aceptar deuda sin registrarla, o entregar código sin verificar que hace lo que debe hacer.

Ese es el umbral. Todo lo que esté por encima de él es buen trabajo.

---

### Ideas clave

- La ingeniería existe para servir al producto, que existe para servir al usuario. Cuando esa cadena se rompe, algo está mal.
- El código que se escribe tiene un costo en todos los sprints siguientes. Escribir menos, con más intención, es con frecuencia mejor ingeniería.
- Los nombres, las capas, y las responsabilidades no son decisiones de estilo — son decisiones de diseño con consecuencias en la mantenibilidad.
- Los tests verifican comportamiento, no implementación. Un test que falla cuando la implementación cambia pero el comportamiento no, está verificando lo incorrecto.
- Feature Freeze no es código muerto. Es una declaración de que la forma es correcta y no cambia sin una razón documentada.
- La deuda técnica que no se registra no puede administrarse. Toda deuda aceptada debe ser explícita.
- La ingeniería no es producir código. Es producir mejores decisiones — algunas de las cuales se expresan como código.

---

[← Capítulo 7: Design Language](../PART-III-Design/07-Design-Language.md) · [Índice](../README.md)
