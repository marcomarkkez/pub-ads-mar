import { Component } from '@angular/core';

/**
 * Política de privacidad. Mismo aviso que los términos: texto de partida, sin revisión
 * legal, y obligatorio antes de abrir al público (todo LAND-1).
 */
@Component({
  selector: 'ld-privacy',
  standalone: true,
  template: `
    <main class="page">
      <div class="wrap narrow legal-doc">
        <p class="eyebrow"><a href="/">Adspot</a></p>
        <h1>Política de privacidad</h1>
        <p class="muted">Borrador de trabajo. Pendiente de revisión legal antes de la apertura al público.</p>

        <h2>Qué datos recogemos</h2>
        <p>
          Del alta: nombre, email, contraseña (cifrada, nunca en claro) y, si lo indicas,
          empresa y teléfono. Del uso: los espacios que publicas o reservas, los mensajes
          que intercambias con la otra parte y las pruebas de publicación.
        </p>

        <h2>Para qué los usamos</h2>
        <p>
          Para prestarte el servicio: mostrarte el catálogo, gestionar tus reservas, cobrar
          y pagar, y resolver disputas. No vendemos tus datos a terceros.
        </p>

        <h2>Qué ve la otra parte</h2>
        <p>
          Mientras una conversación esté abierta entre anunciante y proveedor, tus datos de
          contacto directos aparecen enmascarados. Soporte puede verlos cuando entra a
          resolver una disputa, y esos accesos quedan registrados.
        </p>

        <h2>Cuánto tiempo los guardamos</h2>
        <p>
          Mientras tengas cuenta y durante el plazo que la ley nos obligue a conservar la
          facturación. El contenido que forma parte de una disputa abierta se conserva
          hasta que la disputa se cierre, aunque solicites la baja.
        </p>

        <h2>Tus derechos</h2>
        <p>
          Puedes pedir acceso, rectificación o supresión de tus datos escribiéndonos desde
          tu cuenta. La supresión tiene los límites descritos en el punto anterior.
        </p>
      </div>
    </main>
  `,
})
export class PrivacyComponent {}
