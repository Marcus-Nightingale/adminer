<?php

namespace Adminer;

/** Copy table structure as Markdown, JSON, text, CSV, or SQL
* @link https://www.adminer.org/plugins/#use
* @author Marcus Nightingale
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class CopyStructureExport extends Plugin
{
    public function head()
    {
        $copyTableAs = json_encode($this->lang('Copy table as'));
        $copied = json_encode($this->lang('Copied'));
        $copyFailed = json_encode($this->lang('Copy failed'));
        $column = json_encode($this->lang('Column'));
        $type = json_encode($this->lang('Type'));
        $comment = json_encode($this->lang('Comment'));

        echo <<<HTML
<style>
    #sb-structure-copy {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .65rem;
        margin: .5rem 0 .75rem;
        font: inherit;
    }
    #sb-structure-copy .sb-structure-copy-label {
        color: inherit;
        opacity: .75;
    }
    #sb-structure-copy button {
        padding: 0;
        border: 0;
        background: none;
        color: blue;
        cursor: pointer;
        font: inherit;
        text-decoration: none;
    }
    #sb-structure-copy button:hover,
    #sb-structure-copy button:focus {
        color: red;
        text-decoration: underline;
    }
    #sb-structure-copy button + button {
        margin-left: .05rem;
    }
    #sb-structure-copy .sb-structure-copy-status {
        margin-left: .25rem;
        color: #1C8439;
        font-weight: 400;
        min-width: 6rem;
    }
</style>
HTML;

        echo '<script type="text/javascript" ' . nonce() . '>' . <<<HTML
(function () {
    var copyTableAs = $copyTableAs;
    var copied = $copied;
    var copyFailed = $copyFailed;
    var columnLabel = $column;
    var typeLabel = $type;
    var commentLabel = $comment;

    function normalize(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function escapeMarkdownCell(value) {
        return normalize(value)
            .replace(/\\/g, '\\\\')
            .replace(/\|/g, '\\|')
            .replace(/\r?\n/g, '<br>');
    }

    function escapeCsvCell(value) {
        return '"' + normalize(value).replace(/"/g, '""') + '"';
    }

    function escapeSqlIdentifier(value) {
        return '`' + normalize(value).replace(/`/g, '``') + '`';
    }

    function escapeSqlString(value) {
        return "'" + normalize(value).replace(/\\/g, '\\\\').replace(/'/g, "''") + "'";
    }

    function toMarkdown(rows) {
        var lines = ['| ' + columnLabel + ' | ' + typeLabel + ' | ' + commentLabel + ' |', '| --- | --- | --- |'];
        rows.forEach(function (row) {
            lines.push('| ' + escapeMarkdownCell(row.column) + ' | ' + escapeMarkdownCell(row.type) + ' | ' + escapeMarkdownCell(row.comment) + ' |');
        });
        return lines.join('\n');
    }

    function toText(rows) {
        var lines = [columnLabel + '\t' + typeLabel + '\t' + commentLabel];
        rows.forEach(function (row) {
            lines.push([normalize(row.column), normalize(row.type), normalize(row.comment)].join('\t'));
        });
        return lines.join('\n');
    }

    function toCsv(rows) {
        var lines = ['"' + columnLabel + '","' + typeLabel + '","' + commentLabel + '"'];
        rows.forEach(function (row) {
            lines.push([escapeCsvCell(row.column), escapeCsvCell(row.type), escapeCsvCell(row.comment)].join(','));
        });
        return lines.join('\n');
    }

    function toJson(rows) {
        return JSON.stringify(rows, null, 2);
    }

    function getTableName() {
        var params = new URL(window.location.href).searchParams;
        var table = params.get('table');
        if (table) {
            return table;
        }

        return 'table';
    }

    function toSql(rows) {
        var lines = ['CREATE TABLE ' + escapeSqlIdentifier(getTableName()) + ' ('];

        rows.forEach(function (row, index) {
            var definition = '  ' + escapeSqlIdentifier(row.column) + ' ' + normalize(row.sqlType || row.type);
            if (row.comment) {
                definition += ' COMMENT ' + escapeSqlString(row.comment);
            }
            lines.push(definition + (index < rows.length - 1 ? ',' : ''));
        });

        lines.push(');');
        return lines.join('\n');
    }

    function formatRows(rows, format) {
        switch (format) {
            case 'markdown':
                return toMarkdown(rows);
            case 'json':
                return toJson(rows);
            case 'sql':
                return toSql(rows);
            case 'csv':
                return toCsv(rows);
            case 'text':
            default:
                return toText(rows);
        }
    }

    function getRows(table) {
        var source = table.tBodies && table.tBodies.length ? table.tBodies[0] : table;
        var rows = [];

        Array.prototype.forEach.call(source.rows || [], function (tr) {
            if (!tr.cells || tr.cells.length < 2) {
                return;
            }

            var first = normalize(tr.cells[0].textContent);
            var second = normalize(tr.cells[1].textContent);
            var typeSpan = tr.cells[1].querySelector('span');

            if (first === columnLabel && second === typeLabel) {
                return;
            }

            rows.push({
                column: first,
                type: second,
                sqlType: typeSpan ? normalize(typeSpan.textContent) : second,
                comment: tr.cells[2] ? normalize(tr.cells[2].textContent) : ''
            });
        });

        return rows;
    }

    function findStructureTable() {
        var tables = document.querySelectorAll('table.nowrap.odds, table.odds');

        for (var i = 0; i < tables.length; i++) {
            var table = tables[i];
            var header = table.querySelector('thead tr');
            if (!header) {
                continue;
            }

            var cells = header.querySelectorAll('th, td');
            if (cells.length < 2) {
                continue;
            }

            if (normalize(cells[0].textContent) === columnLabel && normalize(cells[1].textContent) === typeLabel) {
                return table;
            }
        }

        return null;
    }

    function copyText(text, status) {
        var done = function () {
            if (status) {
                status.textContent = '✓ ' + copied;
                window.setTimeout(function () {
                    status.textContent = '';
                }, 1200);
            }
        };

        var fail = function () {
            if (status) {
                status.textContent = copyFailed;
                window.setTimeout(function () {
                    status.textContent = '';
                }, 1600);
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fail();
            });
            return;
        }

        try {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '0';
            document.body.appendChild(textarea);
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);
            var ok = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (ok) {
                done();
            } else {
                fail();
            }
        } catch (error) {
            fail();
        }
    }

    function ensureToolbar(table) {
        var existing = document.getElementById('sb-structure-copy');
        if (existing) {
            return existing;
        }

        var toolbar = document.createElement('div');
        toolbar.id = 'sb-structure-copy';

        var label = document.createElement('span');
        label.className = 'sb-structure-copy-label';
        label.textContent = copyTableAs;
        toolbar.appendChild(label);

        var status = document.createElement('span');
        status.className = 'sb-structure-copy-status';
        status.setAttribute('aria-live', 'polite');
        toolbar.appendChild(status);

        ['markdown', 'json', 'text', 'csv', 'sql'].forEach(function (format) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = format;
            button.addEventListener('click', function () {
                var rows = getRows(table);
                copyText(formatRows(rows, format), status);
            });
            toolbar.insertBefore(button, status);
        });

        var container = table.closest('.scrollable');
        if (container && container.parentNode) {
            if (container.nextSibling) {
                container.parentNode.insertBefore(toolbar, container.nextSibling);
            } else {
                container.parentNode.appendChild(toolbar);
            }
        } else if (table.parentNode) {
            if (table.nextSibling) {
                table.parentNode.insertBefore(toolbar, table.nextSibling);
            } else {
                table.parentNode.appendChild(toolbar);
            }
        }

        return toolbar;
    }

    function init() {
        var table = findStructureTable();
        if (!table) {
            return;
        }

        ensureToolbar(table);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
HTML;
    }

    protected $translations = array(
        'cs' => array(
            '' => 'Kopíruje strukturu tabulky jako Markdown, JSON, text, CSV nebo SQL',
            'Copy table as' => 'Kopírovat tabulku jako',
            'Copied' => 'Zkopírováno',
            'Copy failed' => 'Kopírování selhalo',
            'Column' => 'Sloupec',
            'Type' => 'Typ',
            'Comment' => 'Komentář',
        ),
        'de' => array(
            '' => 'Kopiert die Tabellenstruktur als Markdown, JSON, Text, CSV oder SQL',
            'Copy table as' => 'Tabelle kopieren als',
            'Copied' => 'Kopiert',
            'Copy failed' => 'Kopieren fehlgeschlagen',
            'Column' => 'Spalte',
            'Type' => 'Typ',
            'Comment' => 'Kommentar',
        ),
        'pl' => array(
            '' => 'Kopiuje strukturę tabeli jako Markdown, JSON, tekst, CSV lub SQL',
            'Copy table as' => 'Kopiuj tabelę jako',
            'Copied' => 'Skopiowano',
            'Copy failed' => 'Kopiowanie nie powiodło się',
            'Column' => 'Kolumna',
            'Type' => 'Typ',
            'Comment' => 'Komentarz',
        ),
        'ro' => array(
            '' => 'Copiază structura tabelului ca Markdown, JSON, text, CSV sau SQL',
            'Copy table as' => 'Copiază tabelul ca',
            'Copied' => 'Copiat',
            'Copy failed' => 'Copierea a eșuat',
            'Column' => 'Coloană',
            'Type' => 'Tip',
            'Comment' => 'Comentariu',
        ),
        'ja' => array(
            '' => 'テーブル構造を Markdown、JSON、テキスト、CSV、SQL でコピー',
            'Copy table as' => '形式を選択してコピー',
            'Copied' => 'コピーしました',
            'Copy failed' => 'コピーに失敗しました',
            'Column' => '列',
            'Type' => '型',
            'Comment' => 'コメント',
        ),
        'hr' => array(
            '' => 'Kopira strukturu tablice kao Markdown, JSON, tekst, CSV ili SQL',
            'Copy table as' => 'Kopiraj tablicu kao',
            'Copied' => 'Kopirano',
            'Copy failed' => 'Kopiranje nije uspjelo',
            'Column' => 'Stupac',
            'Type' => 'Vrsta',
            'Comment' => 'Komentar',
        ),
    );
}
