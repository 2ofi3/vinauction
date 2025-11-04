(function ($, Drupal, once) {
  'use strict';

  /**
   * Mobile menu toggle.
   */
  Drupal.behaviors.menuToggle = {
    attach: function (context) {
      once('menuToggle', '.js-menu-toggle', context).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();
          $('.js-menu').slideToggle('fast');
          $('.js-menu-toggle-icon').toggleClass('is-open');
        });
      });
    }
  };

  /**
   * Hero background image (set from contained <img>).
   */
  Drupal.behaviors.heroBackground = {
    attach: function (context) {
      once('heroBackground', '.js-hero', context).forEach(function (el) {
        const $el = $(el);
        const imgSrc = $el.find('img').attr('src');
        if (imgSrc) {
          $el.css('background-image', 'url(' + imgSrc + ')');
        }
      });
    }
  };

  /**
   * Gallery flexslider init.
   */
  Drupal.behaviors.gallerySlider = {
    attach: function (context) {
      once('gallerySlider', '.js-gallery', context).forEach(function (el) {
        $(el).imagesLoaded(function () {
          $(el).flexslider({
            slideshow: false,
            directionNav: false
          });
        });
      });
    }
  };

  /**
   * Toggle auction filters.
   */
  Drupal.behaviors.toggleAuctionFilters = {
    attach: function (context) {
      once('toggleAuctionFilters', '.js-toggle-auction-filters', context).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();
          $(el).toggleClass('is-active');
          $('.js-auction-filters').slideToggle('fast');
        });
      });
    }
  };

  /**
   * Handle the refresh bid button animation.
   */
  Drupal.behaviors.refreshBidButton = {
    attach: function (context) {
      once('refreshBidButton', '.c-lot-bod-wrapper #edit-actions-gethigestbid', context).forEach(function (el) {
        $(el).on('click', function (e) {
          e.preventDefault();
          $(el).toggleClass('is-active');
          console.log('Refresh bid clicked for lot:', $(el).data('lotid'));
        });
      });
    }
  };

  /**
   * Initialize countdown timers using jquery.countdown plugin.
   * (Note: consider migrating to auction.countdown.js later.)
   */
  Drupal.behaviors.lotCountdown = {
    attach: function (context) {
      once('lotCountdown', '[data-countdown]', context).forEach(function (el) {
        const $el = $(el);
        const finalDate = $el.data('countdown');

        // Initialize countdown plugin
        $el.countdown(finalDate)
          .on('update.countdown', function (event) {
            const html = [
              '<div><span>', event.strftime('%D'), '</span> day%!d </div>',
              '<div><span>', event.strftime('%H'), '</span> hr </div>',
              '<div><span>', event.strftime('%M'), '</span> min </div>',
              '<div><span>', event.strftime('%S'), '</span> sec</div>'
            ].join('');
            $el.html(html);
          })
          .on('finish.countdown', function () {
            $el.html('This offer has expired!').addClass('js-disabled');
            const parent = $el.closest('.c-lot-teaser');
            parent.find('.c-lot-title, .c-lot-teaser__enddate').addClass('js-disabled');
            parent.find('.c-lot-timer-closed').removeClass('js-disabled').addClass('js-visible');
            parent.find('.c-lot-bod-wrapper').addClass('js-disabled').removeClass('js-visible');
          });
      });

      // Handle cookie compliance scroll lock (do this once globally)
      once('cookieCompliance', 'body', context).forEach(function () {
        const $html = $('html');
        if ($('body').hasClass('eu-cookie-compliance-popup-open')) {
          $html.css({ overflow: 'hidden' });
        } else {
          $html.css({ overflow: 'auto' });
        }
      });
    }
  };

})(jQuery, Drupal, once);
