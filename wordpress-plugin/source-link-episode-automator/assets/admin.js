/* WordPress Source Link Automator Admin JS */

jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.slea-tab-content').hide();
        $('#slea-tab-' + tabId).show();
    });

    // Custom interval input toggle
    $('#slea-cron-interval').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#slea-custom-interval-wrap').slideDown();
        } else {
            $('#slea-custom-interval-wrap').slideUp();
        }
    });

    // Reset Run Counter
    $('#slea-btn-reset-counter').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Resetting...');

        $.ajax({
            url: sleaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'slea_reset_counter',
                nonce: sleaData.nonce
            },
            success: function(res) {
                $btn.prop('disabled', false).text('Reset Counter');
                if (res.success) {
                    var stats = res.data;
                    var display = (stats.max_runs > 0) ? (stats.runs_completed + ' / ' + stats.max_runs) : ('Run #' + stats.runs_completed + ' (Continuous)');
                    $('#slea-stat-runs-display').text(display);
                    alert('Run counter has been reset to 0.');
                    location.reload();
                } else {
                    alert('Error: ' + (res.data ? res.data.message : 'Unable to reset.'));
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).text('Reset Counter');
                alert('Request error: ' + err);
            }
        });
    });

    // Run Batch Automation
    $('#slea-btn-run-batch').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        var limit = $btn.data('limit') || 10;
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processing ' + limit + ' Pending Posts Safely...');

        var $fb = $('#slea-batch-feedback');
        $fb.hide().removeClass('notice-success notice-error notice-warning').html('<p>Processing batch in strict post-level isolation. Please wait...</p>').slideDown();

        $.ajax({
            url: sleaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'slea_process_batch',
                limit: limit,
                nonce: sleaData.nonce
            },
            success: function(res) {
                $btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    var data = res.data;
                    var successCount = 0;
                    var reportHtml = '<strong>Batch Completed:</strong> Processed ' + data.count + ' posts.<br><ul style="margin:8px 0 0 16px; list-style:disc;">';

                    if (data.results && data.results.length) {
                        data.results.forEach(function(r) {
                            if (r.success) {
                                successCount++;
                                reportHtml += '<li style="color:#0a8553;">Post #' + r.post_id + ': Extracted ' + r.extracted_count + ' episode buttons & marked as Ready.</li>';
                                $('#slea-post-row-' + r.post_id).css('background-color', '#eafaf1').fadeOut(1200);
                            } else {
                                reportHtml += '<li style="color:#d63638;">Post #' + r.post_id + ': ' + (r.error || r.message || 'Failed (kept as Pending)') + '</li>';
                            }
                        });
                    }
                    reportHtml += '</ul>';

                    $fb.removeClass('notice-warning notice-error').addClass('notice-success').html(reportHtml).slideDown();

                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $fb.addClass('notice-error').html('<strong>Batch Aborted:</strong> ' + (res.data ? res.data.message : 'Unknown error')).slideDown();
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).html(originalText);
                $fb.addClass('notice-error').html('<strong>Batch Request Error:</strong> ' + err).slideDown();
            }
        });
    });

    // Run ALL Pending Posts Sequentially
    $('#slea-btn-run-all').on('click', function() {
        if (!confirm('This will sequentially process ALL pending posts in the queue one by one, bypassing redirects, generating isolated episode buttons, and marking each successful post as Ready. Continue?')) {
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processing All Sequentially...');

        var $buttons = $('.slea-btn-process-post:not(:disabled)');
        if ($buttons.length === 0) {
            alert('No actionable pending posts with valid source links found in queue.');
            $btn.prop('disabled', false).html(originalText);
            return;
        }

        var index = 0;
        var total = $buttons.length;
        var successCount = 0;
        var $fb = $('#slea-batch-feedback');
        $fb.removeClass('notice-error notice-success').addClass('notice-warning').html('<p><strong>Sequential Queue Runner Active:</strong> Processing post 1 of ' + total + '...</p>').slideDown();

        function processNext() {
            if (index >= total) {
                $btn.prop('disabled', false).html(originalText);
                $fb.removeClass('notice-warning').addClass('notice-success').html('<strong>All Completed!</strong> Processed ' + total + ' pending posts (' + successCount + ' successfully marked as Ready).').slideDown();
                setTimeout(function() {
                    location.reload();
                }, 2000);
                return;
            }

            var $currBtn = $($buttons[index]);
            var postId = $currBtn.data('post-id');
            $currBtn.prop('disabled', true).text('Processing...');
            $fb.html('<p><strong>Sequential Queue Runner:</strong> Processing post ' + (index + 1) + ' of ' + total + ' (Post #' + postId + ')...</p>');

            $.ajax({
                url: sleaData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'slea_process_single',
                    post_id: postId,
                    nonce: sleaData.nonce
                },
                success: function(res) {
                    if (res.success) {
                        successCount++;
                        $currBtn.removeClass('button-primary').addClass('button-secondary').text('Marked Ready');
                        $('#slea-post-row-' + postId).css('background-color', '#eafaf1').fadeOut(1000);
                    } else {
                        $currBtn.text('Failed (Pending)');
                    }
                    index++;
                    processNext();
                },
                error: function() {
                    $currBtn.text('Error (Pending)');
                    index++;
                    processNext();
                }
            });
        }

        processNext();
    });

    // Process Single Post
    $('.slea-btn-process-post').on('click', function() {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Resolving...');

        $.ajax({
            url: sleaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'slea_process_single',
                post_id: postId,
                nonce: sleaData.nonce
            },
            success: function(res) {
                if (res.success) {
                    $btn.removeClass('button-primary').addClass('button-secondary').text('Ready');
                    var $row = $('#slea-post-row-' + postId);
                    $row.css('background-color', '#eafaf1');
                    alert('Post #' + postId + ' successfully resolved and marked as Ready with ' + res.data.extracted_count + ' episode buttons!');
                } else {
                    $btn.prop('disabled', false).text(originalText);
                    alert('Failed to automate post #' + postId + ': ' + (res.data ? (res.data.error || res.data.message) : 'Error') + '\nPost remains in Pending status.');
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).text(originalText);
                alert('Request failed: ' + err);
            }
        });
    });

    // Refresh Pending list
    $('#slea-btn-refresh-pending').on('click', function() {
        location.reload();
    });

    // Live URL Tester
    $('#slea-btn-test-run').on('click', function() {
        var $btn = $(this);
        var testUrl = $('#slea-test-url-input').val();
        if (!testUrl) {
            alert('Please enter a URL to test.');
            return;
        }

        var origText = $btn.text();
        $btn.prop('disabled', true).text('Resolving & Extracting...');
        $('#slea-test-output').hide();

        $.ajax({
            url: sleaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'slea_test_url',
                test_url: testUrl,
                nonce: sleaData.nonce
            },
            success: function(res) {
                $btn.prop('disabled', false).text(origText);
                if (res.success) {
                    var data = res.data;
                    $('#slea-test-meta').html(
                        '<p><strong>Original URL:</strong> ' + data.original + '<br>' +
                        '<strong>Final Destination:</strong> ' + data.final + '<br>' +
                        '<strong>Bypassed Hops:</strong> ' + data.redirects + '<br>' +
                        '<strong>Buttons Extracted:</strong> ' + (data.items ? data.items.length : 0) + '</p>'
                    );

                    $('#slea-test-buttons-preview').html(data.preview_html);
                    $('#slea-test-html-code').val(data.preview_html);
                    $('#slea-test-output').slideDown();
                } else {
                    alert('Test failed: ' + (res.data ? res.data.message : 'Error'));
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).text(origText);
                alert('Test request error: ' + err);
            }
        });
    });

    // Clear logs
    $('#slea-btn-clear-logs').on('click', function() {
        if (!confirm('Are you sure you want to clear all execution logs?')) return;
        $.ajax({
            url: sleaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'slea_clear_logs',
                nonce: sleaData.nonce
            },
            success: function() {
                location.reload();
            }
        });
    });
});

