(function () {
    if (window.aisnForceRealTablesV4) {
        return;
    }
    window.aisnForceRealTablesV4 = true;

    function esc(value) {
        return String(value || "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function fixBadChars(value) {
        return String(value || "")
            .replaceAll("Ã¨", "è")
            .replaceAll("Ã©", "é")
            .replaceAll("Ã ", "à")
            .replaceAll("Ã²", "ò")
            .replaceAll("Ã¹", "ù")
            .replaceAll("Ã¬", "ì")
            .replaceAll("â€™", "'")
            .replaceAll("â€œ", '"')
            .replaceAll("â€", '"')
            .replaceAll("â€“", "-")
            .replaceAll("Â ", " ");
    }

    function normalizeLine(line) {
        return fixBadChars(String(line || ""))
            .replace(/\u00A0/g, " ")
            .trim();
    }

    function countPipes(line) {
        return (String(line || "").match(/\|/g) || []).length;
    }

    function isMarkdownTableRow(line) {
        line = normalizeLine(line);
        return countPipes(line) >= 2;
    }

    function isSeparatorRow(line) {
        line = normalizeLine(line);
        if (countPipes(line) < 2) {
            return false;
        }

        var cleaned = line
            .replace(/\|/g, "")
            .replace(/:/g, "")
            .replace(/-/g, "")
            .trim();

        return cleaned === "";
    }

    function splitRow(line) {
        line = normalizeLine(line);

        if (line.startsWith("|")) {
            line = line.slice(1);
        }

        if (line.endsWith("|")) {
            line = line.slice(0, -1);
        }

        return line.split("|").map(function (cell) {
            return normalizeLine(cell);
        });
    }

    function looksLikeMath(line) {
        line = normalizeLine(line);

        return (
            /\\\(|\\\[|\\frac|\\sqrt|\\sum|\\int/.test(line) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(line) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(line) ||
            /\b(sin|cos|tan|log|ln)\s*\(/i.test(line)
        );
    }

    function renderTable(headers, rows) {
        var html = '<div class="aisn-force-table-wrap"><table class="aisn-force-table"><thead><tr>';

        headers.forEach(function (h) {
            html += "<th>" + esc(h) + "</th>";
        });

        html += "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html += "<tr>";

            for (var i = 0; i < headers.length; i++) {
                html += "<td>" + esc(row[i] || "") + "</td>";
            }

            html += "</tr>";
        });

        html += "</tbody></table></div>";

        return html;
    }

    function renderMarkdownToHtml(rawText) {
        var text = fixBadChars(rawText)
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .trim();

        var lines = text.split("\n");
        var html = "";
        var i = 0;

        while (i < lines.length) {
            var line = normalizeLine(lines[i]);

            if (!line) {
                i++;
                continue;
            }

            if (/^```/.test(line)) {
                var code = [];
                i++;

                while (i < lines.length && !/^```/.test(normalizeLine(lines[i]))) {
                    code.push(lines[i]);
                    i++;
                }

                i++;
                html += '<pre class="aisn-force-code">' + esc(code.join("\n")) + "</pre>";
                continue;
            }

            if (
                isMarkdownTableRow(line) &&
                i + 1 < lines.length &&
                isSeparatorRow(lines[i + 1])
            ) {
                var headers = splitRow(line);
                var rows = [];
                i += 2;

                while (i < lines.length && isMarkdownTableRow(lines[i]) && !isSeparatorRow(lines[i])) {
                    rows.push(splitRow(lines[i]));
                    i++;
                }

                html += renderTable(headers, rows);
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                html += "<ul>";

                while (i < lines.length && /^[-*]\s+/.test(normalizeLine(lines[i]))) {
                    html += "<li>" + esc(normalizeLine(lines[i]).replace(/^[-*]\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ul>";
                continue;
            }

            if (/^\d+\.\s+/.test(line)) {
                html += "<ol>";

                while (i < lines.length && /^\d+\.\s+/.test(normalizeLine(lines[i]))) {
                    html += "<li>" + esc(normalizeLine(lines[i]).replace(/^\d+\.\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ol>";
                continue;
            }

            if (looksLikeMath(line)) {
                html += '<div class="aisn-force-math">' + esc(line) + "</div>";
                i++;
                continue;
            }

            var paragraph = [line];
            i++;

            while (
                i < lines.length &&
                normalizeLine(lines[i]) &&
                !(isMarkdownTableRow(lines[i]) && i + 1 < lines.length && isSeparatorRow(lines[i + 1])) &&
                !/^[-*]\s+/.test(normalizeLine(lines[i])) &&
                !/^\d+\.\s+/.test(normalizeLine(lines[i])) &&
                !/^```/.test(normalizeLine(lines[i]))
            ) {
                paragraph.push(normalizeLine(lines[i]));
                i++;
            }

            html += "<p>" + esc(paragraph.join(" ")) + "</p>";
        }

        return '<div class="aisn-force-output">' + html + "</div>";
    }

    function findAnswerCard() {
        var headings = Array.from(document.querySelectorAll("h1,h2,h3,h4"));

        for (var i = 0; i < headings.length; i++) {
            var h = headings[i];
            var title = normalizeLine(h.textContent).toLowerCase();

            if (title === "answer" || title === "risposta") {
                var box = h.closest(".card, .generalbox, .box, section");

                if (box) {
                    return { box: box, heading: h };
                }

                return { box: h.parentElement, heading: h };
            }
        }

        return null;
    }

    function formatAnswer() {
        var found = findAnswerCard();

        if (!found || !found.box || found.box.dataset.aisnForceFormatted === "1") {
            return;
        }

        var box = found.box;
        var raw = fixBadChars(box.innerText || box.textContent || "");

        if (raw.indexOf("|") === -1 && raw.indexOf("```") === -1 && !looksLikeMath(raw)) {
            return;
        }

        var alerts = Array.from(box.querySelectorAll(".alert")).map(function (el) {
            return el.cloneNode(true);
        });

        raw = raw.replace(/^\s*Answer\s*/i, "");
        raw = raw.replace(/^\s*Risposta\s*/i, "");

        var used = "";
        raw = raw.replace(/Used materials:\s*([^\n]*)/i, function (_, value) {
            used = normalizeLine(value);
            return "";
        });

        raw = raw.trim();

        box.innerHTML =
            "<h3>Answer</h3>" +
            (used ? '<p style="color:#64748b;margin-bottom:12px;">Used materials: ' + esc(used) + "</p>" : "") +
            renderMarkdownToHtml(raw);

        alerts.forEach(function (alert) {
            box.appendChild(alert);
        });

        box.dataset.aisnForceFormatted = "1";
    }

    function run() {
        formatAnswer();
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 300);
        setTimeout(run, 1000);
        setTimeout(run, 2000);
    });

    var observer = new MutationObserver(function () {
        run();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();