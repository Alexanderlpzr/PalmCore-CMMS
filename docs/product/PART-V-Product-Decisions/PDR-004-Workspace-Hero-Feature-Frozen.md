# PDR-004 — Workspace Hero declarado Feature Frozen

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Mayo 2026 |
| **Última revisión** | Junio 2026 |
| **Responsables** | Alexander Lopez (CPO) |

---

## Contexto

El Workspace Hero — el encabezado de la pantalla de Inicio con el saludo personalizado, el nombre del tenant, la fecha y el reloj en tiempo real — fue diseñado e implementado en el Sprint PX-1 (junio 2026). Desde su primera iteración, pasó por tres versiones de layout antes de llegar a su forma actual.

En la primera versión, el saludo era genérico ("Bienvenido al sistema") sin nombre de usuario. En la segunda, se agregó el nombre pero no el tenant. En la tercera, se incorporaron el tenant, la fecha larga y el reloj en tiempo real.

Una semana después de implementada la versión final, surgió la primera propuesta de cambio: agregar un indicador de "turno activo" (turno mañana / tarde / noche) basado en la hora del sistema. La propuesta era razonable. El equipo la debatió durante dos días.

Al final del debate, la conclusión no fue "sí" o "no" a esa funcionalidad específica. Fue más importante: necesitábamos una política clara sobre cuándo el Workspace Hero puede cambiar, porque sin esa política el componente más crítico del primer viewport iba a estar en constante discusión.

---

## Problema

**Sin una política de estabilidad declarada, el componente que más influye en la primera impresión del sistema es también el más vulnerable a cambios frecuentes.**

El Workspace Hero es lo primero que ve el usuario cada día. Su función es orientar — decirle al usuario quién es, dónde está y en qué momento. Esa función es simple. Es estable. No necesita evolucionar con cada nueva funcionalidad del sistema.

Cada cambio al Workspace Hero — por pequeño que sea — tiene un impacto en la experiencia del primer segundo de uso. Y la acumulación de cambios pequeños puede desestabilizar una experiencia que funciona bien precisamente porque es predecible.

---

## Alternativas consideradas

**Alternativa A — Tratar el Workspace Hero como cualquier otro componente, sujeto a iteración continua.**
Evaluar cada propuesta de cambio en su mérito individual. El problema: sin una línea base declarada de "esto no cambia", cada propuesta empieza desde el mismo punto de negociación y el componente nunca estabiliza.

**Alternativa B — Declarar el Workspace Hero como Feature Frozen.**
El componente en su forma actual es la versión definitiva. Solo puede cambiar si hay una razón de producto de primer orden que lo justifique, y ese cambio requiere la creación de un nuevo PDR que reemplace a este.

---

## Decisión

Se adoptó la **Alternativa B**.

El Workspace Hero está declarado **Feature Frozen** en su forma actual:

- Saludo personalizado con el primer nombre del usuario, en tono emerald.
- Nombre del tenant en gris suave.
- Fecha larga con día de la semana, en gris.
- Reloj en tiempo real (hh:mm), actualizado cada segundo.
- Espacio reservado para integración de clima (marcado como "próximamente").

Ninguna funcionalidad nueva puede agregarse al Workspace Hero sin la creación de un PDR que justifique el cambio y reemplace a este.

**Feature Frozen no significa que el componente nunca cambiará.** Significa que no cambiará por inercia, por preferencia estética, o por presión de una solicitud de usuario individual. Solo cambiará por una razón de producto documentada y evaluada.

---

## Consecuencias

**Ventajas**

- El equipo de diseño puede dar una respuesta definitiva a propuestas de cambio al Workspace Hero: "Está frozen. Para cambiarlo necesitamos un PDR."
- La experiencia del primer segundo de uso es predecible y consistente para todos los usuarios durante un período largo.
- Libera energía de diseño que de otra manera se gastaría en debatir micro-iteraciones de un componente que ya funciona bien.

**Desventajas**

- La integración de clima (actualmente un placeholder) requeriría un PDR cuando llegue el momento de implementarse, aunque ya está prevista.
- Si hay un cambio de identidad visual significativo (nuevo logo, nuevo color primario), el Workspace Hero no puede actualizarse sin un PDR — lo que agrega fricción a una decisión que debería ser sencilla.

---

## Cuándo revisar nuevamente

Este PDR debería revisarse cuando:

- Se implemente la integración de clima. En ese momento, el placeholder debe reemplazarse con el dato real, lo que constituye un cambio menor al Workspace Hero.
- Se introduzca un nuevo tipo de usuario cuya función requiera contexto adicional en el primer viewport (ej.: un operario de control cuyo contexto relevante no es el tenant sino la línea de producción activa).
- Se realice un rediseño de identidad visual de Fronda que requiera cambios en el encabezado.

---

## Referencias

- [Pattern Library — Workspace Hero (FROZEN)](../PART-II-Experience/06-Pattern-Library.md)
- [PDR-001 — El Home reemplaza al Dashboard como punto de entrada](PDR-001-Home-Como-Punto-De-Entrada.md)
- Sprint PX-1 (junio 2026) — implementación original del Workspace Hero

---

[← PDR-003](PDR-003-Cinco-Colores-Semanticos.md) · [README](README.md) · [PDR-005 →](PDR-005-Entidades-No-Son-Islas.md)
