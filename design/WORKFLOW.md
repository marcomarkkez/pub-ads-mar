# Workflow — cómo atacamos los problemas que se repiten

Escrito el 2026-09-22 después de la queja que lo motiva: *"siento que estamos dando vueltas
en círculos… salieron dos errores nuevos que no tienen sentido"*. No es un manual de estilo.
Es el orden de operaciones que impide que un mismo fallo vuelva por tercera vez.

---

## 0 · La regla que explica todas las demás

**Cada hallazgo se cierra en la capa MÁS BARATA que puede volver a encontrarlo.**

| capa | cuesta | la usa |
|---|---|---|
| una prueba (`php artisan test`) | segundos, automática | siempre que el hecho sea verificable en frío |
| una sonda (Playwright / `curl`) | un comando | cuando hay que contar peticiones o medir la pantalla |
| un paso de walk | una persona, minutos | sólo cuando hace falta un juicio humano |
| la cabeza de alguien | se olvida | nunca |

Un hallazgo que termina en una frase de chat vuelve. Un hallazgo que termina en una prueba,
no. Ésa es toda la teoría.

---

## 1 · Triage: qué clase de problema es

Antes de tocar nada, colocar el reporte en una de estas cuatro casillas. Cada una tiene una
salida distinta y confundirlas es la causa real de las vueltas en círculo.

1. **Defecto reproducible.** Hay pasos y el resultado es el mismo dos veces.
   → §2.
2. **Defecto no reproducible.** El reporte es real pero el sistema no lo repite.
   → NO se arregla a ciegas. Se escribe la sonda que lo distinguiría y se devuelve al
   reportante con esa sonda. *Ej.: el alta duplicada del 2026-09-22 — la API devolvía 422 y
   la BD quedaba con una sola fila.*
3. **Ruido del entorno.** Falla la caja, no el código (postgres segado, teselas bloqueadas,
   puerto sin reenviar, proxy).
   → Se documenta en `claude.md` → Learnings y se sigue. No es un bug del producto.
4. **Hueco de especificación.** El walk pide recorrer algo que no existe o que nadie puede
   arrancar.
   → §5.

> **La trampa nº 1 de este repositorio:** tratar un (3) o un (4) como un (1). Así nacieron
> "la API negó el acceso" (era OSM) y "no veo los textos legales" (la landing no arrancaba).

---

## 2 · Un defecto reproducible, de principio a fin

```
reproducir → medir → arreglar → prueba negativa → registrar la CLASE → entregar parche
```

1. **Reproducir en el sandbox**, no razonar sobre el código. Si no se reproduce, es un (2).
2. **Medir, no mirar.** La pantalla miente: una tesela rechazada conserva
   `leaflet-tile-loaded`, un doble clic parece un clic. Contar peticiones, leer
   `getBoundingClientRect()`, comparar respuestas.
3. **Arreglar lo mínimo.** Nada de reordenar código de paso.
4. **Prueba negativa, obligatoria.** Revertir el arreglo y comprobar que la prueba FALLA.
   Si sigue pasando, la prueba no vale y puede que el arreglo tampoco:
   *el 2026-09-22 un reordenado de `toggleView()` que parecía correcto se revirtió porque
   ninguna prueba podía distinguirlo — no se entrega código que nadie puede justificar.*
5. **Registrar la CLASE en `errorHunt`, no el incidente.** El valor está en `howToFind`: la
   búsqueda que mañana se repite sobre código nuevo.
6. **Entregar `pNN.patch`.** Nunca commit ni push desde la sesión del agente.

---

## 3 · Las tres clases que más se repiten aquí

Están en `design.json` → `errorHunt` con su `howToFind`. Resumidas, por si se lee esto primero:

- **EH-19 · la aplicación que nadie puede arrancar.** Compila, se versiona, y no hay forma
  documentada de levantarla. Por cada directorio con `package.json`/`composer.json` propio:
  ¿tiene servicio en `docker-compose.yml`? ¿tiene una línea con su puerto en
  `useful_commands.md`? Si no, para quien recorre no existe.
