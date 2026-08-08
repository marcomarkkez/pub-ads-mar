import { Component } from '@angular/core';

/**
 * Términos y condiciones. Texto de partida, NO revisado por nadie con formación legal:
 * describe lo que la plataforma hace hoy para que el alta no apunte a una página vacía.
 * Antes de abrir al público tiene que pasar por un abogado — está anotado en el todo
 * LAND-1 y en la spec del grupo landing-page.
 */
@Component({
  selector: 'ld-terms',
  standalone: true,
  template: `
    <main class="page">
      <div class="wrap narrow legal-doc">
        <p class="eyebrow"><a href="/">Adspot</a></p>
        <h1>Términos y condiciones</h1>
        <p class="muted">Borrador de trabajo. Pendiente de revisión legal antes de la apertura al público.</p>

        <h2>1. Qué es este servicio</h2>
        <p>
          Adspot es un marketplace que pone en contacto a quien busca espacio publicitario
          con quien lo posee. No somos propietarios de los espacios ni agencia de medios:
          intermediamos la reserva, el pago y la prueba de publicación.
        </p>

        <h2>2. Cuentas y colaboradores</h2>
        <p>
          Quien abre una cuenta es su titular y puede invitar colaboradores. El titular
          responde de lo que hagan las personas a las que da acceso. Un colaborador no
          puede desvincularse por su cuenta: la baja la realiza el titular, o soporte a
          petición justificada.
        </p>

        <h2>3. Reservas y pagos</h2>
        <p>
          El importe de una reserva queda retenido desde su confirmación. Se libera al
          proveedor cuando el anunciante acepta la prueba de publicación. Si el anunciante
          la rechaza, el importe permanece retenido y se abre una disputa que revisa
          soporte. Un pago ya liberado no se revierte automáticamente.
        </p>

        <h2>4. Contenido de los anuncios</h2>
        <p>
          El anunciante responde del contenido que publica y de tener los derechos sobre
          él. El proveedor puede rechazar un anuncio antes de instalarlo. Podemos retirar
          de la plataforma cualquier espacio o anuncio que incumpla la ley o estas
          condiciones.
        </p>

        <h2>5. Cancelación y borrado</h2>
        <p>
          Nada que esté en uso se destruye. Un espacio con reservas vivas no se elimina:
          se despublica y su borrado queda programado. Una cuenta con disputas abiertas no
          puede eliminarse mientras la disputa siga viva, porque las pruebas pertenecen
          también a la otra parte.
        </p>

        <h2>6. Contacto</h2>
        <p>Para cualquier cuestión sobre estas condiciones, escríbenos desde tu cuenta.</p>
      </div>
    </main>
  `,
})
export class TermsComponent {}
