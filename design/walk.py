#!/usr/bin/env python3
"""Cierra (o tumba) un recorrido humano y arrastra lo que referencia.

Un WALK es lo unico que cierra un § o un Fxx en este proyecto: mientras el recorrido
siga `pending`, todo lo que apunta sigue abierto por muy verde que este la suite
(walkthroughs.convention · BR-16). Esa regla estaba escrita y no ejecutada, que es
justo la forma de fallo que BR-16 persigue — asi que aqui esta ejecutada.

    python3 design/walk.py WALK-1 --pass --by "Marco" --notes "todo ok menos W1-4"
    python3 design/walk.py WALK-1 --fail --by "Marco" --notes "W1-5 dejaba escribir al admin"
    python3 design/walk.py --status

`--by` es OBLIGATORIO y nombra a una persona (BR-20, owner 2026-08-20): un recorrido lo
anda alguien por la interfaz real, y las sondas de cada paso solo acompanan. Una sonda en
verde prueba que el servidor contesta lo que se le pidio — no que exista un boton que lo
pida, ni que se vea, ni que la pantalla siguiente pinte la respuesta.

`--pass` marca el recorrido y pone en `done` los todos que cierra. `--fail` los deja
abiertos y guarda lo que se vio, que es la mitad que de verdad hace falta despues.
Las referencias a §, UC, BR y EH NO se tocan solas: se listan para que quien cerro el
recorrido las revise. Marcar un § como hecho es una decision, no un efecto colateral.
"""
import argparse
import collections
import json
import os
import re
import sys
from datetime import date

DESIGN = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'design.json')

# Nombres que NO son quien anduvo las pantallas. La lista es corta y explicita a proposito:
# no intenta adivinar si algo es un nombre propio — solo cierra la puerta a firmar un
# recorrido con el nombre de lo que lo acompana (BR-20).
NOT_A_PERSON = re.compile(
    r'^(agente?|agent|bot|ci|cd|pipeline|suite|tests?|phpunit|playwright|e2e|script|'
    r'automat\w*|claude|gpt|copilot|ia|ai)$', re.I)


def load():
    with open(DESIGN, encoding='utf-8') as fh:
        return json.load(fh, object_pairs_hook=collections.OrderedDict)


def save(data):
    # Mismo formato exacto que el resto del fichero: cualquier otra combinacion
    # reescribe design.json entero y convierte el diff en ilegible.
    with open(DESIGN, 'w', encoding='utf-8') as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)
        fh.write('\n')


def show_status(data):
    walks = data['walkthroughs']['items']
    todos = {t['id']: t for t in data['todos']}
    width = max(len(w['id']) for w in walks)

    for w in walks:
        mark = {'passed': 'OK ', 'failed': 'MAL', 'pending': ' · '}.get(w['status'], ' ? ')
        print(f"{mark} {w['id']:<{width}}  {w['title']}")
        refs = ([f"cierra {c}" for c in w.get('closes', [])] + w.get('specs', [])
                + w.get('ucs', []) + w.get('br', []) + w.get('eh', []))
        if refs:
            estado = 'ya cerradas por el recorrido' if w['status'] == 'passed' else 'ABIERTAS hasta que pase'
            print(f"      {', '.join(refs)}   ({estado})")
        if w.get('result'):
            r = w['result']
            print(f"      resultado {r.get('date', '')} {r.get('by', '')}: {r.get('notes', '')}")

    pend = [w['id'] for w in walks if w['status'] != 'passed']
    blocked = sorted({c for w in walks if w['status'] != 'passed' for c in w.get('closes', [])
                      if c in todos and todos[c]['status'] != 'done'})
    print()
    print(f"{len(pend)} recorrido(s) sin pasar: {', '.join(pend) if pend else '—'}")
    if blocked:
        print(f"y por tanto {len(blocked)} todo(s) que no pueden cerrarse: {', '.join(blocked)}")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('walk', nargs='?', help='id del recorrido, p. ej. WALK-1')
    ap.add_argument('--pass', dest='passed', action='store_true', help='el recorrido salio bien')
    ap.add_argument('--fail', dest='failed', action='store_true', help='el recorrido encontro algo')
    ap.add_argument('--by', default='', help='quien lo recorrio (obligatorio: una persona)')
    ap.add_argument('--notes', default='', help='que se vio (obligatorio si --fail)')
    ap.add_argument('--status', action='store_true', help='que falta por recorrer')
    args = ap.parse_args()

    data = load()

    if args.status or not args.walk:
        show_status(data)
        return 0

    if args.passed == args.failed:
        sys.exit('Elige --pass o --fail (uno de los dos).')
    if not args.by.strip():
        # BR-20 (owner 2026-08-20). Un recorrido lo anda una PERSONA; las sondas acompanan.
        # La firma es lo unico que distingue "lo vi en pantalla" de "el servidor contesto":
        # WALK-6 estuvo a un comando de cerrarse con sus siete sondas en verde y cero
        # pantallas abiertas, mientras los tres fallos que de verdad tenia solo se veian
        # pulsando. Sin nombre no se registra nada.
        sys.exit('--by es obligatorio: un recorrido lo marca quien lo anduvo por la interfaz (BR-20).\n'
                 '  python3 design/walk.py %s --%s --by "Marco"' % (args.walk, 'pass' if args.passed else 'fail'))
    if NOT_A_PERSON.match(args.by.strip()):
        sys.exit('"%s" no es una persona. --by nombra a quien recorrio las pantallas (BR-20):\n'
                 '  una sonda verde prueba que el servidor contesta, no que exista un boton que lo pida.'
                 % args.by.strip())
    if args.failed and not args.notes:
        # Un recorrido fallido sin lo que se vio no sirve de nada: manana nadie sabra
        # que reproducir. Es la unica cosa que este script exige.
        sys.exit('--fail necesita --notes: sin lo observado, el fallo no es reproducible.')

    walks = {w['id']: w for w in data['walkthroughs']['items']}
    walk = walks.get(args.walk)
    if walk is None:
        sys.exit(f"No existe {args.walk}. Hay: {', '.join(walks)}")

    walk['status'] = 'passed' if args.passed else 'failed'
    walk['result'] = collections.OrderedDict([
        ('date', date.today().isoformat()),
        ('by', args.by),
        ('notes', args.notes),
    ])

    todos = {t['id']: t for t in data['todos']}
    moved, absent = [], []

    for tid in walk.get('closes', []):
        todo = todos.get(tid)
        if todo is None:
            absent.append(tid)          # un § o algo que no vive en el tablero
            continue
        if args.passed and todo['status'] != 'done':
            todo['status'] = 'done'
            moved.append(tid)

    save(data)

    print(f"{walk['id']} → {walk['status']}")
    if moved:
        print(f"  todos cerrados: {', '.join(moved)}")
    if absent:
        print(f"  referencias que no son todos (revisalas a mano): {', '.join(absent)}")

    refs = walk.get('specs', []) + walk.get('ucs', []) + walk.get('br', []) + walk.get('eh', [])
    if args.passed and refs:
        print(f"  el recorrido respalda: {', '.join(refs)}")
        print('  (no se marcan solas: cerrar un § es una decision, no un efecto colateral)')
    if args.failed:
        print('  nada se cierra: lo que este recorrido referencia sigue abierto.')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
