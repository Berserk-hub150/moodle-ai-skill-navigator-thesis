(function () {
    if (window.aisnMarkedMathjaxFormatterLoaded) {
        return;
    }
    window.aisnMarkedMathjaxFormatterLoaded = true;

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

    function esc(value) {
        return String(value || "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function findAnswerBlocks() {
        const blocks = [];

        document.querySelectorAll("h1,h2,h3,h4").forEach(function (h) {
            const title = fixBadChars(h.textContent || "").trim().toLowerCase();

            if (title !== "answer" && title !== "risposta") {
                return;
            }

            const parent = h.parentElement;
            if (!parent || parent.querySelector("textarea,input,select,form")) {
                return;
            }

            blocks.push({ heading: h, parent: parent });
        });

        return blocks;
    }

    function normalizeTableMarkdown(text) {
        const lines = text.split(/\n/);
        const out = [];

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];

            if (line.includes("|")) {
                line = line.trim();

                if (!line.startsWith("|")) {
                    line = "|" + line;
                }

                if (!line.endsWith("|")) {
                    line = line + "|";
                }
            }

            out.push(line);
        }

        return out.join("\n");
    }

    function formatAnswerBlock(block) {
        const parent = block.parent;

        if (parent.dataset.aisnMarkedDone === "1") {
            return;
        }

        let raw = fixBadChars(parent.innerText || parent.textContent || "");

        if (!raw.includes("|") && !raw.includes("```") && !raw.includes("\\(") && !raw.includes("\\[") && !raw.includes("$$")) {
            return;
        }

        const alerts = Array.from(parent.querySelectorAll(".alert")).map(function (a) {
            return a.cloneNode(true);
        });

        raw = raw.replace(/^\s*Answer\s*/i, "");
        raw = raw.replace(/^\s*Risposta\s*/i, "");

        let used = "";
        raw = raw.replace(/Used materials:\s*([^\n]*)/i, function (_, value) {
            used = value.trim();
            return "";
        });

        raw = normalizeTableMarkdown(raw.trim());

        let html = "";

        if (window.marked && typeof window.marked.parse === "function") {
            window.marked.setOptions({
                gfm: true,
                breaks: true
            });

            html = window.marked.parse(raw);
        } else {
            html = "<pre>" + esc(raw) + "</pre>";
        }

        parent.innerHTML =
            "<h3>Answer</h3>" +
            (used ? '<p class="aisn-used-materials">Used materials: ' + esc(used) + "</p>" : "") +
            '<div class="aisn-marked-output">' + html + "</div>";

        alerts.forEach(function (a) {
            parent.appendChild(a);
        });

        parent.dataset.aisnMarkedDone = "1";

        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise([parent]).catch(function () {});
        }
    }

    function run() {
        findAnswerBlocks().forEach(formatAnswerBlock);
    }

    run();
    document.addEventListener("DOMContentLoaded", run);
    setTimeout(run, 300);
    setTimeout(run, 1000);
    setTimeout(run, 2000);

    new MutationObserver(run).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();