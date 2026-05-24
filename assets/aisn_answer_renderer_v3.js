(function () {
    if (window.aisnAnswerRendererV3Loaded) {
        return;
    }
    window.aisnAnswerRendererV3Loaded = true;

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
        var html = "<table><thead><tr>";
        headers.forEach(function (h) { html += "<th>" + inlineMd(h) + "</th>"; });
        html += "</tr></thead><tbody>";

        rows.forEach(function (row) {
            html += "<tr>";
            for (var i = 0; i < headers.length; i++) {
                html += "<td>" + inlineMd(row[i] || "") + "</td>";
            }
            html += "</tr>";
        });

        html += "</tbody></table>";
        return html;
    }

    function stripMathDelimiters(s) {
        s = line(s);

        s = s.replace(/^\\\\\[/, "\\[").replace(/\\\\\]$/, "\\]");
        s = s.replace(/^\\\\\(/, "\\(").replace(/\\\\\)$/, "\\)");

        var changed = true;
        while (changed) {
            changed = false;
            var old = s;

            s = s.replace(/^\\\[\s*([\s\S]*?)\s*\\\]$/m, "$1").trim();
            s = s.replace(/^\\\(\s*([\s\S]*?)\s*\\\)$/m, "$1").trim();
            s = s.replace(/^\$\$\s*([\s\S]*?)\s*\$\$$/m, "$1").trim();
            s = s.replace(/^\$\s*([\s\S]*?)\s*\$$/m, "$1").trim();

            if (s !== old) changed = true;
        }

        return s;
    }

    function normalizeTex(s) {
        s = stripMathDelimiters(s);

        s = s.replace(/ℝ/g, "\\mathbb{R}");
        s = s.replace(/→/g, "\\to");
        s = s.replace(/->/g, "\\to");
        s = s.replace(/×/g, "\\cdot ");
        s = s.replace(/\*/g, "\\cdot ");

        s = s.replace(/([a-zA-Z0-9\)])\^([0-9]+)/g, "$1^{$2}");
        s = s.replace(/sqrt\s*\(([^)]+)\)/gi, "\\sqrt{$1}");

        return s.trim();
    }

    function isMathStart(v) {
        v = line(v);
        return v === "\\[" || v === "\\(" || v === "$$" || v === "\\\\[" || v === "\\\\(";
    }

    function isMathEnd(v) {
        v = line(v);
        return v === "\\]" || v === "\\)" || v === "$$" || v === "\\\\]" || v === "\\\\)";
    }

    function containsNaturalLanguage(s) {
        return /\b(nome|funzione|definizione|indica|input|output|prende|restituisce|dove|calcola|passaggi|esempio|questa|questo|sono|viene|serve|con|della|delle|degli|è| e )\b/i.test(s);
    }

    function isPureFormula(v) {
        var s = stripMathDelimiters(v);

        if (!s || s.length > 160) return false;
        if (containsNaturalLanguage(s)) return false;

        return (
            /\\frac|\\sqrt|\\sum|\\int|\\mathbb|\\to|\\times|\\begin/.test(s) ||
            /ℝ|→/.test(s) ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(s) ||
            /[a-zA-Z0-9]\s*\^\s*[0-9]/.test(s) ||
            /\b(sin|cos|tan|log|ln)\s*\(/i.test(s) ||
            /^[a-zA-Z]\s*:\s*.*(R|ℝ|\\mathbb)/.test(s) ||
            (s.includes("=") && /[0-9]/.test(s) && /^[a-zA-Z0-9\s\+\-\*\/\^\(\)=.,]+$/.test(s))
        );
    }

    function renderMath(tex) {
        tex = normalizeTex(tex);
        return '<div class="aisn-math-block">\\[' + esc(tex) + '\\]</div>';
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

    function copyText(text, btn) {
        function done() {
            var old = btn.textContent;
            btn.textContent = "Copied";
            setTimeout(function () { btn.textContent = old; }, 1200);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {});
            return;
        }

        var area = document.createElement("textarea");
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand("copy");
        document.body.removeChild(area);
        done();
    }

    function renderCode(code, explicitLang) {
        code = cleanText(code)
            .replace(/class="aisn-code-str">/g, "")
            .replace(/class="aisn-code-num">/g, "")
            .replace(/class="aisn-code-kw">/g, "")
            .replace(/class="aisn-code-com">/g, "")
            .replace(/<\/span>/g, "")
            .replace(/("""|''')[\s\S]*?\1/g, "\n")
            .replace(/[ \t]+$/gm, "")
            .replace(/\n{3,}/g, "\n\n")
            .trim();

        var lang = detectLanguage(code, explicitLang);
        var id = "aisn_code_" + Math.random().toString(36).slice(2);

        setTimeout(function () {
            var btn = document.querySelector('[data-copy-target="' + id + '"]');
            var codeEl = document.getElementById(id);

            if (btn && codeEl) {
                btn.addEventListener("click", function () {
                    copyText(codeEl.textContent || "", btn);
                });
            }
        }, 0);

        return '<div class="aisn-code-editor">' +
            '<div class="aisn-code-head"><span>' + esc(lang) + '</span><button type="button" class="aisn-copy-btn" data-copy-target="' + id + '">Copy</button></div>' +
            '<pre><code id="' + id + '">' + esc(code) + '</code></pre>' +
            '</div>';
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

            if (isMathStart(current)) {
                flushP();

                var math = [];
                i++;

                while (i < lines.length && !isMathEnd(lines[i])) {
                    math.push(lines[i]);
                    i++;
                }

                if (i < lines.length) i++;

                html += renderMath(math.join("\n"));
                continue;
            }

            if (/^\\begin\{/.test(current) || current.indexOf("\\begin{cases}") !== -1) {
                flushP();

                var block = [current];
                i++;

                while (i < lines.length) {
                    block.push(lines[i]);
                    if (line(lines[i]).indexOf("\\end{") !== -1) {
                        i++;
                        break;
                    }
                    i++;
                }

                html += renderMath(block.join("\n"));
                continue;
            }

            if (isPureFormula(current)) {
                flushP();
                html += renderMath(current);
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
                    if (!isTableRow(lines[i]) || isSeparator(lines[i])) break;
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

        return '<div class="aisn-rendered-answer">' + html + "</div>";
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
                if (node.classList.contains("card") || node.classList.contains("generalbox") || node.classList.contains("box") || node.classList.contains("aisn-card")) {
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
            raw.indexOf("\\[") !== -1 ||
            raw.indexOf("\\(") !== -1 ||
            raw.indexOf("\\begin") !== -1 ||
            raw.indexOf("\\mathbb") !== -1 ||
            /[a-zA-Z]\s*\([^)]+\)\s*=/.test(raw) ||
            /\bdef\s+\w+\s*\(/.test(raw) ||
            /\breturn\b/.test(raw) ||
            /\n\s*[-*]\s+/.test(raw) ||
            /\n\s*\d+\.\s+/.test(raw)
        );
    }

    function run() {
        var h = findAnswerHeading();
        if (!h) return;

        var box = findAnswerBox(h);
        if (!box || box.dataset.aisnAnswerRendererV3Done === "1") return;
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

        box.dataset.aisnAnswerRendererV3Done = "1";

        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise([box]).catch(function () {});
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 400);
        setTimeout(run, 1200);
        setTimeout(run, 2500);
    });

    new MutationObserver(run).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();