---

`PARTE I — IDENTITY`  `CAPÍTULO 3`

**Tiempo de lectura:** 8 minutos · **Objetivo:** Conocer los diez principios concretos que guían cada decisión de diseño y desarrollo en Fronda — con nombre, descripción, ejemplo práctico y razón de fondo.

> *"Cada pantalla que diseñamos le quitó tiempo a alguien. Tenemos que merecerlo."*
> — Fronda

---

# Filosofía de Producto — Fronda

Los principios de esta sección no son aspiraciones generales. Son decisiones de diseño concretas que se aplican cada vez que se construye, modifica o evalúa cualquier parte de Fronda.

Cada principio tiene un nombre, una descripción, un ejemplo práctico, y la razón de fondo.

---

## I. El técnico es el usuario de referencia

**Descripción.** Cuando existe tensión entre la experiencia del técnico de campo y la experiencia del usuario de escritorio, el técnico tiene prioridad. Esto no significa ignorar al gerente o al supervisor — significa que si el flujo de campo está comprometido para dar una mejor vista al gerente, la decisión está equivocada.

**Ejemplo práctico.** La pantalla de cierre de una orden de trabajo en la aplicación móvil no tiene campos opcionales visibles. Los campos obligatorios son tres: causa raíz, descripción del trabajo realizado, firma del técnico. Todo lo demás se puede completar desde el panel de escritorio. El técnico termina su trabajo en campo. El supervisor enriquece el registro desde su escritorio.

**Razón.** El técnico es quien genera los datos. Si el proceso de generación de datos es incómodo, el técnico busca atajos: deja campos en blanco, cierra la orden sin registrar el trabajo real, usa el sistema como validación de lo que ya hizo en papel. Cuando eso ocurre, la integridad del sistema colapsa.

---

## II. La acción antes que la información

**Descripción.** Las pantallas de inicio — la pantalla de Inicio del panel, la pantalla inicial de la app móvil — muestran primero lo que el usuario necesita hacer, no lo que el sistema sabe. Los contadores de información no sustituyen a los accesos directos a la acción.

**Ejemplo práctico.** La pantalla de Inicio muestra "3 órdenes de trabajo vencidas" como un aviso con color de alerta, y ese aviso es un enlace directo a la lista de esas tres órdenes, ya filtradas. No muestra el total de órdenes del mes, ni la evolución semanal, ni ningún dato que no tenga consecuencia inmediata para el usuario que está mirando la pantalla ahora.

**Razón.** La información sin consecuencia es ruido. El usuario que llega a Fronda en la mañana necesita saber qué tiene pendiente, no un resumen de lo que ya ocurrió. Los dashboards analíticos existen para el análisis retroactivo. La pantalla de inicio existe para el momento presente.

---

## III. El contexto viaja con el objeto

**Descripción.** Un equipo, una orden de trabajo, una solicitud — cualquier entidad central del sistema — carga su contexto relevante donde quiera que aparezca. El usuario no debería tener que navegar a tres módulos distintos para entender el estado completo de un equipo.

**Ejemplo práctico.** La vista de detalle de un equipo muestra en pestañas: su estado actual, sus órdenes de trabajo activas, su historial de fallas, sus lecturas de medidor, sus componentes, sus repuestos asociados, y sus indicadores de confiabilidad (MTBF, MTTR, disponibilidad). Todo en un solo lugar. El usuario que llega a la ficha de un equipo tiene todo lo que necesita para tomar una decisión sobre ese equipo.

**Razón.** La fragmentación de la información es la enfermedad principal de los sistemas de gestión complejos. Los módulos están bien para organizar la navegación, pero no deben convertirse en silos. Un sistema donde el técnico tiene que ir al módulo de inventario para saber si hay repuestos disponibles para la orden que está ejecutando en el módulo de mantenimiento es un sistema que interrumpe el flujo de trabajo en lugar de apoyarlo.

---

## IV. Cada número debe poder accionarse

