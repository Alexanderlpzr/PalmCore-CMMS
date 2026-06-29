# PDR-001 — El Home reemplaza al Dashboard como punto de entrada

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Febrero 2026 |
| **Última revisión** | Junio 2026 (confirmado tras Sprint PX-1) |
| **Responsables** | Alexander Lopez (CPO), Equipo de Diseño |

---

## Contexto

Durante los primeros sprints de Fronda, la pantalla principal del panel operativo era el Dashboard. Tenía sentido en aquel momento: el Dashboard mostraba el estado general de la operación con métricas de órdenes abiertas, disponibilidad promedio y fallas del período. Era la respuesta natural a "¿qué está pasando en la operación?".

A medida que el producto maduró y se agregaron módulos de inventario, mantenimiento preventivo y alertas, el Dashboard se fue llenando de widgets. Para el momento en que se completó el módulo de KPIs en noviembre de 2025, el Dashboard tenía nueve widgets de análisis y cuatro métricas de resumen. Era informativo. No era operacional.

El problema emergió cuando comenzamos a hablar con los primeros usuarios reales. Un supervisor de mantenimiento llegaba al sistema cada mañana y lo primero que veía era un gráfico de tendencia de MTBF del último trimestre. Lo que necesitaba saber era si había órdenes vencidas, solicitudes esperando su aprobación, o preventivos por vencer ese día.

Navegaba el Dashboard durante treinta segundos buscando esa información. No la encontraba. Iba al menú lateral. Hacía tres clics. Llegaba a la lista de órdenes filtrada por vencidas. Recién entonces podía empezar a trabajar.

---

## Problema

El Dashboard como punto de entrada **confunde el modo analítico con el modo operacional**.

El análisis es retrospectivo: responde qué ocurrió, con qué frecuencia, con qué costo. El trabajo operacional es prospectivo: responde qué hay que hacer ahora, qué está vencido, qué requiere aprobación hoy.

Un usuario que llega al sistema a las 7 de la mañana no viene a analizar el trimestre anterior. Viene a coordinar el turno que comienza. El Dashboard respondía preguntas que ese usuario no estaba haciendo.

---

## Alternativas consideradas

**Alternativa A — Mantener el Dashboard como punto de entrada y agregar un panel de "Atención requerida".**
Se evaluó agregar al Dashboard existente una sección de avisos urgentes en la parte superior. El problema: el Dashboard ya tenía nueve widgets. Agregar un décimo no cambiaría la experiencia — simplemente agregaría más contenido a una pantalla ya saturada.

**Alternativa B — Crear una vista de "Bandeja de entrada" separada del Dashboard.**
Similar a una inbox de correo electrónico, con solo los ítems que requieren acción del usuario según su rol. El problema: generaba una nueva sección en el menú sin una metáfora clara. Los usuarios en prueba no entendían qué era una "bandeja de entrada" en el contexto de un sistema de mantenimiento.

**Alternativa C — Reemplazar el Dashboard con una pantalla de Home propositamente diseñada.**
Una pantalla cuyo único propósito es responder: ¿estás al día? ¿Qué requiere tu atención? ¿Cuál es la actividad reciente? El Dashboard analítico permanece disponible en el menú de Análisis, para quien necesita el análisis.

---

## Decisión

Se adoptó la **Alternativa C**.

El Home reemplaza al Dashboard como primera pantalla visible al entrar al panel operativo. El Home tiene un propósito único: orientar al usuario en el contexto operacional del momento presente. No tiene métricas retrospectivas, no tiene gráficas de tendencia, no tiene análisis de período.

El Dashboard analítico permanece en el menú de Análisis, accesible para quien lo necesita. Pero ya no es el punto de entrada del sistema.

El Home se diseñó con la siguiente jerarquía de secciones:
1. Workspace Hero (quién soy, dónde estoy, qué hora es)
2. Attention Cards (qué requiere mi atención ahora)
3. Quick Actions (mis acciones más frecuentes a un toque)
4. Institutional Carousel (comunicación del tenant, condicional)
5. Activity Timeline (qué ocurrió en la operación)

---

## Consecuencias

**Ventajas**

- El usuario llega al trabajo operacional en el primer segundo, sin navegación previa.
- Separa claramente los modos analítico y operacional, que tienen propósitos y frecuencias de uso distintos.
- El Home es personalizable por rol sin que el Dashboard cambie — el Home puede mostrar distintos avisos según el rol del usuario mientras el Dashboard permanece igual para todos.
- Reduce la fricción para el caso de uso más frecuente: el inicio del turno.

**Desventajas**

- El Dashboard perdió visibilidad como pantalla principal. Los usuarios que usaban el Dashboard como punto de entrada necesitan un período de adaptación.
- El menú lateral requirió una sección "Inicio" que antes no existía, lo que agregó un ítem de navegación.
- Duplica parcialmente el contenido: el resumen de órdenes vencidas aparece en el Home (como aviso) y en el Dashboard analítico (como dato histórico). La duplicación es intencional y sirve propósitos distintos, pero puede generar confusión en usuarios nuevos.

---

## Cuándo revisar nuevamente

Este PDR debe revisarse cuando:

- Se detecte que más del 30% de los usuarios navegan directamente al Dashboard al iniciar sesión, lo que indicaría que el Home no está cumpliendo su función.
- Se introduzca un módulo de mantenimiento predictivo que cambie fundamentalmente la naturaleza de la pantalla de inicio (podría justificar un Home diferente por rol o por tipo de operación).
- El volumen de tipos de aviso en el Home supere los cuatro actuales y se necesite priorizar cuáles mantener.

---

## Referencias

- [UX Principles — Principio 4: Los dashboards informan, las pantallas de trabajo ayudan](../PART-II-Experience/05-UX-Principles.md)
- [Pattern Library — Workspace Hero](../PART-II-Experience/06-Pattern-Library.md)
- [Pattern Library — Attention Cards](../PART-II-Experience/06-Pattern-Library.md)
- [PDR-002 — La acción siempre aparece antes que la información](PDR-002-Accion-Antes-Que-Informacion.md)
- [PDR-007 — El Dashboard Ejecutivo es exclusivamente analítico](PDR-007-Dashboard-Ejecutivo-Solo-Analitico.md)
- Sprint PX-1 (junio 2026) — Centro de Inicio, CMS Institucional, Feed Empresarial

---

[← README](README.md) · [PDR-002 →](PDR-002-Accion-Antes-Que-Informacion.md)
