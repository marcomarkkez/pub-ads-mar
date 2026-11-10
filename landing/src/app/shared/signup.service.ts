import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export interface SignupPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: 'client' | 'provider';
  company_name?: string;
  phone?: string;
}

/**
 * EL punto de integracion del formulario, deliberadamente solo.
 *
 * El dueño dijo que probablemente cambie el formulario por otro mas adelante (2026-08-05),
 * asi que todo lo que sabe DONDE van los datos vive aqui y en ningun otro sitio: cambiar a
 * un proveedor externo es reescribir este fichero, no perseguir llamadas por las secciones.
 *
 * Va contra `POST /api/register`, que ya existe y es publico. Se descarto inventar un
 * endpoint de leads (superficie nueva de backend para algo que quiza se tire) y tambien
 * dejarlo sin destino: un formulario que no manda nada a ningun sitio es EH-8, un control
 * que solo existe para parecer que hace algo.
 *
 * La landing no guarda el token que devuelve el alta. Es marketing, no es la aplicacion:
 * quien se registra pasa por el acuse y entra por la puerta normal.
 */
/**
 * WALK-8 (Marco, 2026-11-10): el alta devolvia `Unexpected token '<', "<!DOCTYPE "...`.
 * Esto valia `'/api'`, una ruta RELATIVA: el navegador la mandaba al servidor de la propia
 * landing (4300), que no tiene API detras y contesta con HTML. La API vive en el 8000.
 * Misma resolucion que frontend/src/environments/environment.ts: en Codespaces cada puerto
 * es un subdominio `<nombre>-<puerto>.app.github.dev`, en local es localhost:8000.
 * Sujeto por LandingSignupReachesApiTest.
 */
export function resolveApiUrl(loc: { hostname: string; protocol: string } | undefined =
  typeof window === 'undefined' ? undefined : window.location): string {
  if (!loc) return 'http://localhost:8000/api';
  const m = loc.hostname.match(/^(.*)-4300\.(app\.github\.dev|githubpreview\.dev)$/);
  if (m) return `${loc.protocol}//${m[1]}-8000.${m[2]}/api`;
  return `${loc.protocol}//${loc.hostname}:8000/api`;
}

@Injectable({ providedIn: 'root' })
export class SignupService {
  private readonly http = inject(HttpClient);

  /** Sin barra final: se concatena con la ruta. Cambialo por el host real al desplegar. */
  private readonly api = resolveApiUrl();

  register(payload: SignupPayload): Observable<unknown> {
    return this.http.post(this.api + '/register', payload);
  }
}
