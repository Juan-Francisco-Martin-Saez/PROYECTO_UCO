class UcoFooter extends HTMLElement {
  connectedCallback() {
    this.innerHTML = `
    <footer class="footer" id="contacto">
      <div class="contact-head">
        <div>
          <span class="eyebrow">05 — CONTACTO</span>
          <h2>Hablemos de <em>lo que necesitas.</em></h2>
        </div>
        <p>Estamos en Sa Pobla.<br>Contacta con nuestro equipo o ven a visitarnos.</p>
      </div>
      <div class="contact-layout">
        <form class="contact-form" id="uco-contact-form">
          <div class="contact-form-heading">
            <strong>Escríbenos</strong>
            <span>Te responderemos desde nuestro canal habitual.</span>
          </div>
          <div class="contact-fields">
            <label>
              Nombre
              <input name="nombre" autocomplete="given-name" placeholder="Tu nombre" required maxlength="60">
            </label>
            <label>
              Apellidos
              <input name="apellidos" autocomplete="family-name" placeholder="Tus apellidos" required maxlength="100">
            </label>
            <label>
              Correo electrónico
              <input type="email" name="correo" autocomplete="email" placeholder="tu@empresa.com" required maxlength="200">
            </label>
            <label>
              Teléfono
              <input type="tel" name="telefono" autocomplete="tel" placeholder="Tu teléfono" required maxlength="30">
            </label>
            <label class="full">
              Mensaje
              <textarea name="mensaje" rows="4" placeholder="¿En qué podemos ayudarte?" required maxlength="3000"></textarea>
            </label>
          </div>
          <label class="contact-consent">
            <input type="checkbox" name="consent" required>
            <span>He leído la <a href="https://ucosapobla.com/es/content/2-politica-de-privacidad" target="_blank"
                rel="noopener noreferrer">política de privacidad</a> y acepto que se utilicen mis datos para responder a
              esta consulta.</span>
          </label>
          <button class="pill primary contact-submit" type="submit">Enviar formulario</button>
        </form>
        <div class="contact-location">
          <div class="map-frame">
            <iframe title="Mapa de UCO Sa Pobla, calle Príncep 19" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
              src="https://www.google.com/maps?q=Carrer%20del%20Pr%C3%ADncep%2019%2C%2007420%20Sa%20Pobla%2C%20Mallorca&output=embed">
            </iframe>
          </div>
          <div class="location-info">
            <div>
              <span class="eyebrow">VEN A VERNOS</span>
              <strong>C/ Príncep, 19</strong>
              <p>07420 Sa Pobla · Mallorca, Illes Balears</p>
            </div>
            <div>
              <span class="eyebrow">LLÁMANOS</span>
              <a href="tel:+34971862020">+34 971 862 020</a>
              <a href="mailto:clientes@ucosapobla.es">clientes@ucosapobla.es</a>
            </div>
          </div>
        </div>
      </div>
      <div class="footer-main">
        <div class="footer-brand">
          <a href="#inicio" aria-label="UCO, volver al inicio">
            <img src="./img/uco-logo-pie.svg" alt="UCO">
          </a>
          <p>Alimentación para profesionales.<br>Sa Pobla · Mallorca</p>
        </div>
        <div class="footer-links">
          <div>
            <strong>EXPLORA</strong>
            <a href="#inicio">Inicio</a>
            <a href="#familias">Familias</a>
            <a href="#seleccion">Selección</a>
            <a href="#pedidos">Pedidos</a>
          </div>
          <div>
            <strong>UCO</strong>
            <a href="#nosotros">Nuestra filosofía</a>
            <a href="#contacto">Contacto</a>
            <a href="https://ucosapobla.com/es/" target="_blank" rel="noopener noreferrer">Web actual ↗</a>
          </div>
        </div>
        <div class="footer-message">
          Buenas ideas.<br>
          <em>Buenos productos.</em>
          <a class="footer-phone" href="tel:+34971862020">+34 971 862 020</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© ${new Date().getFullYear()} UCO SA POBLA</span>
        <span>PROTOTIPO VISUAL 0.7 · CATÁLOGO Y PEDIDOS EN PREPARACIÓN</span>
        <a href="#inicio">VOLVER ARRIBA</a>
      </div>
    </footer>`;
    this.querySelector('#uco-contact-form').addEventListener('submit', e => {
      e.preventDefault();
      const form = e.currentTarget;
      if (!form.reportValidity()) return;
      const d = new FormData(form),
        subject = 'Consulta desde UCO 2.0 — ' + [d.get('nombre'), d.get('apellidos')].map(x => String(x).trim()).join(' '),
        body = `Nombre: ${d.get('nombre')}\nApellidos: ${d.get('apellidos')}\nCorreo: ${d.get('correo')}\nTeléfono: ${d.get('telefono')}\n\nMensaje:\n${d.get('mensaje')}`;
      location.href = 'mailto:clientes@ucosapobla.es?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    })
  }
}
customElements.define('uco-footer', UcoFooter);
