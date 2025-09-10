(function ($) {
    'use strict';

    function formatErrorMessage(errorMsg) {
        if (!errorMsg) {
            errorMsg = '不明なエラーが発生しました';
        }

        errorMsg = $('<div>').text(errorMsg).html();

        errorMsg = errorMsg.replace(/(https?:\/\/[^\s\)]+)/g, function (match) {
            var displayUrl = match.length > 50 ? match.substring(0, 47) + '...' : match;
            return '<a href="' + match + '" target="_blank" style="color: #0073aa; text-decoration: underline;">' + displayUrl + '</a>';
        });

        var lines = errorMsg.split('\n');
        var formattedLines = [];

        lines.forEach(function (line) {
            if (line.length > 80) {
                var words = line.split(' ');
                var currentLine = '';

                words.forEach(function (word) {
                    if ((currentLine + word).length > 80 && currentLine.length > 0) {
                        formattedLines.push(currentLine.trim());
                        currentLine = word + ' ';
                    } else {
                        currentLine += word + ' ';
                    }
                });

                if (currentLine.trim()) {
                    formattedLines.push(currentLine.trim());
                }
            } else {
                formattedLines.push(line);
            }
        });

        var finalMessage = formattedLines.join('<br>');

        var errorType = 'エラー';
        var iconStyle = '❌';
        var bgColor = '#ffe6e6';
        var borderColor = '#ff9999';
        var textColor = '#d32f2f';

        if (finalMessage.includes('タイムアウト')) {
            errorType = 'タイムアウト';
            iconStyle = '⏰';
            bgColor = '#fff3e0';
            borderColor = '#ffcc02';
            textColor = '#f57c00';
        } else if (finalMessage.includes('ネットワーク')) {
            errorType = 'ネットワークエラー';
            iconStyle = '🌐';
            bgColor = '#e3f2fd';
            borderColor = '#2196f3';
            textColor = '#1976d2';
        } else if (finalMessage.includes('API')) {
            errorType = 'API エラー';
            iconStyle = '🔑';
        }

        return '<div style="background: ' + bgColor + '; border: 1px solid ' + borderColor + '; border-radius: 5px; padding: 15px; margin: 10px 0;">' +
            '<div style="display: flex; align-items: center; margin-bottom: 8px;">' +
            '<span style="font-size: 18px; margin-right: 8px;">' + iconStyle + '</span>' +
            '<strong style="color: ' + textColor + '; font-size: 14px;">' + errorType + '</strong>' +
            '</div>' +
            '<div style="color: ' + textColor + '; font-size: 13px; line-height: 1.5; word-wrap: break-word; overflow-wrap: break-word;">' +
            finalMessage +
            '</div>' +
            '<div style="margin-top: 10px; padding: 8px; background: rgba(255,255,255,0.7); border-radius: 3px; font-size: 11px; color: #666;">' +
            '<strong>💡 対処法:</strong> ' +
            '<span>設定画面でモデルやAPIキーを確認してください。問題が続く場合は、除外されたモデルを復活させるか、別のモデルを選択してください。</span>' +
            '</div>' +
            '</div>';
    }

    $(document).ready(function () {
        var debugLogVisible = false;

        function addDebugLog(message) {
            var timestamp = new Date().toLocaleTimeString();
            var logEntry = '[' + timestamp + '] ' + message;

            var $debugContent = $('#debug-content');
            if ($debugContent.length > 0) {
                var $logDiv = $('<div>').text(logEntry);
                $debugContent.append($logDiv);
                $debugContent.scrollTop($debugContent[0].scrollHeight);
            }
        }

        $('#toggle-debug').on('click', function () {
            debugLogVisible = !debugLogVisible;
            $('#debug-log').toggle(debugLogVisible);
            $(this).text(debugLogVisible ? '🔧 デバッグログを非表示' : '🔧 デバッグログを表示');
        });

        $('#copy-all-logs').on('click', function () {
            var allLogs = $('#debug-content').text();
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(allLogs).then(function () {
                    var originalText = $('#copy-all-logs').text();
                    $('#copy-all-logs').text('✅ コピー完了');
                    setTimeout(function () {
                        $('#copy-all-logs').text(originalText);
                    }, 2000);
                });
            } else {
                var textArea = document.createElement('textarea');
                textArea.value = allLogs;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                var originalText = $('#copy-all-logs').text();
                $('#copy-all-logs').text('✅ コピー完了');
                setTimeout(function () {
                    $('#copy-all-logs').text(originalText);
                }, 2000);
            }
        });

        $('#generate-description-btn').on('click', function () {
            var $button = $(this);
            var $loading = $('#description-loading');
            var $result = $('#description-result');
            var postId = $('#post_ID').val();

            if (!postId) {
                return;
            }

            addDebugLog('✨ Description生成を開始: 投稿ID=' + postId);

            $button.prop('disabled', true);
            $loading.show();
            $result.empty();

            $.ajax({
                url: kashiwazaki_description_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_description',
                    post_id: postId,
                    nonce: kashiwazaki_description_ajax.nonce
                },
                timeout: 30000,
                success: function (response) {
                    addDebugLog('✅ AJAX成功: ' + JSON.stringify(response));

                    if (response.success) {
                        var description = '';
                        var usedModel = '';
                        var switchedModel = false;

                        if (typeof response.data === 'object') {
                            description = response.data.description || response.data;
                            usedModel = response.data.used_model || '';
                            switchedModel = response.data.switched_model || false;
                        } else {
                            description = response.data;
                        }

                        addDebugLog('📝 生成されたdescription: ' + description);
                        addDebugLog('🤖 使用モデル: ' + usedModel);

                        var characterCount = description.length;
                        var displayHtml = '<div class="description-display">' +
                            '<div class="description-text" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9; font-size: 13px; line-height: 1.4;">' +
                            escapeHtml(description) +
                            '</div>' +
                            '<div style="margin-top: 8px; font-size: 11px; color: #666;">' +
                            '文字数: <span id="description-char-count">' + characterCount + '</span>文字' +
                            '</div>' +
                            '</div>' +
                            '<div style="margin-top: 10px;">' +
                            '<button type="button" id="copy-description-btn" style="padding: 4px 12px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">📋 descriptionをコピー</button>' +
                            '</div>';

                        if (usedModel) {
                            var modelColor = switchedModel ? '#f57c00' : '#2e7d32';
                            var modelIcon = switchedModel ? '⚠️' : '🤖';
                            displayHtml += '<div style="margin-top: 8px; padding: 6px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px; color: ' + modelColor + ';">' +
                                modelIcon + ' 使用モデル: <strong>' + escapeHtml(usedModel) + '</strong>' +
                                '</div>';
                        }

                        if (response.data.message) {
                            displayHtml += '<div style="margin-top: 8px; padding: 8px; background: #fff3e0; border: 1px solid #ff9800; border-radius: 3px; font-size: 12px; color: #ef6c00;">' +
                                escapeHtml(response.data.message) +
                                '</div>';
                        }

                        $result.html(displayHtml);
                        $('#description-textarea').val(description);

                        addDebugLog('✅ Description表示完了');

                    } else {
                        var errorMessage = response.data || 'エラーが発生しました';
                        addDebugLog('❌ AJAX処理エラー: ' + errorMessage);
                        $result.html(formatErrorMessage(errorMessage));
                    }
                },
                error: function (xhr, status, error) {
                    var errorMessage = 'AJAX通信エラー: ' + status + ' - ' + error;
                    addDebugLog('❌ ' + errorMessage);
                    $result.html(formatErrorMessage(errorMessage));
                },
                complete: function () {
                    $button.prop('disabled', false);
                    $loading.hide();
                    addDebugLog('🏁 Description生成処理完了');
                }
            });
        });

        $(document).on('click', '#copy-description-btn', function () {
            var description = $('#description-textarea').val();
            if (description) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(description).then(function () {
                        var $button = $('#copy-description-btn');
                        var originalText = $button.text();
                        $button.text('✅ コピー完了');
                        setTimeout(function () {
                            $button.text(originalText);
                        }, 2000);
                        addDebugLog('📋 Descriptionをクリップボードにコピーしました');
                    });
                } else {
                    var textArea = document.createElement('textarea');
                    textArea.value = description;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);

                    var $button = $('#copy-description-btn');
                    var originalText = $button.text();
                    $button.text('✅ コピー完了');
                    setTimeout(function () {
                        $button.text(originalText);
                    }, 2000);
                    addDebugLog('📋 Descriptionをクリップボードにコピーしました（フォールバック方式）');
                }
            }
        });

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        addDebugLog('🚀 Kashiwazaki SEO Description JavaScript初期化完了');
    });
})(jQuery);
