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
        $(document).on('click', '.entrma-modal-close, .entrma-modal-overlay', function(e) {
            e.preventDefault();
            var $modal = $(this).closest('.entrma-modal');
            closeModal($modal);
        });

        // ESC key closes active modal
        $(document).keyup(function(e) {
            if (e.key === "Escape") {
                $('.entrma-modal:visible').fadeOut(200);
            }
        });

        /* ------------------------------------------------------------------
         * 1. COLUMN CUSTOMIZATION MODAL
         * ------------------------------------------------------------------ */
        $('#entrma-customize-cols-btn').on('click', function(e) {
            e.preventDefault();
            openModal($('#entrma-cols-modal'));
        });

        $('#entrma-save-cols-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var formData = $('#entrma-cols-form').serializeArray();
            
            var postData = {
                action: 'entrma_save_column_settings',
                nonce: entrmaData.nonce,
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

            $btn.prop('disabled', true).text(entrmaData.i18n.saving);

            $.post(entrmaData.ajaxUrl, postData, function(response) {
                $btn.prop('disabled', false).text('Save Column Preferences');
                if (response.success) {
                    closeModal($('#entrma-cols-modal'));
                    window.location.reload();
                } else {
                    alert(response.data || entrmaData.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Save Column Preferences');
                alert(entrmaData.i18n.error);
            });
        });

        /* ------------------------------------------------------------------
         * 2. VIEW ENTRY MODAL
         * ------------------------------------------------------------------ */
        $(document).on('click', '.entrma-view-btn', function(e) {
            e.preventDefault();
            var entryId = $(this).data('id');
            var $modal = $('#entrma-view-modal');
            var $body  = $('#entrma-view-modal-body');

            $body.html('<div class="entrma-loading"><span class="spinner is-active"></span> Loading entry details...</div>');
            openModal($modal);

            $.post(entrmaData.ajaxUrl, {
                action: 'entrma_get_entry',
                nonce: entrmaData.nonce,
                entry_id: entryId,
                mode: 'view'
            }, function(response) {
                if (response.success) {
                    $body.html(response.data.html);
                } else {
                    $body.html('<div class="notice notice-error"><p>' + (response.data || entrmaData.i18n.error) + '</p></div>');
                }
            }).fail(function() {
                $body.html('<div class="notice notice-error"><p>' + entrmaData.i18n.error + '</p></div>');
            });
        });

        /* ------------------------------------------------------------------
         * 3. EDIT ENTRY MODAL
         * ------------------------------------------------------------------ */
        $(document).on('click', '.entrma-edit-btn', function(e) {
            e.preventDefault();
            var entryId = $(this).data('id');
            var $modal = $('#entrma-edit-modal');
            var $body  = $('#entrma-edit-modal-body');

            $body.html('<div class="entrma-loading"><span class="spinner is-active"></span> Loading entry editor...</div>');
            openModal($modal);

            $.post(entrmaData.ajaxUrl, {
                action: 'entrma_get_entry',
                nonce: entrmaData.nonce,
                entry_id: entryId,
                mode: 'edit'
            }, function(response) {
                if (response.success) {
                    $body.html(response.data.html);
                } else {
                    $body.html('<div class="notice notice-error"><p>' + (response.data || entrmaData.i18n.error) + '</p></div>');
                }
            }).fail(function() {
                $body.html('<div class="notice notice-error"><p>' + entrmaData.i18n.error + '</p></div>');
            });
        });

        $('#entrma-save-edit-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $form = $('#entrma-edit-entry-form');

            if (!$form.length) {
                return;
            }

            var formData = $form.serialize();
            $btn.prop('disabled', true).text(entrmaData.i18n.saving);

            $.post(entrmaData.ajaxUrl, formData + '&action=entrma_update_entry&nonce=' + entrmaData.nonce, function(response) {
                $btn.prop('disabled', false).text('Update Entry');
                if (response.success) {
                    closeModal($('#entrma-edit-modal'));
                    window.location.reload();
                } else {
                    alert(response.data || entrmaData.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Update Entry');
                alert(entrmaData.i18n.error);
            });
        });

        /* ------------------------------------------------------------------
         * 4. DELETE ENTRY (SINGLE)
         * ------------------------------------------------------------------ */
        $(document).on('click', '.entrma-delete-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var entryId = $btn.data('id');
            var $row = $btn.closest('tr');

            if (!confirm(entrmaData.i18n.confirmDelete)) {
                return;
            }

            $btn.prop('disabled', true);

            $.post(entrmaData.ajaxUrl, {
                action: 'entrma_delete_entry',
                nonce: entrmaData.nonce,
                entry_id: entryId
            }, function(response) {
                if (response.success) {
                    $row.css('background', '#fbeaea').fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    $btn.prop('disabled', false);
                    alert(response.data || entrmaData.i18n.error);
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                alert(entrmaData.i18n.error);
            });
        });

    });
})(jQuery);
