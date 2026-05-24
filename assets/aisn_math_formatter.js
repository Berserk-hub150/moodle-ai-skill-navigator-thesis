(function () {
    if (window.aisnMathFormatterCleanLoaded) {
        return;
    }
    window.aisnMathFormatterCleanLoaded = true;

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

    function isInsideCode(el) {
        return !!el.closest("pre, code, .aisn-final-code-editor, .aisn-code-usage-block");
    }

    function stripMathDelimiters(s) {
        s = cleanText(s).trim();

        // Pulisce backslash doppi prodotti dai vecchi formatter.
        s = s.replace(/\\\\\[/g, "\\[");
        s = s.replace(/\\\\\]/g, "\\]");
        s = s.replace(/\\\\\(/g, "\\(");
        s = s.replace(/\\\\\)/g, "\\)");

        let changed = true;

        while (changed) {
            changed = false;

            let old = s;

            s = s.replace(/^\\\[\s*([\s\S]*?)\s*\\\]$/m, "$1").trim();
            s = s.replace(/^\\\(\s*([\s\S]*?)\s*\\\)$/m, "$1").trim();
            s = s.replace(/^\$\$\s*([\s\S]*?)\s*\$\$$/m, "$1").trim();
            s = s.replace(/^\$\s*([\s\S]*?)\s*\$$/m, "$1").trim();

            if (s !== old) {
                changed = true;
            }
        }

        return s;
    }

    function containsNaturalLanguage(s) {
        return /\b(nome|funzione|definizione|indica|input|output|prende|restituisce|dove|calcola|passaggi|esempio|questa|questo|sono|viene|serve|con|della|delle|degli|è|e)\b/i.test(s);
    }

    function isLikelyFormula(s) {
        const original = cleanText(s).trim();
        const stripped = stripMathDelimiters(original);

        if (!stripped) {
            return false;
        }

        if (stripped.length > 140) {
            return false;
        }

        // Se contiene parole italiane, non è una formula pura.
        if (containsNaturalLanguage(stripped)) {
            return false;
        }

        // Casi forti.
        if (/\\frac|\\sqrt|\\sum|\\int|\\mathbb|\\to/.test(stripped)) {
            return true;
        }

        if (/ℝ|→/.test(stripped)) {
            return true;
        }

        if (/[a-zA-Z]\s*\([^)]+\)\s*=/.test(stripped)) {
            return true;
        }

        if (/[a-zA-Z0-9]\s*\^\s*[0-9]/.test(stripped)) {
            return true;
        }

        if (/\b(sin|cos|tan|log|ln)\s*\(/i.test(stripped)) {
            return true;
        }

        if (/^[a-zA-Z]\s*:\s*.*(R|ℝ|\\mathbb)/.test(stripped)) {
            return true;
        }

        // Formula con uguale, operatori e numeri.
        if (
            stripped.includes("=") &&
            /[0-9]/.test(stripped) &&
            /^[a-zA-Z0-9\s\+\-\*\/\^\(\)=.,]+$/.test(stripped)
        ) {
            return true;
        }

        return false;
    }

    function toTex(s) {
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

    function cleanupBadDelimiters(box) {
        const walker = document.createTreeWalker(box, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(function (node) {
            const parent = node.parentElement;
            if (!parent || isInsideCode(parent)) {
                return;
            }

            let t = cleanText(node.nodeValue || "");

            // Toglie solo delimitatori vecchi rimasti in frasi normali.
            if (containsNaturalLanguage(stripMathDelimiters(t))) {
                t = t.replace(/\\\\\[/g, "");
                t = t.replace(/\\\\\]/g, "");
                t = t.replace(/\\\\\(/g, "");
                t = t.replace(/\\\\\)/g, "");
                t = t.replace(/\\\[/g, "");
                t = t.replace(/\\\]/g, "");
                t = t.replace(/\\\(/g, "");
                t = t.replace(/\\\)/g, "");
                node.nodeValue = t;
            }
        });
    }

    function findAnswerBox() {
        const headings = Array.prototype.slice.call(document.querySelectorAll("h1,h2,h3,h4"));

        for (let i = 0; i < headings.length; i++) {
            const h = headings[i];
            const title = cleanText(h.textContent || "").trim().toLowerCase();

            if (title === "answer" || title === "risposta") {
                let node = h.parentElement;
                let best = h.parentElement;

                for (let d = 0; d < 8 && node && node !== document.body; d++) {
                    const txt = cleanText(node.innerText || node.textContent || "");

                    if (txt.indexOf("Used materials:") !== -1 || txt.length > 120) {
                        best = node;
                    }

                    node = node.parentElement;
                }

                return best;
            }
        }

        return null;
    }

    function convertMathBlocks(box) {
        const candidates = Array.prototype.slice.call(
            box.querySelectorAll("p, .aisn-final-math, div:not(.aisn-final-code-editor):not(.aisn-code-usage-block)")
        );

        candidates.forEach(function (el) {
            if (el.dataset.aisnMathCleanDone === "1") {
                return;
            }

            if (isInsideCode(el)) {
                return;
            }

            // Non toccare contenitori grandi con figli strutturati.
            if (el.children.length > 0 && !el.classList.contains("aisn-final-math")) {
                return;
            }

            const text = cleanText(el.textContent || "").trim();

            if (!isLikelyFormula(text)) {
                return;
            }

            const tex = toTex(text);

            el.classList.add("aisn-math-rendered");
            el.textContent = "\\[" + tex + "\\]";
            el.dataset.aisnMathCleanDone = "1";
        });
    }

    function typeset(box) {
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise([box]).catch(function () {});
        }
    }

    function run() {
        const box = findAnswerBox();
        if (!box) {
            return;
        }

        cleanupBadDelimiters(box);
        convertMathBlocks(box);
        typeset(box);
    }

    document.addEventListener("DOMContentLoaded", function () {
        run();
        setTimeout(run, 500);
        setTimeout(run, 1500);
        setTimeout(run, 3000);
    });

    new MutationObserver(function () {
        run();
    }).observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();