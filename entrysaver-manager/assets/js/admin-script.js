/**
 * EntrySaver Manager Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Helper: Close Modals
        function closeModal($modal) {
            $modal.fadeOut(200);
        }

        function openModal($modal) {
            $modal.fadeIn(200);
        }

        // Close Modal Events
        $(document).on('click', '.cf7em-modal-close, .cf7em-modal-overlay', function(e) {
            e.preventDefault();
            var $modal = $(this).closest('.cf7em-modal');
            closeModal($modal);
        });

        // ESC key closes active modal
        $(document).keyup(function(e) {
            if (e.key === "Escape") {
                $('.cf7em-modal:visible').fadeOut(200);
            }
        });

        /* ------------------------------------------------------------------
         * 1. COLUMN CUSTOMIZATION MODAL
         * ------------------------------------------------------------------ */
        $('#cf7em-customize-cols-btn').on('click', function(e) {
            e.preventDefault();
            openModal($('#cf7em-cols-modal'));
        });

        $('#cf7em-save-cols-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var formData = $('#cf7em-cols-form').serializeArray();
            
            var postData = {
                action: 'cf7em_save_column_settings',
                nonce: cf7emData.nonce,
            };

            $.each(formData, function(i, field) {
                if (field.name.indexOf('[]') !== -1) {
                    if (!postData[field.name]) {
                        postData[field.name] = [];
                    }
                    postData[field.name].push(field.value);
                } else {
                    postData[field.name] = field.value;
                }
            });

            $btn.prop('disabled', true).text(cf7emData.i18n.saving);

            $.post(cf7emData.ajaxUrl, postData, function(response) {
                $btn.prop('disabled', false).text('Save Column Preferences');
                if (response.success) {
                    closeModal($('#cf7em-cols-modal'));
                    window.location.reload();
                } else {
                    alert(response.data || cf7emData.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Save Column Preferences');
                alert(cf7emData.i18n.error);
            });
        });

        /* ------------------------------------------------------------------
         * 2. VIEW ENTRY MODAL
         * ------------------------------------------------------------------ */
        $(document).on('click', '.cf7em-view-btn', function(e) {
            e.preventDefault();
            var entryId = $(this).data('id');
            var $modal = $('#cf7em-view-modal');
            var $body  = $('#cf7em-view-modal-body');

            $body.html('<div class="cf7em-loading"><span class="spinner is-active"></span> Loading entry details...</div>');
            openModal($modal);

            $.post(cf7emData.ajaxUrl, {
                action: 'cf7em_get_entry',
                nonce: cf7emData.nonce,
                entry_id: entryId,
                mode: 'view'
            }, function(response) {
                if (response.success) {
                    $body.html(response.data.html);
                } else {
                    $body.html('<div class="notice notice-error"><p>' + (response.data || cf7emData.i18n.error) + '</p></div>');
                }
            }).fail(function() {
                $body.html('<div class="notice notice-error"><p>' + cf7emData.i18n.error + '</p></div>');
            });
        });

        /* ------------------------------------------------------------------
         * 3. EDIT ENTRY MODAL
         * ------------------------------------------------------------------ */
        $(document).on('click', '.cf7em-edit-btn', function(e) {
            e.preventDefault();
            var entryId = $(this).data('id');
            var $modal = $('#cf7em-edit-modal');
            var $body  = $('#cf7em-edit-modal-body');

            $body.html('<div class="cf7em-loading"><span class="spinner is-active"></span> Loading entry editor...</div>');
            openModal($modal);

            $.post(cf7emData.ajaxUrl, {
                action: 'cf7em_get_entry',
                nonce: cf7emData.nonce,
                entry_id: entryId,
                mode: 'edit'
            }, function(response) {
                if (response.success) {
                    $body.html(response.data.html);
                } else {
                    $body.html('<div class="notice notice-error"><p>' + (response.data || cf7emData.i18n.error) + '</p></div>');
                }
            }).fail(function() {
                $body.html('<div class="notice notice-error"><p>' + cf7emData.i18n.error + '</p></div>');
            });
        });

        $('#cf7em-save-edit-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $form = $('#cf7em-edit-entry-form');

            if (!$form.length) {
                return;
            }

            var formData = $form.serialize();
            $btn.prop('disabled', true).text(cf7emData.i18n.saving);

            $.post(cf7emData.ajaxUrl, formData + '&action=cf7em_update_entry&nonce=' + cf7emData.nonce, function(response) {
                $btn.prop('disabled', false).text('Update Entry');
                if (response.success) {
                    closeModal($('#cf7em-edit-modal'));
                    window.location.reload();
                } else {
                    alert(response.data || cf7emData.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Update Entry');
                alert(cf7emData.i18n.error);
            });
        });

        /* ------------------------------------------------------------------
         * 4. DELETE ENTRY (SINGLE)
         * ------------------------------------------------------------------ */
        $(document).on('click', '.cf7em-delete-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var entryId = $btn.data('id');
            var $row = $btn.closest('tr');

            if (!confirm(cf7emData.i18n.confirmDelete)) {
                return;
            }

            $btn.prop('disabled', true);

            $.post(cf7emData.ajaxUrl, {
                action: 'cf7em_delete_entry',
                nonce: cf7emData.nonce,
                entry_id: entryId
            }, function(response) {
                if (response.success) {
                    $row.css('background', '#fbeaea').fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    $btn.prop('disabled', false);
                    alert(response.data || cf7emData.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                alert(cf7emData.i18n.error);
            });
        });

    });
})(jQuery);
