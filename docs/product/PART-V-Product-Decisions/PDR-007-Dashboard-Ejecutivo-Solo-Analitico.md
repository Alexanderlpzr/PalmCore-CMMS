# PDR-007 — El Dashboard Ejecutivo es exclusivamente analítico

| Campo | Valor |
|---|---|
| **Estado** | `Activo` |
| **Fecha** | Noviembre 2025 |
| **Última revisión** | Febrero 2026 (ratificado al diseñar el Home) |
| **Responsables** | Alexander Lopez (CPO), Equipo de Diseño |

---

## Contexto

Al construir el módulo de análisis (Indicadores, Resumen Ejecutivo, Reportes) en el segundo semestre de 2025, surgió una discusión recurrente: el Dashboard debería tener también accesos rápidos, un panel de tareas pendientes, o al menos una sección de alertas activas.

La presión para agregar elementos operacionales al Dashboard venía de una intuición razonable: si el gerente de mantenimiento entra al Dashboard, podría ver ahí mismo qué alertas están activas, sin tener que navegar a otro módulo. Es conveniente.

El problema de esa conveniencia no era técnico. Era conceptual.

El Dashboard Ejecutivo existe para responder preguntas de período: ¿cómo estuvo la disponibilidad este mes? ¿Cuáles son los equipos con mayor downtime? ¿Bajó el MTTR respecto al trimestre anterior? Esas preguntas son retrospectivas. Requieren un estado mental de análisis.

Una alerta activa es una pregunta diferente: ¿qué está ocurriendo ahora? ¿Tengo que hacer algo? Esa pregunta requiere un estado mental operacional.

Mezclar ambas en la misma pantalla obliga al usuario a cambiar de modo cognitivo constantemente — y ninguna de las dos funciones se cumple bien.

---

## Problema

**El Dashboard que intenta ser tanto analítico como operacional termina siendo malo en ambas cosas.**

La pantalla de análisis requiere concentración. El usuario llega a ella con tiempo, con una pregunta, y necesita poder explorar los datos sin interrupciones. Las alertas activas, las solicitudes pendientes, los avisos urgentes interrumpen esa exploración y generan ansiedad donde debería haber claridad.

La pantalla operacional requiere velocidad. El usuario llega a ella bajo presión, necesita información inmediata y acción inmediata. Los gráficos de tendencia, los rankings de disponibilidad, los análisis de Pareto ralentizan esa respuesta y son ruido en un momento que requiere señal.

---

## Alternativas consideradas

**Alternativa A — Dashboard híbrido con sección operacional y sección analítica.**
Una pantalla dividida en dos mitades: arriba, las alertas y los avisos urgentes; abajo, los KPIs y las gráficas. El problema: la mitad operacional siempre compite con la mitad analítica. El usuario que necesita análisis se distrae con los avisos. El usuario que necesita acción pierde tiempo cargando las gráficas que no necesita.

**Alternativa B — Dos pantallas distintas con propósitos distintos.**
El Home para la operación diaria. El Dashboard para el análisis periódico. Cada uno optimizado para su propósito. El usuario que necesita análisis va al Dashboard. El que necesita orientación operacional va al Home.

---

## Decisión

Se adoptó la **Alternativa B**.

El Dashboard Ejecutivo (y todas las vistas bajo el grupo de Análisis: Indicadores, Resumen Ejecutivo) son exclusivamente retrospectivos. No tienen:
- Panels de alertas activas
- Solicitudes pendientes de aprobación
- Quick Actions hacia módulos operacionales
- Contadores de ítems urgentes del turno actual

Lo que sí tienen:
- KPIs de período con comparativa respecto al período anterior
- Gráficas de tendencia
- Rankings y clasificaciones entre equipos o áreas
- Análisis de Pareto de fallas
- Filtros de período, planta y área
- Drill-down hacia los datos que componen cada métrica

Esta decisión fue el antecedente directo de [PDR-001](PDR-001-Home-Como-Punto-De-Entrada.md): si el Dashboard no puede ser operacional, necesitamos otra pantalla que lo sea. Esa pantalla es el Home.

---

## Consecuencias

**Ventajas**

- El usuario del Dashboard puede analizar sin interrupciones. La pantalla no cambia mientras la usa — no aparecen alertas nuevas, no se actualizan contadores urgentes.
- El diseño del Dashboard puede optimizarse para la densidad de información analítica sin comprometer la claridad operacional de otras pantallas.
- La separación de modos hace que el sistema sea más fácil de explicar: "Para ver qué tienes pendiente hoy, ve al Inicio. Para ver cómo fue el mes, ve a Indicadores."

**Desventajas**

- Un gerente que quiere tanto el análisis del mes como las alertas del día actual necesita visitar dos pantallas. La alternativa (una sola pantalla) sería más conveniente, aunque inferior en calidad de experiencia para cada uno de los dos usos.
- El menú lateral tiene dos secciones (Inicio + Análisis) que en la práctica responden a la misma pregunta sobre el estado de la operación, aunque desde perspectivas temporales distintas. Esto puede ser confuso para usuarios nuevos.

---

## Cuándo revisar nuevamente

Este PDR debería revisarse cuando:

- Se introduzca un rol de usuario cuya función principal sea el monitoreo en tiempo real (ej.: un operador de sala de control). Ese rol podría justificar un modo "mixto" — analítico y en tiempo real simultáneamente — que no corresponde a ninguno de los dos modos actuales.
- Con IoT y sensores, la definición de "analítico" puede expandirse para incluir datos en tiempo real de sensores. En ese caso, la frontera entre Dashboard y Home se vuelve más difusa y este PDR necesita revisión.

---

## Referencias

- [UX Principles — Principio 4: Los dashboards informan, las pantallas de trabajo ayudan](../PART-II-Experience/05-UX-Principles.md)
- [Pattern Library — Dashboard Analytics](../PART-II-Experience/06-Pattern-Library.md)
- [PDR-001 — El Home reemplaza al Dashboard como punto de entrada](PDR-001-Home-Como-Punto-De-Entrada.md)
- [PDR-002 — La acción siempre aparece antes que la información](PDR-002-Accion-Antes-Que-Informacion.md)
- [PDR-008 — Toda pantalla importante responde primero: ¿qué debo hacer hoy?](PDR-008-Pantallas-Responden-Que-Hacer-Hoy.md)

---

[← PDR-006](PDR-006-Patrones-Antes-Que-Componentes.md) · [README](README.md) · [PDR-008 →](PDR-008-Pantallas-Responden-Que-Hacer-Hoy.md)
