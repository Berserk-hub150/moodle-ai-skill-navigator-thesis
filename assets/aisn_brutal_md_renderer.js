(function () {
    if (window.aisnBrutalMarkdownRendererLoaded) {
        return;
    }
    window.aisnBrutalMarkdownRendererLoaded = true;

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

    function norm(line) {
        return fix(String(line || "")).replace(/\u00A0/g, " ").trim();
    }

    function pipeCount(line) {
        return (String(line || "").match(/\|/g) || []).length;
    }

    function isTableRow(line) {
        line = norm(line);
        return pipeCount(line) >= 2;
    }

    function isSeparatorRow(line) {
        line = norm(line);

        if (pipeCount(line) < 2) {
            return false;
        }

        const cleaned = line.replace(/\|/g, "").trim();
        return /^[:\-\s]+$/.test(cleaned) && cleaned.includes("-");
    }

    function splitRow(line) {
        line = norm(line);

        if (line.startsWith("|")) {
            line = line.slice(1);
        }

        if (line.endsWith("|")) {
            line = line.slice(0, -1);
        }

        return line.split("|").map(function (x) {
            return norm(x);
        });
    }

    function inlineMd(text) {
        text = esc(text);
        text = text.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        text = text.replace(/`([^`]+)`/g, "<code>$1</code>");
        return text;
    }

    function looksMath(line) {
        line = norm(line);
        return (
            /\\\(|\\\[|\\frac|\\sqrt|\\sum|\\int/.test(line) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(line) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(line)
        );
    }

    function renderTable(headers, rows) {
        let html = '<div class="aisn-md-rendered-table-wrap"><table><thead><tr>';

        headers.forEach(function (h) {
            html += "<th>" + inlineMd(h) + "</th>";
        });

        html += "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html += "<tr>";

            for (let i = 0; i < headers.length; i++) {
                html += "<td>" + inlineMd(row[i] || "") + "</td>";
            }

            html += "</tr>";
        });

        html += "</tbody></table></div>";

        return html;
    }

    function renderMarkdown(raw) {
        raw = fix(raw)
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .trim();

        const lines = raw.split("\n");
        let html = "";
        let i = 0;

        function startsTable(index) {
            return (
                index + 1 < lines.length &&
                isTableRow(lines[index]) &&
                isSeparatorRow(lines[index + 1])
            );
        }

        while (i < lines.length) {
            let line = norm(lines[i]);

            if (!line) {
                i++;
                continue;
            }

            if (/^```/.test(line)) {
                const code = [];
                i++;

                while (i < lines.length && !/^```/.test(norm(lines[i]))) {
                    code.push(lines[i]);
                    i++;
                }

                i++;
                html += "<pre><code>" + esc(code.join("\n")) + "</code></pre>";
                continue;
            }

            if (startsTable(i)) {
                const headers = splitRow(lines[i]);
                const rows = [];

                i += 2;

                while (i < lines.length && isTableRow(lines[i]) && !isSeparatorRow(lines[i])) {
                    rows.push(splitRow(lines[i]));
                    i++;
                }

                html += renderTable(headers, rows);
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                html += "<ul>";

                while (i < lines.length && /^[-*]\s+/.test(norm(lines[i]))) {
                    html += "<li>" + inlineMd(norm(lines[i]).replace(/^[-*]\s+/, "")) + "</li>";
                    i++;
                }

                html += "</ul>";
                continue;
            }

            if (/^\d+\.\s+/.test(line)) {
                html += "<ol>";

                while (i < lines.length && /^\d+\.\s+/.test(norm(lines[i]))) {
                    html += "<li>" + inlineMd(norm(lines[i]).replace(/^\d+\.\s+/, "")) + "</li>";
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

            const paragraph = [line];
            i++;

            while (
                i < lines.length &&
                norm(lines[i]) &&
                !startsTable(i) &&
                !/^```/.test(norm(lines[i])) &&
                !/^[-*]\s+/.test(norm(lines[i])) &&
                !/^\d+\.\s+/.test(norm(lines[i]))
            ) {
                paragraph.push(norm(lines[i]));
                i++;
            }

            html += "<p>" + inlineMd(paragraph.join(" ")) + "</p>";
        }

        return '<div class="aisn-md-rendered">' + html + "</div>";
    }

    function findAnswerHeading() {
        const headings = Array.from(document.querySelectorAll("h1,h2,h3,h4"));

        return headings.find(function (h) {
            const t = norm(h.textContent).toLowerCase();
            return t === "answer" || t === "risposta";
        }) || null;
    }

    function findBestAnswerBox(heading) {
        let node = heading.parentElement;
        let best = heading.parentElement;

        for (let i = 0; i < 8 && node && node !== document.body; i++) {
            const text = fix(node.innerText || node.textContent || "");

            if (
                text.includes("Used materials:") ||
                text.includes("|") ||
                text.length > 120
            ) {
                best = node;

                if (
                    node.classList.contains("card") ||
                    node.classList.contains("generalbox") ||
                    node.classList.contains("box") ||
                    node.classList.contains("aisn-card")
                ) {
                    return node;
                }
            }

            node = node.parentElement;
        }

        return best;
    }

    function formatAnswer() {
        const heading = findAnswerHeading();

        if (!heading) {
            return;
        }

        const box = findBestAnswerBox(heading);

        if (!box || box.dataset.aisnBrutalMdDone === "1") {
            return;
        }

        if (box.querySelector("textarea,input,select,form")) {
            return;
        }

        let raw = fix(box.innerText || box.textContent || "");

        const hasMarkdown =
            raw.includes("|") ||
            raw.includes("```") ||
            raw.includes("\\(") ||
            raw.includes("\\[") ||
            /\n\s*[-*]\s+/.test(raw) ||
            /\n\s*\d+\.\s+/.test(raw);

        if (!hasMarkdown) {
            return;
        }

        const alerts = Array.from(box.querySelectorAll(".alert")).map(function (x) {
            return x.cloneNode(true);
        });

        raw = raw.replace(/^\s*Answer\s*/i, "");
        raw = raw.replace(/^\s*Risposta\s*/i, "");

        let used = "";
        raw = raw.replace(/Used materials:\s*([^\n]*)/i, function (_, value) {
            used = norm(value);
            return "";
        });

        box.innerHTML =
            "<h3>Answer</h3>" +
            (used ? '<p style="color:#64748b;margin-bottom:12px;">Used materials: ' + esc(used) + "</p>" : "") +
            renderMarkdown(raw.trim());

        alerts.forEach(function (a) {
            box.appendChild(a);
        });

        box.dataset.aisnBrutalMdDone = "1";
    }

    function run() {
        formatAnswer();
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 250);
        setTimeout(run, 800);
        setTimeout(run, 1600);
        setTimeout(run, 3000);
    });

    new MutationObserver(function () {
        run();
    }).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();