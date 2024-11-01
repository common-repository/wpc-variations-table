'use strict';

(function($) {
  let wpcvt_media = {
    frame: null,
    button: null,
    upload_id: null,
    post_id: wp.media.model.settings.post.id,
  };

  $(function() {
    wpcvt_image_selector();

    if ($('input[name="_wpcvt_active"]:checked').val() === 'yes') {
      $('.wpcvt_show_if_active').css('display', 'flex');
    }
  });

  $(document).
      on('woocommerce_variations_added woocommerce_variations_loaded',
          function() {
            wpcvt_image_selector();
          });

  $(document).on('click touch', 'a.wpcvt_image_remove', function(e) {
    e.preventDefault();
    $(this).
        closest('.wpcvt_image_selector').
        find('.wpcvt_image_id').val('').trigger('change');
    $(this).
        closest('.wpcvt_image_selector').
        find('.wpcvt_image_preview').html('');
  });

  $(document).on('change', 'input[name="_wpcvt_active"]', function() {
    if ($(this).val() === 'yes') {
      $('.wpcvt_show_if_active').css('display', 'flex');
    } else {
      $('.wpcvt_show_if_active').css('display', 'none');
    }
  });

  $('#woocommerce-product-data').
      on('woocommerce_variations_loaded', function() {
        var data = {
          action: 'wpcvt_dropdown_attributes',
          pid: $('#post_ID').val(),
        };

        $.post(ajaxurl, data, function(response) {
          $('.wpcvt_dropdown_attributes').html(response);
        });
      });

  function wpcvt_image_selector() {
    $('a.wpcvt_image_add').on('click touch', function(e) {
      e.preventDefault();

      var $button = $(this);
      var upload_id = parseInt($button.attr('rel'));

      wpcvt_media.button = $button;

      if (upload_id) {
        wpcvt_media.upload_id = upload_id;
      } else {
        wpcvt_media.upload_id = wpcvt_media.post_id;
      }

      if (wpcvt_media.frame) {
        wpcvt_media.frame.uploader.uploader.param('post_id',
            wpcvt_media.upload_id);
        wpcvt_media.frame.open();
        return;
      } else {
        wp.media.model.settings.post.id = wpcvt_media.upload_id;
      }

      wpcvt_media.frame = wp.media.frames.wpcvt_media = wp.media({
        title: wpcvt_vars.media_title, button: {
          text: wpcvt_vars.media_add_text,
        }, library: {
          type: 'image',
        }, multiple: true,
      });

      wpcvt_media.frame = wp.media.frames.wpcvt_media = wp.media({
        title: wpcvt_vars.media_title, button: {
          text: wpcvt_vars.media_add_text,
        }, library: {
          type: 'image',
        }, multiple: true,
      });

      wpcvt_media.frame.on('select', function() {
        var selection = wpcvt_media.frame.state().get('selection');
        var $preview = wpcvt_media.button.
            closest('.wpcvt_image_selector').
            find('.wpcvt_image_preview');
        var $image_id = wpcvt_media.button.
            closest('.wpcvt_image_selector').
            find('.wpcvt_image_id');

        selection.map(function(attachment) {
          attachment = attachment.toJSON();

          if (attachment.id) {
            var url = attachment.sizes.thumbnail ?
                attachment.sizes.thumbnail.url :
                attachment.url;
            $preview.html('<img src="' + url +
                '" /><a class="wpcvt_image_remove button" href="#">' +
                wpcvt_vars.media_remove + '</a>');
            $image_id.val(attachment.id).trigger('change');
          }
        });

        wp.media.model.settings.post.id = wpcvt_media.post_id;
      });

      wpcvt_media.frame.open();
    });
  }
})(jQuery);