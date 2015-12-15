jQuery(document).ready(function($) {
    wputh_taxometas_set_qtranslate();
});

/* ----------------------------------------------------------
  Set-up Qtranslate
---------------------------------------------------------- */

function wputh_taxometas_set_qtranslate() {
    // only proceed if qTranslate is loaded
    if (!qTranslateConfig || !qTranslateConfig.qtx) {
        return;
    }

    // Display default lang
    wputh_taxometas_qt_display_lang(qTranslateConfig.activeLanguage);

    // Toggle visible lang when user chose another.
    qTranslateConfig.qtx.addLanguageSwitchListener(function(lang_to) {
        wputh_taxometas_qt_display_lang(lang_to);
    });
}

function wputh_taxometas_qt_display_lang(lang) {
    jQuery('[data-wputaxometaslang]').hide();
    jQuery('[data-wputaxometaslang="' + lang + '"]').show();
}