**Descripción.** Ningún KPI o métrica aparece en Fronda sin que el usuario pueda llegar, desde ese número, a los datos que lo componen y a una acción relacionada. Un indicador que solo informa — sin dirección hacia qué hacer — no cumple su función.

**Ejemplo práctico.** El indicador de MTBF de un equipo muestra el valor en horas. Al hacer clic, muestra la lista de fallas que lo calculan, con fechas y duraciones. Al seleccionar una falla, muestra la orden de trabajo correspondiente. Desde la orden de trabajo, se puede crear un nuevo plan de mantenimiento preventivo o asignar una revisión técnica. El número no es el destino — es el punto de partida.

**Razón.** Los gerentes no necesitan más reportes. Necesitan información que los lleve a decisiones. Un tablero lleno de KPIs que el usuario mira, asiente, y cierra no aporta valor operacional. La medición solo tiene sentido cuando genera acción.

---

## V. La pantalla de inicio es el estado del negocio

**Descripción.** Un usuario que llega a Fronda y mira la pantalla de inicio durante treinta segundos debe poder responder: ¿está la operación bajo control? Los avisos que requieren atención, las órdenes vencidas, las solicitudes pendientes de aprobación — todo debe ser visible sin navegar a ningún otro módulo.

**Ejemplo práctico.** El panel de "Avisos importantes" muestra exactamente cuatro tarjetas: órdenes vencidas, solicitudes pendientes, preventivos próximos (7 días), y alertas críticas. Si todos son cero, la pantalla muestra un estado de calma activa. Si alguno tiene número, el número es un enlace que lleva directo a la lista filtrada.

**Razón.** El tiempo de un supervisor o gerente es escaso. Si la primera acción que deben hacer cada mañana es navegar por tres módulos para saber qué necesita atención, el sistema está trabajando en su contra. La pantalla de inicio es la única pantalla que todos los usuarios ven todos los días — es el lugar más valioso del producto y debe usarse como tal.

---

## VI. El código QR es el pasaporte del equipo

**Descripción.** Todo equipo registrado en Fronda tiene un código QR. Ese código, cuando se escanea desde la aplicación móvil, lleva directamente a la ficha completa del equipo: estado, historial, órdenes activas, y las acciones disponibles para el rol del técnico que está escaneando. No requiere buscar, no requiere recordar un código de activo.

**Ejemplo práctico.** Un técnico llega a un compresor que está sonando diferente. Escanea el código QR en la placa del equipo. Ve inmediatamente: el estado actual del equipo (activo / en mantenimiento), la última orden de trabajo cerrada hace 47 días con una nota sobre el mismo síntoma, y el técnico que la ejecutó. Crea un reporte de falla desde la misma pantalla. Todo sin volver al panel de escritorio.

**Razón.** La barrera de entrada al registro de información debe ser mínima en campo. Si el técnico tiene que recordar un código de activo, navegar por un menú, o usar el buscador con guantes puestos, no lo va a hacer. El QR elimina esa fricción completamente — el equipo se identifica a sí mismo.

---

## VII. La simplicidad de una pantalla es inversamente proporcional a su importancia

**Descripción.** Las pantallas más críticas del sistema — las que el usuario usa en condiciones de urgencia o con frecuencia diaria — son las más simples. Las pantallas de configuración, los reportes complejos, los módulos de análisis avanzado pueden tener mayor densidad de información. Las pantallas operacionales no.

**Ejemplo práctico.** El formulario de cierre de una orden de trabajo en móvil tiene cinco campos visibles. El formulario de creación de un plan de mantenimiento programado en el panel de escritorio puede tener veinte campos distribuidos en pasos. La diferencia no es arbitraria — refleja el contexto en que se usan y la urgencia del momento.

**Razón.** La densidad de información tiene un costo cognitivo. Un técnico que cierra cinco órdenes en un turno no debería tener que procesar la misma cantidad de información que un ingeniero de mantenimiento que configura un plan preventivo una vez al mes. Adaptar la complejidad al contexto de uso no es una preferencia de diseño — es una responsabilidad.

---

