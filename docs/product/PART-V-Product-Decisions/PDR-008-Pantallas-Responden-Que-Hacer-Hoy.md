# PDR-008 — Toda pantalla importante responde primero: ¿qué debo hacer hoy?

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Agosto 2025 |
| **Última revisión** | Junio 2026 |
| **Responsables** | Alexander Lopez (CPO), Equipo de Diseño |

---

## Contexto

En agosto de 2025, con los módulos de Solicitudes y Órdenes de Trabajo ya funcionales, realizamos la primera revisión de usabilidad con usuarios reales en entorno industrial. No era un test de laboratorio — era observación directa en planta durante dos turnos.

Dos observaciones fueron constantes en todos los usuarios:

La primera: al llegar a cualquier lista (solicitudes, órdenes, equipos), lo primero que hacían era buscar manualmente los ítems que requerían su atención. Si el supervisor tenía que aprobar solicitudes, revisaba visualmente la columna de estado de cada una para encontrar las que tenían estado "Pendiente". Si el técnico necesitaba ver sus órdenes del día, buscaba su nombre en la columna de asignado.

La segunda: cuando encontraban lo que buscaban, lo hacían en un promedio de 47 segundos. El tiempo variaba según cuántos ítems había en la lista y cuánto había scrolleado el usuario.

47 segundos para responder una pregunta que debería responderse en cero segundos: el sistema debería saber, según el rol del usuario, qué requiere su atención.

---

## Problema

**Una pantalla que presenta información sin filtrar por relevancia para el usuario actual no es una herramienta — es un repositorio de datos.**

La diferencia entre una herramienta y un repositorio es que la herramienta sabe para quién está trabajando. El repositorio solo sabe que tiene datos.

Fronda en agosto de 2025 era, en sus pantallas principales, un repositorio bien diseñado. Los datos estaban ahí. La organización era correcta. Pero no había ningún procesamiento que dijera: dado que eres este rol, en este momento, lo que necesitas ver es esto.

---

## Alternativas consideradas

**Alternativa A — Mejorar los filtros existentes.**
Agregar más opciones de filtro para que el usuario pueda llegar más rápido a lo que le importa. El problema: agregar filtros mejora la búsqueda, no elimina la necesidad de buscar. El usuario sigue teniendo que saber qué filtrar y cómo.

**Alternativa B — Pre-filtrar las vistas según el rol y el contexto del usuario.**
Cuando un supervisor llega a la lista de solicitudes, la vista por defecto muestra solo las solicitudes que esperan su aprobación — no todas las solicitudes del sistema. Cuando un técnico llega a las órdenes de trabajo, la vista por defecto muestra las órdenes asignadas a él. El usuario puede cambiar el filtro si necesita ver más, pero el punto de partida ya responde la pregunta más probable.

**Alternativa C — Crear vistas personalizadas por rol además de las vistas generales.**
Un técnico tiene "Mis órdenes" además de "Órdenes de trabajo". Un supervisor tiene "Solicitudes pendientes de mi aprobación" además de "Todas las solicitudes". El problema: duplica los módulos y complica la navegación.

---

## Decisión

Se adoptó la **Alternativa B**, con elementos de la Alternativa C solo para el Home (que es inherentemente personalizado por rol).

La regla: **las pantallas de trabajo de Fronda asumen un contexto por defecto** basado en el rol del usuario. Los filtros por defecto son los más relevantes para ese rol. El usuario puede cambiarlos, pero no debe tener que hacerlo para su caso de uso primario.

Específicamente:
- La lista de órdenes de trabajo muestra por defecto las órdenes activas (no históricas).
- La lista de solicitudes muestra por defecto las solicitudes que requieren acción según el rol (para supervisores: las pendientes de aprobación; para técnicos: las asignadas).
- El Home muestra por defecto los avisos del día actual, no del mes.
- Las listas filtradas por fecha muestran por defecto el período más corto útil (el día, la semana), no el período más largo disponible (todo el tiempo).

Esta decisión es la extensión natural de [PDR-002](PDR-002-Accion-Antes-Que-Informacion.md): si la acción siempre aparece antes que la información, el filtro por defecto debe ser el que expone las acciones más probables — no el que muestra todos los datos disponibles.

---

## Consecuencias

**Ventajas**

- El tiempo promedio para llegar al primer ítem relevante baja drásticamente. En los tests posteriores a la implementación, bajó de 47 segundos a 8 segundos.
- La pantalla es más limpia: menos ítems visibles por defecto, más relevantes.
- El usuario desarrolla confianza en el sistema: "cuando llego aquí, lo que veo es lo que me corresponde".

**Desventajas**

- Los filtros por defecto son decisiones de producto que deben revisarse cuando cambian los patrones de uso. Si el rol de supervisor cambia de naturaleza, el filtro por defecto correcto podría ser otro.
- Un usuario que quiere ver todos los datos (no solo los relevantes para su rol) necesita cambiar los filtros manualmente. Eso es un paso extra para un caso de uso secundario — aceptable, pero real.
- Los filtros por defecto pueden crear una sensación de que "hay menos datos de los que hay". Un técnico que solo ve sus órdenes puede creer, erróneamente, que no hay otras órdenes activas. El diseño debe comunicar que el filtro está activo.

---

## Cuándo revisar nuevamente

Revisar cuando:

- Se detecte que los usuarios cambian frecuentemente los filtros por defecto en una pantalla específica — lo que indicaría que el filtro elegido no es el correcto para ese rol en esa pantalla.
- Se agreguen nuevos roles con patrones de uso distintos a los existentes.

---

## Referencias

- [UX Principles — Principio 1: La acción siempre precede a la información](../PART-II-Experience/05-UX-Principles.md)
- [PDR-002 — La acción siempre aparece antes que la información](PDR-002-Accion-Antes-Que-Informacion.md)
- [PDR-001 — El Home reemplaza al Dashboard como punto de entrada](PDR-001-Home-Como-Punto-De-Entrada.md)

---

[← PDR-007](PDR-007-Dashboard-Ejecutivo-Solo-Analitico.md) · [README](README.md) · [PDR-009 →](PDR-009-Fronda-Book-Gobierna-El-Producto.md)
