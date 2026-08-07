@extends('layouts.app')

@section('title', 'Dashboard — PDFHub')
@section('page-title', 'Author Network Dashboard')
@section('topbar-actions')
    <a href="{{ route('documents.upload') }}" class="btn btn-sm btn-primary">+ Upload PDFs</a>
@endsection

@section('content')
    <div class="stat-grid">
        <div class="stat">
            <div class="label">Documents</div>
            <div class="value">{{ $stats['documents'] }}</div>
            <div class="sub">{{ $stats['processed'] }} processed · {{ $stats['pending'] }} pending · {{ $stats['failed'] }} failed</div>
        </div>
        <div class="stat">
            <div class="label">Authors</div>
            <div class="value value-accent">{{ $stats['authors'] }}</div>
            <div class="sub">{{ $stats['papers_with_authors'] }} papers with authors parsed</div>
        </div>
        <div class="stat">
            <div class="label">Collaborations</div>
            <div class="value value-green">{{ $stats['collaborations'] }}</div>
            <div class="sub">distinct co-author pairs</div>
        </div>
        <div class="stat">
            <div class="label">Network density</div>
            <div class="value value-amber">
                @if ($stats['authors'] > 1)
                    {{ round($stats['collaborations'] / max(1, $stats['authors'] * ($stats['authors'] - 1) / 2) * 100, 2) }}%
                @else
                    0%
                @endif
            </div>
            <div class="sub">possible links realized</div>
        </div>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="graph-wrap" id="graph-wrap">
            <div class="graph-toolbar">
                <button class="btn btn-sm btn-primary" id="btn-2d">2D</button>
                <button class="btn btn-sm" id="btn-3d">3D</button>
                <button class="btn btn-sm" id="btn-fit">⤢ Fit view</button>
            </div>
            <div class="graph-canvas" id="graph-2d"></div>
            <div class="graph-canvas" id="graph-3d" style="display:none;"></div>
            <div id="graph-3d-error" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); z-index:6; max-width:440px; text-align:center; color:#ffd5d5; background:rgba(40,10,20,0.85); border:1px solid #ff5d73; border-radius:10px; padding:16px 20px; font-size:13px; line-height:1.5;"></div>
            <div class="graph-legend">
                <div style="font-weight: 700; margin-bottom: 6px;">Co-authorship network</div>
                <div class="legend-row"><span class="legend-dot" style="background:#7c5cff;"></span> Node size = # papers</div>
                <div class="legend-row"><span class="legend-dot" style="background:#4aa8ff;"></span> Edge width = shared papers</div>
                <div class="legend-row"><span class="legend-dot" style="background:#a5f3fc; border-radius:50%; height:4px; width:20px; margin-top:2px;"></span> Moving dots = collaboration strength</div>
                <div class="muted small" style="margin-top: 6px;">2D: click a node to open the author profile</div>
                <div class="muted small">3D: drag = rotate · wheel = zoom · right-drag = pan · click = open author</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/3d-force-graph.min.js') }}"></script>
