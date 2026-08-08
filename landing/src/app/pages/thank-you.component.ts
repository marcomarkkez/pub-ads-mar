import { Component } from '@angular/core';

/**
 * El acuse. Existe porque el formulario navega aqui al crear la cuenta: sin una pagina
 * propia, un alta correcta se veria exactamente igual que un formulario que no hizo nada.
 */
@Component({
  selector: 'ld-thank-you',
  standalone: true,
  template: `
    <main class="page center-page">
      <div class="wrap narrow">
        <p class="eyebrow">Cuenta creada</p>
        <h1>Ya está. Bienvenido.</h1>
        <p class="lead">
          Te hemos dado de alta. Entra con tu email y tu contraseña para ver el mapa de
          espacios, o para publicar el primero si eres proveedor.
        </p>
        <p><a class="btn btn-lg" href="/">Volver al inicio</a></p>
        <p class="muted small">
          Si algo no cuadra, escríbenos y lo miramos. Nadie de nuestro equipo te pedirá
          nunca tu contraseña.
        </p>
      </div>
    </main>
  `,
})
export class ThankYouComponent {}
