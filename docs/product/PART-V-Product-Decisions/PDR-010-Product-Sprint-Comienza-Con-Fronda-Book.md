# PDR-010 — Todo Product Sprint comienza revisando The Fronda Book

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Junio 2026 |
| **Responsables** | Alexander Lopez (CPO) |

---

## Contexto

Una vez establecido en [PDR-009](PDR-009-Fronda-Book-Gobierna-El-Producto.md) que The Fronda Book es la autoridad máxima sobre el producto, la pregunta inmediata fue operativa: ¿cuándo y cómo se consulta el libro en la práctica?

La respuesta teórica — "siempre que sea relevante" — no funciona. Una norma que se aplica cuando parece relevante es una norma que se aplica cuando hay tiempo, cuando alguien la recuerda, o cuando el problema ya surgió. Eso no es un proceso, es una intención.

La experiencia del Sprint DS-1 lo confirmó. Ese sprint existió precisamente porque durante sprints anteriores nadie había consultado los principios del libro antes de tomar decisiones de diseño. Las decisiones habían sido razonables en aislamiento. El problema era la acumulación de decisiones razonables pero no coordinadas.

La solución no es confiar en la disciplina individual. Es hacer de la consulta del libro un paso formal y obligatorio del proceso de sprint — algo que sucede antes de empezar, no cuando hay un problema.

---

## Problema

**Una norma de proceso que no tiene un momento de ejecución definido no tiene enforcement real — solo aspiracional.**

"Consultar The Fronda Book" es una buena intención. "El Product Sprint comienza con la revisión de los capítulos relevantes del libro" es un proceso.

La diferencia es que el proceso tiene un momento claro (el inicio del sprint), un responsable claro (el CPO o quien lidera el sprint), y un output verificable (los capítulos consultados quedan registrados en la descripción del sprint). La intención no tiene ninguno de los tres.

---

## Alternativas consideradas

**Alternativa A — Consultar el libro solo cuando hay dudas de diseño durante el sprint.**
La consulta es reactiva: si surge una pregunta de diseño que no tiene respuesta obvia, se consulta el libro. El problema: la mayor parte de las desviaciones de diseño no surgen como preguntas — surgen como decisiones que parecen obvias en el momento y que solo en retrospectiva se reconocen como inconsistentes con los principios.

**Alternativa B — Consultar el libro como paso formal al inicio de cada Product Sprint.**
Antes de escribir cualquier tarea de diseño o implementación, el equipo revisa los capítulos del libro relevantes para el sprint. Los capítulos relevantes se identifican por los módulos y flujos que se van a tocar. Si el sprint toca el módulo de órdenes de trabajo, se revisa el capítulo de UX Principles y los patrones que aplican a esa pantalla. Si el sprint introduce un nuevo flujo, se revisa la sección de anti-patrones.

**Alternativa C — Revisión completa del libro al inicio de cada sprint, sin importar el contenido del sprint.**
Leer el libro completo cada sprint garantiza que nada se pierde, pero es una inversión de tiempo que no escala cuando el libro crece. Un libro de veinte capítulos no puede releerse completo cada dos semanas.

---

## Decisión

Se adoptó la **Alternativa B**.

El proceso establecido para cada Product Sprint es:

1. **Antes del inicio del sprint** (en la sesión de planificación), el responsable de producto identifica los capítulos del libro relevantes para el trabajo planeado.
   - Sprints de nuevas pantallas o módulos: revisar PART II (UX Principles + Pattern Library).
   - Sprints de cambios a flujos existentes: revisar los PDRs relevantes para esos flujos.
   - Sprints que introducen nuevas entidades o relaciones: revisar las secciones de arquitectura de información del libro.

2. Los capítulos consultados se mencionan explícitamente en la descripción del sprint o en el ticket principal. No como formalismo — como registro que permite auditar si una decisión tomada durante el sprint contradice lo que el libro decía al empezarlo.

3. Si durante la planificación se detecta que el sprint requiere hacer algo que contradice un principio del libro, hay dos opciones:
   - Cambiar el enfoque del sprint para alinearlo con el libro.
   - Crear un PDR que documente y justifique la excepción antes de proceder.

No hay una tercera opción. No existe "lo hacemos así por ahora y después lo documentamos".

---

## Consecuencias

**Ventajas**

- La consulta del libro deja de ser opcional en la práctica. Es el primer paso formal del proceso.
- Las decisiones de diseño tomadas durante el sprint tienen trazabilidad hacia los principios del libro. Cuando algo es cuestionado en revisión, se puede responder "esto respeta el principio X del capítulo 5" o "está documentado como excepción en el PDR-XXX".
- El proceso crea presión orgánica para mantener el libro actualizado: si el libro no menciona algo que el sprint necesita resolver, se hace evidente al inicio — no al final.

**Desventajas**

- Agrega un paso al inicio de cada sprint. En sprints con mucha presión de tiempo, este paso puede sentirse como overhead antes de empezar "el trabajo real".
- Si el libro está desactualizado (que puede ocurrir), la revisión inicial puede consumir tiempo en discutir si lo que dice el libro sigue siendo válido — una conversación útil pero no siempre oportuna al inicio de un sprint.

---

## Cuándo revisar nuevamente

Revisar cuando:

- Se adopte una metodología de gestión de sprints diferente que cambie la estructura de la planificación.
- El libro crezca a un tamaño en que la identificación de capítulos relevantes requiera más tiempo del razonable en una sesión de planificación.

---

## Referencias

- [The Fronda Book — Prólogo](../00-README.md)
- [The Fronda Book — Convenciones editoriales](../00-Convenciones.md)
- [PDR-009 — The Fronda Book gobierna el producto por encima del código](PDR-009-Fronda-Book-Gobierna-El-Producto.md)
- Sprint DS-1 (junio 2026) — Product Consistency Sprint, caso que motivó este proceso

---

[← PDR-009](PDR-009-Fronda-Book-Gobierna-El-Producto.md) · [README](README.md)
