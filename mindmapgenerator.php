<?php
// This file is part of Moodle - https://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_aiskillnavigator\service\real_ai_service;

require_login();

global $PAGE, $OUTPUT;

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/aiskillnavigator/mindmapgenerator.php'));
$PAGE->set_title(get_string('mindmapgenerator', 'local_aiskillnavigator'));
$PAGE->set_heading(get_string('mindmapgenerator', 'local_aiskillnavigator'));

$topic = optional_param('topic', 'Digital Twin', PARAM_TEXT);
$generate = optional_param('generate', 0, PARAM_BOOL);

$result = '';
$mindmap = null;
$parseerror = '';

function local_aiskillnavigator_extract_json(string $raw): ?array {
    $clean = trim($raw);

    $clean = preg_replace('/^```json\s*/i', '', $clean);
    $clean = preg_replace('/^```\s*/i', '', $clean);
    $clean = preg_replace('/\s*```$/', '', $clean);

    $start = strpos($clean, '{');
    $end = strrpos($clean, '}');

    if ($start !== false && $end !== false && $end > $start) {
        $clean = substr($clean, $start, $end - $start + 1);
    }

    $decoded = json_decode($clean, true);

    if (!is_array($decoded)) {
        return null;
    }

    if (empty($decoded['central_topic']) || empty($decoded['branches']) || !is_array($decoded['branches'])) {
        return null;
    }

    $decoded['branches'] = array_slice(array_values($decoded['branches']), 0, 4);

    foreach ($decoded['branches'] as $branchindex => $branch) {
        if (empty($branch['title'])) {
            return null;
        }

        if (empty($branch['description'])) {
            $decoded['branches'][$branchindex]['description'] = 'Concetto collegato a ' . ($decoded['central_topic'] ?? 'questo argomento') . '.';
        }

        if (empty($branch['children']) || !is_array($branch['children'])) {
            $decoded['branches'][$branchindex]['children'] = [];
        }

        $decoded['branches'][$branchindex]['children'] = array_slice(array_values($decoded['branches'][$branchindex]['children']), 0, 2);

        foreach ($decoded['branches'][$branchindex]['children'] as $childindex => $child) {
            if (is_string($child)) {
                $decoded['branches'][$branchindex]['children'][$childindex] = [
                    'title' => $child,
                    'description' => 'Sotto-concetto utile per approfondire il ramo "' . $branch['title'] . '".',
                ];
            } else if (is_array($child)) {
                if (empty($child['title'])) {
                    $decoded['branches'][$branchindex]['children'][$childindex]['title'] = 'Dettaglio';
                }

                if (empty($child['description'])) {
                    $decoded['branches'][$branchindex]['children'][$childindex]['description'] = 'Dettaglio utile per comprendere meglio "' . $branch['title'] . '".';
                }
            }
        }
    }

    if (empty($decoded['title'])) {
        $decoded['title'] = ($decoded['central_topic'] ?? 'Argomento') . ' - Mappa mentale';
    }

    if (empty($decoded['summary'])) {
        $decoded['summary'] = 'Mappa mentale interattiva generata per organizzare lo studio dell’argomento.';
    }

    if (empty($decoded['central_description'])) {
        $decoded['central_description'] = 'Questo è il concetto centrale della mappa. I rami mostrano definizione, parti principali, funzionamento e applicazioni.';
    }

    return $decoded;
}

function local_aiskillnavigator_short_label(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);

    if (core_text::strlen($text) > 30) {
        $text = core_text::substr($text, 0, 27) . '...';
    }

    return $text;
}

function local_aiskillnavigator_clean_info(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);

    if ($text === '') {
        return 'Nessuna descrizione disponibile.';
    }

    return $text;
}

if ($generate) {
    $service = new real_ai_service();

    $result = $service->generate_mindmap($topic);
    $mindmap = local_aiskillnavigator_extract_json($result);

    if ($mindmap === null) {
        // Retry once: free models sometimes cut JSON on the first response.
        $result = $service->generate_mindmap($topic);
        $mindmap = local_aiskillnavigator_extract_json($result);
    }

    if ($mindmap === null) {
        $parseerror = 'L’AI ha restituito un JSON incompleto o non valido. Riprova con un argomento più specifico, per esempio "Arduino Uno components" invece di "Arduino".';
    }
}

