(function () {
    if (window.aisnFinalMarkdownRendererLoadedV2) {
        return;
    }
    window.aisnFinalMarkdownRendererLoadedV2 = true;

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function cleanText(v) {
        return String(v || "")
            .replace(/Ã¨/g, "è")
            .replace(/Ã©/g, "é")
            .replace(/Ã /g, "à")
            .replace(/Ã²/g, "ò")
            .replace(/Ã¹/g, "ù")
            .replace(/Ã¬/g, "ì")
            .replace(/â€™/g, "'")
            .replace(/â€œ/g, '"')
            .replace(/â€/g, '"')
            .replace(/â€“/g, "-")
            .replace(/Â /g, " ");
    }

    function line(v) {
        return cleanText(String(v || "")).replace(/\u00A0/g, " ").trim();
    }

    function inlineMd(v) {
        var t = esc(v);
        t = t.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        t = t.replace(/`([^`]+)`/g, '<code class="aisn-inline-code">$1</code>');
        return t;
    }

    function pipeCount(v) {
        var m = String(v || "").match(/\|/g);
        return m ? m.length : 0;
    }

    function isTableRow(v) {
        return pipeCount(line(v)) >= 2;
    }

    function isSeparator(v) {
        v = line(v);
        if (pipeCount(v) < 2) return false;
        var cleaned = v.replace(/\|/g, "").trim();
        return /^[:\-\s]+$/.test(cleaned) && cleaned.indexOf("-") !== -1;
    }

    function splitRow(v) {
        v = line(v);
        if (v.charAt(0) === "|") v = v.substring(1);
        if (v.charAt(v.length - 1) === "|") v = v.substring(0, v.length - 1);
        return v.split("|").map(function (x) { return line(x); });
    }

    function nextNonEmpty(lines, start) {
        for (var i = start; i < lines.length; i++) {
            if (line(lines[i]) !== "") return i;
        }
        return -1;
    }

    function startsTable(lines, i) {
        if (!isTableRow(lines[i])) return false;
        var sep = nextNonEmpty(lines, i + 1);
        return sep !== -1 && isSeparator(lines[sep]);
    }

    function renderTable(headers, rows) {
        var html = '<div class="aisn-final-table-wrap"><table><thead><tr>';
        headers.forEach(function (h) { html += "<th>" + inlineMd(h) + "</th>"; });
        html += "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html += "<tr>";
            for (var i = 0; i < headers.length; i++) {
                html += "<td>" + inlineMd(row[i] || "") + "</td>";
            }
            html += "</tr>";
        });

        html += "</tbody></table></div>";
        return html;
    }

    function detectLanguage(code, explicit) {
        explicit = line(explicit).toLowerCase();

        if (explicit.includes("python") || explicit === "py") return "Python";
        if (explicit.includes("java")) return "Java";
        if (explicit.includes("javascript") || explicit === "js") return "JavaScript";
        if (explicit.includes("cpp") || explicit.includes("c++")) return "C++";
        if (explicit === "c") return "C";

        if (/^\s*def\s+\w+\s*\(/m.test(code)) return "Python";
        if (/public\s+class|class\s+Solution|System\.out\.println/.test(code)) return "Java";
        if (/^\s*#include|std::|cout\s*<</m.test(code)) return "C++";
        if (/^\s*(const|let|var)\s+|function\s+\w+\s*\(/m.test(code)) return "JavaScript";

        return "Code";
    }

    function stripBrokenHighlightArtifacts(code) {
        return String(code || "")
            .replace(/class="aisn-code-str">/g, "")
            .replace(/class="aisn-code-num">/g, "")
            .replace(/class="aisn-code-kw">/g, "")
            .replace(/class="aisn-code-com">/g, "")
            .replace(/<\/span>/g, "");
    }

    function renderCode(code, explicitLang) {
        code = stripBrokenHighlightArtifacts(cleanText(code)).trim();
        var lang = detectLanguage(code, explicitLang);

        return '<div class="aisn-final-code-editor">' +
            '<div class="aisn-code-tabs">' + esc(lang) + '</div>' +
            '<pre><code>' + esc(code) + '</code></pre>' +
            '</div>';
    }

    function looksMath(v) {
        v = line(v);
        return (
            /\\\(|\\\[|\\frac|\\sqrt|\\sum|\\int/.test(v) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(v) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(v)
        );
    }

    function isCodeStart(v) {
        v = line(v);
        return (
            /^def\s+\w+\s*\(/.test(v) ||
            /^class\s+\w+/.test(v) ||
            /^function\s+\w+\s*\(/.test(v) ||
            /^public\s+/.test(v) ||
            /^private\s+/.test(v) ||
            /^protected\s+/.test(v) ||
            /^const\s+/.test(v) ||
            /^let\s+/.test(v) ||
            /^var\s+/.test(v) ||
            /^return\b/.test(v) ||
            /^if\s*\(/.test(v) ||
            /^for\s*\(/.test(v) ||
            /^while\s*\(/.test(v)
        );
    }

    function renderMarkdown(raw) {
        raw = cleanText(raw).replace(/\r\n/g, "\n").replace(/\r/g, "\n").trim();

        var lines = raw.split("\n");
        var html = "";
        var p = [];
        var i = 0;

        function flushP() {
            if (p.length > 0) {
                html += "<p>" + inlineMd(p.join(" ")) + "</p>";
                p = [];
            }
        }

        while (i < lines.length) {
            var current = line(lines[i]);

            if (!current) {
                flushP();
                i++;
                continue;
            }

            if (/^`{2,3}/.test(current)) {
                flushP();

                var lang = current.replace(/^`{2,3}/, "").trim();
                var code = [];
                i++;

                while (i < lines.length && !/^`{2,3}/.test(line(lines[i]))) {
                    code.push(lines[i]);
                    i++;
                }

                if (i < lines.length) i++;

                html += renderCode(code.join("\n"), lang);
                continue;
            }

            if (startsTable(lines, i)) {
                flushP();

                var headers = splitRow(lines[i]);
                var sep = nextNonEmpty(lines, i + 1);
                var rows = [];
                i = sep + 1;

                while (i < lines.length) {
                    if (line(lines[i]) === "") {
                        i++;
                        continue;
                    }
                    if (!isTableRow(lines[i]) || isSeparator(lines[i])) {
                        break;
                    }
                    rows.push(splitRow(lines[i]));
                    i++;
                }

                html += renderTable(headers, rows);
                continue;
            }

            if (/^[-*]\s+/.test(current)) {
                flushP();
                html += "<ul>";
                while (i < lines.length && /^[-*]\s+/.test(line(lines[i]))) {
                    html += "<li>" + inlineMd(line(lines[i]).replace(/^[-*]\s+/, "")) + "</li>";
                    i++;
                }
                html += "</ul>";
                continue;
            }

            if (/^\d+\.\s+/.test(current)) {
                flushP();
                html += "<ol>";
                while (i < lines.length && /^\d+\.\s+/.test(line(lines[i]))) {
                    html += "<li>" + inlineMd(line(lines[i]).replace(/^\d+\.\s+/, "")) + "</li>";
                    i++;
                }
                html += "</ol>";
                continue;
            }

            if (looksMath(current)) {
                flushP();
                html += '<div class="aisn-final-math">' + esc(current) + "</div>";
                i++;
                continue;
            }

            if (isCodeStart(current)) {
                flushP();

                var codeBlock = [lines[i]];
                i++;

                while (i < lines.length) {
                    var nxt = line(lines[i]);

                    if (!nxt) {
                        var after = nextNonEmpty(lines, i + 1);
                        if (after === -1) {
                            i++;
                            break;
                        }

                        if (!isCodeStart(lines[after]) && !/^[\s]+/.test(lines[after]) && !/^(["']{3}|#|\/\/|\*|else:|elif\b|except\b|finally:)/.test(line(lines[after]))) {
                            break;
                        }

                        codeBlock.push("");
                        i++;
                        continue;
                    }

                    if (
                        isCodeStart(nxt) ||
                        /^[\s]+/.test(lines[i]) ||
                        /^(["']{3}|#|\/\/|\*|else:|elif\b|except\b|finally:)/.test(nxt) ||
                        /^[A-Za-z_]\w*\s*=/.test(nxt) ||
                        /^print\s*\(/.test(nxt)
                    ) {
                        codeBlock.push(lines[i]);
                        i++;
                        continue;
                    }

                    break;
                }

                html += renderCode(codeBlock.join("\n"), "");
                continue;
            }

            p.push(current);
            i++;
        }

        flushP();

        return '<div class="aisn-final-md-output">' + html + "</div>";
    }

    function findAnswerHeading() {
        var headings = Array.prototype.slice.call(document.querySelectorAll("h1,h2,h3,h4"));
        for (var i = 0; i < headings.length; i++) {
            var t = line(headings[i].textContent).toLowerCase();
            if (t === "answer" || t === "risposta") return headings[i];
        }
        return null;
    }

    function findAnswerBox(h) {
        var node = h.parentElement;
        var best = h.parentElement;

        for (var i = 0; i < 8 && node && node !== document.body; i++) {
            var txt = cleanText(node.innerText || node.textContent || "");
            if (txt.indexOf("Used materials:") !== -1 || txt.length > 120) {
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

    function shouldRender(raw) {
        return (
            raw.indexOf("|") !== -1 ||
            raw.indexOf("```") !== -1 ||
            raw.indexOf("``") !== -1 ||
            raw.indexOf("\\(") !== -1 ||
            raw.indexOf("\\[") !== -1 ||
            /\n\s*[-*]\s+/.test(raw) ||
            /\n\s*\d+\.\s+/.test(raw) ||
            /\bdef\s+\w+\s*\(/.test(raw) ||
            /\bclass\s+\w+/.test(raw) ||
            /\bfunction\s+\w+\s*\(/.test(raw) ||
            /\breturn\b/.test(raw)
        );
    }

    function run() {
        var h = findAnswerHeading();
        if (!h) return;

        var box = findAnswerBox(h);
        if (!box || box.dataset.aisnFinalMdDoneV2 === "1") return;
        if (box.querySelector("textarea,input,select,form")) return;

        var raw = cleanText(box.innerText || box.textContent || "");
        if (!shouldRender(raw)) return;

        var alerts = Array.prototype.slice.call(box.querySelectorAll(".alert")).map(function (a) {
            return a.cloneNode(true);
        });

        raw = raw.replace(/^\s*Answer\s*/i, "");
        raw = raw.replace(/^\s*Risposta\s*/i, "");

        var used = "";
        raw = raw.replace(/Used materials:\s*([^\n]*)/i, function (_, v) {
            used = line(v);
            return "";
        });

        box.innerHTML =
            "<h3>Answer</h3>" +
            (used ? '<p style="color:#64748b;margin-bottom:12px;">Used materials: ' + esc(used) + "</p>" : "") +
            renderMarkdown(raw.trim());

        alerts.forEach(function (a) {
            box.appendChild(a);
        });

        box.dataset.aisnFinalMdDoneV2 = "1";
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 300);
        setTimeout(run, 1000);
        setTimeout(run, 2000);
    });

    new MutationObserver(run).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();