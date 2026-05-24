(function () {
    if (window.aisnCodeSpacingFixLoaded) {
        return;
    }
    window.aisnCodeSpacingFixLoaded = true;

    function compactCode(raw, lang) {
        var code = String(raw || "")
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n");

        code = code.replace(/[ \t]+$/gm, "");
        code = code.replace(/\n{3,}/g, "\n\n");
        code = code.trim();

        if ((lang || "").toLowerCase().includes("python")) {
            var lines = code.split("\n");

            // Se l'AI ha perso l'indentazione dopo def, correggiamo i casi base.
            for (var i = 0; i < lines.length; i++) {
                var t = lines[i].trim();

                if (
                    i > 0 &&
                    /:\s*$/.test(lines[i - 1].trim()) &&
                    t &&
                    !/^\s+/.test(lines[i]) &&
                    /^(return|print|if|for|while|result|risultato|[a-zA-Z_]\w*\s*=)/.test(t)
                ) {
                    lines[i] = "    " + t;
                }
            }

            code = lines.join("\n");
            code = code.replace(/\n{3,}/g, "\n\n").trim();
        }

        return code;
    }

    function fixOne(editor) {
        if (!editor || editor.dataset.aisnSpacingFixed === "1") {
            return;
        }

        var label = editor.querySelector(".aisn-code-lang-label, .aisn-code-tabs");
        var code = editor.querySelector("pre code");

        if (!code) {
            return;
        }

        var lang = label ? label.textContent.trim() : "";
        code.textContent = compactCode(code.textContent || "", lang);

        editor.dataset.aisnSpacingFixed = "1";
    }

    function fixUsage(block) {
        if (!block || block.dataset.aisnSpacingFixed === "1") {
            return;
        }

        var label = block.querySelector(".aisn-code-usage-head span");
        var code = block.querySelector("pre code");

        if (!code) {
            return;
        }

        var lang = label ? label.textContent.trim() : "";
        code.textContent = compactCode(code.textContent || "", lang);

        block.dataset.aisnSpacingFixed = "1";
    }

    function run() {
        document.querySelectorAll(".aisn-final-code-editor").forEach(fixOne);
        document.querySelectorAll(".aisn-code-usage-block").forEach(fixUsage);
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