
jQuery(function ($) {
    $('.tooltip-title').hover(function (e) { // Hover event
        var toolTipText = $(this).attr('title');
        $(this).data('tiptext', toolTipText).removeAttr('title');
        $('<p class="tooltip-bar"></p>').text(toolTipText).appendTo('body').css('top', (e.pageY - 10) + 'px').css('left', (e.pageX + 20) + 'px').fadeIn();
    }, function () { // Hover off event
        $(this).attr('title', $(this).data('tiptext'));
        $('.tooltip-bar').remove();
    }).mousemove(function (e) { // Mouse move event
        $('.tooltip-bar').css('top', (e.pageY - 10) + 'px').css('left', (e.pageX + 20) + 'px');
    });

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
                    $this.data('tiptext', wpmoObject.addToBlackList);
                    $('.tooltip-bar').text(wpmoObject.addToBlackList);
                } else {
                    $tr.addClass('black-list-column');
                    $this.data('tiptext', wpmoObject.removeFromBlackList);
                    $('.tooltip-bar').text(wpmoObject.removeFromBlackList);
                }
            } else
                $tr.addClass('error-blink');

            setTimeout(function () {
                $tr.removeClass('success-blink').removeClass('error-blink');
            }, 1000);
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
                }, 1500);
            });
        }
    });

    $(".wpmo-wrap .rename-table-column").on('click', function (e) {
        e.preventDefault();
        var $this = $(this),
            $tr = $this.closest('tr'),
            $blackListIcon = $tr.find('.add-remove-black-list'),
            $oldName = $this.attr('data-column'),
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
                    $this.attr('data-column', $newName);
                    $tr.find('.delete-table-column').data('column', $newName);

                    $blackListIcon.removeClass('dashicons-insert').removeClass('dashicons-remove');
                    $blackListIcon.attr('data-column', $newName);

                    if (response.data.blackListAction === 'insert') {
                        $tr.addClass('black-list-column');
                        $blackListIcon.addClass('dashicons-remove');
                        $blackListIcon.attr('title', wpmoObject.removeFromBlackList);
                        $blackListIcon.attr('data-action', 'remove');
                    } else {
                        $tr.removeClass('black-list-column');
                        $blackListIcon.addClass('dashicons-insert');
                        $blackListIcon.attr('title', wpmoObject.addToBlackList);
                        $blackListIcon.attr('data-action', 'insert');
                    }
                } else
                    $tr.addClass('error-blink');

                setTimeout(function () {
                    $tr.removeClass('success-blink').removeClass('error-blink');
                }, 1500);
            });
        }
    });
});