echo $OUTPUT->header();

echo html_writer::start_div('container-fluid');

echo html_writer::tag('h2', get_string('mindmapgenerator', 'local_aiskillnavigator'));

echo html_writer::tag(
    'p',
    'Generate a readable draggable AI mind map. Click a node to read more information.',
    ['class' => 'lead']
);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

echo html_writer::tag('h3', 'Generate a new mind map');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/aiskillnavigator/mindmapgenerator.php'),
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'generate',
    'value' => '1',
]);

echo html_writer::start_div('form-group');

echo html_writer::tag(
    'label',
    get_string('mindmap_topic', 'local_aiskillnavigator'),
    ['for' => 'topic']
);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'topic',
    'id' => 'topic',
    'class' => 'form-control',
    'value' => s($topic),
    'placeholder' => 'Esempio: Arduino Uno components, Digital Twin, CSS selectors...',
]);

echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
    'value' => 'Generate mind map',
]);

echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

if ($parseerror !== '') {
    echo html_writer::start_div('alert alert-warning mt-4');
    echo html_writer::tag('h4', 'Mind map generation failed');
    echo html_writer::tag('p', s($parseerror));
    echo html_writer::tag('p', 'Raw AI response:');
    echo html_writer::tag('pre', s($result), [
        'style' => 'white-space: pre-wrap; max-height: 220px; overflow:auto;',
    ]);
    echo html_writer::end_div();
}

