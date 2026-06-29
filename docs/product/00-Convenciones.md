---

`REFERENCIA EDITORIAL`

**Tiempo de lectura:** 4 minutos · **Objetivo:** Establecer las convenciones que mantienen la coherencia del libro a lo largo del tiempo y entre autores.

> *"Un libro escrito por muchas manos puede sonar a muchas voces. Eso no es riqueza — es ruido."*
> — Fronda

---

# Convenciones Editoriales — The Fronda Book

Este documento define cómo se escribe, estructura y mantiene The Fronda Book. Todo nuevo capítulo debe seguirlas. Toda revisión de capítulo existente debe respetarlas.

---

## Tono

**Directo, sin condescendencia.** El lector de este libro es una persona inteligente con criterio propio. No se le explica lo obvio. No se le dice qué pensar — se le da el marco para pensarlo.

**Afirmativo, sin soberbia.** Fronda tiene posiciones claras. Las expresa sin disculparse, pero sin pretender que son las únicas verdaderas. La diferencia entre convicción y arrogancia es que la convicción acepta ser cuestionada.

**Concreto, sin exceso de ejemplos.** Cada principio tiene exactamente un ejemplo. No dos. Uno bien elegido es más poderoso que tres mediocres.

**En español de América Latina.** No en español peninsular. No en español académico. En el español que usa una persona técnica y educada que trabaja en industria en la región.

---

## Lo que no tiene lugar en este libro

**Marketing.** Frases como "solución integral", "plataforma de clase mundial", "ecosistema robusto" no aportan información. Si no puede decirse de manera más concreta, no debe decirse.

**Lenguaje generado por IA.** La prosa generada tiene una cadencia reconocible: enumera en tríos, usa "es importante destacar", introduce cada punto con un adjetivo vago. Ese patrón rompe la identidad del libro. Si un párrafo suena como si lo hubiera escrito una máquina, debe reescribirse.

**Aspiraciones sin fundamento.** No se escribe sobre lo que Fronda quiere ser si aún no hay evidencia de que pueda serlo. La visión a 10 años es legítima. Una promesa de funcionalidad que no está en construcción no lo es.

**Explicaciones del código.** Este libro no describe la implementación técnica. No menciona tecnologías específicas, frameworks, ni decisiones de infraestructura. Eso pertenece a la documentación técnica.

---

## Estructura de un capítulo

Todo capítulo de The Fronda Book sigue la misma estructura. Las secciones marcadas con `(requerido)` son obligatorias. Las marcadas con `(opcional)` se incluyen cuando el contenido lo justifica.

### Encabezado `(requerido)`

```markdown
---

`PARTE [número] — [NOMBRE]`  `CAPÍTULO [número]`

**Tiempo de lectura:** X minutos · **Objetivo:** [Una sola oración que describe qué aprende el lector.]

> *"[Cita original de Fronda — no copiada de ningún otro lugar.]"*
> — Fronda

---
```

El encabezado aparece **antes** del título principal del capítulo (`# Título`).

### Cuerpo `(requerido)`

El contenido del capítulo. Sin restricciones de longitud, pero con preferencia por la concisión. Si algo puede decirse en dos párrafos, no usar cuatro.

### Resumen `(requerido)`

```markdown
---

### Ideas clave

- [Primera idea — una oración.]
- [Segunda idea — una oración.]
- [Tercera idea — una oración.]
- [Máximo cinco ideas. Mínimo tres.]

---
```

### Navegación `(requerido)`

```markdown
[← Capítulo anterior](nombre-archivo.md) · [Índice](README.md) · [Capítulo siguiente →](nombre-archivo.md)
```

Para el primer capítulo, el enlace izquierdo apunta a la portada. Para el último capítulo disponible, el enlace derecho apunta a la portada.

---

## Cómo se agrega un nuevo capítulo

1. Determinar en qué Parte pertenece (Identity / Experience / Technology / Future).
2. Asignarlo al número que sigue en secuencia dentro de esa Parte.
3. Crear el archivo con el nombre `[##]-[Nombre-Kebab-Case].md`.
4. Agregar la entrada correspondiente al índice en `README.md`.
5. Actualizar la navegación del capítulo anterior para que apunte al nuevo.
6. Seguir la estructura definida en esta sección.

El contenido de un capítulo nuevo debe surgir de algo aprendido, validado o decidido — no de especulaciones sobre el futuro del producto. Si el contenido es una intención que aún no tiene respaldo en el producto real, pertenece al roadmap, no al libro.

---

## Cómo se revisa un capítulo existente

Los capítulos existentes no se reescriben por preferencia estilística. Se actualizan cuando:

- Un principio documentado contradice una decisión real y validada del producto.
- Un ejemplo práctico ya no refleja cómo funciona el sistema.
- El contexto del mercado o del producto cambió de manera significativa.

Cuando se actualiza un capítulo, se registra la fecha de la última revisión al pie del documento.

---

## Cómo se retira un capítulo

Un capítulo no se elimina — se archiva. Si un principio o una sección completa deja de ser válida, se mueve a `docs/product/archivo/` con una nota explicando por qué fue retirado y cuándo. La historia del libro importa tanto como su estado actual.

---

### Ideas clave

- El tono es directo, afirmativo y concreto. Nunca condescendiente, nunca aspiracional sin fundamento.
- Cada capítulo tiene encabezado, cuerpo, resumen e ídem de navegación.
- Los capítulos nuevos nacen de aprendizajes reales, no de intenciones.
- Los capítulos obsoletos se archivan, no se eliminan.

---

[← Portada](README.md) · [Índice](README.md) · [Prólogo →](00-README.md)
