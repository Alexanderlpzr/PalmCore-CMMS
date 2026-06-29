# PDR-002 — La acción siempre aparece antes que la información

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Junio 2025 |
| **Última revisión** | Febrero 2026 (ratificado al diseñar el Home) |
| **Responsables** | Alexander Lopez (CPO), Equipo de Diseño |

---

## Contexto

En los primeros meses de Fronda, las pantallas principales se diseñaban siguiendo una lógica natural: primero se muestra la información disponible, luego el usuario decide qué hacer con ella. Era el modelo que teníamos interiorizado de otros sistemas.

El primer módulo con pantalla compleja fue el módulo de Solicitudes de Mantenimiento. La pantalla principal mostraba una tabla con todas las solicitudes — número, fecha, equipo, solicitante, estado, prioridad. Toda la información estaba ahí. Lo que no estaba eran las respuestas a las preguntas que el supervisor se hacía cuando abría esa pantalla.

En una sesión de observación con un supervisor de mantenimiento industrial, anotamos exactamente lo que dijo cuando vio la pantalla por primera vez: "Sí, veo las solicitudes. Pero ¿cuáles tengo que aprobar yo? ¿Cuáles llevan más tiempo esperando? ¿Hay alguna urgente?"

La pantalla le daba datos. No le daba respuestas.

---

## Problema

El diseño orientado a datos produce pantallas que **informan sin ayudar**.

La diferencia entre informar y ayudar es pequeña pero crítica: informar pone los datos frente al usuario y lo deja solo para que los procese. Ayudar hace el procesamiento previo y le presenta al usuario la conclusión que necesita para actuar.

Un sistema de mantenimiento tiene muchos datos. La mayoría no es urgente. Una pequeña parte requiere atención hoy. El sistema que no diferencia entre ambas categorías le transfiere al usuario el trabajo de hacer esa diferenciación — trabajo que el sistema debería hacer por él.

---

## Alternativas consideradas

**Alternativa A — Mantener la orientación a datos y mejorar los filtros.**
Dejar las pantallas como estaban pero agregarles más y mejores filtros para que el usuario encuentre lo urgente rápidamente. El problema: agregar filtros no cambia el modelo mental. El usuario sigue llegando a una pantalla de datos y teniendo que buscar lo urgente. Solo cambia la velocidad de la búsqueda, no la necesidad de hacerla.

**Alternativa B — Diseñar primero desde la acción.**
Partir de la pregunta: ¿qué necesita hacer el usuario en este momento? Y diseñar la pantalla para que esa respuesta sea lo primero visible. Los datos de soporte — la información que explica o contextualiza la acción — vienen después.

---

## Decisión

Se adoptó la **Alternativa B** como principio de diseño universal para todas las pantallas operacionales de Fronda.

La regla es: **en toda pantalla operacional, la acción disponible o requerida es el elemento de mayor jerarquía visual**. La información que apoya esa acción viene después.

Esto se traduce concretamente en:
- Las listas muestran el estado de los elementos antes que su fecha o su descripción. El estado responde implícitamente: ¿qué debo hacer con este ítem?
- Los encabezados de entidad (Entity Detail Page) muestran la acción disponible en el primer viewport, en el header sticky.
- La pantalla de Inicio muestra los avisos urgentes (lo que requiere acción) antes que el feed de actividad (lo que informalmente ocurrió).
- Los filtros de lista tienen como primera opción el filtro por estado o urgencia — no por fecha o por orden alfabético.

---

## Consecuencias

**Ventajas**

- Reduce el tiempo que el usuario tarda en llegar a su primera acción concreta después de abrir la pantalla.
- Pantallas diseñadas desde la acción tienen naturalmente menos elementos — solo aparece lo que sirve a la acción.
- La priorización que el sistema hace por el usuario reduce errores: si lo urgente es visible, el usuario no puede pasarlo por alto accidentalmente.

**Desventajas**

- Diseñar desde la acción requiere conocer el flujo de trabajo del usuario con mayor profundidad que diseñar desde los datos. Si la acción primaria está mal identificada, la pantalla estará diseñada alrededor de la cosa equivocada.
- Usuarios habituados a pantallas de datos pueden encontrar el cambio desconcertante inicialmente: buscan la tabla y la encuentran más abajo de lo que esperan.
- El principio es difícil de mantener en módulos complejos con múltiples tipos de usuario — la acción primaria de un técnico no es la misma que la de un supervisor.

---

## Cuándo revisar nuevamente

Este PDR es un principio de diseño, no una decisión táctica. Debería revisarse solo si:

- Se introduce un tipo de usuario cuyo modo de uso es fundamentalmente analítico (exploración de datos sin acción inmediata) y ese usuario requiere pantallas distintas al resto.
- Los datos de uso muestren consistentemente que los usuarios ignoran las acciones primarias y navegan directamente a los datos, lo que indicaría que la identificación de la acción primaria es incorrecta.

En ningún caso debería revisarse por preferencia estética o por presión de usuarios individuales que prefieren más datos visibles.

---

## Referencias

- [UX Principles — Principio 1: La acción siempre precede a la información](../PART-II-Experience/05-UX-Principles.md)
- [Product Philosophy — Principio II: La acción antes que la información](../03-Product-Philosophy.md)
- [PDR-001 — El Home reemplaza al Dashboard como punto de entrada](PDR-001-Home-Como-Punto-De-Entrada.md)
- [PDR-008 — Toda pantalla importante responde primero: ¿qué debo hacer hoy?](PDR-008-Pantallas-Responden-Que-Hacer-Hoy.md)

---

[← PDR-001](PDR-001-Home-Como-Punto-De-Entrada.md) · [README](README.md) · [PDR-003 →](PDR-003-Cinco-Colores-Semanticos.md)
