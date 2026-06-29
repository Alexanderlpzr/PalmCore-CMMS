# Product Decision Records — Fronda

## Qué es un Product Decision Record

Un Product Decision Record (PDR) documenta una decisión de producto importante: por qué se tomó, qué alternativas se consideraron, cuáles fueron sus consecuencias, y cuándo debería revisarse.

Un PDR no es un Architecture Decision Record (ADR). Los ADRs documentan decisiones técnicas: qué base de datos, qué framework, qué patrón de código. Los PDRs documentan decisiones de producto: qué experiencia construir, cómo debe comportarse el sistema frente al usuario, qué principios gobiernan el diseño.

La diferencia es el nivel de abstracción. Un ADR muere cuando cambia la tecnología. Un PDR sobrevive porque documenta decisiones sobre problemas humanos, y los problemas humanos no cambian cuando se migra de un framework a otro.

---

## Por qué existen

Los productos toman decisiones todo el tiempo. Algunas son tácticas y efímeras. Otras son fundacionales y duraderas. La diferencia no siempre es obvia en el momento de tomarlas.

Lo que sí es siempre obvio es lo que ocurre cuando una decisión fundacional no está documentada: alguien llega meses después, no entiende por qué algo fue hecho de cierta manera, lo cambia "para mejorarlo", y sin saberlo deshace una decisión que fue tomada por una razón muy específica que nunca fue escrita.

Los PDRs existen para que eso no ocurra.

No son burocracia. Son memoria institucional.

---

## Cuándo crear un PDR

Crear un PDR cuando:

- Una decisión cambia fundamentalmente la experiencia del usuario (qué ve primero, cómo navega, qué colores significan qué).
- Una decisión restringe o abre opciones futuras de manera significativa.
- Una decisión fue debatida durante más de un sprint antes de resolverse.
- Una decisión contradice lo que "todos asumían" que era correcto.
- Una decisión establece un patrón que se aplicará en muchas pantallas o módulos.

---

## Cuándo NO crear un PDR

No crear un PDR cuando:

- La decisión es de implementación técnica (eso es un ADR).
- La decisión es táctica y reversible sin consecuencias significativas.
- La decisión es un bug fix o una mejora incremental a algo ya decidido.
- La decisión fue completamente obvia y no generó ninguna deliberación.

Una regla práctica: si la decisión puede deshacerse en un sprint sin consecuencias de diseño o filosofía de producto, no necesita un PDR.

---

## Estados de un PDR

| Estado | Significado |
|---|---|
| `Activo` | La decisión está vigente y governa el producto. |
| `Revisado` | Fue revisado explícitamente y confirmado. La fecha de revisión está registrada. |
| `Supersedido` | Fue reemplazado por otro PDR. Se indica cuál. |
| `En revisión` | Está bajo discusión activa. No tomar decisiones que dependan de él hasta que se resuelva. |
| `Archivado` | Ya no aplica. Se conserva por razones históricas. |

---

## Cómo evoluciona

Los PDRs no se eliminan. Se superseden o se archivan.

Si una decisión cambia, se crea un nuevo PDR que documenta la nueva decisión y su razón, y el PDR anterior se marca como `Supersedido` con una referencia al nuevo. El historial permanece intacto.

Si una decisión deja de ser relevante (por ejemplo, un módulo que ya no existe), el PDR se marca como `Archivado` con una nota explicando el contexto.

---

## Índice

| PDR | Título | Fecha | Estado |
|---|---|---|---|
| [PDR-001](PDR-001-Home-Como-Punto-De-Entrada.md) | El Home reemplaza al Dashboard como punto de entrada | Feb 2026 | `Activo` |
| [PDR-002](PDR-002-Accion-Antes-Que-Informacion.md) | La acción siempre aparece antes que la información | Jun 2025 | `Activo` |
| [PDR-003](PDR-003-Cinco-Colores-Semanticos.md) | El sistema utiliza únicamente cinco colores semánticos | Nov 2024 | `Activo` |
| [PDR-004](PDR-004-Workspace-Hero-Feature-Frozen.md) | Workspace Hero declarado Feature Frozen | May 2026 | `Activo` |
| [PDR-005](PDR-005-Entidades-No-Son-Islas.md) | Las entidades nunca son islas | Feb 2025 | `Activo` |
| [PDR-006](PDR-006-Patrones-Antes-Que-Componentes.md) | Los patrones UX gobiernan el diseño antes que los componentes | Abr 2026 | `Activo` |
| [PDR-007](PDR-007-Dashboard-Ejecutivo-Solo-Analitico.md) | El Dashboard Ejecutivo es exclusivamente analítico | Nov 2025 | `Activo` |
| [PDR-008](PDR-008-Pantallas-Responden-Que-Hacer-Hoy.md) | Toda pantalla importante responde primero: ¿qué debo hacer hoy? | Ago 2025 | `Activo` |
| [PDR-009](PDR-009-Fronda-Book-Gobierna-El-Producto.md) | The Fronda Book gobierna el producto por encima del código | Jun 2026 | `Activo` |
| [PDR-010](PDR-010-Product-Sprint-Comienza-Con-Fronda-Book.md) | Todo Product Sprint comienza revisando The Fronda Book | Jun 2026 | `Activo` |

---

*Los PDRs son parte de The Fronda Book. Se rigen por las mismas convenciones editoriales.*

[← Índice del libro](../README.md)
