/**
 * @file
 * Handles live countdown timers for lots.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.auctionCountdown = {
    attach: function (context) {
      once('auctionCountdown', '.c-lot-timer[data-end-ts]', context).forEach(function (el) {
        const $el = $(el);
        const endTs = parseInt($el.attr('data-end-ts'), 10);
        const nowServer = parseInt($el.attr('data-server-now'), 10) || Math.floor(Date.now() / 1000);

        // Correctie: verschil tussen lokale en server tijd.
        const offset = Math.floor(Date.now() / 1000) - nowServer;

        function updateCountdown() {
          const now = Math.floor(Date.now() / 1000) - offset;
          let remaining = endTs - now;

          if (remaining <= 0) {
            $el.html('<span class="countdown-finished">Lot afgesloten</span>');
            clearInterval(timer);
            return;
          }

          const hours = Math.floor(remaining / 3600);
          remaining %= 3600;
          const minutes = Math.floor(remaining / 60);
          const seconds = remaining % 60;

          const formatted =
            (hours > 0 ? hours + 'u ' : '') +
            String(minutes).padStart(2, '0') + 'm ' +
            String(seconds).padStart(2, '0') + 's';

          $el.find('.js-countdown').text(formatted);
        }

        updateCountdown();
        const timer = setInterval(updateCountdown, 1000);
      });
    }
  };

})(jQuery, Drupal);
