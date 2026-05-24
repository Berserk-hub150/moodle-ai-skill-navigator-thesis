(function () {
    if (window.aisnCleanMarkdownRendererLoaded) {
        return;
    }
    window.aisnCleanMarkdownRendererLoaded = true;

    function esc(v) {
        return String(v || "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function fix(v) {
        return String(v || "")
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

    function trimLine(line) {
        return fix(String(line || "")).replace(/\u00A0/g, " ").trim();
    }

    function pipeCount(line) {
        return (String(line || "").match(/\|/g) || []).length;
    }

    function isTableRow(line) {
        line = trimLine(line);
        return pipeCount(line) >= 2;
    }

    function isSeparatorRow(line) {
        line = trimLine(line);

        if (pipeCount(line) < 2) {
            return false;
        }

        var cleaned = line.replace(/\|/g, "").trim();

        return /^[:\-\s]+$/.test(cleaned) && cleaned.indexOf("-") !== -1;
    }

    function splitTableRow(line) {
        line = trimLine(line);

        if (line.startsWith("|")) {
            line = line.slice(1);
        }

        if (line.endsWith("|")) {
            line = line.slice(0, -1);
        }

        return line.split("|").map(function (x) {
            return trimLine(x);
        });
    }

    function inlineMarkdown(text) {
        text = esc(text);

        text = text.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        text = text.replace(/`([^`]+)`/g, "<code>$1</code>");

        return text;
    }

    function looksMath(line) {
        line = trimLine(line);

        return (
            /\\\(|\\\[|\\frac|\\sqrt|\\sum|\\int/.test(line) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(line) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(line)
        );
    }

    function renderTable(headers, rows) {
        var html = "<table><thead><tr>";

        headers.forEach(function (h) {
            html += "<th>" + inlineMarkdown(h) + "</th>";
        });

        html += "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html += "<tr>";

            for (var i = 0; i < headers.length; i++) {
                html += "<td>" + inlineMarkdown(row[i] || "") + "</td>";
            }

            html += "</tr>";
        });

        html += "</tbody></table>";

        return html;
    }

    function markdownToHtml(raw) {
        raw = fix(raw)
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .trim();

        var lines = raw.split("\n");
        var html = "";
        var i = 0;

        function nextIsTableStart(index) {
            return (
                index + 1 < lines.length &&
                isTableRow(lines[index]) &&
                isSeparatorRow(lines[index + 1])
            );
        }

        while (i < lines.length) {
            var line = trimLine(lines[i]);

            if (!line) {
                i++;
                continue;
            }

            if (/^```/.test(line)) {
                var code = [];
                i++;

                while (i < lines.length && !/^```/.test(trimLine(lines[i]))) {
                    code.push(lines[i]);
                    i++;
                }

                i++;
                html += "<pre><code>" + esc(code.join("\n")) + "</code></pre>";
                continue;
            }

            if (nextIsTableStart(i)) {
                var headers = splitTableRow(lines[i]);
                var rows = [];

                i += 2;

                while (i < lines.length && isTableRow(lines[i]) && !isSeparatorRow(lines[i])) {
                    rows.push(splitTableRow(lines[i]));
                    i++;
                }

                html += renderTable(headers, rows);
                continue;
            }

            if (/^#{1,4}\s+/.test(line)) {
                var level = Math.min((line.match(/^#+/) || [""])[0].length + 2, 5);
                var title = line.replace(/^#{1,4}\s+/, "");
                html += "<h" + level + ">" + inlineMarkdown(title) + "</h" + level + ">";
                i++;
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                html += "<ul>";

                while (i < lines.length && /^[-*]\s+/.test(trimLine(lines[i]))) {
                    html += "<li>" + inlineMarkdown(trimLine(lines[i]).replace(/^[-*]\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ul>";
                continue;
            }

            if (/^\d+\.\s+/.test(line)) {
                html += "<ol>";

                while (i < lines.length && /^\d+\.\s+/.test(trimLine(lines[i]))) {
                    html += "<li>" + inlineMarkdown(trimLine(lines[i]).replace(/^\d+\.\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ol>";
                continue;
            }

            if (looksMath(line)) {
                html += '<div class="aisn-md-math">' + esc(line) + "</div>";
                i++;
                continue;
            }

            var paragraph = [line];
            i++;

            while (
                i < lines.length &&
                trimLine(lines[i]) &&
                !nextIsTableStart(i) &&
                !/^```/.test(trimLine(lines[i])) &&
                !/^#{1,4}\s+/.test(trimLine(lines[i])) &&
                !/^[-*]\s+/.test(trimLine(lines[i])) &&
                !/^\d+\.\s+/.test(trimLine(lines[i]))
            ) {
                paragraph.push(trimLine(lines[i]));
                i++;
            }

            html += "<p>" + inlineMarkdown(paragraph.join(" ")) + "</p>";
        }

        return '<div class="aisn-md-output">' + html + "</div>";
    }

    function findAnswerContainer() {
        var headings = Array.from(document.querySelectorAll("h1,h2,h3,h4"));

        for (var i = 0; i < headings.length; i++) {
            var h = headings[i];
            var title = trimLine(h.textContent).toLowerCase();

            if (title !== "answer" && title !== "risposta") {
                continue;
            }

            var node = h;

            for (var depth = 0; depth < 6 && node; depth++) {
                var text = fix(node.innerText || node.textContent || "");

                if (
                    text.includes("Used materials:") ||
                    text.includes("|") ||
                    text.length > 150
                ) {
                    if (!node.querySelector("textarea,input,select,form")) {
                        return node;
                    }
                }

                node = node.parentElement;
            }

            return h.parentElement;
        }

        return null;
    }

    function formatAnswer() {
        var box = findAnswerContainer();

        if (!box || box.dataset.aisnMdRendered === "1") {
            return;
        }

        var raw = fix(box.innerText || box.textContent || "");

        if (
            !raw.includes("|") &&
            !raw.includes("```") &&
            !raw.includes("\\(") &&
            !raw.includes("\\[") &&
            !/\d+\.\s+/.test(raw) &&
            !/[-*]\s+/.test(raw)
        ) {
            return;
        }

        var alerts = Array.from(box.querySelectorAll(".alert")).map(function (x) {
            return x.cloneNode(true);
        });

        raw = raw.replace(/^\s*Answer\s*/i, "");
        raw = raw.replace(/^\s*Risposta\s*/i, "");

        var used = "";
        raw = raw.replace(/Used materials:\s*([^\n]*)/i, function (_, v) {
            used = trimLine(v);
            return "";
        });

        box.innerHTML =
            "<h3>Answer</h3>" +
            (used ? '<p style="color:#64748b;margin-bottom:12px;">Used materials: ' + esc(used) + "</p>" : "") +
            markdownToHtml(raw.trim());

        alerts.forEach(function (a) {
            box.appendChild(a);
        });

        box.dataset.aisnMdRendered = "1";
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

    new MutationObserver(function () {
        run();
    }).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();