/**
 * @file
 * Alpine.js component for the htmx dashboard.
 *
 * htmx does the server work; this component only reacts to htmx events to
 * give feedback. Dragging comes from the Alpine sort plugin, see the x-sort
 * attributes in htmxdemo-dashboard.html.twig.
 *
 * This file must run before Alpine starts, so it is loaded without defer.
 * See htmxdemo.libraries.yml.
 */
((Drupal) => {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('htmxdemoDashboard', () => ({
      toast: '',
      toastTimer: null,

      /**
       * Shows a short-lived status message.
       *
       * @param {string} message
       *   The message to show.
       */
      notify(message) {
        this.toast = message;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => {
          this.toast = '';
        }, 2000);
      },

      /**
       * Handles htmx:after:request from the add card form.
       *
       * @param {CustomEvent} event
       *   The htmx event; event.detail.ctx holds the request context.
       */
      cardAdded(event) {
        const { ctx } = event.detail;
        // The server answers HX-Reswap: none when the card was not added.
        if (ctx.response?.status === 200 && ctx.hx?.reswap !== 'none') {
          this.notify(Drupal.t('Card added'));
          event.target.reset();
        }
      },

      /**
       * Handles htmx:after:request from the grid, which saves the order.
       *
       * @param {CustomEvent} event
       *   The htmx event; event.detail.ctx holds the request context.
       */
      layoutSaved(event) {
        const { ctx } = event.detail;
        this.notify(
          ctx.response?.status < 300
            ? Drupal.t('Layout saved')
            : Drupal.t('The layout could not be saved'),
        );
      },
    }));

    /**
     * A clock that ticks in the browser, started from the server's time.
     *
     * Used by the Site status card. htmx re-renders it on every poll, which
     * re-syncs it with the server.
     *
     * @param {number} serverTime
     *   The server time in milliseconds since the epoch.
     * @param {string} timeZone
     *   The IANA timezone to display the time in.
     */
    window.Alpine.data('htmxdemoClock', (serverTime, timeZone) => ({
      time: '',
      timer: null,
      offset: serverTime - Date.now(),
      format: new Intl.DateTimeFormat(undefined, {
        timeZone,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
      }),

      init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
      },

      // Alpine calls this when htmx swaps the card body out.
      destroy() {
        clearInterval(this.timer);
      },

      tick() {
        this.time = this.format.format(Date.now() + this.offset);
      },
    }));
  });
})(Drupal);
