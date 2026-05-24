(function () {
    if (window.aisnCodeBlockFixerLoaded) {
        return;
    }
    window.aisnCodeBlockFixerLoaded = true;

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function cleanCode(v) {
        return String(v || "")
            .replace(/class="aisn-code-str">/g, "")
            .replace(/class="aisn-code-num">/g, "")
            .replace(/class="aisn-code-kw">/g, "")
            .replace(/class="aisn-code-com">/g, "")
            .replace(/<\/span>/g, "")
            .replace(/^\s*python\s*\n/i, "")
            .trim();
    }

    function copyText(text, btn) {
        function done() {
            var old = btn.textContent;
            btn.textContent = "Copied";
            setTimeout(function () {
                btn.textContent = old;
            }, 1200);
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

    function extractDocstrings(code) {
        return code.replace(/("""|''')[\s\S]*?\1/g, "\n").trim();
    }

    function splitUsage(code) {
        var lines = code.split(/\r?\n/);
        var idx = -1;

        for (var i = 0; i < lines.length; i++) {
            var t = String(lines[i] || "").trim().toLowerCase();

            if (
                t === "esempio di utilizzo" ||
                t === "esempio:" ||
                t === "uso:" ||
                t === "utilizzo:" ||
                t === "example usage" ||
                t === "example:"
            ) {
                idx = i;
                break;
            }
        }

        if (idx === -1) {
            return { main: code.trim(), usageTitle: "", usageCode: "" };
        }

        var main = lines.slice(0, idx).join("\n").trim();
        var usageLines = lines.slice(idx + 1).join("\n").trim();

        return {
            main: main,
            usageTitle: "Esempio di utilizzo",
            usageCode: usageLines
        };
    }

    function enhanceEditor(editor) {
        if (!editor || editor.dataset.aisnCodeFixed === "1") {
            return;
        }

        var head = editor.querySelector(".aisn-code-tabs");
        var code = editor.querySelector("pre code");

        if (!head || !code) {
            return;
        }

        var original = cleanCode(code.textContent || "");
        var withoutDocstrings = extractDocstrings(original);
        var parts = splitUsage(withoutDocstrings);

        if (parts.main) {
            code.textContent = parts.main;
        } else {
            code.textContent = withoutDocstrings || original;
        }

        var lang = head.textContent.trim() || "Code";
        head.innerHTML = "";

        var label = document.createElement("span");
        label.className = "aisn-code-lang-label";
        label.textContent = lang;

        var copy = document.createElement("button");
        copy.type = "button";
        copy.className = "aisn-copy-code-btn";
        copy.textContent = "Copy";

        copy.addEventListener("click", function () {
            copyText(code.textContent || "", copy);
        });

        head.appendChild(label);
        head.appendChild(copy);

        if (parts.usageCode) {
            var title = document.createElement("div");
            title.className = "aisn-code-usage-title";
            title.textContent = parts.usageTitle || "Esempio di utilizzo";

            var usage = document.createElement("div");
            usage.className = "aisn-code-usage-block";

            var usageHead = document.createElement("div");
            usageHead.className = "aisn-code-usage-head";

            var usageLabel = document.createElement("span");
            usageLabel.textContent = lang;

            var usageCopy = document.createElement("button");
            usageCopy.type = "button";
            usageCopy.className = "aisn-copy-code-btn";
            usageCopy.textContent = "Copy";

            var pre = document.createElement("pre");
            var c = document.createElement("code");
            c.textContent = parts.usageCode;

            usageCopy.addEventListener("click", function () {
                copyText(c.textContent || "", usageCopy);
            });

            usageHead.appendChild(usageLabel);
            usageHead.appendChild(usageCopy);
            pre.appendChild(c);
            usage.appendChild(usageHead);
            usage.appendChild(pre);

            editor.insertAdjacentElement("afterend", usage);
            editor.insertAdjacentElement("afterend", title);
        }

        editor.dataset.aisnCodeFixed = "1";
    }

    function run() {
        document.querySelectorAll(".aisn-final-code-editor").forEach(enhanceEditor);
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