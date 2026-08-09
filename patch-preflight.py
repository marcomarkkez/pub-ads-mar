#!/usr/bin/env python3
"""patch-preflight.py — que va a hacer falta DESPUES de aplicar este parche.

El aprendizaje que lo motiva (2026-08-09): la prevencion estaba puesta en el sitio
equivocado. Decia "cuando el login falle, corre mvp-init.py" — es decir, actuaba cuando
el destrozo ya estaba hecho y el sintoma ya habia costado media hora de adivinar
contrasenas. El momento correcto es ANTES de aplicar, porque el propio parche dice lo
que va a romper: sus rutas de fichero lo anuncian.

    python3 patch-preflight.py p89.patch

No aplica nada ni toca la base. Lee el parche, dice que va a exigir, y se calla.
Ejecutarlo cuesta un segundo; no ejecutarlo cuesta la sesion entera de ayer.
"""
import argparse
import os
import re
import sys

# Cada regla: (patron de ruta, etiqueta, por que, comandos). El orden importa — se
# imprime como una receta, y las migraciones van antes que el sembrado.
RULES = [
    (r"^backend/database/migrations/",
     "MIGRACIONES NUEVAS",
     "El esquema cambia. Si no migras, la aplicacion arranca contra tablas viejas y falla "
     "en runtime con una columna que no existe — que se lee como un bug del codigo, no como "
     "una migracion pendiente.",
     ["docker compose exec backend php artisan migrate --force"]),

    (r"^backend/database/seeders/",
     "SEMBRADORES CAMBIADOS",
     "Las filas sembradas quedan viejas respecto al codigo nuevo. `mvp-init.py` NO lo detecta "
     "por si solo si la base ya tiene usuarios: ve el conteo, lo da por bueno y no resiembra.",
     ["python3 mvp-init.py --fresh"]),

    (r"^backend/(app/Models/RolePermission\.php|database/migrations/.*permission)",
     "PERMISOS TOCADOS",
     "`PermissionMiddleware` lee una cache de 60 minutos respaldada en base de datos. Sin "
     "limpiarla, la aplicacion responde 'Forbidden' con los permisos correctos ya escritos.",
     ["docker compose exec backend php artisan cache:clear"]),

    (r"^backend/composer\.(json|lock)$",
     "DEPENDENCIAS PHP",
     "El contenedor lleva el `vendor/` de antes.",
     ["docker compose exec backend composer install"]),

    # Estas dos llevan `None` en los comandos: se calculan segun QUE proyecto toca el
    # parche. Mandar a reinstalar `frontend` cuando solo cambio `landing` es una
    # instruccion falsa, y una herramienta que da instrucciones falsas se deja de leer.
    (r"^(frontend|landing)/package(-lock)?\.json$",
     "DEPENDENCIAS NPM",
     "`npm ci` y NUNCA `npm install` (BR-18): `ci` instala exactamente el lockfile y falla si "
     "no cuadra; `install` resuelve versiones nuevas sin pedir permiso.",
     None),

    (r"^(frontend|landing)/src/",
     "FRONTEND",
     "Hay que reconstruir para ver el cambio.",
     None),

    (r"^design/(app\.js|styles\.css|design\.html)$",
     "DASHBOARD DE DISENO",
     "El navegador cachea estos ficheros con fuerza: sin recarga dura veras la version vieja "
     "y creeras que el parche no entro. Ya paso una vez.",
     ["Recarga dura en el navegador (Ctrl+Shift+R)"]),
]

# Migraciones que ESTRECHAN el esquema. Las filas ya sembradas pueden violarlas, y entonces
# `migrate` se cae a medias y deja la base en un estado que no es ni el viejo ni el nuevo.
NARROWING = re.compile(
    r"\b(dropColumn|dropTable|->change\(\)|CHECK\s*\(|NOT NULL|unique\(|foreign\(|"
    r"renameColumn|dropForeign)\b", re.I)


def paths_in(patch_text):
    return sorted(set(re.findall(r"^diff --git a/(\S+) b/", patch_text, re.M)))


def added_lines(patch_text):
    return "\n".join(l[1:] for l in patch_text.splitlines()
                     if l.startswith("+") and not l.startswith("+++"))


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("patch", help="ruta del .patch")
    args = ap.parse_args()

    if not os.path.exists(args.patch):
        sys.exit("No existe: %s" % args.patch)

    text = open(args.patch, encoding="utf-8", errors="replace").read()
    files = paths_in(text)
    if not files:
        sys.exit("Ese fichero no parece un parche de git (no hay lineas 'diff --git').")

    print("\n%s — %d fichero(s)\n" % (args.patch, len(files)))

    hits, steps = [], []
    for pattern, label, why, cmds in RULES:
        matched = [f for f in files if re.search(pattern, f)]
        if not matched:
            continue
        hits.append((label, why, matched))
        if cmds is None:
            projects = sorted({f.split("/")[0] for f in matched})
            verb = "npm ci" if label == "DEPENDENCIAS NPM" else "npx ng build"
            cmds = ["cd %s && %s" % (proj, verb) for proj in projects]
        steps.extend(cmds)

    if not hits:
        print("  Nada que preparar: este parche no toca esquema, sembrado ni dependencias.")
        print("  Aplicalo y sigue.\n")
        return

    for label, why, matched in hits:
        print("  ● %s" % label)
        for f in matched[:4]:
            print("      %s" % f)
        if len(matched) > 4:
            print("      … y %d mas" % (len(matched) - 4))
        print("      %s\n" % why)

    # El aviso caro: una migracion que estrecha el esquema sobre datos ya sembrados.
    migrations = [f for f in files if f.startswith("backend/database/migrations/")]
    if migrations and NARROWING.search(added_lines(text)):
        print("  ⚠ ESTE PARCHE ESTRECHA EL ESQUEMA (drop / change / CHECK / NOT NULL / unique).")
        print("    Las filas que ya tienes sembradas pueden violarlo, y entonces `migrate` se")
        print("    cae a la mitad y deja la base entre dos estados. Estamos en un MVP y los")
        print("    datos son desechables (owner 2026-08-03), asi que aqui la opcion barata es")
        print("    la buena:\n")
        print("        python3 mvp-init.py --fresh\n")
        steps = ["python3 mvp-init.py --fresh"] + [
            s for s in steps if "mvp-init" not in s and "artisan migrate" not in s]

    print("  DESPUES DE APLICAR, en este orden:\n")
    seen = set()
    for s in steps:
        if s not in seen:
            seen.add(s)
            print("      %s" % s)
    print("\n      python3 mvp-init.py     # siempre lo ultimo: deja la pila lista para entrar\n")


if __name__ == "__main__":
    main()
