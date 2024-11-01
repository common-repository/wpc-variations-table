'use strict';

(function($) {
  if (typeof wc_add_to_cart_params === 'undefined') {
    var wc_add_to_cart_params = wpcvt_vars;
  }

  $(function() {
    if (!$('.wpcvt-wrap').length) {
      return;
    }

    $('.wpcvt-wrap').each(function() {
      wpcvt_init($(this));
    });
  });

  $(document).on('change keyup mouseup', '.wpcvt-qty', function() {
    let $wrap = $(this).closest('.wpcvt-wrap');

    wpcvt_init($wrap);
  });

  $(document).on('click touch', '.wpcvt_atc_btn', function() {
    let $btn = $(this);
    let $wrap = $btn.closest('.wpcvt-wrap');
    let data = {};
    let variations = [];

    $(document.body).
        trigger('wpcvt_adding_to_cart', [$btn]);
    $(document.body).trigger('adding_to_cart', [$btn]);

    $wrap.find('.wpcvt-variation[data-purchasable="yes"]').each(function() {
      let variation = {};
      let id = parseInt($(this).data('id'));
      let pid = parseInt($(this).data('pid'));
      let qty = parseFloat($(this).find('.wpcvt-qty').val());

      if (id > 0 && pid > 0 && qty > 0) {
        variation.id = id;
        variation.pid = pid;
        variation.qty = qty;
        variation.attrs = $(this).data('attrs');

        variations.push(variation);
      }
    });

    if (variations.length) {
      data.action = 'wpcvt_add_to_cart';
      data.variations = variations;
      data.nonce = wpcvt_vars.nonce;

      $btn.removeClass('added').addClass('loading');

      $.post(wpcvt_vars.ajax_url, data, function(response) {
        if (!response) {
          return;
        }

        if (response.error && response.product_url) {
          window.location = response.product_url;
          return;
        }

        // Redirect to cart option
        if (wc_add_to_cart_params.cart_redirect_after_add === 'yes') {
          window.location = wc_add_to_cart_params.cart_url;
          return;
        }

        $btn.removeClass('loading');

        // Trigger event so themes can refresh other areas.
        $(document.body).
            trigger('added_to_cart', [
              response.fragments, response.cart_hash, $btn]);
        $(document.body).
            trigger('wpcvt_added_to_cart', [
              response.fragments, response.cart_hash, $btn]);
      });
    }
  });

  function wpcvt_init($wrap) {
    if ($wrap.find(
        '.wpcvt-variations-table:not(.wpcvt-variations-table-initialized)').length) {
      $wrap.find(
          '.wpcvt-variations-table:not(.wpcvt-variations-table-initialized)').
          addClass('wpcvt-variations-table-initialized').
          DataTable(JSON.parse(wpcvt_vars.datatable_params));
    }

    if ($wrap.find('.wpcvt-actions').length) {
      // save qty
      let qty = 0;

      $wrap.find('.wpcvt-variation[data-purchasable="yes"]').each(function() {
        qty += parseFloat($(this).find('.wpcvt-qty').val());
      });

      $wrap.find('.wpcvt_atc_count').html(qty);

      if (qty > 0) {
        $wrap.find('.wpcvt_atc_btn').
            removeClass('disabled wpcvt_atc_btn_disabled');
      } else {
        $wrap.find('.wpcvt_atc_btn').
            addClass('disabled wpcvt_atc_btn_disabled');
      }
    }

    jQuery(document).trigger('wpcvt_init', [$wrap]);
  }
})(jQuery);