if ($mindmap !== null) {
    $nodes = [];
    $edges = [];

    $centralid = 1;
    $nextid = 2;

    $central = local_aiskillnavigator_short_label((string) ($mindmap['central_topic'] ?? $topic));
    $centraldescription = local_aiskillnavigator_clean_info((string) ($mindmap['central_description'] ?? $mindmap['summary'] ?? ''));

    $nodes[] = [
        'id' => $centralid,
        'label' => $central,
        'group' => 'central',
        'x' => 0,
        'y' => 0,
        'shape' => 'box',
        'margin' => 18,
        'infoTitle' => $central,
        'infoType' => 'Argomento centrale',
        'infoDescription' => $centraldescription,
        'infoHint' => 'Parti da questo nodo per capire il concetto generale.',
        'title' => $centraldescription,
        'widthConstraint' => ['minimum' => 210, 'maximum' => 250],
    ];

    $branches = array_values($mindmap['branches']);
    $count = count($branches);

    $positions = [
        ['x' => 0, 'y' => -260],
        ['x' => 340, 'y' => 0],
        ['x' => 0, 'y' => 260],
        ['x' => -340, 'y' => 0],
    ];

    foreach ($branches as $i => $branch) {
        $position = $positions[$i] ?? ['x' => 0, 'y' => 0];

        $branchid = $nextid++;
        $branchtitle = local_aiskillnavigator_short_label((string) ($branch['title'] ?? 'Ramo'));
        $branchdescription = local_aiskillnavigator_clean_info((string) ($branch['description'] ?? ''));

        $nodes[] = [
            'id' => $branchid,
            'label' => $branchtitle,
            'group' => 'branch',
            'x' => $position['x'],
            'y' => $position['y'],
            'shape' => 'box',
            'margin' => 14,
            'infoTitle' => $branchtitle,
            'infoType' => 'Ramo principale',
            'infoDescription' => $branchdescription,
            'infoHint' => 'Questo ramo organizza una parte importante dell’argomento.',
            'title' => $branchdescription,
            'widthConstraint' => ['minimum' => 180, 'maximum' => 230],
        ];

        $edges[] = ['from' => $centralid, 'to' => $branchid, 'width' => 4];

        $children = $branch['children'] ?? [];
        $children = is_array($children) ? array_slice(array_values($children), 0, 2) : [];

        foreach ($children as $j => $child) {
            $childid = $nextid++;

            $childtitle = is_array($child)
                ? local_aiskillnavigator_short_label((string) ($child['title'] ?? 'Dettaglio'))
                : local_aiskillnavigator_short_label((string) $child);

            $childdescription = is_array($child)
                ? local_aiskillnavigator_clean_info((string) ($child['description'] ?? ''))
                : 'Sotto-concetto collegato al ramo "' . $branchtitle . '".';

            if ($i === 0) {
                $childx = $position['x'] + (($j === 0) ? -160 : 160);
                $childy = $position['y'] - 150;
            } else if ($i === 1) {
                $childx = $position['x'] + 220;
                $childy = $position['y'] + (($j === 0) ? -85 : 85);
            } else if ($i === 2) {
                $childx = $position['x'] + (($j === 0) ? -160 : 160);
                $childy = $position['y'] + 150;
            } else {
                $childx = $position['x'] - 220;
                $childy = $position['y'] + (($j === 0) ? -85 : 85);
            }

            $nodes[] = [
                'id' => $childid,
                'label' => $childtitle,
                'group' => 'child',
                'x' => $childx,
                'y' => $childy,
                'shape' => 'box',
                'margin' => 10,
                'infoTitle' => $childtitle,
                'infoType' => 'Sotto-nodo',
                'infoDescription' => $childdescription,
                'infoHint' => 'Questo nodo rappresenta un dettaglio utile per il ripasso.',
                'title' => $childdescription,
                'widthConstraint' => ['minimum' => 150, 'maximum' => 190],
            ];

            $edges[] = ['from' => $branchid, 'to' => $childid, 'width' => 2];
        }
    }

    echo html_writer::start_div('mindmap-panel mt-4');

    echo html_writer::tag('h3', s($mindmap['title'] ?? 'AI Mind Map'), ['class' => 'text-center mb-2']);

    if (!empty($mindmap['summary'])) {
        echo html_writer::tag('p', s($mindmap['summary']), ['class' => 'text-center text-muted mb-3']);
    }

    echo html_writer::tag(
        'p',
        'Clicca un nodo per leggere la spiegazione. Puoi trascinare i nodi, zoomare e spostare la mappa.',
        ['class' => 'text-center text-muted']
    );

    echo html_writer::start_div('mindmap-toolbar mb-3 text-center');
    echo html_writer::tag('button', 'Reset view', [
        'type' => 'button',
        'id' => 'mindmapResetView',
        'class' => 'btn btn-secondary btn-sm',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mindmap-layout');

    echo html_writer::div('', 'interactive-mindmap', ['id' => 'interactiveMindmap']);

    echo html_writer::start_div('mindmap-info-panel', ['id' => 'mindmapInfoPanel']);
    echo html_writer::tag('h4', 'Informazioni nodo', ['id' => 'mindmapInfoTitle']);
    echo html_writer::tag('span', 'Seleziona un nodo', [
        'id' => 'mindmapInfoType',
        'class' => 'badge badge-primary mb-3',
    ]);
    echo html_writer::tag('p', 'Clicca su un nodo della mappa per visualizzare una spiegazione.', [
        'id' => 'mindmapInfoDescription',
    ]);
    echo html_writer::tag('p', 'Suggerimento: inizia dal nodo centrale e poi esplora i rami principali.', [
        'id' => 'mindmapInfoHint',
        'class' => 'text-muted',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::tag('script', '
window.localAiSkillNavigatorMindmapNodes = ' . json_encode($nodes) . ';
window.localAiSkillNavigatorMindmapEdges = ' . json_encode($edges) . ';
');
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/aiskillnavigator/index.php'),
        'Back to plugin home',
        ['class' => 'btn btn-secondary mt-3']
    ),
    'mt-4'
);

echo html_writer::end_div();

echo html_writer::tag('style', '
.mindmap-panel {
    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
    border: 1px solid #d8e4f5;
    border-radius: 22px;
    padding: 24px;
    box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
}

.mindmap-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 18px;
    align-items: stretch;
}

.interactive-mindmap {
    height: 620px;
    width: 100%;
    background: radial-gradient(circle at center, rgba(13, 110, 253, 0.08), transparent 30%), #ffffff;
    border: 1px solid #dbe4f0;
    border-radius: 22px;
    overflow: hidden;
}

.mindmap-info-panel {
    background: #ffffff;
    border: 1px solid #dbe4f0;
    border-radius: 22px;
    padding: 22px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.mindmap-info-panel h4 {
    font-weight: 700;
}

.mindmap-info-panel p {
    font-size: 1rem;
    line-height: 1.5;
}

@media (max-width: 1100px) {
    .mindmap-layout {
        grid-template-columns: 1fr;
    }

    .interactive-mindmap {
        height: 560px;
    }
}
');

echo html_writer::empty_tag('link', [
    'rel' => 'stylesheet',
    'href' => 'https://unpkg.com/vis-network/styles/vis-network.min.css',
]);

echo html_writer::tag('script', '', [
    'src' => 'https://unpkg.com/vis-network/standalone/umd/vis-network.min.js',
]);

$script = <<<'JS'
(function () {
    function escapeHtml(text) {
        return String(text || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function startMindmap() {
        var container = document.getElementById("interactiveMindmap");

        if (!container || typeof vis === "undefined") {
            window.setTimeout(startMindmap, 300);
            return;
        }

        var nodes = new vis.DataSet(window.localAiSkillNavigatorMindmapNodes || []);
        var edges = new vis.DataSet(window.localAiSkillNavigatorMindmapEdges || []);

        var network = new vis.Network(container, { nodes: nodes, edges: edges }, {
            autoResize: true,
            physics: false,
            interaction: {
                dragNodes: true,
                dragView: true,
                zoomView: true,
                hover: true,
                multiselect: false,
                keyboard: false
            },
            layout: {
                improvedLayout: false
            },
            nodes: {
                borderWidth: 3,
                shadow: {
                    enabled: true,
                    color: "rgba(0,0,0,0.16)",
                    size: 10,
                    x: 0,
                    y: 4
                },
                font: {
                    face: "Arial",
                    size: 18,
                    color: "#102033",
                    bold: {
                        color: "#102033"
                    }
                }
            },
            edges: {
                smooth: {
                    enabled: true,
                    type: "cubicBezier",
                    roundness: 0.35
                },
                color: {
                    color: "#78a7f8",
                    highlight: "#0d6efd",
                    hover: "#0d6efd"
                }
            },
            groups: {
                central: {
                    color: { background: "#0d6efd", border: "#ffffff" },
                    font: { color: "#ffffff", size: 26 }
                },
                branch: {
                    color: { background: "#ffffff", border: "#0d6efd" },
                    font: { color: "#102033", size: 19 }
                },
                child: {
                    color: { background: "#f8fafc", border: "#94a3b8" },
                    font: { color: "#334155", size: 16 }
                }
            }
        });

        function updateInfoPanel(node) {
            document.getElementById("mindmapInfoTitle").innerHTML = escapeHtml(node.infoTitle || node.label || "Nodo");
            document.getElementById("mindmapInfoType").innerHTML = escapeHtml(node.infoType || "Nodo");
            document.getElementById("mindmapInfoDescription").innerHTML = escapeHtml(node.infoDescription || "Nessuna descrizione disponibile.");
            document.getElementById("mindmapInfoHint").innerHTML = escapeHtml(node.infoHint || "Usa questo nodo per orientarti nello studio.");
        }

        network.on("click", function (params) {
            if (!params.nodes || params.nodes.length === 0) {
                return;
            }

            var node = nodes.get(params.nodes[0]);
            if (node) {
                updateInfoPanel(node);
            }
        });

        window.setTimeout(function () {
            network.moveTo({
                position: { x: 0, y: 0 },
                scale: 0.82,
                animation: {
                    duration: 500,
                    easingFunction: "easeInOutQuad"
                }
            });
        }, 300);

        var reset = document.getElementById("mindmapResetView");
        if (reset) {
            reset.addEventListener("click", function () {
                network.moveTo({
                    position: { x: 0, y: 0 },
                    scale: 0.82,
                    animation: {
                        duration: 500,
                        easingFunction: "easeInOutQuad"
                    }
                });
            });
        }

        var allNodes = nodes.get();
        if (allNodes.length > 0) {
            updateInfoPanel(allNodes[0]);
        }
    }

    startMindmap();
})();
JS;

echo html_writer::tag('script', $script);

echo $OUTPUT->footer();