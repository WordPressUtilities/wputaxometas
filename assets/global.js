'use strict';

jQuery(document).ready(function() {
    wputh_taxometas_set_addtag();
    wputh_taxometas_set_media();
    wputh_taxometas_set_colorpicker();
    wputh_taxometas_set_checkbox();
});

/* ----------------------------------------------------------
  Add tag
---------------------------------------------------------- */

var wputh_taxometas_set_addtag = function() {
    jQuery(document).ajaxComplete(function(e, response) {
        if (!response.responseText) {
            return;
        }
        if (response.responseText.match(/<wp_ajax><response action='add-tag_/)) {
            // Unset medias
            jQuery('.wputaxometas_add_media').trigger('unset_media');
            // Clear TinyMCE
            if (typeof tinyMCE == 'object' && tinyMCE.activeEditor) {
                tinyMCE.activeEditor.setContent('');
            }
            // Reset fields
            jQuery("#addtag").trigger('reset');
        }
    });
};

/* ----------------------------------------------------------
  Checkbox
---------------------------------------------------------- */

var wputh_taxometas_set_checkbox = function() {
    jQuery('.wpu-taxometas-input-checkbox').each(function() {
        var $this = jQuery(this),
            $hidden = $this.parent().find('[type=hidden]');

        function set_checked() {
            $hidden.val($this.is(':checked') ? '1' : '0');
        }
        set_checked();
        $this.on('change', set_checked);
    });
};

/* ----------------------------------------------------------
  Color Picker
---------------------------------------------------------- */

var wputh_taxometas_set_colorpicker = function() {
    jQuery('.wpu-taxometas-form [type=color]').attr('type', 'text').wpColorPicker();
};

/* ----------------------------------------------------------
  Upload files
---------------------------------------------------------- */

var wputaxometas_file_frame,
    wputaxometas_datafor;

var wputh_taxometas_set_media = function() {

    function unset_media($this) {
        var $button = false,
            $preview = false,
            $parent = false;

        wputaxometas_datafor = $this.data('for');
        $preview = jQuery('#preview-' + wputaxometas_datafor);
        $parent = $preview.parent();
        $button = $parent.find('.wputaxometas_add_media');

        // Delete preview HTML
        $preview.html('');

        // Change button text
        $button.html($preview.attr('data-baselabel'));

        // Reset attachment value
        jQuery('#' + wputaxometas_datafor).attr('value', '');
    }

    jQuery('body').on('click', '.wpu-taxometas-upload-wrap .x', function(e) {
        e.preventDefault();
        unset_media(jQuery(this));
    });

    jQuery('body').on('unset_media', '.wputaxometas_add_media', function() {
        unset_media(jQuery(this));
    });

    jQuery('body').on('click', '.wputaxometas_add_media', function(event) {
        event.preventDefault();
        var $this = jQuery(this);

        wputaxometas_datafor = $this.data('for');

        // If the media frame already exists, reopen it.
        if (wputaxometas_file_frame) {
            wputaxometas_file_frame.open();
            return;
        }

        // Create the media frame.
        wputaxometas_file_frame = wp.media.frames.wputaxometas_file_frame = wp.media({
            title: $this.data('uploader_title'),
            button: {
                text: $this.data('uploader_button_text'),
            },
            multiple: false // Set to true to allow multiple files to be selected
        });

        // When an image is selected, run a callback.
        wputaxometas_file_frame.on('select', function() {
            // We set multiple to false so only get one image from the uploader
            var attachment = wputaxometas_file_frame.state().get('selection').first().toJSON(),
                $preview = jQuery('#preview-' + wputaxometas_datafor);

            // Set attachment ID
            jQuery('#' + wputaxometas_datafor).attr('value', attachment.id);

            // Set preview image
            $preview.html('<img class="wpu-taxometas-upload-preview" src="' + attachment.url + '" /><span data-for="' + wputaxometas_datafor + '" class="x">&times;</span>');

            // Change button label
            $this.html($preview.attr('data-label'));

        });

        // Finally, open the modal
        wputaxometas_file_frame.open();
    });
};
