# PDR-009 — The Fronda Book gobierna el producto por encima del código

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Junio 2026 |
| **Responsables** | Alexander Lopez (CPO) |

---

## Contexto

En junio de 2026, con el producto operativo y el equipo creciendo, enfrentamos una pregunta que hasta ese momento habíamos evitado formular explícitamente: ¿qué es la autoridad final sobre cómo se diseña Fronda?

La respuesta implícita había sido: el código. Si algo estaba implementado de una manera, esa era la manera correcta — porque era la que funcionaba, la que tenía tests, la que estaba en producción.

El problema de esa respuesta es que el código puede implementar una mala decisión. Y lo hace frecuentemente. El código es el registro de decisiones pasadas — incluidas las que se tomaron con información incompleta, bajo presión de tiempo, o simplemente de manera incorrecta.

Durante el Sprint DS-1 (Product Consistency Sprint), revisamos catorce pantallas del panel operativo y encontramos inconsistencias que venían de allí: decisiones que se habían tomado localmente, sin referencia a ningún principio más amplio, y que habían quedado implementadas. El código era internamente consistente. La experiencia no lo era.

The Fronda Book nació como respuesta a ese problema. Pero escribir el libro no era suficiente — necesitábamos declarar formalmente cuál es su rol en relación con el código.

---

## Problema

**Un equipo que consulta el código para saber "cómo se hace algo en Fronda" aprende qué se hizo, no qué se debería hacer.**

La distinción importa. El código registra decisiones; el libro gobierna decisiones. Si un desarrollador diseña una nueva pantalla mirando pantallas existentes como referencia, aprende patrones de las pantallas existentes — incluyendo los errores de esas pantallas. Si consulta primero The Fronda Book, aprende los principios que deberían gobernar el diseño, con independencia de si las pantallas existentes los cumplen o no.

Sin una declaración clara de autoridad, el libro y el código compiten. Y en esa competencia, el código siempre gana — porque el código es concreto, es inmediato, y funciona. El libro es abstracto y requiere interpretación.

---

## Alternativas consideradas

**Alternativa A — El libro como guía no vinculante.**
The Fronda Book como referencia optativa, útil para onboarding y para entender la filosofía del producto, pero sin autoridad formal sobre las decisiones de diseño. El problema: sin autoridad, el libro se vuelve aspiracional en el mejor caso, decorativo en el peor.

**Alternativa B — El libro como autoridad máxima sobre decisiones de producto y experiencia.**
Toda decisión de diseño de pantallas, patrones de interacción, semántica visual y filosofía de producto está gobernada por The Fronda Book. El código implementa lo que el libro decide. Cuando el código y el libro son contradictorios, el libro tiene razón hasta que el libro se actualice — no al revés.

**Alternativa C — El libro como autoridad compartida con el código, con revisión periódica.**
Cada seis meses se audita la alineación entre el código y el libro. Las divergencias se resuelven en esa revisión — a veces actualizando el libro, a veces corrigiendo el código. El problema: un ciclo de seis meses es demasiado largo para errores de diseño que tienen impacto inmediato en los usuarios.

---

## Decisión

Se adoptó la **Alternativa B** con un matiz práctico de la Alternativa C.

**The Fronda Book es la autoridad máxima sobre producto, experiencia y diseño de interacción en Fronda.** Esto significa:

1. Antes de diseñar cualquier nueva pantalla o flujo, el equipo consulta The Fronda Book. No el código.
2. Cuando el código y el libro son contradictorios, el código tiene un error de diseño — a menos que el libro esté desactualizado, lo que debe corregirse de inmediato.
3. Los PDRs documentados en PART V son las únicas excepciones válidas a los principios del libro. Toda excepción que no tenga un PDR es un error, no una decisión.
4. Actualizar The Fronda Book es un acto de producto, no de documentación. Requiere la misma revisión editorial que un PR que cambia una funcionalidad crítica.

El libro no puede estar desactualizado respecto al código por más de un sprint. Si el código evolucionó y el libro no, la próxima tarea de producto incluye actualizar el libro.

---

## Consecuencias

**Ventajas**

- Las decisiones de diseño tienen una referencia explícita y accesible. "Aquí dice que las alertas críticas son siempre rojo" es una instrucción más útil que "mira cómo lo hace la pantalla de OTs".
- El libro actúa como freno ante desviaciones que individualmente parecen razonables pero que acumuladas erosionan la coherencia del producto.
- El onboarding de nuevos miembros del equipo puede comenzar con el libro — que explica el por qué — antes de explorar el código — que muestra el cómo.

**Desventajas**

- El libro requiere mantenimiento activo. Un libro desactualizado que se declara "autoridad máxima" es peor que no tener libro: genera falsa certeza.
- La actualización del libro agrega tiempo al proceso de cada sprint que introduce cambios de diseño significativos.
- Puede crear fricción cuando una decisión práctica de implementación choca con un principio del libro que no previó ese caso específico.

---

## Cuándo revisar nuevamente

Este PDR establece un principio de proceso. Debería revisarse cuando:

- El equipo crezca a más de cinco personas trabajando simultáneamente en diseño/frontend, en cuyo caso el proceso de actualización del libro necesitará más estructura que la actual.
- Se evalúe incorporar contribuciones de diseño externas (agencias, consultores), que requieran entender la autoridad del libro antes de proponer cambios.

---

## Referencias

- [The Fronda Book — Prólogo](../00-README.md)
- [The Fronda Book — Convenciones editoriales](../00-Convenciones.md)
- [PDR-010 — Todo Product Sprint comienza revisando The Fronda Book](PDR-010-Product-Sprint-Comienza-Con-Fronda-Book.md)
- Sprint DS-1 (junio 2026) — Product Consistency Sprint, antecedente directo

---

[← PDR-008](PDR-008-Pantallas-Responden-Que-Hacer-Hoy.md) · [README](README.md) · [PDR-010 →](PDR-010-Product-Sprint-Comienza-Con-Fronda-Book.md)