<script>
    const rawGraph = @json($graph);
    const palette = [
        '#7c5cff', '#4aa8ff', '#35d07f', '#f5b544', '#ff5d73',
        '#22c1c3', '#a26dff', '#ff9a3d', '#5dd0c6', '#ff7bac',
    ];

    const groupMap = {};
    let groupCounter = 0;

    const graph = {
        nodes: rawGraph.nodes.map(n => {
            let group = (n.affiliation || 'Unknown').split(';')[0].trim();
            if (!(group in groupMap)) groupMap[group] = groupCounter++ % palette.length;
            return {
                id: n.id,
                name: n.name,
                email: n.email,
                affiliation: n.affiliation,
                papers: n.papers,
                collaborators: n.collaborators,
                size: 8 + Math.min(40, (n.papers || 0) * 5),
                color: palette[groupMap[group]],
                group: group,
            };
        }),
        links: rawGraph.links.map(l => ({
            source: l.source,
            target: l.target,
            weight: l.weight,
        })),
    };

    const container2d = document.getElementById('graph-2d');
    const container3d = document.getElementById('graph-3d');
    let network = null;
    let forceGraph = null;
    let currentMode = '2d';

    function tooltipHtml(n) {
        return `<b>${n.name}</b><br/>${n.papers || 0} papers · ${n.collaborators || 0} collaborators` +
            (n.affiliation ? `<br/>${n.affiliation}` : '') +
            (n.email ? `<br/>${n.email}` : '');
    }

    function webglOk() {
        try {
            const canvas = document.createElement('canvas');
            return !!(window.WebGLRenderingContext &&
                (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
        } catch (e) {
            return false;
        }
    }

    function show3dError(msg) {
        const err = document.getElementById('graph-3d-error');
        if (!err) return;
        if (msg) {
            err.textContent = msg;
            err.style.display = 'block';
        } else {
            err.style.display = 'none';
        }
    }

    function render2d() {
        const visNodes = graph.nodes.map(n => ({
            id: n.id,
            label: n.name,
            title: tooltipHtml(n),
            size: n.size,
            color: n.color,
        }));
        const visEdges = graph.links.map(l => ({
            from: l.source,
            to: l.target,
            width: 1 + (l.weight || 1) * 2,
            color: { color: '#3a4170', opacity: 0.7, highlight: '#c9d2ff' },
        }));
        network = new vis.Network(container2d, { nodes: visNodes, edges: visEdges }, {
            nodes: {
                font: { color: '#e8eaf6', size: 13, face: 'Segoe UI' },
                borderWidth: 0,
                shadow: true,
            },
            edges: { smooth: { type: 'continuous' } },
            physics: {
                solver: 'forceAtlas2Based',
                forceAtlas2Based: { gravitationalConstant: -50, centralGravity: 0.005, springLength: 130, springConstant: 0.08 },
                stabilization: { iterations: 250 },
            },
            interaction: { hover: true, tooltipDelay: 120 },
        });
        network.on('click', params => {
            if (params.nodes.length) window.location = '/authors/' + params.nodes[0];
        });
    }

    function render3d() {
        if (!webglOk()) {
            show3dError('WebGL is not available in this browser, so 3D cannot render. Enable hardware acceleration or try another browser (Chrome/Edge/Firefox).');
            return;
        }
        show3dError('');

        const fgNodes = graph.nodes.map(n => ({
            id: n.id,
            name: n.name,
            email: n.email,
            affiliation: n.affiliation,
            val: Math.max(1, n.papers || 1),
            color: n.color,
        }));
        const fgLinks = graph.links.map(l => ({
            source: l.source,
            target: l.target,
            value: l.weight || 1,
        }));

        let fg = null;
        try {
            fg = ForceGraph3D({ controlType: 'orbit' });
            fg(container3d);
            fg
                .backgroundColor('#0d1020')
                .graphData({ nodes: fgNodes, links: fgLinks })
                .nodeLabel(n => tooltipHtml(n))
                .nodeVal(n => n.val)
                .nodeColor(n => n.color)
                .nodeRelSize(6)
                .nodeResolution(16)
                .nodeOpacity(0.95)
                .linkWidth(l => 0.6 + (l.value || 1) * 0.9)
                .linkOpacity(0.4)
                .linkColor(() => 'rgba(120,140,255,0.45)')
                .linkDirectionalParticles(l => Math.min(4, Math.round(l.value || 1)))
                .linkDirectionalParticleWidth(2.2)
                .linkDirectionalParticleSpeed(0.006)
                .linkDirectionalParticleColor(() => '#a5f3fc')
                .onNodeHover(node => {
                    document.body.style.cursor = node ? 'pointer' : 'default';
                    fg.nodeColor(n => (node && n.id === node.id) ? '#ffffff' : n.color)
                      .nodeVal(n => (node && n.id === node.id) ? n.val * 1.4 : n.val);
                })
                .onNodeClick(n => {
                    fg.cameraPosition({ x: n.x, y: n.y, z: Math.max(70, n.val * 16) }, n, 800);
                    setTimeout(() => { window.location = '/authors/' + n.id; }, 650);
                });
        } catch (e) {
            show3dError('3D render failed: ' + (e && e.message ? e.message : e));
            return;
        }

        forceGraph = fg;
        setTimeout(() => { try { forceGraph.zoomToFit(600, 60); } catch (e) {} }, 350);
    }

    function setMode(mode) {
        if (mode === currentMode) return;
        currentMode = mode;

        if (mode === '2d') {
            if (forceGraph) { forceGraph._destructor && forceGraph._destructor(); forceGraph = null; }
            show3dError('');
            container3d.style.display = 'none';
            container2d.style.display = '';
            if (!network) render2d();
            document.getElementById('btn-2d').classList.add('btn-primary');
            document.getElementById('btn-3d').classList.remove('btn-primary');
        } else {
            if (network) { network.destroy(); network = null; }
            container2d.style.display = 'none';
            container3d.style.display = '';
            if (!forceGraph) render3d();
            document.getElementById('btn-3d').classList.add('btn-primary');
            document.getElementById('btn-2d').classList.remove('btn-primary');
        }
    }

    window.addEventListener('resize', () => {
        if (currentMode === '3d' && forceGraph) {
            forceGraph.width(container3d.clientWidth).height(container3d.clientHeight);
        }
    });

    document.getElementById('btn-2d').addEventListener('click', () => setMode('2d'));
    document.getElementById('btn-3d').addEventListener('click', () => setMode('3d'));
    document.getElementById('btn-fit').addEventListener('click', () => {
        if (currentMode === '2d' && network) network.fit();
        if (currentMode === '3d' && forceGraph) forceGraph.zoomToFit(400);
    });

    render2d();
</script>
@endpush
