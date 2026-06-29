# PDR-003 — El sistema utiliza únicamente cinco colores semánticos

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Noviembre 2024 |
| **Última revisión** | Junio 2026 (confirmado con el color índigo para Context Banners) |
| **Responsables** | Alexander Lopez (CPO / Diseño) |

---

## Contexto

Cuando comenzamos a diseñar las primeras pantallas de Fronda, las decisiones de color se tomaban pantalla por pantalla. La tabla de órdenes usaba verde para "completada" y naranja para "en progreso". El módulo de equipos usaba azul para "activo" y gris para "inactivo". El módulo de alertas usaba rojo para "crítica" y amarillo para "advertencia".

Cada equipo de diseño que intervenía agregaba su propia capa de color. Para el tercer módulo, el sistema tenía siete tonos de verde distintos, cuatro de rojo, y tres de ámbar — ninguno con el mismo significado en cada contexto.

Un técnico que aprendía que el naranja significaba "en progreso" en el módulo de órdenes llegaba al módulo de equipos y veía que naranja significaba "criticidad alta". No era lo mismo. No lo sabía.

El color había dejado de ser información. Era decoración.

---

## Problema

**Un sistema sin semántica de color obliga al usuario a leer el texto para entender el estado.**

En entornos industriales, la velocidad de lectura de la interfaz importa. Un técnico que revisa diez órdenes de trabajo en un turno no debe tener que leer la etiqueta de estado de cada una para saber cuáles están en ejecución. Debería poder escanearlo visualmente en dos segundos.

Eso es solo posible si el color tiene un significado único y estable en todo el sistema.

El problema secundario era de mantenimiento: con colores sin semántica, cada nueva pantalla requería una decisión de color nueva. No había regla que aplicar. Era juicio subjetivo en cada caso, lo que generaba inconsistencia inevitable.

---

## Alternativas consideradas

**Alternativa A — Definir un color por módulo.**
Cada módulo tiene su propio color primario. El módulo de mantenimiento es verde, el de inventario es azul, el de análisis es violeta. Los estados dentro de cada módulo usan variaciones del color del módulo.

Esta alternativa organiza visualmente el sistema por módulo, pero destruye la semántica transversal. Un usuario que ve "verde" no sabe si significa "módulo de mantenimiento" o "estado saludable". Y un equipo que aparece en ambos módulos (como cuando una OT referencia un equipo) tiene que decidir qué color prevalece.

**Alternativa B — Definir colores semánticos transversales que aplican a todo el sistema.**
Cinco colores, cada uno con un significado único, usado con ese significado en todos los módulos sin excepción.

---

## Decisión

Se adoptó la **Alternativa B**.

Los cinco colores semánticos de Fronda son:

| Color | Semántica | Cuándo usar |
|---|---|---|
| **Emerald** | Salud, éxito, operación normal, acción primaria | Disponibilidad alta, orden completada, estado activo, botones de acción principal |
| **Rojo** | Peligro, falla, vencido, crítico | Downtime, orden vencida, alerta crítica, estado fuera de servicio, criticidad alta |
| **Ámbar** | Advertencia, atención requerida, inminente | Preventivo próximo, solicitud pendiente, criticidad media, en espera |
| **Azul** | Información, proceso en curso, dato neutro | Estado en ejecución, información general, MTBF, procesos activos sin urgencia |
| **Índigo** | Contexto relacional, jerarquía, navegación | Banners de contexto entre entidades relacionadas, breadcrumbs activos |

Un sexto no-color, **Slate/Gris**, opera como neutro: metadata, fechas, etiquetas secundarias, estados inactivos o archivados.

La regla de aplicación es absoluta: ningún color de este sistema puede usarse con un significado diferente en ninguna pantalla de Fronda. El rojo nunca es un elemento de marca. El emerald nunca es "el color del módulo de mantenimiento". El ámbar nunca es un color de acento decorativo.

---

## Consecuencias

**Ventajas**

- El usuario aprende el sistema de colores una sola vez y lo aplica en cualquier parte del producto.
- Las nuevas pantallas no requieren decisiones de color — el sistema ya decidió.
- El escaneo visual de listas y dashboards es significativamente más rápido porque el color carga información antes de que el usuario lea ningún texto.
- Reduce el tiempo de diseño de nuevas pantallas.

**Desventajas**

- El sistema de colores es rígido. Si el emerald ya significa "activo/saludable", no puede usarse como fondo de un carrusel institucional, aunque sería estéticamente atractivo.
- Con cinco colores semánticos, el rango expresivo del sistema es limitado. Cuando aparezca un sexto estado que no encaje limpiamente en ninguno de los cinco, habrá presión para agregar un sexto color — resistir esa presión requiere disciplina.
- El índigo fue agregado posteriormente (en junio 2026, con el patrón de Context Banners). Eso significa que la decisión original fue de cuatro colores y fue expandida a cinco. Una nueva expansión no está descartada, pero debe ocurrir por la misma razón: un nuevo patrón de uso que no puede servirse con ninguno de los colores existentes.

---

## Cuándo revisar nuevamente

Revisar este PDR cuando:

- Se identifique un patrón de uso recurrente para el que ninguno de los cinco colores sea apropiado, y la alternativa sea usarlos con un significado diferente (lo cual está prohibido) o introducir un nuevo color.
- Se realice una revisión completa del sistema de diseño que evalúe la identidad visual de Fronda.
- Los datos de uso muestren que los usuarios no están leyendo los colores correctamente (ej.: confunden ámbar con rojo en condiciones de luz directa en campo).

---

## Referencias

- [UX Principles — Principio 7: Los colores son un contrato con el usuario](../PART-II-Experience/05-UX-Principles.md)
- [Pattern Library — Context Banner](../PART-II-Experience/06-Pattern-Library.md)
- [PDR-006 — Los patrones UX gobiernan el diseño antes que los componentes](PDR-006-Patrones-Antes-Que-Componentes.md)

---

[← PDR-002](PDR-002-Accion-Antes-Que-Informacion.md) · [README](README.md) · [PDR-004 →](PDR-004-Workspace-Hero-Feature-Frozen.md)
