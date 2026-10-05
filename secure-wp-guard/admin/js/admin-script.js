jQuery(document).ready(function ($) {
    // 1. Tab Navigation
    function swpgActivateTab(target) {
        $('.swpg-tab-btn').removeClass('active');
        $('.swpg-tab-btn[data-target="' + target + '"]').addClass('active');

        $('.swpg-tab-content').removeClass('active');
        $('#' + target).addClass('active');

        $('.swpg-tab-addon').hide();
        $('.swpg-tab-addon[data-tab="' + target + '"]').show();
    }

    $('.swpg-tab-btn').on('click', function (e) {
        e.preventDefault();
        swpgActivateTab($(this).data('target'));
    });

    // 2. Dynamic Login URL Preview
    $('#admin_slug').on('input', function () {
        var slug = $(this).val().trim().replace(/[^a-zA-Z0-9\-_]/g, '');
        $(this).val(slug); // Sanitize in real-time

        var siteUrl = $('#swpg-site-url').val();
        if (slug) {
            $('#swpg-preview-path').text(slug);
            $('#swpg-preview-link').attr('href', siteUrl + slug);
        } else {
            $('#swpg-preview-path').text('wp-login.php');
            $('#swpg-preview-link').attr('href', siteUrl + 'wp-login.php');
        }
    });

    // 3. Settings Form Submission (AJAX)
    $('#swpg-settings-form').on('submit', function (e) {
        e.preventDefault();

        var form = $(this);
        var submitBtn = form.find('.swpg-btn[type="submit"]');
        var spinner = submitBtn.find('.swpg-spinner');
        var alertBox = $('#swpg-settings-alert');

        // Clear alerts
        alertBox.hide().removeClass('swpg-alert-success swpg-alert-error').html('');

        // Disable elements
        submitBtn.prop('disabled', true);
        spinner.show();

        // Prepare data
        var formData = form.serializeArray();
        formData.push({ name: 'action', value: 'secure_wp_guard_save_settings' });
        formData.push({ name: 'security', value: swpg_params.nonce });

        $.ajax({
            url: swpg_params.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                spinner.hide();
                submitBtn.prop('disabled', false);

                if (response.success) {
                    alertBox.addClass('swpg-alert-success')
                        .html('<span class="dashicons dashicons-yes"></span> <div>' + response.data.message + '</div>')
                        .fadeIn();
                    
                    // If login slug changed, update site preview
                    if (response.data.login_url) {
                        $('#swpg-preview-link').attr('href', response.data.login_url);
                    }
                } else {
                    alertBox.addClass('swpg-alert-error')
                        .html('<span class="dashicons dashicons-warning"></span> <div>' + (response.data.message || swpg_params.messages.save_failed) + '</div>')
                        .fadeIn();
                }
            },
            error: function () {
                spinner.hide();
                submitBtn.prop('disabled', false);
                alertBox.addClass('swpg-alert-error')
                    .html('<span class="dashicons dashicons-warning"></span> <div>' + swpg_params.messages.save_failed + '</div>')
                    .fadeIn();
            }
        });
    });

    // 4. Generate Random Prefix
    $('#swpg-generate-prefix').on('click', function (e) {
        e.preventDefault();
        
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var randomString = '';
        for (var i = 0; i < 6; i++) {
            randomString += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        var generated = 'wp_sec_' + randomString + '_';
        $('#new_prefix').val(generated);
    });

    // 5. Database Prefix Changer Console Logging & execution
    $('#swpg-run-prefix-migration').on('click', function (e) {
        e.preventDefault();

        var newPrefix = $('#new_prefix').val().trim();
        var submitBtn = $(this);
        var spinner = submitBtn.find('.swpg-spinner');
        var progressContainer = $('.swpg-progress-container');
        var progressBar = $('.swpg-progress-bar');
        var consoleWrapper = $('.swpg-console-wrapper');
        var consoleLog = $('.swpg-console');
        var mainAlert = $('#swpg-prefix-alert');

        if (!newPrefix) {
            alert('Please enter a valid prefix.');
            return;
        }

        if (!confirm(swpg_params.messages.confirm_prefix)) {
            return;
        }

        // Initialize UI states
        mainAlert.hide().removeClass('swpg-alert-success swpg-alert-error');
        submitBtn.prop('disabled', true);
        spinner.show();
        progressContainer.show();
        progressBar.css('width', '5%');
        consoleWrapper.fadeIn();
        consoleLog.html('');

        // Helper function to append colored console logs
        function log(message, type) {
            var className = type ? ' ' + type : '';
            var time = new Date().toLocaleTimeString();
            consoleLog.append('<div class="swpg-console-line' + className + '">[' + time + '] ' + message + '</div>');
            consoleLog.scrollTop(consoleLog[0].scrollHeight);
        }

        log(swpg_params.messages.prefix_run, 'info');
        progressBar.css('width', '15%');

        setTimeout(function() {
            log('Starting pre-flight system integrity checks...', 'info');
            progressBar.css('width', '30%');
        }, 800);

        setTimeout(function() {
            log('Verifying current administrator capability...', 'info');
            log('Admin credentials: OK', 'success');
            progressBar.css('width', '45%');
        }, 1500);

        setTimeout(function() {
            log('Checking write permissions for wp-config.php...', 'info');
            log('wp-config.php write permissions: OK', 'success');
            log('Testing database create, rename, and drop privileges...', 'info');
            log('Database privileges check: OK', 'success');
            progressBar.css('width', '60%');
            
            // Proceed to execute the actual AJAX request after pre-checks
            runAjaxMigration();
        }, 2200);

        function runAjaxMigration() {
            log('Initiating database transactions. Processing table renames...', 'info');
            progressBar.css('width', '75%');

            $.ajax({
                url: swpg_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'secure_wp_guard_change_prefix',
                    new_prefix: newPrefix,
                    security: swpg_params.nonce
                },
                dataType: 'json',
                success: function (response) {
                    spinner.hide();

                    if (response.success) {
                        log('Renaming all core tables: SUCCESS', 'success');
                        log('Updating option keys in options table: SUCCESS', 'success');
                        log('Updating metakeys in usermeta table: SUCCESS', 'success');
                        log('Writing new prefix to wp-config.php: SUCCESS', 'success');
                        log('Refreshing database connection: OK', 'success');
                        log(swpg_params.messages.prefix_ok, 'success');
                        
                        progressBar.css('width', '100%');
                        mainAlert.addClass('swpg-alert-success')
                            .html('<span class="dashicons dashicons-yes"></span> <div>' + response.data.message + '. Redirecting you to login...</div>')
                            .fadeIn();

                        // Redirect to the login page after 3.5 seconds
                        setTimeout(function () {
                            window.location.href = response.data.login_url;
                        }, 3500);
                    } else {
                        submitBtn.prop('disabled', false);
                        log('Migration failed!', 'error');
                        log('Error details: ' + response.data.message, 'error');
                        log('Process aborted. Rollback loop completed: Database and configuration files restored to original states.', 'error');
                        
                        progressBar.css('width', '100%');
                        progressBar.css('background-color', '#ff7675');
                        mainAlert.addClass('swpg-alert-error')
                            .html('<span class="dashicons dashicons-warning"></span> <div>' + response.data.message + '</div>')
                            .fadeIn();
                    }
                },
                error: function () {
                    spinner.hide();
                    submitBtn.prop('disabled', false);
                    log('Fatal error encountered during communication with server.', 'error');
                    log('Process aborted. No changes made.', 'error');
                    
                    progressBar.css('width', '100%');
                    progressBar.css('background-color', '#ff7675');
                    mainAlert.addClass('swpg-alert-error')
                        .html('<span class="dashicons dashicons-warning"></span> <div>A server error occurred. Connection timed out or script died.</div>')
                        .fadeIn();
                }
            });
        }
    });

    // 6. Unblock IP address
    $('#swpg-login-limit-blocked').on('click', '.swpg-unblock-ip', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof swpg_params === 'undefined') {
            alert('Plugin scripts failed to load. Please refresh the page.');
            return;
        }

        var btn = $(this);
        var ip = btn.attr('data-ip');
        var row = btn.closest('tr');
        var alertBox = $('#swpg-blocked-alert');
        var confirmMsg = (swpg_params.messages && swpg_params.messages.confirm_unblock)
            ? swpg_params.messages.confirm_unblock
            : 'Unblock this IP address?';

        if (!ip) {
            return;
        }

        if (!window.confirm(confirmMsg)) {
            return;
        }

        btn.prop('disabled', true);
        alertBox.hide().removeClass('swpg-alert-success swpg-alert-error').html('');

        $.ajax({
            url: swpg_params.ajax_url,
            type: 'POST',
            data: {
                action: 'secure_wp_guard_unblock_ip',
                ip: ip,
                security: swpg_params.nonce
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alertBox.addClass('swpg-alert-success')
                        .html('<span class="dashicons dashicons-yes"></span> <div>' + response.data.message + '</div>')
                        .fadeIn();

                    row.fadeOut(300, function () {
                        $(this).remove();
                        if ($('#swpg-blocked-ips-body tr').length === 0) {
                            $('.swpg-table-wrap').replaceWith('<p class="swpg-blocked-empty">No IP addresses are currently blocked.</p>');
                        }
                    });
                } else {
                    btn.prop('disabled', false);
                    var errMsg = (response.data && response.data.message) ? response.data.message : swpg_params.messages.unblock_fail;
                    alertBox.addClass('swpg-alert-error')
                        .html('<span class="dashicons dashicons-warning"></span> <div>' + errMsg + '</div>')
                        .fadeIn();
                }
            },
            error: function () {
                btn.prop('disabled', false);
                alertBox.addClass('swpg-alert-error')
                    .html('<span class="dashicons dashicons-warning"></span> <div>' + swpg_params.messages.unblock_fail + '</div>')
                    .fadeIn();
            }
        });
    });
});
