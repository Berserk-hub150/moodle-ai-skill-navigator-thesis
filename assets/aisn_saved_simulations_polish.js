(function () {
    "use strict";

    if (window.aisnSavedSimPolishLoaded) {
        return;
    }
    window.aisnSavedSimPolishLoaded = true;

    function text(el) {
        return (el && (el.innerText || el.textContent) || "").trim();
    }

    function normalize(raw) {
        return String(raw || "")
            .replace(/\r\n/g, "\n")
            .replace(/\r/g, "\n")
            .replace(/\n{4,}/g, "\n\n\n")
            .trim();
    }

    function looksLikeSavedSimulationCard(el) {
        const value = text(el);
        return /Level:\s*|Date:\s*|Materials:\s*|Delete/i.test(value) &&
               value.length > 80;
    }

    function findCards() {
        const candidates = Array.from(document.querySelectorAll(".card, .box, section, div"));
        return candidates.filter(function (el) {
            if (el.closest(".aisn-saved-sim-card")) {
                return false;
            }

            if (!looksLikeSavedSimulationCard(el)) {
                return false;
            }

            const parent = el.parentElement;
            if (parent && looksLikeSavedSimulationCard(parent)) {
                return false;
            }

            return true;
        });
    }

    function createMetaPills(card) {
        const allText = text(card);
        const title = card.querySelector("h1,h2,h3,h4");

        if (card.querySelector(".aisn-saved-meta")) {
            return;
        }

        const level = (allText.match(/Level:\s*([^|\\n]+)/i) || [null, ""])[1].trim();
        const date = (allText.match(/Date:\s*([^\\n]+)/i) || [null, ""])[1].trim();

        if (!level && !date) {
            return;
        }

        const meta = document.createElement("div");
        meta.className = "aisn-saved-meta";

        if (level) {
            const pill = document.createElement("span");
            pill.className = "aisn-saved-pill";
            pill.textContent = "Level: " + level;
            meta.appendChild(pill);
        }

        if (date) {
            const pill = document.createElement("span");
            pill.className = "aisn-saved-pill";
            pill.textContent = "Date: " + date;
            meta.appendChild(pill);
        }

        if (title && title.parentNode) {
            title.insertAdjacentElement("afterend", meta);
        } else {
            card.insertBefore(meta, card.firstChild);
        }
    }

    function styleMaterials(card) {
        Array.from(card.querySelectorAll("p,div,span")).forEach(function (el) {
            if (/^Materials:/i.test(text(el)) && !el.classList.contains("aisn-saved-materials")) {
                el.classList.add("aisn-saved-materials");
            }
        });
    }

    function replaceHugeRawBlocks(card) {
        const blocks = Array.from(card.querySelectorAll("textarea, pre"));

        blocks.forEach(function (block) {
            if (block.dataset.aisnSavedProcessed === "1") {
                return;
            }

            const raw = normalize(block.value || block.textContent || "");
            if (!raw || raw.length < 250) {
                return;
            }

            block.dataset.aisnSavedProcessed = "1";

            const box = document.createElement("div");
            box.className = "aisn-saved-content-box";

            const header = document.createElement("div");
            header.className = "aisn-saved-content-header";

            const label = document.createElement("span");
            label.textContent = "Simulation content preview";

            const toggle = document.createElement("button");
            toggle.type = "button";
            toggle.className = "aisn-saved-toggle";
            toggle.textContent = "Show full";

            const preview = document.createElement("div");
            preview.className = "aisn-saved-content-preview";

            const max = 1400;
            const isLong = raw.length > max;
            preview.textContent = isLong ? raw.slice(0, max).trim() + "\n\n..." : raw;

            header.appendChild(label);
            header.appendChild(toggle);
            box.appendChild(header);
            box.appendChild(preview);

            toggle.addEventListener("click", function () {
                const full = toggle.dataset.full === "1";
                if (full) {
                    preview.textContent = isLong ? raw.slice(0, max).trim() + "\n\n..." : raw;
                    toggle.textContent = "Show full";
                    toggle.dataset.full = "0";
                } else {
                    preview.textContent = raw;
                    toggle.textContent = "Show preview";
                    toggle.dataset.full = "1";
                }
            });

            block.classList.add("aisn-saved-hidden");
            block.insertAdjacentElement("afterend", box);
        });
    }

    function removeUglyMetaText(card) {
        const walker = document.createTreeWalker(card, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach(function (node) {
            const value = node.nodeValue || "";
            if (/Level:\s*[^|]+?\s*\|\s*Date:/i.test(value)) {
                node.nodeValue = value.replace(/Level:\s*[^|]+?\s*\|\s*Date:\s*[^\n]+/i, "").trim();
            }
        });
    }

    function enhance() {
        const pageTitle = Array.from(document.querySelectorAll("h1,h2,h3")).find(function (h) {
            return /Saved simulations/i.test(text(h));
        });

        if (pageTitle) {
            const container = pageTitle.closest(".container,.container-fluid,#region-main,main") || document.body;
            container.classList.add("aisn-saved-sim-page");
        }

        findCards().forEach(function (card) {
            card.classList.add("aisn-saved-sim-card");
            createMetaPills(card);
            styleMaterials(card);
            replaceHugeRawBlocks(card);
            removeUglyMetaText(card);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", enhance);
    } else {
        enhance();
    }

    setTimeout(enhance, 300);
    setTimeout(enhance, 900);
    setTimeout(enhance, 1600);
})();