## VIII. Los módulos se conectan; los flujos no se interrumpen

**Descripción.** Cuando una acción en un módulo genera consecuencias en otro, esas consecuencias son visibles y navegables sin salir del flujo actual. El usuario no debería perder contexto para completar una tarea que involucra múltiples partes del sistema.

**Ejemplo práctico.** Al ejecutar una orden de trabajo que requiere consumir un repuesto, el técnico puede ver el stock disponible del repuesto directamente desde la pantalla de la orden. Si el stock es insuficiente, puede crear una solicitud de reposición sin salir de la orden. Cuando regresa a la orden, su estado está actualizado. El flujo no se interrumpió en ningún momento.

**Razón.** Los flujos de trabajo de mantenimiento no son lineales. Involucran equipos, repuestos, técnicos, supervisores, planes y presupuesto. Un sistema que obliga al usuario a saltar entre módulos sin mantener contexto convierte cada tarea compleja en una serie de interrupciones. El costo no es solo de tiempo — es de errores: el usuario pierde el hilo, omite un paso, o registra información en el lugar equivocado.

---

## IX. Los estados son siempre visibles y siempre actuales

**Descripción.** Toda entidad del sistema — un equipo, una orden de trabajo, una solicitud, un repuesto — muestra su estado actual de manera prominente y sin ambigüedad. El usuario nunca debería tener que hacer inferencias sobre qué está pasando con un registro.

**Ejemplo práctico.** Una orden de trabajo muestra su estado (`Abierta`, `En ejecución`, `Verificada`, `Cerrada`) con un badge visual de color constante en todos los lugares donde aparece: en la lista, en el detalle, en la ficha del equipo relacionado, en el feed de actividad. El color del badge no varía entre contextos. El técnico aprende a reconocer el estado en menos de un segundo.

**Razón.** La ambigüedad sobre el estado de un proceso es una fuente constante de errores operacionales y de comunicación. "¿Ya se cerró esa orden?" no debería ser una pregunta que alguien necesite hacer. Si la respuesta no es visible instantáneamente, el sistema está fallando en su función más básica: representar fielmente el estado de las cosas.

---

## X. Lo que no se puede explicar en una frase no debería existir

**Descripción.** Toda funcionalidad de Fronda debe poder describirse en una sola oración que cualquier persona con conocimiento básico de mantenimiento entienda sin contexto adicional. Si la explicación requiere más de una frase, o requiere conocer el sistema para entender la funcionalidad, es probable que la funcionalidad esté mal definida.

**Ejemplo práctico.** "Planes de mantenimiento programado" → Secuencias de tareas que se asignan automáticamente a los técnicos según un calendario o un contador de uso del equipo. Una frase. Concepto claro. Si la funcionalidad no puede resumirse así, el problema no es de comunicación — es de diseño.

**Razón.** La claridad conceptual precede a la usabilidad. Si el equipo que construye la funcionalidad no puede explicarla en una frase, el usuario que la usa tampoco va a entender cuándo usarla, cómo usarla, o por qué existe. Las funcionalidades confusas se convierten en funcionalidades ignoradas.

---

*Los principios de esta sección se revisan cuando un nuevo módulo se diseña. Si un módulo existente los viola, es candidato a ser rediseñado.*

---

### Ideas clave

- El técnico de campo es el usuario de referencia: si el flujo en campo está comprometido para dar una mejor vista al gerente, la decisión está equivocada.
- Las pantallas muestran primero lo que hay que hacer, no lo que el sistema sabe. La información sin consecuencia es ruido.
- Ningún KPI existe en Fronda si el usuario no puede llegar, desde ese número, a una acción concreta.
- El código QR elimina la barrera de entrada al registro en campo. El equipo se identifica a sí mismo.
- Toda funcionalidad de Fronda puede describirse en una sola oración. Si no puede, el problema es de diseño, no de comunicación.

---

[← Capítulo 2: El Manifiesto](02-Fronda-Manifesto.md) · [Índice](README.md) · [Portada →](README.md)
