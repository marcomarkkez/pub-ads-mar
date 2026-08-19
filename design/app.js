/* PubAds — Design Dashboard (vanilla JS). Data comes from design.json at runtime. */
(function () {
  "use strict";

  // Tiny inline fixture — used ONLY as a smoke-test fallback if design.json cannot be fetched
  // while developing. The live path always fetches design.json (see load()).
  var FIXTURE = {
    meta: { title: "PubAds — Design Dashboard (fixture)", source: "design.json (fixture)", version: "0" },
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
    specLayout: "kanban", // "list" | "kanban" — specs ARE spec-kanban, so default to the board
    specFilter: { feature: null, status: null, q: "" },
    storyQ: "",
    todoFilter: { status: null, q: "" },
    theme: null,          // null = system, "light", "dark"
    renderedDiagrams: {}, // cache: view -> true
    tray: []              // prompt tray: collected citations (see refFor)
  };

  var DEFAULT_TODO_STATUSES = [
    { key: "done", label: "Hecho", color: "#22c55e" },
    { key: "in_progress", label: "En curso", color: "#3b82f6" },
    { key: "pending", label: "Pendiente", color: "#f59e0b" },
    { key: "deferred", label: "Diferido", color: "#94a3b8" }
  ];

  var els = {};
  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  /* ---------------- boot ---------------- */
  function boot() {
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

    // deep-link routing: react to pasted URLs / back-forward (our own writes use replaceState)
    window.addEventListener("hashchange", navFromHash);

    // drawer close + delegated cross-link navigation (chips carry data-goto-type/id)
    els.drawer.addEventListener("click", function (e) {
      if (e.target.hasAttribute("data-close")) return closeDrawer();
      var el = e.target.closest ? e.target.closest("[data-el-view]") : null;
      if (el) { closeDrawer(); location.hash = el.getAttribute("data-el-view") + "/" + el.getAttribute("data-el-id"); return; }
      var chip = e.target.closest ? e.target.closest("[data-goto-type]") : null;
      if (chip) { goto(chip.getAttribute("data-goto-type"), chip.getAttribute("data-goto-id")); }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeDrawer();
    });

    // prompt tray
    els.tray = $("tray");
    els.trayN = $("tray-n");
    els.trayChips = $("tray-chips");
    $("tray-copy").addEventListener("click", function () { copyText(trayText(), this, "✓ Copiado"); });
    $("tray-clear").addEventListener("click", trayClear);
    $("drawer-copy").addEventListener("click", function () {
      if (state.drawerRef) copyText(refText(state.drawerRef), this, "✓ Copiado");
    });
    $("drawer-pin").addEventListener("click", function () {
      if (state.drawerRef) { trayToggle(state.drawerRef); syncDrawerPin(); }
    });
    trayRestore();

    initMermaid();
    load();
  }
  // app.js may be injected AFTER DOMContentLoaded (cache-bust loader), so don't rely on the event.
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();

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
    fetch("design.json?t=" + Date.now(), { cache: "no-store" })
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
    d.todos = Array.isArray(d.todos) ? d.todos : [];
    if (!Array.isArray(d.legend.todoStatuses) || !d.legend.todoStatuses.length) {
      d.legend.todoStatuses = DEFAULT_TODO_STATUSES;
    }
    d.diagrams = d.diagrams || {};
    return d;
  }

  function onData() {
    var m = state.data.meta;
    els.brandTitle.textContent = m.title || "Design Dashboard";
    els.brandSub.textContent = (state.data.specs.length) + " specs · " + state.data.userStories.length + " stories";
    els.metaLine.textContent = "source: " + (m.source || "design.json") +
      (m.version ? " · v" + m.version : "") + (m.generatedAt ? " · " + m.generatedAt : "");
    buildIndexes();
    navFromHash();
  }

  /* ---------------- deep-link routing (friendly URLs) ---------------- */
  // #view (specs|todos|flow|er|classes|stories) OR #type/id (spec|todo|uc) to open an item.
  function writeHash(h) {
    if (window.history && history.replaceState) history.replaceState(null, "", "#" + h);
    else location.hash = h; // fallback fires hashchange; navFromHash is idempotent
  }
  function navFromHash() {
    var raw = (location.hash || "").replace(/^#/, "");
    if (!raw) { return setView("specs"); }
    var slash = raw.indexOf("/");
    var head = slash === -1 ? raw : raw.slice(0, slash);
    var id = slash === -1 ? "" : decodeURIComponent(raw.slice(slash + 1));
    var diagViews = { flow: 1, er: 1, classes: 1 };
    if (diagViews[head] && id) {
      state.pendingHighlight = { view: head, id: id };
      return setView(head);
    }
    var itemViews = { spec: "specs", todo: "todos", uc: "stories" };
    if (itemViews[head] && id) {
      setView(itemViews[head]);
      return goto(head === "uc" ? "story" : head, id); // opens the item's drawer
    }
    var views = ["specs", "todos", "flow", "er", "classes", "stories", "sprint", "glossary", "rules", "errors", "questions", "endpoints", "walks"];
    setView(views.indexOf(head) !== -1 ? head : "specs");
  }

  /* ---------------- cross-link graph (specs ⇄ stories ⇄ todos) ---------------- */
  // Match keys: spec => "§id" + features; story => its "§sections"; todo => its tags + Fxx id.
  // Keys are EXPANDED feature<->section via the specs so the graph is well connected.
  function buildIndexes() {
    var specBySec = {}, specsByFeat = {}, specById = {}, storyById = {}, todoById = {};
    state.data.specs.forEach(function (s) {
      specById[s.id] = s;
      specBySec["§" + s.id] = s;
      (s.features || []).forEach(function (f) { (specsByFeat[f] = specsByFeat[f] || []).push(s); });
    });
    state.data.userStories.forEach(function (u) { storyById[u.id] = u; });
    state.data.todos.forEach(function (t) { if (t.id) todoById[t.id] = t; });
    state._ix = { specBySec: specBySec, specsByFeat: specsByFeat, specById: specById, storyById: storyById, todoById: todoById };

    // diagram-element indexes by id (for the "Impacto" chips + highlight)
    var dg = state.data.diagrams || {};
    state._flowById = {}; (((dg.flow || {}).index) || []).forEach(function (n) { state._flowById[n.id] = n; });
    state._erById = {};   (((dg.er || {}).index) || []).forEach(function (n) { state._erById[n.id] = n; });
    state._clsById = {};  (((dg.classes || {}).index) || []).forEach(function (n) { state._clsById[n.id] = n; });
  }

  /* ---------------- Impacto (Flow/ER/Classes) chips for a drawer ---------------- */
  function elChips(label, view, ids, byId, labelFn) {
    if (!ids || !ids.length) return "";
    return '<h4>' + esc(label) + '</h4><div class="rel-list">' + ids.map(function (id) {
      var n = byId[id];
      return '<button type="button" class="link-chip" data-el-view="' + view + '" data-el-id="' + esc(id) + '">' +
        esc(n ? labelFn(n) : id) + '</button>';
    }).join("") + '</div>';
  }
  function impactBlock(item) {
    var parts = [];
    parts.push(elChips("Flow", "flow", item.flow, state._flowById, function (n) { return n.id + " · " + n.label; }));
    parts.push(elChips("ER", "er", item.er, state._erById, function (n) { return n.id + " · " + n.name; }));
    parts.push(elChips("Clases", "classes", item.classes, state._clsById, function (n) { return n.id + " · " + n.name; }));
    if (item.suggestedUcs && item.suggestedUcs.length) {
      parts.push('<h4>UC sugeridas</h4><div class="rel-list">' + item.suggestedUcs.map(function (id) {
        return '<button type="button" class="link-chip suggested" data-goto-type="story" data-goto-id="' + esc(id) + '">' + esc(id) + ' · sugerida</button>';
      }).join("") + '</div>');
    }
    var html = parts.join("");
    return html ? '<h4 class="impact-head">Impacto (gráficos)</h4>' + html : "";
  }

  function baseKeys(type, item) {
    if (type === "spec") return ["§" + item.id].concat(item.features || []);
    if (type === "story") return (item.sections || []).slice();
    // todo
    var k = (item.tags || []).slice();
    if (/^F\d+$/i.test(item.id || "")) k.push(item.id);
    return k;
  }
  function expandKeys(tokens) {
    var out = {}, ix = state._ix;
    tokens.forEach(function (t) { out[t] = 1; });
    tokens.forEach(function (t) {
      if (t.charAt(0) === "§") { var sp = ix.specBySec[t]; if (sp) (sp.features || []).forEach(function (f) { out[f] = 1; }); }
      else { (ix.specsByFeat[t] || []).forEach(function (sp) { out["§" + sp.id] = 1; }); }
    });
    return Object.keys(out);
  }
  function keysOf(type, item) { return expandKeys(baseKeys(type, item)); }
  function related(item, itemType, targetType) {
    var ik = keysOf(itemType, item);
    var pool = targetType === "spec" ? state.data.specs : targetType === "story" ? state.data.userStories : state.data.todos;
    return pool.filter(function (t) {
      if (t === item) return false;
      var tk = keysOf(targetType, t);
      return ik.some(function (x) { return tk.indexOf(x) !== -1; });
    });
  }
  // delegated navigation: any [data-goto-type][data-goto-id] chip opens that item's drawer
  function goto(type, id) {
    var ix = state._ix; if (!ix) return;
    if (type === "spec" && ix.specById[id]) return openSpec(ix.specById[id]);
    if (type === "story" && ix.storyById[id]) return openStory(ix.storyById[id]);
    if (type === "todo" && ix.todoById[id]) return openTodo(ix.todoById[id]);
  }
  function relChips(label, type, items, labelFn) {
    if (!items || !items.length) return "";
    return '<h4>' + esc(label) + '</h4><div class="rel-list">' + items.map(function (it) {
      return '<button type="button" class="link-chip" data-goto-type="' + type + '" data-goto-id="' + esc(it.id) + '">' +
        esc(labelFn(it)) + '</button>';
    }).join("") + '</div>';
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
    var titles = { specs: "Specs", todos: "Todos", flow: "Flow", er: "ER", classes: "Classes", stories: "User Stories", sprint: "Sprint", glossary: "Glosario", rules: "Reglas de negocio", errors: "Cacería de errores", questions: "Preguntas abiertas", endpoints: "Endpoints", walks: "Recorridos humanos" };
    els.title.textContent = titles[v] || v;
    writeHash(v);
    render();
  }

  function render() {
    if (!state.data) return;
    els.tools.innerHTML = "";
    switch (state.view) {
      case "specs": return renderSpecs();
      case "todos": return renderTodos();
      case "flow": return renderDiagram("flow");
      case "er": return renderDiagram("er");
      case "classes": return renderDiagram("classes");
      case "stories": return renderStories();
      case "sprint": return renderSprint();
      case "glossary": return renderGlossary();
      case "rules": return renderBusinessRules();
      case "errors": return renderErrorHunt();
      case "questions": return renderQuestions();
      case "endpoints": return renderEndpoints();
      case "walks": return renderWalks();
    }
  }

  /* ---------------- Sprint view ----------------
     The granular build backlog, folded in from the deleted
     .claude/todos/mvp-sprint.json. Ids carry the `mvp-sprint:` prefix so their
     provenance stays readable; `[built|partial|missing Pn]` is the status. */
  function renderSprint() {
    var ms = state.data.mvpSprint;
    els.content.innerHTML = "";
    if (!ms) { els.content.innerHTML = '<div class="empty">No <code>mvpSprint</code> block in design.json.</div>'; return; }

    var box = document.createElement("div");
    box.className = "sprint";
    var html = '<p class="prose" style="color:var(--muted)">' + esc(ms.note || "") + '</p>';

    if (ms.goal) html += '<h3>Goal</h3><p class="prose">' + esc(ms.goal) + '</p>';

    var sb = ms.specBacklog || {};
    if (sb.legend) html += '<h3>Backlog legend</h3><p class="prose" style="color:var(--muted)">' + esc(sb.legend) + '</p>';

    var doms = sb.domains || {};
    Object.keys(doms).forEach(function (dom) {
      var lines = doms[dom] || [];
      html += '<h3 data-pin-sb="' + esc(dom) + '">' + esc(dom.replace(/_/g, " ")) +
        ' <span class="badge">' + lines.length + '</span></h3><ul class="sprint-list">';
      lines.forEach(function (l) {
        var m = /\[(built|partial|missing)\s/.exec(l);
        var cls = m ? " is-" + m[1] : "";
        html += '<li class="sprint-item' + cls + '">' + esc(l) + '</li>';
      });
      html += '</ul>';
    });

    if (ms.remaining && ms.remaining.length) {
      html += '<h3 data-pin-sb="__remaining">Remaining <span class="badge">' + ms.remaining.length + '</span></h3><ul class="sprint-list">';
      ms.remaining.forEach(function (r) { html += '<li class="sprint-item">' + esc(r) + '</li>'; });
      html += '</ul>';
    }

    box.innerHTML = html;
    els.content.appendChild(box);

    Object.keys(doms).forEach(function (dom) {
      attachPin(box, '[data-pin-sb="' + cssq(dom) + '"]', "sprintBlock", {
        title: dom.replace(/_/g, " "), count: (doms[dom] || []).length,
        jq: '.mvpSprint.specBacklog.domains["' + dom + '"]', id: "sprint/" + dom,
      });
    });
    if (ms.remaining && ms.remaining.length) {
      attachPin(box, '[data-pin-sb="__remaining"]', "sprintBlock", {
        title: "Remaining", count: ms.remaining.length,
        jq: '.mvpSprint.remaining', id: "sprint/remaining",
      });
    }
  }

  /* ---------------- Glosario ----------------
     design.json .glossary — el vocabulario del proyecto para quien no se sabe los endpoints de
     memoria. Dos secciones: prefijos de identificador y terminos de dominio. Un termino RETIRADO
     se marca en vez de borrarse, para poder leer los textos fechados sin confundirse. */
  function renderGlossary() {
    var g = state.data.glossary;
    els.content.innerHTML = "";
    if (!g) { els.content.innerHTML = '<div class="empty">No <code>glossary</code> block in design.json.</div>'; return; }

    var q = "";
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = "Buscar en el glosario…";
    search.addEventListener("input", function () { q = search.value.toLowerCase(); paint(); });
    els.tools.appendChild(search);

    var box = document.createElement("div");
    box.className = "sprint";
    els.content.appendChild(box);
    paint();

    function paint() {
      var pre = (g.idPrefixes || []).filter(function (p) {
        return !q || (p.code + " " + p.name + " " + p.meaning + " " + (p.example || "") + " " + (p.where || "")).toLowerCase().indexOf(q) !== -1;
      });
      var ter = (g.terms || []).filter(function (t) {
        return !q || (t.term + " " + t.meaning).toLowerCase().indexOf(q) !== -1;
      });
      var html = '<p class="prose" style="color:var(--muted)">' + esc(g.convention || "") + '</p>';
      html += '<h3>Prefijos de identificador <span class="badge">' + pre.length + '</span></h3><div class="gloss-list">';
      pre.forEach(function (p) {
        html += '<div class="gloss-row" data-pin-pre="' + esc(p.code) + '"><div class="gloss-code">' + esc(p.code) + '</div><div>' +
          '<div class="gloss-name">' + esc(p.name) + '</div>' +
          '<p class="prose">' + linkRefs(p.meaning) + '</p>' +
          '<div class="gloss-meta">' + (p.example ? 'ej. ' + linkRefs(p.example) : "") +
          (p.where ? ' · vive en <code>' + esc(p.where) + '</code>' : "") + '</div></div></div>';
      });
      html += '</div><h3>Terminos <span class="badge">' + ter.length + '</span></h3><div class="gloss-list">';
      ter.forEach(function (t) {
        var dead = /RETIRAD/.test(t.meaning);
        html += '<div class="gloss-row' + (dead ? " is-retired" : "") + '" data-pin-term="' + esc(t.term) + '">' +
          '<div class="gloss-code">' + esc(t.term) + '</div>' +
          '<div><p class="prose">' + linkRefs(t.meaning) + '</p></div></div>';
      });
      html += '</div>';
      box.innerHTML = html;
      pre.forEach(function (p) { attachPin(box, '[data-pin-pre="' + cssq(p.code) + '"]', "prefix", p); });
      ter.forEach(function (t) { attachPin(box, '[data-pin-term="' + cssq(t.term) + '"]', "term", t); });
    }
  }

  /* ---------------- Reglas de negocio ----------------
     design.json .businessRules — los guardrailes que el owner ya fallo y que el codigo debe
     hacer ciertos. Cada regla nombra en `enforcedBy` lo que la sostiene HOY, y `status`
     (built/partial/missing) dice si eso existe: una regla `partial` es trabajo pendiente,
     no un hecho. */
  function renderBusinessRules() {
    var br = state.data.businessRules;
    els.content.innerHTML = "";
    if (!br) { els.content.innerHTML = '<div class="empty">No <code>businessRules</code> block in design.json.</div>'; return; }

    var q = "";
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = "Buscar reglas…";
    search.addEventListener("input", function () { q = search.value.toLowerCase(); paint(); });
    els.tools.appendChild(search);

    var box = document.createElement("div");
    box.className = "sprint";
    els.content.appendChild(box);
    paint();

    function paint() {
      var items = (br.items || []).filter(function (r) {
        var hay = r.id + " " + (r.key || "") + " " + r.title + " " + r.rule + " " + r.why + " " +
          (r.appliesTo || []).join(" ") + " " + r.enforcedBy + " " + r.status;
        return !q || hay.toLowerCase().indexOf(q) !== -1;
      });
      var html = '<p class="prose" style="color:var(--muted)">' + esc(br.convention || "") + '</p>';
      html += '<h3>Reglas <span class="badge">' + items.length + '</span></h3>';
      items.forEach(function (r) {
        html += '<div class="rule is-' + esc(r.status) + '"><div class="rule-head">' +
          '<span class="q-id">' + esc(r.id) + '</span>' +
          keyChip(r.key) +
          '<span class="rule-title">' + esc(r.title) + '</span>' +
          '<span class="tag rule-state is-' + esc(r.status) + '">' + esc(r.status) + '</span>' +
          /* Mismo sitio que en Recorridos: extremo derecho de la cabecera (ver renderWalks). */
          '<span class="head-pin" data-pin-rule="' + esc(r.id) + '"></span>' +
          '</div>' +
          '<p class="prose rule-rule">' + esc(r.rule) + '</p>' +
          '<p class="prose rule-why"><span class="lbl">por que</span> ' + esc(r.why) + '</p>' +
          '<p class="prose rule-by"><span class="lbl">lo sostiene</span> ' + esc(r.enforcedBy) + '</p>' +
          '<div class="row-tags">' + (r.appliesTo || []).map(function (a) {
            return '<span class="tag">' + esc(a) + '</span>';
          }).join('') + '</div></div>';
      });
      if (!items.length) html += '<div class="empty">Ninguna regla coincide.</div>';
      box.innerHTML = html;
      items.forEach(function (r) { attachPin(box, '[data-pin-rule="' + cssq(r.id) + '"]', "rule", r); });
    }
  }

  /* ---------------- Caceria de errores ----------------
     design.json .errorHunt — patrones de fallo que YA aparecieron aqui, cada uno con el caso
     real que lo delato. Lo util es `howToFind`: la busqueda que se puede repetir manana sobre
     codigo nuevo. `whyItHides` explica por que ninguna prueba fallaba mientras el error vivia. */
  function renderErrorHunt() {
    var eh = state.data.errorHunt;
    els.content.innerHTML = "";
    if (!eh) { els.content.innerHTML = '<div class="empty">No <code>errorHunt</code> block in design.json.</div>'; return; }

    var q = "";
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = "Buscar patrones de error…";
    search.addEventListener("input", function () { q = search.value.toLowerCase(); paint(); });
    els.tools.appendChild(search);

    var box = document.createElement("div");
    box.className = "sprint";
    els.content.appendChild(box);
    paint();

    function paint() {
      var items = (eh.items || []).filter(function (e) {
        var hay = e.id + " " + (e.key || "") + " " + e.pattern + " " + e.howToFind + " " + e.whyItHides + " " +
          e.realExample + " " + e.status;
        return !q || hay.toLowerCase().indexOf(q) !== -1;
      });
      var html = '<p class="prose" style="color:var(--muted)">' + esc(eh.convention || "") + '</p>';
      html += '<h3>Patrones <span class="badge">' + items.length + '</span></h3>';
      items.forEach(function (e) {
        var open = e.status === "open";
        html += '<div class="hunt' + (open ? " is-open" : "") + '"><div class="rule-head">' +
          '<span class="q-id">' + esc(e.id) + '</span>' +
          keyChip(e.key) +
          '<span class="rule-title">' + esc(e.pattern) + '</span>' +
          '<span class="tag q-state ' + (open ? "open" : "done") + '">' + esc(e.status) + '</span>' +
          /* Antes colgaba de un .row-tags VACIO al final de la ficha, invisible por partida doble.
             Ahora en la cabecera, como en el resto de vistas. */
          '<span class="head-pin" data-pin-hunt="' + esc(e.id) + '"></span>' +
          '</div>' +
          '<p class="prose hunt-find"><span class="lbl">como buscarlo</span> ' + esc(e.howToFind) + '</p>' +
          '<p class="prose hunt-hide"><span class="lbl">por que se esconde</span> ' + esc(e.whyItHides) + '</p>' +
          '<p class="prose hunt-real"><span class="lbl">caso real</span> ' + esc(e.realExample) + '</p>' +
          '</div>';
      });
      if (!items.length) html += '<div class="empty">Ningun patron coincide.</div>';
      box.innerHTML = html;
      items.forEach(function (e) { attachPin(box, '[data-pin-hunt="' + cssq(e.id) + '"]', "hunt", e); });
    }
  }

  /* ---------------- Preguntas ----------------
     design.json .openQuestions — decisiones que solo el dueño toma. `items` son las abiertas;
     `resolved` guarda UNA linea por decision cerrada, para que nadie la vuelva a plantear. */
  function renderQuestions() {
    var oq = state.data.openQuestions;
    els.content.innerHTML = "";
    if (!oq) { els.content.innerHTML = '<div class="empty">No <code>openQuestions</code> block in design.json.</div>'; return; }

    var q = "";
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = "Buscar preguntas…";
    search.addEventListener("input", function () { q = search.value.toLowerCase(); paint(); });
    els.tools.appendChild(search);

    var box = document.createElement("div");
    box.className = "sprint";
    els.content.appendChild(box);
    paint();

    function paint() {
      var open = (oq.items || []).filter(function (i) {
        return !q || (i.id + " " + i.question + " " + i.oneLiner + " " + (i.impacts || []).join(" ")).toLowerCase().indexOf(q) !== -1;
      });
      var res = (oq.resolved || []).filter(function (i) {
        return !q || (i.id + " " + i.oneLiner + " " + i.resolution).toLowerCase().indexOf(q) !== -1;
      });
      var html = '<p class="prose" style="color:var(--muted)">' + esc(oq.convention || "") + '</p>';
      html += '<h3>Abiertas <span class="badge">' + open.length + '</span></h3><div class="q-list">';
      open.forEach(function (i) {
        html += '<div class="q-row is-open"><div class="q-head"><span class="q-id">' + esc(i.id) + '</span>' +
          '<span class="tag q-state open">abierta</span>' +
          '<span class="head-pin" data-pin-q="' + esc(i.id) + '"></span></div>' +
          '<div class="q-question">' + esc(i.question) + '</div>' +
          '<p class="prose">' + esc(i.oneLiner) + '</p>' +
          '<div class="row-tags">' + (i.impacts || []).map(function (x) {
            return '<span class="tag">' + esc(x) + '</span>';
          }).join('') + '</div></div>';
      });
      if (!open.length) html += '<div class="empty">Ninguna pregunta abierta coincide.</div>';
      html += '</div><h3>Resueltas <span class="badge">' + res.length + '</span></h3><div class="q-list">';
      res.forEach(function (i) {
        html += '<div class="q-row"><div class="q-head"><span class="q-id">' + esc(i.id) + '</span>' +
          '<span class="tag q-state done">resuelta ' + esc(i.answeredOn || "") + '</span>' +
          '<span class="head-pin" data-pin-q="' + esc(i.id) + '"></span></div>' +
          '<p class="prose" style="color:var(--muted)">' + esc(i.oneLiner) + '</p>' +
          '<p class="prose"><strong>→ ' + esc(i.resolution) + '</strong></p></div>';
      });
      html += '</div>';
      box.innerHTML = html;
      open.concat(res).forEach(function (i) {
        attachPin(box, '[data-pin-q="' + cssq(i.id) + '"]', "question", i);
      });
    }
  }

  /* ---------------- Endpoints ----------------
     design.json .endpoints[] — el mapa de la API. Una entrada cuyo `group` NO empieza por
     "Planned - " describe una ruta que el backend sirve HOY; una "Planned - " es trabajo sin
     construir y nombra el Fxx que lo debe. Las dos direcciones las sostiene
     PlanningCodeCongruenceTest, asi que esta vista no es una lista de intenciones: es lo que
     de verdad responde, o rojo (BR-10). */
  function renderEndpoints() {
    var eps = state.data.endpoints || [];
    els.content.innerHTML = "";

    var q = "";
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = "Buscar rutas…";
    search.addEventListener("input", function () { q = search.value.toLowerCase(); paint(); });
    els.tools.appendChild(search);

    var onlyPlanned = document.createElement("label");
    onlyPlanned.className = "tool-check";
    onlyPlanned.innerHTML = '<input type="checkbox"> solo pendientes';
    onlyPlanned.querySelector("input").addEventListener("change", function () {
      pendingOnly = this.checked; paint();
    });
    els.tools.appendChild(onlyPlanned);
    var pendingOnly = false;

    var box = document.createElement("div");
    box.className = "sprint";
    els.content.appendChild(box);
    paint();

    function paint() {
      var items = eps.filter(function (e) {
        if (pendingOnly && !isPlanned(e)) return false;
        var hay = e.method + " " + e.path + " " + e.desc + " " + e.group + " " + (e.todo || "");
        return !q || hay.toLowerCase().indexOf(q) !== -1;
      });

      var live = items.filter(function (e) { return !isPlanned(e); });
      var html = '<p class="prose" style="color:var(--muted)">' +
        esc((state.data.meta && state.data.meta.endpointsConvention) || "") + '</p>';
      html += '<h3>Rutas <span class="badge">' + live.length + ' vivas</span> ' +
        '<span class="badge">' + (items.length - live.length) + ' pendientes</span></h3>';

      var groups = {};
      items.forEach(function (e) { (groups[e.group] = groups[e.group] || []).push(e); });

      Object.keys(groups).sort(function (a, b) {
        /* Lo que existe primero; lo pendiente al final, que es donde se lee como backlog
           y no como capacidad. */
        var pa = a.indexOf("Planned") === 0, pb = b.indexOf("Planned") === 0;
        return pa === pb ? a.localeCompare(b) : (pa ? 1 : -1);
      }).forEach(function (g) {
        html += '<div class="ep-group"><h4>' + esc(g) +
          '<span class="badge">' + groups[g].length + '</span></h4><div class="ep-rows">';
        groups[g].forEach(function (e) {
          var planned = isPlanned(e);
          html += '<div class="ep-row' + (planned ? " is-planned" : "") + '">' +
            '<span class="ep-method m-' + esc(e.method.toLowerCase()) + '">' + esc(e.method) + '</span>' +
            '<code class="ep-path">' + esc(e.path) + '</code>' +
            '<span class="ep-desc">' + esc(e.desc) + '</span>' +
            (planned ? '<span class="tag feat">falta ' + esc(e.todo || "?") + '</span>' : "") +
            (e.sectionId ? '<span class="tag">§' + esc(e.sectionId) + '</span>' : "") +
            '<span class="ep-pin" data-pin-ep="' + esc(e.method + " " + e.path) + '"></span>' +
            '</div>';
        });
        html += '</div></div>';
      });

      if (!items.length) html += '<div class="empty">Ninguna ruta coincide.</div>';
      box.innerHTML = html;
      items.forEach(function (e) {
        attachPin(box, '[data-pin-ep="' + cssq(e.method + " " + e.path) + '"]', "endpoint", e);
      });
    }

    function isPlanned(e) { return (e.group || "").indexOf("Planned") === 0; }
  }

  /* ---------------- Recorridos humanos ----------------
     design.json .walkthroughs — verificaciones que hace una PERSONA sobre la UI real. Un Fxx no
     pasa a `done` mientras su WALK siga pendiente, asi que esta vista dice literalmente que es lo
     que falta para cerrar cada feature. */
  function renderWalks() {
    var w = state.data.walkthroughs;
    els.content.innerHTML = "";
    if (!w) { els.content.innerHTML = '<div class="empty">No <code>walkthroughs</code> block in design.json.</div>'; return; }

    var q = "";
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = "Buscar recorridos…";
    search.addEventListener("input", function () { q = search.value.toLowerCase(); paint(); });
    els.tools.appendChild(search);

    var box = document.createElement("div");
    box.className = "sprint";
    els.content.appendChild(box);
    paint();

    function paint() {
      var items = (w.items || []).filter(function (it) {
        var hay = it.id + " " + it.title + " " + (it.closes || []).join(" ") + " " +
          (it.br || []).join(" ") + " " + (it.eh || []).join(" ") + " " +
          (it.steps || []).map(function (s) { return s.role + " " + s.action + " " + s.expected; }).join(" ");
        return !q || hay.toLowerCase().indexOf(q) !== -1;
      });
      var html = '<p class="prose" style="color:var(--muted)">' + esc(w.convention || "") + '</p>';
      items.forEach(function (it) {
        /* Un recorrido sin recorrer mantiene ABIERTO todo lo que referencia, por muy verde que
           este la suite (walkthroughs.convention · BR-16). Por eso las referencias se pintan
           siempre, y en `passed` cambian de tono en vez de desaparecer: son la evidencia de
           que ESE § o ESE Fxx se cerro por un recorrido y no por revision en seco. */
        var done = it.status === "passed";
        var meta = walkStatusMeta(it.status);
        /* El boton de bandeja va en la CABECERA, no al final de .row-tags. Ahi era el chip numero
           quince de una fila de quince chips grises (WALK-6) y el dueno, literalmente, "no veia el
           +". En la cabecera el numero de elementos es fijo y pequeno, es por donde se entra a la
           tarjeta, y .row-tags vuelve a ser lo que dice ser: referencias, sin un control de accion
           mezclado entre los datos. */
        html += '<div class="walk is-' + esc(it.status) + '"><div class="walk-head">' +
          '<span class="q-id">' + esc(it.id) + '</span>' +
          '<span class="walk-title">' + esc(it.title) + '</span>' +
          '<span class="tag q-state ' + (done ? "done" : "open") + '" style="border-color:' + esc(meta.color) + '">' +
          esc(meta.label) + '</span>' +
          '<span class="head-pin" data-pin-walk="' + esc(it.id) + '"></span>' +
          '</div><div class="row-tags">' +
          (it.closes || []).map(function (c) { return refChip("cierra " + c, done); }).join('') +
          (it.specs || []).map(function (s) { return refChip(s, done); }).join('') +
          (it.ucs || []).map(function (u) { return refChip(u, done); }).join('') +
          (it.br || []).map(function (b) { return refChip(b, done, "br"); }).join('') +
          (it.eh || []).map(function (e) { return refChip(e, done, "eh"); }).join('') +
          '</div><ol class="walk-steps">';
        (it.steps || []).forEach(function (s) {
          html += '<li class="walk-step"><span class="walk-role">' + esc(s.role) + '</span>' +
            '<div class="walk-action">' + esc(s.action) + '</div>' +
            '<div class="walk-expect"><span>se espera</span> ' + esc(s.expected) + '</div>' +
            /* BR-17: hay verdades que la pantalla no ensena. Un 404 y un 403 se ven identicos
               en la interfaz y son toda la diferencia de BR-3, asi que el paso lleva su
               comprobacion de consola/cURL al lado de lo que hay que mirar. */
            /* Y va con boton de copiar, que no es adorno: WALK-6 (2026-08-15) fallo porque
               el probe correcto estaba aqui pero no se podia copiar — se tecleo a mano y en
               el camino se perdio la linea que llena el id, asi que la URL colapso a la
               coleccion y devolvio 405. Un comando que no se puede copiar se vuelve a
               teclear, y se teclea mal. */
            (s.probe ? '<div class="walk-probe">' +
              '<div class="walk-probe-head"><span>comprobar</span>' +
              '<button type="button" class="probe-copy" title="Copiar el comando entero">copiar</button>' +
              '</div><pre><code>' + esc(s.probe) + '</code></pre></div>' : "") +
            '</li>';
        });
        html += '</ol>';
        if (it.result) {
          html += '<p class="prose walk-result"><span class="lbl">resultado</span> ' +
            esc([it.result.date, it.result.by].filter(Boolean).join(" · ")) + ' — ' +
            esc(it.result.notes || "") + '</p>';
        }
        html += (it.notes ? '<p class="prose walk-note">' + esc(it.notes) + '</p>' : "") + '</div>';
      });
      if (!items.length) html += '<div class="empty">Ningun recorrido coincide.</div>';
      box.innerHTML = html;
      items.forEach(function (it) { attachPin(box, '[data-pin-walk="' + cssq(it.id) + '"]', "walk", it); });
    }
  }

  /* ---------------- helpers ---------------- */
  function statusMeta(key) {
    var list = state.data.legend.kanbanStatuses;
    for (var i = 0; i < list.length; i++) if (list[i].key === key) return list[i];
    return { key: key, label: key || "—", color: "#94a3b8" };
  }

  /* Un recorrido no usa los estados del kanban: no esta "en curso", esta hecho o no.
     Si `legend.walkStatuses` no existe todavia, el fallback deja el id crudo en pantalla
     antes que inventarse una etiqueta — un estado desconocido tiene que NOTARSE. */
  function walkStatusMeta(key) {
    var list = state.data.legend.walkStatuses || [];
    for (var i = 0; i < list.length; i++) if (list[i].key === key) return list[i];
    return { key: key, label: key || "—", color: "#94a3b8" };
  }

  /* Referencia de un recorrido (§, UC, Fxx, BR, EH). Mientras el recorrido no haya pasado,
     la referencia se lee como trabajo abierto; cuando pasa, se apaga. */
  function refChip(text, done, kind) {
    return '<span class="tag walk-ref' + (kind ? " is-" + kind : "") +
      (done ? " is-done" : "") + '">' + esc(text) + '</span>';
  }

  /* La `key` de una BR/EH existe para poder citarla en una conversacion sin reescribirla
     entera (owner 2026-08-04), asi que aqui es un boton que copia, no un adorno. */
  function keyChip(key) {
    if (!key) return "";
    return '<button type="button" class="key-chip" data-key="' + esc(key) + '" ' +
      'title="Copiar «' + esc(key) + '»">' + esc(key) + '</button>';
  }

  /* El comando del paso se copia ENTERO, leyendolo del DOM y no de un atributo: un probe
     lleva comillas anidadas de tres niveles y meterlo en un `data-` es una via mas de
     estropearlo. Pasa por `copyText` — que sabe caer a `execCommand` — porque el tablero
     tambien se sirve por http:// en un port-forward, donde `navigator.clipboard` no existe. */
  document.addEventListener("click", function (ev) {
    var btn = ev.target.closest ? ev.target.closest(".probe-copy") : null;
    if (!btn) return;
    var code = btn.parentNode.parentNode.querySelector("code");
    if (code) copyText(code.textContent, btn, "copiado");
  });

  document.addEventListener("click", function (ev) {
    var chip = ev.target.closest ? ev.target.closest(".key-chip") : null;
    if (!chip) return;
    var key = chip.getAttribute("data-key");
    var done = function () {
      var prev = chip.textContent;
      chip.textContent = "copiado";
      setTimeout(function () { chip.textContent = prev; }, 900);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(key).then(done, function () { window.prompt("Copia la clave:", key); });
    } else {
      window.prompt("Copia la clave:", key);
    }
  });
  function allFeatures() {
    var set = {};
    state.data.specs.forEach(function (s) { (s.features || []).forEach(function (f) { set[f] = 1; }); });
    return Object.keys(set).sort();
  }

  /* Cross-cutting groups (design.json .groups). A group spans specs + stories + todos, so it is
     NOT part of the §/feature cross-link graph: it labels a WORK FRONT, not a dependency. */
  function groupMeta(slug) {
    var reg = ((state.data.groups || {}).registry) || [];
    for (var i = 0; i < reg.length; i++) if (reg[i].slug === slug) return reg[i];
    return { slug: slug, label: slug };
  }
  function groupChips(item) {
    return (item.groups || []).map(function (g) {
      var m = groupMeta(g);
      return '<span class="tag group" title="' + esc(m.description || m.label) + '">' + esc(m.label) + '</span>';
    }).join('');
  }
  /* Group slugs and labels join the free-text haystack so the existing search box filters by group. */
  function groupHay(item) {
    return (item.groups || []).map(function (g) { return g + " " + (groupMeta(g).label || ""); }).join(" ");
  }

  /* ---------------- Specs view ---------------- */
  function renderSpecs() {
    var f = state.specFilter;
    els.tools.innerHTML = "";

    // layout toggle — Lista ⇄ Kanban are two views of the SAME specs data.
    var seg = document.createElement("div");
    seg.className = "seg-toggle";
    [["list", "Lista"], ["kanban", "Kanban"]].forEach(function (m) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "seg" + (state.specLayout === m[0] ? " on" : "");
      b.textContent = m[1];
      b.addEventListener("click", function () { state.specLayout = m[0]; renderSpecs(); });
      seg.appendChild(b);
    });
    els.tools.appendChild(seg);

    // search (shared by both layouts)
    var search = document.createElement("input");
    search.type = "search";
    search.placeholder = state.specLayout === "kanban" ? "Filter cards…" : "Search specs…";
    search.value = f.q;
    search.addEventListener("input", function () {
      f.q = search.value.toLowerCase();
      if (state.specLayout === "kanban") paintKanban(); else paintSpecs();
    });
    els.tools.appendChild(search);

    // KANBAN layout: columns by status
    if (state.specLayout === "kanban") {
      var cols = document.createElement("div");
      cols.className = "kanban"; cols.id = "kanban-cols";
      els.content.innerHTML = "";
      els.content.appendChild(cols);
      paintKanban();
      return;
    }

    // LIST layout: filter chips + compact rows
    var wrap = document.createElement("div");
    wrap.innerHTML =
      '<div class="filterbar">' +
        '<div class="filter-group" id="status-filters"></div>' +
        '<span style="width:1px;height:20px;background:var(--border)"></span>' +
        '<div class="filter-group" id="feature-filters"></div>' +
        '<span class="count-note" id="spec-count"></span>' +
      '</div><div class="spec-list" id="spec-list"></div>';
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
      var hay = (s.id + " " + (s.title || "") + " " + (s.summary || "") + " " + (s.body || "") + " " +
                 (s.features || []).join(" ") + " " + groupHay(s)).toLowerCase();
      if (hay.indexOf(f.q) === -1) return false;
    }
    return true;
  }

  function paintSpecs() {
    var f = state.specFilter;
    var listEl = $("spec-list");
    var count = $("spec-count");
    if (!listEl) return;
    var list = state.data.specs.filter(function (s) { return specMatches(s, f); });
    if (count) count.textContent = list.length + " of " + state.data.specs.length + " specs";
    if (!list.length) { listEl.innerHTML = '<div class="empty">No specs match the current filters.</div>'; return; }
    listEl.innerHTML = "";
    list.forEach(function (s) { listEl.appendChild(specRow(s)); });
  }

  function specRow(s) {
    var k = s.kanban;
    var st = k ? statusMeta(k.status) : null;
    var el = document.createElement("div");
    el.className = "spec-row"; el.tabIndex = 0; el.setAttribute("role", "button");
    el.innerHTML =
      '<span class="row-id">§' + esc(s.id) + '</span>' +
      '<span class="row-main">' +
        '<span class="row-title">' + esc(s.title || "Untitled") + '</span>' +
        (s.summary ? '<span class="row-sub">' + esc(s.summary) + '</span>' : '') +
      '</span>' +
      '<span class="row-tags">' +
        groupChips(s) +
        (s.features || []).map(function (ft) { return '<span class="tag feat">' + esc(ft) + '</span>'; }).join('') +
        (k && k.weight ? '<span class="weight-pill">' + esc(k.weight) + '</span>' : '') +
        (st ? '<span class="status-pill" style="background:' + esc(st.color) + '">' + esc(st.label) + '</span>' : '') +
      '</span>';
    el.querySelector(".row-tags").appendChild(pinBtn("spec", s));
    el.addEventListener("click", function () { openSpec(s); });
    el.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openSpec(s); } });
    return el;
  }

  /* ---------------- Kanban layout (rendered inside the Specs view) ---------------- */
  function paintKanban() {
    var cols = $("kanban-cols");
    if (!cols) return;
    var q = state.specFilter.q;
    cols.innerHTML = "";
    state.data.legend.kanbanStatuses.forEach(function (st) {
      var inCol = state.data.specs.filter(function (s) {
        if (!s.kanban || s.kanban.status !== st.key) return false;
        if (q) {
          var hay = (s.id + " " + (s.title || "") + " " + (s.features || []).join(" ") + " " + groupHay(s)).toLowerCase();
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
      '<div class="tags">' + groupChips(s) +
        (s.features || []).map(function (ft) { return '<span class="tag feat">' + esc(ft) + '</span>'; }).join('') + '</div>';
    el.querySelector(".card-head").appendChild(pinBtn("spec", s));
    el.addEventListener("click", function () { openSpec(s); });
    el.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openSpec(s); } });
    return el;
  }

  /* ---------------- Todos view (consolidated; done + pending, single list) ---------------- */
  function renderTodos() {
    var f = state.todoFilter;
    els.tools.innerHTML = "";
    var search = document.createElement("input");
    search.type = "search"; search.placeholder = "Search todos…"; search.value = f.q;
    search.addEventListener("input", function () { f.q = search.value.toLowerCase(); paintTodos(); });
    els.tools.appendChild(search);

    var wrap = document.createElement("div");
    wrap.innerHTML =
      '<div class="filterbar"><div class="filter-group" id="todo-filters"></div>' +
      '<span class="count-note" id="todo-count"></span></div><div id="todo-host"></div>';
    els.content.innerHTML = "";
    els.content.appendChild(wrap);

    var tf = wrap.querySelector("#todo-filters");
    tf.appendChild(chip("All", f.status === null, function () { f.status = null; paintTodos(); refreshTChips(); }));
    state.data.legend.todoStatuses.forEach(function (st) {
      tf.appendChild(chip(st.label, f.status === st.key, function () {
        f.status = f.status === st.key ? null : st.key; paintTodos(); refreshTChips();
      }, st.color));
    });
    function refreshTChips() {
      tf.querySelectorAll(".chip").forEach(function (c, i) {
        c.classList.toggle("on", i === 0 ? f.status === null : f.status === state.data.legend.todoStatuses[i - 1].key);
      });
    }
    paintTodos();
  }

  function paintTodos() {
    var f = state.todoFilter;
    var host = $("todo-host");
    var count = $("todo-count");
    if (!host) return;
    var items = state.data.todos.filter(function (t) {
      if (f.status && t.status !== f.status) return false;
      if (f.q) {
        var hay = ((t.id || "") + " " + (t.title || "") + " " + (t.note || "") + " " +
                   (t.mvpSprintNote || "") + " " + (t.tags || []).join(" ") + " " + groupHay(t)).toLowerCase();
        if (hay.indexOf(f.q) === -1) return false;
      }
      return true;
    });
    if (count) count.textContent = items.length + " of " + state.data.todos.length + " todos";
    if (!items.length) { host.innerHTML = '<div class="empty">No todos match the current filters.</div>'; return; }
    host.innerHTML = "";
    state.data.legend.todoStatuses.forEach(function (st) {
      var group = items.filter(function (t) { return t.status === st.key; });
      if (!group.length) return;
      var g = document.createElement("div"); g.className = "todo-group";
      g.innerHTML = '<h3 class="todo-head"><span class="kcol-dot" style="background:' + esc(st.color) + '"></span>' +
        esc(st.label) + ' <span class="badge">' + group.length + '</span></h3>';
      group.forEach(function (t) { g.appendChild(todoRow(t, st)); });
      host.appendChild(g);
    });
  }

  function todoRow(t, st) {
    var el = document.createElement("div");
    el.className = "todo-row"; el.tabIndex = 0; el.setAttribute("role", "button");
    var done = t.status === "done";
    el.innerHTML =
      '<span class="todo-check" style="border-color:' + esc(st.color || "#94a3b8") + ';background:' + (done ? esc(st.color) : "transparent") + '">' + (done ? "✓" : "") + '</span>' +
      '<span class="todo-main">' +
        '<span class="todo-title' + (done ? " is-done" : "") + '">' + (t.id ? '<span class="todo-id">' + esc(t.id) + '</span> ' : '') + esc(t.title || "") + '</span>' +
        (t.note ? '<span class="todo-note">' + esc(t.note) + '</span>' : '') +
      '</span>' +
      '<span class="todo-tags">' + groupChips(t) +
        (t.tags || []).map(function (x) { return '<span class="tag feat">' + esc(x) + '</span>'; }).join('') + '</span>';
    el.querySelector(".todo-tags").appendChild(pinBtn("todo", t));
    el.addEventListener("click", function () { openTodo(t); });
    el.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openTodo(t); } });
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

    /* El indice bajo el diagrama. El SVG de mermaid no ofrece un sitio estable donde colgar
       un boton —sus nodos se regeneran en cada repintado y sus ids los inventa la libreria—,
       asi que lo citable es esta lista: mismos nodos, con sus referencias cruzadas y su boton
       de bandeja. Ademas hace buscable un diagrama, que un SVG no es. */
    var idx = dg.index || [];
    if (idx.length) {
      var list = document.createElement("div");
      list.className = "diagram-index";
      list.innerHTML = '<h3>Indice <span class="badge">' + idx.length + '</span></h3>' +
        '<div class="ep-rows">' + idx.map(function (n) {
          return '<div class="ep-row" data-pin-node="' + esc(n.id) + '">' +
            '<span class="ep-method">' + esc(n.id) + '</span>' +
            '<code class="ep-path">' + esc(n.label || n.name || "") + '</code>' +
            '<span class="ep-desc">' + (n.spec || []).concat(n.uc || []).concat(n.todo || [])
              .map(function (r) { return '<a href="#" class="ref-link" data-ref-jump="' + esc(r) + '">' + esc(r) + '</a>'; })
              .join(' · ') + '</span>' +
            '<span class="ep-pin"></span></div>';
        }).join('') + '</div>';
      els.content.appendChild(list);
      idx.forEach(function (n) {
        attachPin(list, '[data-pin-node="' + cssq(n.id) + '"] .ep-pin', "node", n);
      });
    }

    if (!window.mermaid) { host.innerHTML = '<div class="empty">Mermaid library failed to load (needs network / CDN).</div>'; return; }
    var id = "mmd-" + key + "-" + Date.now();
    try {
      mermaid.render(id, dg.def).then(function (res) {
        host.innerHTML = res.svg;
        if (res.bindFunctions) res.bindFunctions(host);
        maybeHighlight(host, key);
      }).catch(function (e) { host.innerHTML = diagramErr(e, dg.def); });
    } catch (e) {
      host.innerHTML = diagramErr(e, dg.def);
    }
  }
  // Highlight the pending element (FL/ER/CL) after a diagram renders, then scroll to it.
  function maybeHighlight(host, view) {
    var h = state.pendingHighlight;
    if (!h || h.view !== view) return;
    state.pendingHighlight = null;
    var target = null;
    if (view === "flow") {
      // Mermaid names the NODE group "…flowchart-FLxx-N" and EDGES "…L_FLaa_FLxx_0".
      // A bare "[id*=FLxx]" matches an edge first (edges come first in the DOM) and the
      // highlight then lands on a <path> with no child shape → nothing paints. Pin the node.
      target = host.querySelector('.node[id*="' + h.id + '"]') ||
               host.querySelector('[id*="flowchart-' + h.id + '"]') ||
               host.querySelector('[id*="' + h.id + '"]');
    } else {
      var meta = view === "er" ? state._erById[h.id] : state._clsById[h.id];
      var name = meta && meta.name ? meta.name.toLowerCase() : null;
      if (name) {
        var texts = host.querySelectorAll("text, .nodeLabel, tspan");
        var k;
        // Climb to the NODE group (holds the shape): class diagrams nest the label in a
        // foreignObject so closest("g") stops at a shapeless ".label" — .node has the rect.
        // pass 1: EXACT text match (the entity/class title); pass 2: contains (fallback)
        for (k = 0; k < texts.length && !target; k++) {
          if ((texts[k].textContent || "").trim().toLowerCase() === name) target = texts[k].closest(".node") || texts[k].closest("g") || texts[k];
        }
        for (k = 0; k < texts.length && !target; k++) {
          if ((texts[k].textContent || "").toLowerCase().indexOf(name) !== -1) target = texts[k].closest(".node") || texts[k].closest("g") || texts[k];
        }
      }
    }
    if (target) {
      target.classList.add("hl-target");
      try { target.scrollIntoView({ behavior: "smooth", block: "center", inline: "center" }); } catch (e) { /* noop */ }
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
      var hay = (u.id + " " + (u.actor || "") + " " + (u.title || "") + " " + (u.detail || "") + " " + (u.satisfiedBy || "") + " " + groupHay(u)).toLowerCase();
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
        var secs = (u.sections || []).join(" ");
        var row = document.createElement("div");
        row.className = "spec-row"; row.tabIndex = 0; row.setAttribute("role", "button");
        row.innerHTML =
          '<span class="row-id">' + esc(u.id) + '</span>' +
          '<span class="row-main"><span class="row-title">' + esc(u.title || "") + '</span>' +
          (u.detail ? '<span class="row-sub">' + esc(u.detail) + '</span>' : '') + '</span>' +
          '<span class="row-tags">' + groupChips(u) +
            (u.satisfiedBy ? '<span class="tag tag-satisfied" title="' + esc(u.satisfiedBy) + '">✓ en codigo</span>' : '') +
            (secs ? '<span class="tag">' + esc(secs) + '</span>' : '') + '</span>';
        row.querySelector(".row-tags").appendChild(pinBtn("story", u));
        row.addEventListener("click", function () { openStory(u); });
        row.addEventListener("keydown", function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openStory(u); } });
        g.appendChild(row);
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
    parts.push(groupChips(s));
    (s.features || []).forEach(function (ft) { parts.push('<span class="tag feat">' + esc(ft) + '</span>'); });
    parts.push('</div>');

    if (s.summary) parts.push('<p class="prose" style="color:var(--muted)">' + esc(s.summary) + '</p>');

    if (k) {
      parts.push(relBlock("Impacts", k.impacts));
      parts.push(relBlock("Depends on", k.deps));
      parts.push(relBlock("IDs", k.ids));
    }
    parts.push(relChips("User Stories", "story", related(s, "spec", "story"), storyLabel));
    parts.push(relChips("Todos", "todo", related(s, "spec", "todo"), todoLabel));
    parts.push(impactBlock(s));
    if (s.body) {
      parts.push('<h4>Details</h4><div class="prose">' + esc(s.body) + '</div>');
    }
    openDrawer("§" + s.id + " · " + (s.title || "Untitled"), parts.join(""), "spec/" + encodeURIComponent(s.id), refFor("spec", s));
  }

  function openStory(u) {
    var parts = [];
    parts.push('<div class="meta-row">');
    parts.push(groupChips(u));
    (u.sections || []).forEach(function (sec) { parts.push('<span class="tag">' + esc(sec) + '</span>'); });
    if (u.actor) parts.push('<span class="tag feat">' + esc(u.actor) + '</span>');
    parts.push('</div>');
    if (u.detail) parts.push('<div class="prose">' + esc(u.detail) + '</div>');
    // `satisfiedBy` names the code that already honours the story. A UC with nothing
    // behind it is an intention, not a requirement — showing the difference is the point.
    if (u.satisfiedBy) parts.push('<div class="satisfied-by"><span class="sb-label">Cumplido en codigo</span><code>' + esc(u.satisfiedBy) + '</code></div>');
    parts.push(relChips("Specs", "spec", related(u, "story", "spec"), specLabel));
    parts.push(relChips("Todos", "todo", related(u, "story", "todo"), todoLabel));
    parts.push(impactBlock(u));
    openDrawer(u.id + " · " + (u.title || ""), parts.join(""), "uc/" + encodeURIComponent(u.id), refFor("story", u));
  }

  function openTodo(t) {
    var st = todoStatusMeta(t.status);
    var parts = [];
    parts.push('<div class="meta-row"><span class="status-pill" style="background:' + esc(st.color) + '">' + esc(st.label) + '</span>');
    parts.push(groupChips(t));
    (t.tags || []).forEach(function (x) { parts.push('<span class="tag">' + esc(x) + '</span>'); });
    parts.push('</div>');
    if (t.note) parts.push('<p class="prose" style="color:var(--muted)">' + esc(t.note) + '</p>');
    // Folded in from the deleted .claude/todos/mvp-sprint.json — the per-feature
    // build log. It is the only place a feature's implementation history lives.
    if (t.mvpSprintNote) {
      parts.push('<h4>Feature review <span class="tag">' + esc(t.mvpSprintId || "mvp-sprint") + '</span>' +
        (t.mvpSprintStatus ? ' <span class="tag">' + esc(t.mvpSprintStatus) + '</span>' : '') + '</h4>');
      parts.push('<div class="prose">' + esc(t.mvpSprintNote) + '</div>');
    }
    parts.push(relChips("Specs", "spec", related(t, "todo", "spec"), specLabel));
    parts.push(relChips("User Stories", "story", related(t, "todo", "story"), storyLabel));
    parts.push(impactBlock(t));
    openDrawer((t.id ? t.id + " · " : "") + (t.title || "Todo"), parts.join(""), t.id ? "todo/" + encodeURIComponent(t.id) : null, refFor("todo", t));
  }

  function specLabel(s) { return "§" + s.id + " · " + (s.title || ""); }
  function storyLabel(u) { return u.id + " · " + (u.title || ""); }
  function todoLabel(t) { return (t.id ? t.id + " · " : "") + (t.title || ""); }
  function todoStatusMeta(key) {
    var list = state.data.legend.todoStatuses || [];
    for (var i = 0; i < list.length; i++) if (list[i].key === key) return list[i];
    return { key: key, label: key || "—", color: "#94a3b8" };
  }

  function openDrawer(title, html, hash, ref) {
    els.drawerTitle.textContent = title;
    els.drawerBody.innerHTML = html;
    els.drawer.hidden = false;
    state.drawerRef = ref || null;
    syncDrawerPin();
    if (hash) writeHash(hash);
    els.drawerBody.parentNode.querySelector(".drawer-close").focus();
  }
  function relBlock(label, arr) {
    if (!arr || !arr.length) return "";
    return '<h4>' + esc(label) + '</h4><div class="rel-list">' +
      arr.map(function (x) { return '<span class="tag">' + esc(x) + '</span>'; }).join("") + '</div>';
  }
  function closeDrawer() {
    if (els.drawer.hidden) return;
    els.drawer.hidden = true;
    writeHash(state.view); // drop the item id from the URL, keep the view
  }

  /* ---------------- Prompt tray ----------------
     The dashboard is READ-ONLY: there is no server behind it and no edit mode, so nothing here
     writes to design.json. What it CAN do is hand over an unambiguous citation — id, title, live
     status and the jq path that locates the node — so a change can be requested in the prompt and
     applied to the file in the repo. "Copiar ref" copies one item; the tray collects several. */

  function refFor(type, item) {
    var lines, k;
    if (type === "spec") {
      k = item.kanban || {};
      lines = [
        "§" + item.id + " · " + (item.title || "Untitled"),
        "status: " + (k.status || "—") + (k.weight ? " · weight: " + k.weight : "") +
          ((item.features || []).length ? " · features: " + item.features.join(", ") : ""),
        "jq: '.specs[] | select(.id==\"" + item.id + "\")'"
      ];
    } else if (type === "story") {
      lines = [
        item.id + " · " + (item.title || ""),
        (item.actor ? "actor: " + item.actor + " · " : "") +
          "secciones: " + ((item.sections || []).join(", ") || "—"),
        "jq: '.userStories[] | select(.id==\"" + item.id + "\")'"
      ];
    } else if (type === "rule" || type === "hunt") {
      var block = type === "rule" ? "businessRules" : "errorHunt";
      lines = [
        item.id + (item.key ? " (" + item.key + ")" : "") + " · " + (item.title || item.pattern || ""),
        "status: " + (item.status || "—"),
        "jq: '." + block + ".items[] | select(.id==\"" + item.id + "\")'"
      ];
    } else if (type === "walk") {
      lines = [
        item.id + " · " + (item.title || ""),
        "status: " + (item.status || "—") +
          ((item.closes || []).length ? " · cierra: " + item.closes.join(", ") : ""),
        "jq: '.walkthroughs.items[] | select(.id==\"" + item.id + "\")'"
      ];
    } else if (type === "endpoint") {
      // Un endpoint no tiene id: lo identifica el par metodo+ruta, asi que el jq filtra por
      // los dos. Filtrar solo por `.path` devolveria varias filas en cuanto una ruta sirva
      // dos verbos, que es justo el caso normal.
      lines = [
        item.method + " " + item.path,
        (item.group || "") + (item.todo ? " · falta " + item.todo : " · servido hoy"),
        "jq: '.endpoints[] | select(.path==\"" + item.path + "\" and .method==\"" + item.method + "\")'"
      ];
    } else if (type === "term" || type === "prefix") {
      var isTerm = type === "term";
      lines = [
        (isTerm ? item.term : item.code + " · " + (item.name || "")),
        (isTerm ? "glosario · termino" : "glosario · prefijo de identificador") +
          (item.where ? " · vive en " + item.where : ""),
        "jq: '.glossary." + (isTerm ? "terms[] | select(.term==\"" + item.term + "\")"
                                    : "idPrefixes[] | select(.code==\"" + item.code + "\")") + "'"
      ];
    } else if (type === "question") {
      // Las resueltas viven en otro array que las abiertas, asi que el jq tiene que apuntar
      // al correcto o la cita no encuentra nada.
      lines = [
        item.id + " · " + (item.question || item.oneLiner || ""),
        item.resolution ? "resuelta " + (item.answeredOn || "") + ": " + item.resolution : "abierta",
        "jq: '.openQuestions." + (item.resolution ? "resolved" : "items") +
          "[] | select(.id==\"" + item.id + "\")'"
      ];
    } else if (type === "sprintBlock") {
      // El sprint se pinta como prosa, no como objetos: lo citable es el BLOQUE. Un pin por
      // linea seria un boton que produce una cita sin ruta detras, que es EH-8 en miniatura.
      lines = [
        "Sprint · " + item.title,
        item.count + " linea(s)",
        "jq: '" + item.jq + "'"
      ];
    } else if (type === "node") {
      // Flow, ER y Classes comparten forma: un id, un nombre y sus referencias cruzadas.
      var kind = item.id.indexOf("FL") === 0 ? "flow" : (item.id.indexOf("ER") === 0 ? "er" : "classes");
      lines = [
        item.id + " · " + (item.label || item.name || ""),
        "diagrama: " + kind +
          ((item.spec || []).length ? " · " + item.spec.join(", ") : "") +
          ((item.todo || []).length ? " · " + item.todo.join(", ") : ""),
        "jq: '.diagrams." + kind + ".index[] | select(.id==\"" + item.id + "\")'"
      ];
    } else {
      // Dos ruidos que el owner detecto leyendo lo copiado (2026-08-08). Un Fxx se etiqueta
      // a si mismo —`F01` lleva `tags:["F01"]`, porque el todo ES la feature— y repetirlo en
      // la cita no aporta nada; y `mvpSprintId` ya viene con el prefijo dentro del valor, asi
      // que la etiqueta producia "mvp-sprint: mvp-sprint:F01". La cita tiene que leerse de un
      // vistazo: cada palabra repetida es una que hay que descartar al leer.
      var tags = (item.tags || []).filter(function (t) { return t !== item.id; });
      lines = [
        (item.id ? item.id + " · " : "") + (item.title || "Todo"),
        "status: " + (item.status || "—") +
          (tags.length ? " · tags: " + tags.join(", ") : "") +
          (item.mvpSprintId ? " · mvp-sprint: " + String(item.mvpSprintId).replace(/^mvp-sprint:/, "") : ""),
        item.id ? "jq: '.todos[] | select(.id==\"" + item.id + "\")'" : "jq: '.todos[] | select(.title==\"" + (item.title || "") + "\")'"
      ];
    }
    // The group goes in just above the jq path: it tells the prompt WHICH work front this belongs
    // to, which is what makes a bare "§10" or "F09" unambiguous when several fronts touch the same spec.
    if ((item.groups || []).length) lines.splice(lines.length - 1, 0, "grupo: " + item.groups.join(", "));
    // La clave identifica la ficha en la bandeja. Un endpoint no tiene id y un termino del
    // glosario tampoco, asi que se construye con lo que SI los distingue; si no, dos rutas
    // distintas compartirian clave `endpoint/` y la segunda expulsaria a la primera.
    var key = item.id || (item.method ? item.method + " " + item.path : "") ||
      item.term || item.code || item.title || "";
    return { key: type + "/" + key, type: type, label: lines[0], lines: lines };
  }

  function refText(r) {
    return ["Contexto — design/design.json:", ""].concat(r.lines).concat(["", "Cambio solicitado: "]).join("\n");
  }
  function trayText() {
    if (!state.tray.length) return "";
    var out = ["Contexto — design/design.json (Design Dashboard):", ""];
    state.tray.forEach(function (r, i) {
      out.push((i + 1) + ". " + r.lines[0]);
      for (var j = 1; j < r.lines.length; j++) out.push("   " + r.lines[j]);
    });
    out.push("", "Cambio solicitado: ");
    return out.join("\n");
  }

  function trayIndex(key) {
    for (var i = 0; i < state.tray.length; i++) if (state.tray[i].key === key) return i;
    return -1;
  }
  function trayHas(key) { return trayIndex(key) !== -1; }
  function trayToggle(ref) {
    var i = trayIndex(ref.key);
    if (i === -1) state.tray.push(ref); else state.tray.splice(i, 1);
    trayChanged();
  }
  function trayClear() { state.tray = []; trayChanged(); }
  function trayChanged() { traySave(); renderTray(); syncPins(); syncDrawerPin(); }

  // sessionStorage keeps the tray across the hard reloads this dashboard needs (cache-busting).
  function traySave() {
    try { sessionStorage.setItem("pubads.tray", JSON.stringify(state.tray)); } catch (e) { /* private mode */ }
  }
  function trayRestore() {
    try {
      var raw = sessionStorage.getItem("pubads.tray");
      if (raw) state.tray = JSON.parse(raw) || [];
    } catch (e) { state.tray = []; }
    renderTray();
  }

  function renderTray() {
    if (!els.tray) return;
    els.tray.hidden = state.tray.length === 0;
    els.trayN.textContent = state.tray.length;
    els.trayChips.innerHTML = "";
    state.tray.forEach(function (r) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "tray-chip";
      b.title = "Quitar de la bandeja";
      b.textContent = r.lines[0].split(" · ")[0] + " ✕";
      b.addEventListener("click", function () { trayToggle(r); });
      els.trayChips.appendChild(b);
    });
  }

  /* Un puntero citado en prosa (§10, UC-21, F09, BR-4, EH-8, WALK-1) se convierte en algo
     PULSABLE que lleva a lo citado. Nacio de WALK-4: el paso W4-3 pedia "seguir el puntero del
     glosario hasta la seccion citada" y en el glosario no habia nada que pulsar — el puntero
     estaba pintado como texto. Un recorrido no puede comprobar una navegacion que no existe. */
  function linkRefs(text) {
    return esc(text || "").replace(
      /(§\d+|UC-\d+|F\d{2}|BR-\d+|EH-\d+|WALK-\d+|CHAT-\d+)/g,
      function (m) { return '<a href="#" class="ref-link" data-ref-jump="' + m + '">' + m + '</a>'; }
    );
  }

  /* Delegado una sola vez: los renderers repintan innerHTML constantemente y un listener por
     enlace se perderia en cada repintado. */
  document.addEventListener("click", function (ev) {
    var a = ev.target.closest ? ev.target.closest("[data-ref-jump]") : null;
    if (!a) return;
    ev.preventDefault();
    jumpToRef(a.getAttribute("data-ref-jump"));
  });

  function jumpToRef(ref) {
    var view = ref.charAt(0) === "§" ? "specs"
      : ref.indexOf("UC-") === 0 ? "stories"
      : ref.indexOf("BR-") === 0 ? "rules"
      : ref.indexOf("EH-") === 0 ? "errors"
      : ref.indexOf("WALK-") === 0 ? "walks"
      : "todos";
    setView(view);
    // El buscador de cada vista ya filtra por id, asi que reutilizarlo es mas honesto que
    // inventar un resaltado paralelo que habria que mantener en las doce vistas.
    setTimeout(function () {
      var box = els.tools.querySelector('input[type="search"]');
      if (!box) return;
      box.value = ref.charAt(0) === "§" ? ref.slice(1) : ref;
      box.dispatchEvent(new Event("input"));
      box.scrollIntoView({ block: "nearest" });
    }, 0);
  }

  /* Escapa un valor para meterlo dentro de un selector de atributo. */
  function cssq(v) { return String(v).replace(/["\\]/g, "\\$&"); }

  /* Cuelga un boton de bandeja de la fila que casa con el selector, si esta ahi. */
  function attachPin(root, selector, type, item) {
    var host = root.querySelector(selector);
    if (host) host.appendChild(pinBtn(type, item));
  }

  function pinBtn(type, item) {
    var ref = refFor(type, item);
    var b = document.createElement("button");
    b.type = "button";
    b.className = "pin-btn" + (trayHas(ref.key) ? " on" : "");
    b.setAttribute("data-ref", ref.key);
    // El glifo (＋ / ✓) no dice nada a un lector de pantalla; el nombre accesible lo pone aqui.
    b.setAttribute("aria-label", "Añadir " + ref.label + " a la bandeja");
    b.title = "Añadir a la bandeja para citarlo en el prompt";
    b.textContent = trayHas(ref.key) ? "✓" : "＋";
    b.addEventListener("click", function (e) { e.stopPropagation(); trayToggle(ref); });
    return b;
  }
  function syncPins() {
    document.querySelectorAll(".pin-btn").forEach(function (b) {
      var on = trayHas(b.getAttribute("data-ref"));
      b.classList.toggle("on", on);
      b.textContent = on ? "✓" : "＋";
    });
  }
  function syncDrawerPin() {
    var b = $("drawer-pin");
    if (!b) return;
    var on = !!state.drawerRef && trayHas(state.drawerRef.key);
    b.classList.toggle("on", on);
    b.textContent = on ? "✓ En la bandeja" : "＋ Bandeja";
  }

  function copyText(text, btn, okLabel) {
    if (!text) return;
    var done = function () { flash(btn, okLabel || "✓ Copiado"); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () { legacyCopy(text, done); });
    } else {
      legacyCopy(text, done);
    }
  }
  // http:// port-forwards and older browsers have no async clipboard — fall back, then to manual.
  function legacyCopy(text, done) {
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.setAttribute("readonly", "");
    ta.style.position = "fixed"; ta.style.top = "-1000px";
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand("copy"); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    if (ok) done(); else window.prompt("Copia manualmente (Ctrl+C):", text);
  }
  function flash(btn, label) {
    if (!btn) return;
    var prev = btn.textContent;
    btn.textContent = label;
    btn.classList.add("ok");
    setTimeout(function () { btn.textContent = prev; btn.classList.remove("ok"); }, 1400);
  }

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