- **EH-20 · el gesto que dispara dos veces.** Un manejador colgado de un evento que el
  navegador emite más de una vez. No se ve: se cuenta con `page.on('request')`.
- **EH-21 · el fallo externo que se dibuja como hueco.** Un recurso de terceros que falla en
  silencio. Por cada recurso externo: si el servidor dice que no, ¿qué ve la persona? Si la
  respuesta es "nada", falta un aviso en pantalla.

---

## 4 · Walks: qué es y qué no es un paso

**BR-20 (Marco, 2026-08-20): un walk lo marca una persona. Un agente jamás.**

- Un paso de walk es siempre **interfaz**. Las `probe` acompañan, nunca sustituyen.
- Si un paso se puede comprobar entero desde la terminal, **no es un paso de walk**: es una
  prueba. Bajarlo a `tests/`. *(Así salió `BUILD-1` del retirado WALK-7.)*
- Un walk por persona-sesión, no por funcionalidad. Hoy son seis: WALK-8 visitante,
  WALK-2 cliente, WALK-6 proveedor, WALK-5 dinero y staff, WALK-1 Messages, WALK-4 tablero.
- El paso `W0` de cada walk deja la pila lista **y dice en qué URL empieza**. Un walk que no
  dice dónde vive lo que hay que mirar es el hueco del §5.

---

## 5 · Hueco de especificación (el walk pide algo que no existe)

No se arregla el walk borrando el paso. Se decide una de dos, con fecha:

- **Existe pero no se alcanza** → es un EH-19: darle puerta (servicio, comando, enlace de
  menú) y una prueba que la sujete. *La landing y `/collaborations` fueron esto.*
- **No existe todavía** → el paso apunta a un `todo` con estado, y el walk queda bloqueado
  hasta que ese todo pase a `review`. Nunca se marca "bien" por educación.

---

## 6 · Antes de entregar un parche

```bash
service postgresql start                      # el contenedor sega postgres entre sesiones
cd backend && php artisan test                # suite completa, verde
php artisan test --filter=PlanningCodeCongruence   # los artefactos, congruentes
cd ../frontend && npx tsc --noEmit            # tipos
node e2e-client-map.mjs                       # mapa del cliente
node e2e-provider-spaces.mjs                  # espacios del proveedor
```

Y la receta del parche (worktree + `git write-tree` + tar + poda con `git ls-files`) está en
`claude.md`. **`tar -x` sólo AÑADE: sin la poda, los borrados y los renombrados no viajan.**

---

## 7 · Higiene de `design.json`

Es el spec canónico y pesa ~98k tokens: cada sesión lo paga entero antes de empezar.

- **Una afirmación, un sitio.** Si aparece en dos bloques, se queda donde es *ejecutable*
  (prueba > regla de negocio > historia > spec) y la copia pasa a ser un puntero. Dos copias
  de una regla se separan y entonces nadie sabe cuál manda: eso ES el error redundante.
- **Nada se borra: se archiva** en `design/archive.json` con la fecha y el porqué.
- **Sólo `collections.OrderedDict` + `json.dump(..., ensure_ascii=False, indent=2)` + salto
  de línea final.** Cualquier otra cosa reformatea el fichero entero.
- Después de cada bloque: las pruebas de congruencia y las 13 vistas del tablero.
- El trabajo pendiente está en el todo **`SPEC-1`**.

---

## 8 · Lo que no se hace, nunca

- `git checkout <fichero>` en este repositorio. Un agente destruyó tres semanas así el
  2026-08-23. Recuperar desde `origin/<rama>`.
- Commit o push desde la sesión del agente. Se entrega `pNN.patch`.
- Marcar un walk. Lo firma quien lo anduvo (BR-20).
- Escribir un comando con `{...}` sin haberlo ejecutado.
