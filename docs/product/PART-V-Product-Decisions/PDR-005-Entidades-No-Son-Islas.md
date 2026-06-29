# PDR-005 — Las entidades nunca son islas

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Febrero 2025 |
| **Última revisión** | Junio 2026 (ratificado con Context Banners y UX-3 Ficha 360°) |
| **Responsables** | Alexander Lopez (CPO), Equipo de Arquitectura |

---

## Contexto

Cuando se construyeron los primeros módulos de Fronda, cada uno era independiente. La ficha de un equipo mostraba los datos del equipo. La lista de órdenes de trabajo mostraba las órdenes. El módulo de inventario mostraba los repuestos.

No había ninguna pantalla que conectara estas tres entidades, aunque en la realidad del mantenimiento industrial están profundamente relacionadas. Una orden de trabajo existe porque un equipo la requiere. Una orden consume repuestos del inventario. El estado de disponibilidad de un equipo depende de las órdenes que lo han atendido.

El primer test real con un supervisor de mantenimiento hizo evidente el problema. Le pedimos que nos mostrara el estado del compresor de la línea 2 — si tenía órdenes pendientes y si había repuestos disponibles para el filtro que necesitaba cambio.

Tardó cuatro minutos. Navegó a equipos, encontró el compresor, volvió al menú, fue a órdenes, buscó por equipo, encontró las órdenes, anotó los números en papel, fue a inventario, buscó el filtro. Todo correcto. Todo separado.

Cuatro minutos para responder una pregunta que debería tardar diez segundos.

---

## Problema

**Un sistema con módulos aislados transfiere al usuario el trabajo de conectar información que el sistema podría conectar automáticamente.**

El costo de esa transferencia no es solo de tiempo. Es de errores: el usuario que transcribe manualmente información entre pantallas comete errores. Es de cansancio: la navegación entre módulos para tareas frecuentes fatiga al usuario. Es de adopción: un sistema que requiere mucho trabajo para obtener respuestas simples termina siendo abandonado.

---

## Alternativas consideradas

**Alternativa A — Mantener los módulos aislados y crear una pantalla de "resumen" por entidad.**
Una pantalla especial que agrega información de múltiples módulos. El problema: generaba una pantalla adicional sin un lugar claro en la navegación, y requería mantener dos fuentes de verdad para la misma información.

**Alternativa B — Enriquecer cada entidad con sus relaciones relevantes directamente en su vista de detalle.**
La ficha de un equipo muestra sus órdenes activas, su historial de fallas y sus repuestos asociados — sin que el usuario navegue a ningún otro módulo. La ficha de una orden muestra el equipo que la originó con un enlace directo. El inventario muestra en qué órdenes se ha consumido cada repuesto.

---

## Decisión

Se adoptó la **Alternativa B** como principio arquitectónico de todo el diseño de pantallas en Fronda.

La regla: **toda entidad relevante de una vista es navegable desde esa misma vista**. El usuario nunca necesita ir al menú lateral para acceder a información directamente relacionada con la entidad que está viendo.

Esto se implementa de dos maneras:

1. **Context Banners** — Cuando una entidad relacionada es la más importante en el contexto actual (el equipo de una OT, el equipo padre de un subcomponente), aparece un banner con fondo índigo que muestra la información clave y un enlace "Ver →".

2. **Tabs en la Entity Detail Page** — Cuando hay múltiples categorías de entidades relacionadas (las OTs de un equipo, el historial de fallas, los componentes hijos), aparecen como secciones dentro de la ficha de la entidad principal.

La navegación entre entidades relacionadas preserva el contexto de origen: el parámetro `from` en la URL permite que el botón de retroceso diga "Volver al equipo" en lugar de "Volver" — y lleva al lugar exacto de donde el usuario partió.

---

## Consecuencias

**Ventajas**

- Reduce drásticamente el tiempo para responder preguntas que cruzan entidades ("¿este equipo tiene OTs abiertas?", "¿hay stock del repuesto que necesita esta OT?").
- Elimina la necesidad de navegar al menú lateral para tareas que involucran múltiples módulos.
- El usuario puede resolver la mayoría de sus tareas sin abandonar la pantalla de la entidad principal.

**Desventajas**

- Agrega complejidad a cada Entity Detail Page. Una ficha de equipo con seis tabs y tres Context Banners es más compleja de construir y de mantener que una ficha simple con solo los datos del equipo.
- Requiere que las APIs devuelvan datos relacionados de manera eficiente. Si cada sección de tab hace una llamada API independiente, la carga de la ficha puede ser lenta si no se implementa correctamente (lazy loading por tab).
- El principio de "entidades conectadas" puede llevarse demasiado lejos. No toda relación entre entidades merece estar visible. El diseño debe priorizar las relaciones más relevantes para el flujo de trabajo del usuario.

---

## Cuándo revisar nuevamente

Este PDR es un principio arquitectónico. Debería revisarse solo si:

- Se introduce una nueva categoría de entidad con relaciones tan complejas que no encajan en el modelo de tabs + Context Banners.
- Los datos de uso muestren que los usuarios no utilizan las secciones relacionadas en la Entity Detail Page, lo que sugeriría que la conexión implementada no refleja las conexiones que los usuarios realmente necesitan.

---

## Referencias

- [UX Principles — Principio 2: El contexto nunca se pierde](../PART-II-Experience/05-UX-Principles.md)
- [Pattern Library — Context Banner](../PART-II-Experience/06-Pattern-Library.md)
- [Pattern Library — Entity Detail Page](../PART-II-Experience/06-Pattern-Library.md)
- [Product Philosophy — Principio III: El contexto viaja con el objeto](../03-Product-Philosophy.md)
- Sprint P6+P7 (junio 2026) — Equipment Profile redesign + Component hierarchy
- Sprint UX-3 (junio 2026) — Ficha 360° de Activos y Órdenes de Trabajo

---

[← PDR-004](PDR-004-Workspace-Hero-Feature-Frozen.md) · [README](README.md) · [PDR-006 →](PDR-006-Patrones-Antes-Que-Componentes.md)
