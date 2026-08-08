import { Component } from '@angular/core';
import { SignupFormComponent } from '../shared/signup-form.component';

/**
 * La landing entera, en una sola pagina (owner 2026-08-05).
 *
 * Lenguaje visual tomado de Be Agency / Bridge / Impreza: secciones a sangre completa que
 * alternan fondo, tipografia de titular muy grande, filas de iconos, cifras y un CTA que
 * cierra. Los tres temas estaban inaccesibles desde aqui, asi que esto reproduce esa
 * familia de memoria y no copia ningun HTML ajeno.
 *
 * Una sola decision de contenido merece explicacion: el formulario aparece DOS veces, en
 * el heroe y al cierre. No es duplicidad — es el mismo componente montado dos veces, y es
 * el patron de esa familia de plantillas porque quien ya esta convencido no deberia tener
 * que volver arriba a buscar donde firmar.
 */
@Component({
  selector: 'ld-landing',
  standalone: true,
  imports: [SignupFormComponent],
  template: `
    <header class="nav">
      <a class="brand" href="/">Adspot</a>
      <nav>
        <a href="#como">Cómo funciona</a>
        <a href="#espacios">Espacios</a>
        <a href="#confianza">Confianza</a>
        <a class="btn btn-ghost" href="#alta">Crear cuenta</a>
      </nav>
    </header>

    <section class="hero">
      <div class="hero-copy">
        <p class="eyebrow">Marketplace de espacios publicitarios</p>
        <h1>El espacio que buscas<br>ya existe. Solo hay que<br><em>encontrarlo</em>.</h1>
        <p class="lead">
          Vallas, muros, fachadas y locales reales, con su ubicación en el mapa, sus medidas
          y su precio a la vista. Reserva, sube tu creativo y recibe la prueba de que salió
          publicado. Sin llamadas, sin intermediarios, sin presupuestos por correo.
        </p>
        <div class="hero-stats">
          <div><strong>1.400+</strong><span>espacios listados</span></div>
          <div><strong>32</strong><span>ciudades</span></div>
          <div><strong>48h</strong><span>de la reserva al muro</span></div>
        </div>
      </div>
      <div class="hero-form" id="alta">
        <h2>Empieza gratis</h2>
        <p class="muted">Publicar un espacio o buscar uno no cuesta nada. Solo pagas cuando hay trato.</p>
        <ld-signup-form />
      </div>
    </section>

    <section class="band" id="como">
      <div class="wrap">
        <p class="eyebrow center">Cómo funciona</p>
        <h2 class="center">Tres pasos, y el tercero es el que nadie más te da</h2>
        <div class="steps">
          <article>
            <span class="num">01</span>
            <h3>Busca en el mapa</h3>
            <p>
              Filtra por zona, fechas, tamaño y presupuesto. Cada ficha trae fotos, medidas
              exactas y disponibilidad real: si el calendario dice libre, está libre.
            </p>
          </article>
          <article>
            <span class="num">02</span>
            <h3>Reserva y sube tu arte</h3>
            <p>
              Organiza tus anuncios por campaña, sube el creativo y el proveedor lo instala.
              El dinero queda retenido hasta que el trabajo esté hecho.
            </p>
          </article>
          <article>
            <span class="num">03</span>
            <h3>Recibe la prueba</h3>
            <p>
              El instalador sube la foto del anuncio puesto. Tú la aceptas y el pago se
              libera. Si algo no cuadra, la rechazas y se abre una disputa con soporte
              dentro. Nadie cobra por un muro vacío.
            </p>
          </article>
        </div>
      </div>
    </section>

    <section class="split" id="espacios">
      <div class="wrap two">
        <div>
          <p class="eyebrow">Para quien tiene el espacio</p>
          <h2>Tu muro lleva años trabajando gratis</h2>
          <p>
            Publica una vez, define tu precio por día o por mes y deja el calendario
            conectado a tu iCal para que nunca se dupliquen reservas. Nosotros traemos a los
            anunciantes y nos ocupamos del cobro.
          </p>
          <ul class="ticks">
            <li>Tú fijas el precio y las fechas</li>
            <li>Sincronización de calendario con iCal</li>
            <li>Cobro garantizado al entregar la prueba</li>
            <li>Puedes sumar colaboradores a tu cuenta</li>
          </ul>
        </div>
        <div>
          <p class="eyebrow">Para quien anuncia</p>
          <h2>Deja de pedir presupuestos por correo</h2>
          <p>
            Precios a la vista, disponibilidad al día y toda la campaña en un sitio. Una
            factura por campaña, no una por espacio, y el histórico completo de lo que se
            publicó y dónde.
          </p>
          <ul class="ticks">
            <li>Búsqueda por cercanía en el mapa</li>
            <li>Campañas, conjuntos y anuncios organizados</li>
            <li>Prueba fotográfica de cada publicación</li>
            <li>Una factura por campaña</li>
          </ul>
        </div>
      </div>
    </section>

    <section class="band dark" id="confianza">
      <div class="wrap">
        <p class="eyebrow center">Confianza</p>
        <h2 class="center">El dinero no se mueve hasta que el anuncio está puesto</h2>
        <p class="center lead narrow">
          Es la única regla que no negociamos. El pago queda retenido desde la reserva; se
          libera cuando el anunciante acepta la prueba de publicación. Si la rechaza, se
          congela y entra soporte. Ni el proveedor cobra por un trabajo que no hizo, ni el
          anunciante paga por un muro que nunca vio su marca.
        </p>
        <figure class="quote">
          <blockquote>
            Tenía cuatro fachadas paradas medio año. Las publiqué un martes y el viernes
            tenía la primera reserva pagada.
          </blockquote>
          <figcaption>Propietario de espacios · Monterrey</figcaption>
        </figure>
      </div>
    </section>

    <section class="close">
      <div class="wrap two">
        <div>
          <h2>Crea tu cuenta y mira qué hay cerca</h2>
          <p class="lead">
            Tarda menos de un minuto y no pedimos tarjeta. Si eres proveedor, publicas tu
            primer espacio hoy mismo; si anuncias, ves el mapa completo al entrar.
          </p>
        </div>
        <div class="hero-form">
          <ld-signup-form />
        </div>
      </div>
    </section>

    <footer class="foot">
      <div class="wrap two">
        <div>
          <a class="brand" href="/">Adspot</a>
          <p class="muted">El marketplace de espacios publicitarios.</p>
        </div>
        <nav class="foot-links">
          <a href="/terminos">Términos y condiciones</a>
          <a href="/privacidad">Política de privacidad</a>
        </nav>
      </div>
    </footer>
  `,
})
export class LandingComponent {}
