(function ($) {

    /*
     * Responsible for admin custom js
     */

    jQuery(document).ready(function () {

        jQuery('.olzrepeater').repeaterolz({
            initEmpty: false,
            show: function () {
                jQuery(this).slideDown("slow", function () { });
            },
            hide: function (deleteElement) {
                jQuery(this).slideUp(deleteElement);
            }
        });


        jQuery(document).on('click', '#olza-refresh', function (e) {

            var olz_obj = jQuery(this);

            e.preventDefault();
            let refresh_flag = olza_global_admin.confirm_msg;
            if (confirm(refresh_flag) == true) {

                jQuery('.olza-admin-spinner').show();

                olz_obj.prop('disabled', true);

                var countryField = jQuery('input[name="olza_options[country_codes]"]');
                var speditionField = jQuery('input[name="olza_options[spedition_codes]"]');
                var clearField = jQuery('input[name="olza_options[clear_before_sync]"]');

                var countries = countryField.length ? countryField.val().trim() : '';
                var speditions = speditionField.length ? speditionField.val().trim() : '';
                var clearBeforeSync = clearField.length && clearField.is(':checked') ? 'yes' : 'no';

                if (!countries.length) {
                    alert(olza_global_admin.country_required);
                    olz_obj.prop('disabled', false);
                    jQuery('.olza-admin-spinner').hide();
                    return false;
                }

                var olza_data = {
                    nonce: olza_global_admin.nonce,
                    action: 'olza_get_pickup_point_files',
                    countries: countries,
                    speditions: speditions,
                    clear_before_sync: clearBeforeSync
                };
                $.ajax({
                    type: 'POST',
                    data: olza_data,
                    dataType: 'json',
                    url: olza_global_admin.ajax_url,
                    crossDomain: true,
                    cache: false,
                    async: true,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', olza_global_admin.nonce);
                    },
                }).done(function (response) {
                    alert(response.message);
                }).fail(function () {
                    alert(olza_global_admin.generic_error);
                }).always(function () {
                    olz_obj.prop('disabled', false);
                    jQuery('.olza-admin-spinner').hide();
                });

            } else {
                return false;
            }

        });

    });

})(jQuery);