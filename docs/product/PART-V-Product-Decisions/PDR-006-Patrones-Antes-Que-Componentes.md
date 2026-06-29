# PDR-006 — Los patrones UX gobiernan el diseño antes que los componentes

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Abril 2026 |
| **Última revisión** | Junio 2026 |
| **Responsables** | Alexander Lopez (CPO), Equipo de Diseño |

---

## Contexto

Durante el Sprint DS-1 (Product Consistency Sprint, junio 2026), revisamos todas las pantallas del panel operativo con un objetivo: verificar que la experiencia fuera coherente. Lo que encontramos fue una coherencia visual parcial — los componentes visuales eran similares — pero una coherencia de interacción incompleta.

Dos módulos distintos resolvían el mismo problema de manera diferente. El módulo de órdenes de trabajo mostraba los ítems relacionados de una OT como una lista dentro de una tab. El módulo de equipos mostraba los ítems relacionados en un panel lateral deslizable. El resultado era visualmente diferente pero el problema subyacente era idéntico: mostrar entidades secundarias relacionadas con la entidad principal.

El mismo patrón, dos implementaciones. El usuario que aprendía uno no sabía cómo usar el otro.

La causa: habíamos documentado componentes (qué se ve), pero no habíamos documentado patrones (cómo se resuelve un tipo de problema). Cada equipo que construía una nueva pantalla hacía sus propias elecciones de interacción, con buenas intenciones pero sin una referencia común.

---

## Problema

**Sin patrones documentados, la consistencia de interacción depende de que todos los diseñadores y desarrolladores hayan visto todas las pantallas anteriores y recuerden cómo resolvieron el mismo problema.**

Eso funciona en un equipo de dos personas durante tres meses. No funciona cuando el equipo crece, cuando hay rotación, o cuando pasan seis meses entre dos pantallas que resuelven el mismo tipo de problema.

Un catálogo de componentes resuelve la consistencia visual. No resuelve la consistencia de interacción. Un botón puede verse igual en todo el sistema y aun así estar mal ubicado, mal etiquetado, o resolver el problema de manera distinta en cada módulo.

---

## Alternativas consideradas

**Alternativa A — Documentar reglas de estilo por componente.**
Agregar al catálogo de componentes existente una sección de "cuándo usar" para cada componente. El problema: las reglas de estilo por componente no capturan la solución al problema de interacción. "Usa este botón en formularios" no dice cuándo una interacción debe ser un formulario vs. un click directo vs. una transición de estado.

**Alternativa B — Documentar patrones de interacción como unidades independientes de los componentes.**
Un patrón describe cómo se resuelve un tipo de problema de interacción. Los componentes son el material con que se implementa el patrón. La biblioteca de patrones es la capa que gobierna el diseño; los componentes son los ladrillos.

---

## Decisión

Se adoptó la **Alternativa B**.

La biblioteca de patrones de Fronda (documentada en el Capítulo 6 de The Fronda Book) gobierna el diseño antes que los componentes. Esto significa:

1. Antes de diseñar cualquier nueva pantalla, se identifica qué patrones aplican.
2. La pantalla se construye componiendo esos patrones, en el orden y la combinación apropiados.
3. Si ningún patrón existente resuelve el problema, se documenta un nuevo patrón antes de implementarlo — no al revés.
4. Los componentes se eligen para implementar los patrones, no al contrario.

Los quince patrones documentados en el Capítulo 6 cubren el 95% de los casos de diseño de Fronda. Para el 5% restante existe un proceso de creación de nuevos patrones que requiere revisión de producto antes de implementación.

---

## Consecuencias

**Ventajas**

- Un diseñador nuevo puede entender la lógica de una pantalla no conocida si entiende los patrones que la componen.
- Las decisiones de diseño nuevas son más rápidas: "usa el patrón Master-Detail" es una instrucción completa, no el inicio de un debate.
- Las revisiones de diseño son más objetivas: una pantalla puede evaluarse contra los patrones que debería usar, no contra el gusto del revisor.
- Reduce la duplicación de soluciones: el mismo problema se resuelve de la misma manera en todo el producto.

**Desventajas**

- La biblioteca de patrones requiere mantenimiento. Si los patrones no se actualizan cuando el producto evoluciona, se vuelven una referencia obsoleta que nadie consulta.
- Existe tensión entre la estandarización que los patrones imponen y la creatividad que algunos problemas de diseño requieren. Un patrón mal aplicado a un problema diferente produce una mala experiencia.
- El proceso de crear un nuevo patrón antes de implementarlo agrega tiempo al proceso de diseño para casos no cubiertos.

---

## Cuándo revisar nuevamente

Revisar este PDR cuando:

- La biblioteca de patrones crezca a más de 25 patrones, lo que podría indicar que los patrones actuales no son suficientemente abstractos.
- Se detecte que el equipo no consulta la biblioteca de patrones antes de diseñar nuevas pantallas, lo que indicaría que la documentación es difícil de acceder o usar.
- Se incorpore un nuevo tipo de pantalla significativamente diferente a los casos cubiertos (ej.: pantallas de control IoT en tiempo real, visualizaciones de mantenimiento predictivo).

---

## Referencias

- [Pattern Library — Capítulo 6](../PART-II-Experience/06-Pattern-Library.md)
- [UX Principles — Capítulo 5](../PART-II-Experience/05-UX-Principles.md)
- Sprint DS-1 (junio 2026) — Product Consistency Sprint

---

[← PDR-005](PDR-005-Entidades-No-Son-Islas.md) · [README](README.md) · [PDR-007 →](PDR-007-Dashboard-Ejecutivo-Solo-Analitico.md)
