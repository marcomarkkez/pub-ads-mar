/* PubAds — Design Dashboard (vanilla JS). Data comes from design.json at runtime. */
(function () {
  "use strict";

  // Tiny inline fixture — used ONLY as a smoke-test fallback if design.json cannot be fetched
  // while developing. The live path always fetches design.json (see load()).
  var FIXTURE = {
    meta: { title: "PubAds — Design Dashboard (fixture)", source: "design.md", version: "0" },
    legend: {
      kanbanStatuses: [
        { key: "backlog", label: "Backlog", color: "#94a3b8" },
        { key: "wip", label: "WIP", color: "#f59e0b" },
        { key: "review", label: "Review", color: "#3b82f6" },
        { key: "done", label: "Done", color: "#22c55e" }
      ],
      weights: ["S", "M", "L", "XL"]
    },
    specs: [{
      id: "10", title: "Chats, Objetos & Flags", features: ["F08", "F09"],
      kanban: { status: "wip", weight: "XL", impacts: ["F07"], deps: ["F06"], ids: ["CH-mask-02"] },
      summary: "The one chat primitive.", body: "Fixture body.\n\nServe design.json to see real data."
    }],
    userStories: [{ id: "UC-7", actor: "CLIENT", title: "Client rejects proof", sections: ["§7"], detail: "Fixture story." }],
    diagrams: {
      flow: { type: "mermaid", title: "Flow", def: "flowchart TD\n A[Search]-->B[Book]" },
      er: { type: "mermaid", title: "ER", def: "erDiagram\n CHATS ||--o{ MESSAGES : has" },
      classes: { type: "mermaid", title: "Classes", def: "classDiagram\n class Chat" }
    }
  };

  var DEFAULT_STATUSES = [
    { key: "backlog", label: "Backlog", color: "#94a3b8" },
    { key: "wip", label: "WIP", color: "#f59e0b" },
    { key: "review", label: "Review", color: "#3b82f6" },
    { key: "done", label: "Done", color: "#22c55e" }
  ];

  var state = {
    data: null,
    view: "specs",
    specFilter: { feature: null, status: null, q: "" },
    storyQ: "",
    theme: null,          // null = system, "light", "dark"
    renderedDiagrams: {}  // cache: view -> true
  };

  var els = {};
  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  /* ---------------- boot ---------------- */
  document.addEventListener("DOMContentLoaded", function () {
    els.content = $("content");
    els.title = $("view-title");
    els.tools = $("view-tools");
    els.brandTitle = $("brand-title");
    els.brandSub = $("brand-sub");
    els.metaLine = $("meta-line");
    els.app = $("app");
    els.drawer = $("drawer");
    els.drawerTitle = $("drawer-title");
    els.drawerBody = $("drawer-body");

    // nav
    var navBtns = document.querySelectorAll(".nav-btn");
    navBtns.forEach(function (b) {
      b.addEventListener("click", function () {
        setView(b.getAttribute("data-view"));
        els.app.classList.remove("nav-open");
      });
    });
    $("menu-btn").addEventListener("click", function () { els.app.classList.toggle("nav-open"); });
    $("theme-toggle").addEventListener("click", toggleTheme);

    // drawer close
    els.drawer.addEventListener("click", function (e) {
      if (e.target.hasAttribute("data-close")) closeDrawer();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeDrawer();
    });

    initMermaid();
    load();
  });

  function currentThemeIsDark() {
    if (state.theme === "dark") return true;
    if (state.theme === "light") return false;
    return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
  }

  function initMermaid() {
    if (window.mermaid) {
      try {
        mermaid.initialize({ startOnLoad: false, securityLevel: "loose", theme: currentThemeIsDark() ? "dark" : "default" });
      } catch (e) { /* noop */ }
    }
  }

  function toggleTheme() {
    // cycle: system -> light -> dark -> system
    state.theme = state.theme === null ? "light" : state.theme === "light" ? "dark" : null;
    var root = document.documentElement;
    if (state.theme === null) root.removeAttribute("data-theme");
    else root.setAttribute("data-theme", state.theme);
    state.renderedDiagrams = {};        // force diagram re-render with new theme
    initMermaid();
    render();
  }

  /* ---------------- data load ---------------- */
  function load() {
    fetch("design.json", { cache: "no-store" })
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status + " " + r.statusText);
        return r.json();
      })
      .then(function (json) {
        state.data = normalize(json);
        onData();
      })
      .catch(function (err) { showLoadError(err); });
  }

  function normalize(d) {
    d = d || {};
    d.meta = d.meta || {};
    d.legend = d.legend || {};
    if (!Array.isArray(d.legend.kanbanStatuses) || !d.legend.kanbanStatuses.length) {
      d.legend.kanbanStatuses = DEFAULT_STATUSES;
    }
    d.specs = Array.isArray(d.specs) ? d.specs : [];
    d.userStories = Array.isArray(d.userStories) ? d.userStories : [];
    d.diagrams = d.diagrams || {};
    return d;
  }

  function onData() {
    var m = state.data.meta;
    els.brandTitle.textContent = m.title || "Design Dashboard";
    els.brandSub.textContent = (state.data.specs.length) + " specs · " + state.data.userStories.length + " stories";
    els.metaLine.textContent = "source: " + (m.source || "design.md") +
      (m.version ? " · v" + m.version : "") + (m.generatedAt ? " · " + m.generatedAt : "");
    setView(state.view);
  }

  function showLoadError(err) {
    els.brandSub.textContent = "load failed";
    els.content.innerHTML =
      '<div class="error-box">' +
      '<h2>Could not load <code>design.json</code></h2>' +
      '<p>' + esc(err && err.message ? err.message : String(err)) + '</p>' +
      '<p>This dashboard must be <strong>served over HTTP</strong>, not opened from the file system ' +
      '(a <code>file://</code> URL blocks <code>fetch()</code>). Start a local server:</p>' +
      '<pre>cd design\npython3 -m http.server 8080</pre>' +
      '<p>then open <code>http://localhost:8080/design.html</code></p>' +
      '<p style="color:var(--muted)">If you are already serving it, make sure <code>design.json</code> ' +
      'exists in the same folder as <code>design.html</code>.</p>' +
      '</div>';
    els.tools.innerHTML = "";
  }

  /* ---------------- view switching ---------------- */
  function setView(v) {
    state.view = v;
    document.querySelectorAll(".nav-btn").forEach(function (b) {
      b.classList.toggle("active", b.getAttribute("data-view") === v);
      if (b.getAttribute("data-view") === v) b.setAttribute("aria-selected", "true");
      else b.removeAttribute("aria-selected");
    });
    var titles = { specs: "Specs", kanban: "Kanban", flow: "Flow", er: "ER", classes: "Classes", stories: "User Stories" };
    els.title.textContent = titles[v] || v;
    render();
  }

  function render() {
    if (!state.data) return;
    els.tools.innerHTML = "";
    switch (state.view) {
      case "specs": return renderSpecs();
      case "kanban": return renderKanban();
      case "flow": return renderDiagram("flow");
      case "er": return renderDiagram("er");
      case "classes": return renderDiagram("classes");
      case "stories": return renderStories();
    }
  }

  /* ---------------- helpers ---------------- */
  function statusMeta(key) {
    var list = state.data.legend.kanbanStatuses;
    for (var i = 0; i < list.length; i++) if (list[i].key === key) return list[i];
    return { key: key, label: key || "—", color: "#94a3b8" };
  }
  function allFeatures() {
    var set = {};
    state.data.specs.forEach(function (s) { (s.features || []).forEach(function (f) { set[f] = 1; }); });
    return Object.keys(set).sort();
  }

  /* ---------------- Specs view ---------------- */
  function renderSpecs() {
    var f = state.specFilter;
    // tools: search
    var search = document.createElement("input");
    search.type = "search"; search.placeholder = "Search specs…"; search.value = f.q;
    search.addEventListener("input", function () { f.q = search.value.toLowerCase(); paintSpecs(); });
    els.tools.appendChild(search);

    var wrap = document.createElement("div");
    wrap.innerHTML =
      '<div class="filterbar">' +
        '<div class="filter-group" id="status-filters"></div>' +
        '<span style="width:1px;height:20px;background:var(--border)"></span>' +
        '<div class="filter-group" id="feature-filters"></div>' +
        '<span class="count-note" id="spec-count"></span>' +
      '</div><div class="grid" id="spec-grid"></div>';
    els.content.innerHTML = "";
    els.content.appendChild(wrap);

    // status chips
    var sf = wrap.querySelector("#status-filters");
    sf.appendChild(chip("All status", f.status === null, function () { f.status = null; paintSpecs(); refreshChips(); }));
    state.data.legend.kanbanStatuses.forEach(function (st) {
      sf.appendChild(chip(st.label, f.status === st.key, function () {
        f.status = f.status === st.key ? null : st.key; paintSpecs(); refreshChips();
      }, st.color));
    });
    // feature chips
    var ff = wrap.querySelector("#feature-filters");
    ff.appendChild(chip("All features", f.feature === null, function () { f.feature = null; paintSpecs(); refreshChips(); }));
    allFeatures().forEach(function (feat) {
      ff.appendChild(chip(feat, f.feature === feat, function () {
        f.feature = f.feature === feat ? null : feat; paintSpecs(); refreshChips();
      }));
    });

    function refreshChips() {
      sf.querySelectorAll(".chip").forEach(function (c, i) {
        c.classList.toggle("on", i === 0 ? f.status === null : f.status === state.data.legend.kanbanStatuses[i - 1].key);
      });
      var feats = allFeatures();
      ff.querySelectorAll(".chip").forEach(function (c, i) {
        c.classList.toggle("on", i === 0 ? f.feature === null : f.feature === feats[i - 1]);
      });
    }
    paintSpecs();
  }

  function specMatches(s, f) {
    if (f.status && (!s.kanban || s.kanban.status !== f.status)) return false;
    if (f.feature && (s.features || []).indexOf(f.feature) === -1) return false;
    if (f.q) {
      var hay = (s.id + " " + (s.title || "") + " " + (s.summary || "") + " " + (s.body || "") + " " + (s.features || []).join(" ")).toLowerCase();
      if (hay.indexOf(f.q) === -1) return false;
    }
    return true;
  }

  function paintSpecs() {
    var f = state.specFilter;
    var grid = $("spec-grid");
    var count = $("spec-count");
    if (!grid) return;
    var list = state.data.specs.filter(function (s) { return specMatches(s, f); });
    count.textContent = list.length + " of " + state.data.specs.length + " specs";
    if (!list.length) { grid.innerHTML = '<div class="empty">No specs match the current filters.</div>'; return; }
    grid.innerHTML = "";
    list.forEach(function (s) { grid.appendChild(specCard(s)); });
  }

  function specCard(s) {
    var k = s.kanban;
    var el = document.createElement("div");
    el.className = "card"; el.tabIndex = 0; el.setAttribute("role", "button");
    var st = k ? statusMeta(k.status) : null;
    var html =
      '<div class="card-head">' +
        '<span class="card-id">§' + esc(s.id) + '</span>' +
        (st ? '<span class="status-pill" style="background:' + esc(st.color) + '">' + esc(st.label) + '</span>' : '') +
      '</div>' +
      '<h3>' + esc(s.title || "Untitled") + '</h3>' +
      (s.summary ? '<p>' + esc(s.summary) + '</p>' : '') +
      '<div class="tags">' +
        (k && k.weight ? '<span class="weight-pill">' + esc(k.weight) + '</span>' : '') +
        (s.features || []).map(function (ft) { return '<span class="tag feat">' + esc(ft) + '</span>'; }).join('') +
      '</div>';
    el.innerHTML = html;
    el.addEventListener("click", function () { openSpec(s); });
    el.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openSpec(s); } });
    return el;
  }

  /* ---------------- Kanban view ---------------- */
  function renderKanban() {
    var search = document.createElement("input");
    search.type = "search"; search.placeholder = "Filter cards…"; search.value = state.specFilter.q;
    search.addEventListener("input", function () { state.specFilter.q = search.value.toLowerCase(); paintKanban(); });
    els.tools.appendChild(search);

    var cols = document.createElement("div");
    cols.className = "kanban"; cols.id = "kanban-cols";
    els.content.innerHTML = "";
    els.content.appendChild(cols);
    paintKanban();
  }

  function paintKanban() {
    var cols = $("kanban-cols");
    if (!cols) return;
    var q = state.specFilter.q;
    cols.innerHTML = "";
    state.data.legend.kanbanStatuses.forEach(function (st) {
      var inCol = state.data.specs.filter(function (s) {
        if (!s.kanban || s.kanban.status !== st.key) return false;
        if (q) {
          var hay = (s.id + " " + (s.title || "") + " " + (s.features || []).join(" ")).toLowerCase();
          if (hay.indexOf(q) === -1) return false;
        }
        return true;
      });
      var col = document.createElement("div");
      col.className = "kcol";
      col.innerHTML = '<div class="kcol-head"><span class="kcol-dot" style="background:' + esc(st.color) + '"></span>' +
        esc(st.label) + '<span class="kcol-count">' + inCol.length + '</span></div>';
      inCol.forEach(function (s) { col.appendChild(kanbanCard(s)); });
      if (!inCol.length) {
        var e = document.createElement("div"); e.className = "empty"; e.style.padding = "18px"; e.textContent = "—";
        col.appendChild(e);
      }
      cols.appendChild(col);
    });
  }

  function kanbanCard(s) {
    var el = document.createElement("div");
    el.className = "card"; el.tabIndex = 0; el.setAttribute("role", "button");
    el.innerHTML =
      '<div class="card-head"><span class="card-id">§' + esc(s.id) + '</span>' +
        (s.kanban && s.kanban.weight ? '<span class="weight-pill" style="margin-left:auto">' + esc(s.kanban.weight) + '</span>' : '') +
      '</div>' +
      '<h3 style="font-size:13px">' + esc(s.title || "Untitled") + '</h3>' +
      '<div class="tags">' + (s.features || []).map(function (ft) { return '<span class="tag feat">' + esc(ft) + '</span>'; }).join('') + '</div>';
    el.addEventListener("click", function () { openSpec(s); });
    el.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openSpec(s); } });
    return el;
  }

  /* ---------------- Diagrams ---------------- */
  function renderDiagram(key) {
    var dg = state.data.diagrams[key];
    els.content.innerHTML = "";
    if (!dg || !dg.def) {
      els.content.innerHTML = '<div class="empty">No <code>' + esc(key) + '</code> diagram in design.json.</div>';
      return;
    }
    var wrap = document.createElement("div");
    wrap.className = "diagram-wrap";
    wrap.innerHTML = (dg.title ? '<p class="diagram-title">' + esc(dg.title) + '</p>' : '');
    var host = document.createElement("div");
    host.className = "mermaid-host";
    wrap.appendChild(host);
    els.content.appendChild(wrap);

    if (!window.mermaid) { host.innerHTML = '<div class="empty">Mermaid library failed to load (needs network / CDN).</div>'; return; }
    var id = "mmd-" + key + "-" + Date.now();
    try {
      mermaid.render(id, dg.def).then(function (res) {
        host.innerHTML = res.svg;
        if (res.bindFunctions) res.bindFunctions(host);
      }).catch(function (e) { host.innerHTML = diagramErr(e, dg.def); });
    } catch (e) {
      host.innerHTML = diagramErr(e, dg.def);
    }
  }
  function diagramErr(e, def) {
    return '<div class="empty">Could not render diagram.<br><small>' + esc(e && e.message ? e.message : e) + '</small>' +
      '<pre style="text-align:left;margin-top:12px;background:var(--surface-2);padding:12px;border-radius:8px;overflow:auto">' + esc(def) + '</pre></div>';
  }

  /* ---------------- User Stories ---------------- */
  function renderStories() {
    var search = document.createElement("input");
    search.type = "search"; search.placeholder = "Search stories / actors…"; search.value = state.storyQ;
    search.addEventListener("input", function () { state.storyQ = search.value.toLowerCase(); paintStories(); });
    els.tools.appendChild(search);
    var host = document.createElement("div"); host.id = "stories-host";
    els.content.innerHTML = ""; els.content.appendChild(host);
    paintStories();
  }

  function paintStories() {
    var host = $("stories-host");
    if (!host) return;
    var q = state.storyQ;
    var groups = {};
    var order = [];
    state.data.userStories.forEach(function (u) {
      var hay = (u.id + " " + (u.actor || "") + " " + (u.title || "") + " " + (u.detail || "")).toLowerCase();
      if (q && hay.indexOf(q) === -1) return;
      var a = u.actor || "OTHER";
      if (!groups[a]) { groups[a] = []; order.push(a); }
      groups[a].push(u);
    });
    if (!order.length) { host.innerHTML = '<div class="empty">No stories match “' + esc(q) + '”.</div>'; return; }
    host.innerHTML = "";
    order.forEach(function (actor) {
      var g = document.createElement("div"); g.className = "actor-group";
      g.innerHTML = '<h3 class="actor-head">' + esc(actor) + ' <span class="badge">' + groups[actor].length + '</span></h3>';
      groups[actor].forEach(function (u) {
        var d = document.createElement("details"); d.className = "story";
        var secs = (u.sections || []).join(" ");
        d.innerHTML =
          '<summary><span class="st-id">' + esc(u.id) + '</span>' +
          '<span class="st-title">' + esc(u.title || "") + '</span>' +
          (secs ? '<span class="st-secs">' + esc(secs) + '</span>' : '') + '</summary>' +
          '<div class="st-detail">' + esc(u.detail || u.title || "") + '</div>';
        g.appendChild(d);
      });
      host.appendChild(g);
    });
  }

  /* ---------------- Detail drawer ---------------- */
  function openSpec(s) {
    var k = s.kanban;
    els.drawerTitle.textContent = "§" + s.id + " · " + (s.title || "Untitled");
    var parts = [];
    parts.push('<div class="meta-row">');
    if (k) {
      var st = statusMeta(k.status);
      parts.push('<span class="status-pill" style="background:' + esc(st.color) + '">' + esc(st.label) + '</span>');
      if (k.weight) parts.push('<span class="weight-pill">weight ' + esc(k.weight) + '</span>');
    }
    (s.features || []).forEach(function (ft) { parts.push('<span class="tag feat">' + esc(ft) + '</span>'); });
    parts.push('</div>');

    if (s.summary) parts.push('<p class="prose" style="color:var(--muted)">' + esc(s.summary) + '</p>');

    if (k) {
      parts.push(relBlock("Impacts", k.impacts));
      parts.push(relBlock("Depends on", k.deps));
      parts.push(relBlock("IDs", k.ids));
    }
    if (s.body) {
      parts.push('<h4>Details</h4><div class="prose">' + esc(s.body) + '</div>');
    }
    els.drawerBody.innerHTML = parts.join("");
    els.drawer.hidden = false;
    els.drawerBody.parentNode.querySelector(".drawer-close").focus();
  }
  function relBlock(label, arr) {
    if (!arr || !arr.length) return "";
    return '<h4>' + esc(label) + '</h4><div class="rel-list">' +
      arr.map(function (x) { return '<span class="tag">' + esc(x) + '</span>'; }).join("") + '</div>';
  }
  function closeDrawer() { els.drawer.hidden = true; }

  /* ---------------- misc ---------------- */
  function chip(label, on, onClick, color) {
    var b = document.createElement("button");
    b.className = "chip" + (on ? " on" : "");
    b.type = "button";
    b.textContent = label;
    if (color && on) { b.style.background = color; b.style.borderColor = color; }
    b.addEventListener("click", onClick);
    return b;
  }

  // expose fixture for a manual smoke test in console: __useFixture()
  window.__useFixture = function () { state.data = normalize(FIXTURE); onData(); };
})();
