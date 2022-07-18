
jQuery(function ($) {
    // Tabs
    $(".wpmo-tab").on('click', function (e) {
        e.preventDefault();
        $('.wpmo-tab-content').hide();
        $(".wpmo-tab").removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('#' + $(this).attr('id') + '-content').show();
    });

    $(".wpmo-wrap .add-remove-black-list").on('click', function (e) {
        e.preventDefault();
        var $this = $(this),
            $tr = $this.closest('tr');

        var data = {
            'action': 'wpmo_add_remove_black_list',
            'type': $this.data('type'),
            'column': $this.data('column'),
            'list_action': $this.data('action'),
            'nonce': wpmoObject.nonce
        };

        jQuery.post(wpmoObject.ajaxurl, data, function (response) {
            if (response.success) {
                $this.removeClass('dashicons-' + $this.data('action')).addClass('dashicons-' + response.data.newAction);
                $this.data('action', response.data.newAction);
                $tr.addClass('success-blink');
                $('#' + $this.data('type') + '_black_list').val(response.data.list);
                if (response.data.newAction === 'insert') {
                    $tr.removeClass('black-list-column');
                    $this.attr('title', wpmoObject.addToBlackList);
                } else {
                    $tr.addClass('black-list-column');
                    $this.attr('title', wpmoObject.removeFromBlackList);
                }
            } else
                $tr.addClass('error-blink');

            setTimeout(function () {
                $tr.removeClass('success-blink').removeClass('error-blink');
            }, 2000);
        });
    });

    $(".wpmo-wrap .delete-table-column").on('click', function (e) {
        e.preventDefault();
        var $this = $(this),
            $tr = $this.closest('tr');

        if (confirm(wpmoObject.deleteColumnMessage + "\n" + $this.data('column'))) {
            var data = {
                'action': 'wpmo_delete_table_column',
                'type': $this.data('type'),
                'column': $this.data('column'),
                'nonce': wpmoObject.nonce
            };

            jQuery.post(wpmoObject.ajaxurl, data, function (response) {
                if (response.success)
                    $tr.fadeOut(function () {
                        $tr.remove();
                    });
                else
                    $tr.addClass('error-blink');

                setTimeout(function () {
                    $tr.removeClass('error-blink');
                }, 2000);
            });
        }
    });

    $(".wpmo-wrap .rename-table-column").on('click', function (e) {
        e.preventDefault();
        var $this = $(this),
            $tr = $this.closest('tr'),
            $oldName = $this.data('column'),
            $newName = prompt(wpmoObject.renamePromptColumnMessage, $oldName);

        if ($newName != null && $newName != '' && $newName !== $oldName && confirm(wpmoObject.renameConfirmColumnMessage + "\n" + wpmoObject.oldName + ': ' + $oldName + "\n" + wpmoObject.newName + ': ' + $newName)) {
            var data = {
                'action': 'wpmo_rename_table_column',
                'type': $this.data('type'),
                'column': $oldName,
                'newColumnName': $newName,
                'nonce': wpmoObject.nonce
            };

            jQuery.post(wpmoObject.ajaxurl, data, function (response) {
                if (response.success) {
                    $tr.find('.column-name').text($newName);
                    $tr.addClass('success-blink');
                    $this.data('column', $newName);
                    $tr.find('.delete-table-column').data('column', $newName);
                } else
                    $tr.addClass('error-blink');

                setTimeout(function () {
                    $tr.removeClass('success-blink').removeClass('error-blink');
                }, 2000);
            });
        }
    });
});