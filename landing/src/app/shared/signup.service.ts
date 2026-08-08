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
@Injectable({ providedIn: 'root' })
export class SignupService {
  private readonly http = inject(HttpClient);

  /** Sin barra final: se concatena con la ruta. Cambialo por el host real al desplegar. */
  private readonly api = '/api';

  register(payload: SignupPayload): Observable<unknown> {
    return this.http.post(this.api + '/register', payload);
  }
}
