jQuery(document).ready(function($) {
    var selectedPosts = [];

    function updateSelectedCount() {
        selectedPosts = [];
        $('.post-checkbox:checked').each(function() {
            selectedPosts.push($(this).val());
        });
        $('#selected-count').text(selectedPosts.length + '件選択中');
        $('#bulk-generate-btn').prop('disabled', selectedPosts.length === 0);
        $('#bulk-excerpt-btn').prop('disabled', selectedPosts.length === 0);
    }

    // チェックボックス変更
    $('.post-checkbox').on('change', updateSelectedCount);
    $('#cb-select-all').on('change', function() {
        $('.post-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    // 全選択/全解除ボタン
    $('#select-all-posts').on('click', function() {
        $('.post-checkbox').prop('checked', true);
        $('#cb-select-all').prop('checked', true);
        updateSelectedCount();
    });
    $('#deselect-all-posts').on('click', function() {
        $('.post-checkbox').prop('checked', false);
        $('#cb-select-all').prop('checked', false);
        updateSelectedCount();
    });

    // Desc未生成を選択ボタン
    $('#select-no-description').on('click', function() {
        $('.post-checkbox').prop('checked', false);
        $('tr[data-has-description="0"] .post-checkbox').prop('checked', true);
        $('#cb-select-all').prop('checked', false);
        updateSelectedCount();
    });

    // Desc生成済みを選択ボタン
    $('#select-has-description').on('click', function() {
        $('.post-checkbox').prop('checked', false);
        $('tr[data-has-description="1"] .post-checkbox').prop('checked', true);
        $('#cb-select-all').prop('checked', false);
        updateSelectedCount();
    });

    // 一括ディスクリプション生成
    $('#bulk-generate-btn').on('click', function() {
        if (selectedPosts.length === 0) return;

        var btn = $(this);
        btn.prop('disabled', true).text('処理中...');
        $('#bulk-progress').show();
        $('#progress-log').empty();

        var total = selectedPosts.length;
        var current = 0;
        var success = 0;
        var failed = 0;

        function processNext() {
            if (current >= total) {
                btn.prop('disabled', false).html('✨ ディスクリプション生成');
                $('#progress-log').prepend('<div class="success"><strong>完了: ' + success + '件成功, ' + failed + '件失敗</strong></div>');
                return;
            }

            var postId = selectedPosts[current];
            var row = $('tr[data-post-id="' + postId + '"]');
            row.find('.desc-status-cell').html('<span class="status-badge processing">...</span>');

            $.ajax({
                url: kashiwazaki_bulk_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bulk_generate_description',
                    post_id: postId,
                    nonce: kashiwazaki_bulk_ajax.nonce
                },
                success: function(response) {
                    current++;
                    var percent = Math.round((current / total) * 100);
                    $('#progress-bar').css('width', percent + '%');
                    $('#progress-text').text(current + ' / ' + total);

                    if (response.success) {
                        success++;
                        var description = response.data.description;
                        row.find('.desc-status-cell').html('<span class="status-icon status-ok" title="ディスクリプション生成済み">✓</span>');
                        row.attr('data-has-description', '1');

                        // ディスクリプション表示を更新
                        var displayText = description.substring(0, 50);
                        var html = '<div class="description-display-mini">' + displayText;
                        if (description.length > 50) {
                            html += '<span class="description-more">...</span>';
                        }
                        html += '</div>';
                        row.find('.description-cell').html(html);

                        $('#progress-log').prepend('<div class="success">✓ ID:' + postId + ' - 成功</div>');
                    } else {
                        failed++;
                        row.find('.desc-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                        $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - ' + response.data + '</div>');
                    }

                    // 次の記事を処理（少し遅延を入れてAPI制限を回避）
                    setTimeout(processNext, 1000);
                },
                error: function() {
                    current++;
                    failed++;
                    row.find('.desc-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                    $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - 通信エラー</div>');
                    setTimeout(processNext, 1000);
                }
            });
        }

        processNext();
    });

    // 一括抜粋登録
    $('#bulk-excerpt-btn').on('click', function() {
        if (selectedPosts.length === 0) return;

        // ディスクリプションが設定されている記事のみをフィルタ
        var postsWithDescription = [];
        selectedPosts.forEach(function(postId) {
            var row = $('tr[data-post-id="' + postId + '"]');
            if (row.attr('data-has-description') === '1') {
                postsWithDescription.push(postId);
            }
        });

        if (postsWithDescription.length === 0) {
            alert('ディスクリプションが生成されている記事が選択されていません。\n「Desc生成済みを選択」ボタンを使用して選択してください。');
            return;
        }

        if (!confirm(postsWithDescription.length + '件の記事のディスクリプションを抜粋に登録します。よろしいですか？')) {
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('処理中...');
        $('#bulk-progress').show();
        $('#progress-log').empty();

        var total = postsWithDescription.length;
        var current = 0;
        var success = 0;
        var failed = 0;

        function processNextExcerpt() {
            if (current >= total) {
                btn.prop('disabled', false).html('📝 ディスクリプション→抜粋に登録');
                $('#progress-log').prepend('<div class="success"><strong>完了: ' + success + '件成功, ' + failed + '件失敗</strong></div>');
                return;
            }

            var postId = postsWithDescription[current];
            var row = $('tr[data-post-id="' + postId + '"]');
            row.find('.excerpt-status-cell').html('<span class="status-badge processing">...</span>');

            $.ajax({
                url: kashiwazaki_bulk_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bulk_register_excerpt',
                    post_id: postId,
                    nonce: kashiwazaki_bulk_ajax.nonce
                },
                success: function(response) {
                    current++;
                    var percent = Math.round((current / total) * 100);
                    $('#progress-bar').css('width', percent + '%');
                    $('#progress-text').text(current + ' / ' + total);

                    if (response.success) {
                        success++;
                        var excerpt = response.data.excerpt;
                        row.find('.excerpt-status-cell').html('<span class="status-icon status-ok" title="抜粋登録済み">✓</span>');

                        // 抜粋表示を更新
                        var displayText = excerpt.substring(0, 50);
                        var html = '<div class="excerpt-display-mini">' + displayText;
                        if (excerpt.length > 50) {
                            html += '<span class="excerpt-more">...</span>';
                        }
                        html += '</div>';
                        row.find('.excerpt-cell').html(html);

                        $('#progress-log').prepend('<div class="success">✓ ID:' + postId + ' - ' + response.data.message + '</div>');
                    } else {
                        failed++;
                        row.find('.excerpt-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                        $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - ' + response.data + '</div>');
                    }

                    // 次の記事を処理
                    setTimeout(processNextExcerpt, 500);
                },
                error: function() {
                    current++;
                    failed++;
                    row.find('.excerpt-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                    $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - 通信エラー</div>');
                    setTimeout(processNextExcerpt, 500);
                }
            });
        }

        processNextExcerpt();
    });
});
