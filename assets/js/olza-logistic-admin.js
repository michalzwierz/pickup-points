(function ($) {

    function normalizeCode(code) {
        return (code || '')
            .toString()
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\-_]/g, '');
    }

    function parseCodes(value) {
        if (!value) {
            return [];
        }

        return value.split(',').map(normalizeCode).filter(function (code) {
            return !!code;
        });
    }

    $(document).ready(function () {
        $('.olzrepeater').repeaterolz({
            initEmpty: false,
            show: function () {
                $(this).slideDown('slow', function () { });
            },
            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            }
        });

        var $countryField = $('input[name="olza_options[country_codes]"]');
        var $speditionField = $('input[name="olza_options[spedition_codes]"]');
        var $clearField = $('input[name="olza_options[clear_before_sync]"]');

        var providerData = $.extend(true, {}, olza_global_admin.available_providers || {});
        var selectedProviders = new Set();
        var providerUI = null;

        function ensureProviderUI() {
            if (!$speditionField.length) {
                return null;
            }

            if (!providerUI) {
                var $cell = $speditionField.closest('td');
                providerUI = {
                    container: $('<div class="olza-provider-picker"></div>'),
                    heading: null,
                    groups: null
                };

                if ($cell.length) {
                    providerUI.heading = $('<p class="description olza-provider-heading"></p>').text(olza_global_admin.provider_heading || '');
                    providerUI.groups = $('<div class="olza-provider-groups"></div>');

                    providerUI.container.append(providerUI.heading);
                    providerUI.container.append(providerUI.groups);
                    $cell.append(providerUI.container);
                }
            }

            return providerUI;
        }

        function setSelectedProviders(codes) {
            selectedProviders = new Set();

            (codes || []).forEach(function (code) {
                code = normalizeCode(code);
                if (code) {
                    selectedProviders.add(code);
                }
            });
        }

        function getSelectedProvidersArray() {
            return Array.from(selectedProviders);
        }

        function updateCheckboxStates() {
            if (!providerUI || !providerUI.groups) {
                return;
            }

            providerUI.groups.find('input.olza-provider-checkbox').each(function () {
                var code = normalizeCode($(this).val());
                $(this).prop('checked', selectedProviders.has(code));
            });
        }

        function syncFieldFromSelection() {
            if (!$speditionField.length) {
                return;
            }

            var currentValue = getSelectedProvidersArray().join(', ');
            $speditionField.val(currentValue);
        }

        function renderProviderList(data) {
            providerData = $.extend(true, {}, data || {});

            if (!providerUI || !providerUI.groups) {
                return;
            }

            providerUI.groups.empty();

            var hasProviders = false;

            $.each(providerData, function (countryCode, providers) {
                if (!$.isArray(providers) || !providers.length) {
                    return;
                }

                var $group = $('<div class="olza-provider-group"></div>');
                var $title = $('<h4 class="olza-provider-country"></h4>').text(countryCode);
                var $list = $('<div class="olza-provider-options"></div>');

                providers.forEach(function (provider) {
                    var code = normalizeCode(provider.code || provider);
                    if (!code) {
                        return;
                    }

                    var label = provider.label || code.toUpperCase();
                    var inputId = 'olza-provider-' + countryCode.toLowerCase() + '-' + code.replace(/[^a-z0-9]+/g, '-');
                    var $option = $('<label class="olza-provider-option" for="' + inputId + '"></label>');
                    var $checkbox = $('<input type="checkbox" class="olza-provider-checkbox" />').attr('id', inputId).val(code);

                    $option.append($checkbox).append(' ' + label);
                    $list.append($option);
                });

                if ($list.children().length) {
                    hasProviders = true;
                    $group.append($title).append($list);
                    providerUI.groups.append($group);
                }
            });

            if (!hasProviders) {
                providerUI.groups.append(
                    $('<p class="description olza-provider-empty"></p>').text(olza_global_admin.provider_empty || '')
                );
            }

            updateCheckboxStates();
        }

        providerUI = ensureProviderUI();

        if ($speditionField.length) {
            var initialSelection = [];

            if ($.isArray(olza_global_admin.selected_providers) && olza_global_admin.selected_providers.length) {
                initialSelection = olza_global_admin.selected_providers;
            } else {
                initialSelection = parseCodes($speditionField.val());
            }

            setSelectedProviders(initialSelection);
            renderProviderList(providerData);
            syncFieldFromSelection();
            updateCheckboxStates();

            $speditionField.on('input change', function () {
                var manualCodes = parseCodes($(this).val());
                setSelectedProviders(manualCodes);
                updateCheckboxStates();
            });
        }

        if (providerUI && providerUI.groups) {
            providerUI.groups.on('change', 'input.olza-provider-checkbox', function () {
                var code = normalizeCode($(this).val());
                if (!code) {
                    return;
                }

                if ($(this).is(':checked')) {
                    selectedProviders.add(code);
                } else {
                    selectedProviders.delete(code);
                }

                syncFieldFromSelection();
            });
        }

        $(document).on('click', '#olza-refresh', function (e) {
            var $button = $(this);

            e.preventDefault();
            var refreshFlag = olza_global_admin.confirm_msg;

            if (!confirm(refreshFlag)) {
                return false;
            }

            $('.olza-admin-spinner').show();
            $button.prop('disabled', true);

            var countries = $countryField.length ? $.trim($countryField.val()) : '';
            var speditions = $speditionField.length ? $.trim($speditionField.val()) : '';
            var clearBeforeSync = ($clearField.length && $clearField.is(':checked')) ? 'yes' : 'no';

            if (!countries.length) {
                alert(olza_global_admin.country_required);
                $button.prop('disabled', false);
                $('.olza-admin-spinner').hide();
                return false;
            }

            var requestData = {
                nonce: olza_global_admin.nonce,
                action: 'olza_get_pickup_point_files',
                countries: countries,
                speditions: speditions,
                clear_before_sync: clearBeforeSync
            };

            $.ajax({
                type: 'POST',
                data: requestData,
                dataType: 'json',
                url: olza_global_admin.ajax_url,
                crossDomain: true,
                cache: false,
                async: true,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', olza_global_admin.nonce);
                }
            }).done(function (response) {
                if (response && response.message) {
                    alert(response.message);
                }

                if (response && response.providers !== undefined) {
                    if ($.isArray(response.selected_providers)) {
                        setSelectedProviders(response.selected_providers);
                    }

                    renderProviderList(response.providers);
                    syncFieldFromSelection();
                }
            }).fail(function () {
                alert(olza_global_admin.generic_error);
            }).always(function () {
                $button.prop('disabled', false);
                $('.olza-admin-spinner').hide();
            });
        });
    });

})(jQuery);
