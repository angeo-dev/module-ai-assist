/**
 * Copyright (c) 2026 Ievgenii Gryshkun (angeo.dev)
 * MIT License — see LICENSE for full terms.
 *
 * The AI assistant panel. Sends only {source, ref, task, question}; the
 * server builds the facts. The answer is shown as text with a small Markdown
 * subset. Everything is escaped first, and links are never made clickable:
 * an answer can quote text from the store or from other AI models, and a
 * clickable link there would be an easy phishing path.
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function inline(text) {
        // text is already escaped
        return text
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    }

    /**
     * Markdown subset: headings, paragraphs, - and 1. lists, fenced code,
     * inline code, bold, a whole-line _note_. Returns safe HTML.
     */
    function render(markdown) {
        var lines = String(markdown).replace(/\r\n?/g, '\n').split('\n'),
            html = [],
            list = null,
            para = [],
            code = null,
            i,
            line,
            m;

        function flushPara() {
            if (para.length) {
                html.push('<p>' + inline(escapeHtml(para.join(' '))) + '</p>');
                para = [];
            }
        }

        function flushList() {
            if (list) {
                html.push('<' + list.tag + '>' + list.items.map(function (item) {
                    return '<li>' + inline(escapeHtml(item)) + '</li>';
                }).join('') + '</' + list.tag + '>');
                list = null;
            }
        }

        for (i = 0; i < lines.length; i++) {
            line = lines[i];

            if (code !== null) {
                if (/^\s*```/.test(line)) {
                    html.push(
                        '<div class="angeo-ai__code"><button type="button" class="angeo-ai__copy">' +
                        escapeHtml($t('Copy')) + '</button><pre><code>' + escapeHtml(code.join('\n')) + '</code></pre></div>'
                    );
                    code = null;
                } else {
                    code.push(line);
                }
                continue;
            }
            if (/^\s*```/.test(line)) {
                flushPara();
                flushList();
                code = [];
                continue;
            }
            if (/^\s*$/.test(line)) {
                flushPara();
                flushList();
                continue;
            }
            m = line.match(/^\s*#{1,4}\s+(.*)$/);
            if (m) {
                flushPara();
                flushList();
                html.push('<h4>' + inline(escapeHtml(m[1])) + '</h4>');
                continue;
            }
            m = line.match(/^\s*[-*]\s+(.*)$/) || line.match(/^\s*\d+[.)]\s+(.*)$/);
            if (m) {
                flushPara();
                var tag = /^\s*\d/.test(line) ? 'ol' : 'ul';
                if (!list || list.tag !== tag) {
                    flushList();
                    list = {tag: tag, items: []};
                }
                list.items.push(m[1]);
                continue;
            }
            m = line.match(/^_(.+)_$/);
            if (m) {
                flushPara();
                flushList();
                html.push('<p class="angeo-ai__small">' + escapeHtml(m[1]) + '</p>');
                continue;
            }
            if (list && /^\s{2,}\S/.test(line)) {
                list.items[list.items.length - 1] += ' ' + line.trim();
                continue;
            }
            flushList();
            para.push(line.trim());
        }
        if (code !== null) {
            html.push('<pre><code>' + escapeHtml(code.join('\n')) + '</code></pre>');
        }
        flushPara();
        flushList();

        return html.join('');
    }

    var component = function (config, element) {
        var $root = $(element),
            $buttons = $root.find('button[data-task], form[data-role="ask"] button'),
            $status = $root.find('[data-role="status"]'),
            $answer = $root.find('[data-role="answer"]'),
            $meta = $root.find('[data-role="meta"]'),
            $input = $root.find('#angeo-ai-question'),
            ref = config.ref || '',
            busy = false;

        function setReady(ready) {
            $buttons.prop('disabled', !ready || busy);
            $input.prop('disabled', !ready);
            $root.find('[data-role="waiting"]').prop('hidden', ready);
        }

        function ask(task, question) {
            if (busy || !ref) {
                return;
            }
            busy = true;
            setReady(true);
            $status.removeClass('angeo-ai__status--error').text($t('Thinking… this can take up to a minute.'));
            $answer.prop('hidden', true).empty();
            $meta.prop('hidden', true).empty();

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY,
                    source: config.source,
                    ref: ref,
                    task: task,
                    question: question || ''
                }
            }).done(function (data) {
                if (!data || !data.ok) {
                    $status.addClass('angeo-ai__status--error').text((data && data.error) || $t('No answer.'));
                    return;
                }
                $status.text('');
                $answer.html(render(data.answer)).prop('hidden', false);
                $meta.text(
                    $t('AI answer. Check it before you change anything.') + ' ' +
                    data.model + (data.cached ? ' · ' + $t('from cache') : '') +
                    ' · ' + $t('%1 new answers left today').replace('%1', String(data.remaining))
                ).prop('hidden', false);
            }).fail(function (xhr) {
                $status.addClass('angeo-ai__status--error').text(
                    xhr.status === 403 || xhr.status === 302
                        ? $t('Your session ended or you have no permission. Reload the page.')
                        : $t('The request failed. Try again.')
                );
            }).always(function () {
                busy = false;
                setReady(Boolean(ref));
            });
        }

        $root.on('click', 'button[data-task]', function () {
            ask($(this).data('task'), '');
        });
        $root.on('submit', 'form[data-role="ask"]', function (event) {
            event.preventDefault();
            var question = String($input.val() || '').trim();
            if (question) {
                ask('ask', question);
            }
        });
        $root.on('click', '.angeo-ai__copy', function () {
            var $button = $(this),
                text = $button.siblings('pre').text();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    $button.text($t('Copied'));
                    setTimeout(function () { $button.text($t('Copy')); }, 1500);
                });
            }
        });

        // Pages that load their record in the browser (the brand-visibility
        // run page) announce it: $(document).trigger('angeo-ai-assist:ref',
        // {source: 'brand_visibility', ref: '42'}).
        $(document).on('angeo-ai-assist:ref', function (event, data) {
            if (data && data.source === config.source && /^[A-Za-z0-9_.:=&\-]{1,64}$/.test(String(data.ref))) {
                ref = String(data.ref);
                $answer.prop('hidden', true).empty();
                $meta.prop('hidden', true).empty();
                $status.text('');
                setReady(true);
            }
        });

        setReady(Boolean(ref));
    };
    // Exposed for tests.
    component.render = render;

    return component;
});
