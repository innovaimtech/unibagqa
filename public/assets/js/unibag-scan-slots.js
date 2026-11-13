/*
 * Casillas de escaneo (igual que Solicitudes MP): cada lectura queda en su casilla, sin consultar al servidor, así no se
 * pierden códigos al escanear rápido. Se parte con una casilla y cada código agrega la siguiente. Si la pistola junta dos
 * códigos se separan solos; los repetidos se marcan. Uso: <form class="ca-slots" data-existing='["COD1",...]'>
 * con .ca-slot-list, .ca-slot-summary y el botón .ca-load-btn.
 */
(function () {
  function norm(v) { return String(v || "").replace(/['`´’‘‛]/g, "-").replace(/\s+/g, "").toUpperCase(); }
  function split(raw) { return String(raw || "").replace(/['`´’‘‛]/g, "-").split(/[\s,;]+/).map(norm).filter(Boolean); }

  function setup(form) {
    var list = form.querySelector(".ca-slot-list"), summary = form.querySelector(".ca-slot-summary"), loadBtn = form.querySelector(".ca-load-btn");
    if (!list || !loadBtn) return;
    var label = loadBtn.getAttribute("data-label") || "Cargar";
    var existing = {};
    try { JSON.parse(form.getAttribute("data-existing") || "[]").forEach(function (c) { existing[String(c).toUpperCase()] = true; }); } catch (e) {}
    function inputs() { return [].slice.call(list.querySelectorAll(".ca-slot-input")); }
    function row(n, val) {
      var r = document.createElement("div"); r.className = "ca-slot-row";
      r.innerHTML = '<span class="ca-slot-idx">#' + n + '</span><input type="text" name="codes[]" class="ca-slot-input" maxlength="80" autocomplete="off">'
        + '<span class="ca-slot-tag"></span><button type="button" class="ca-slot-rm">✕</button>';
      if (val) r.querySelector("input").value = val;
      return r;
    }
    function refresh() {
      var seen = {}, ok = 0, dup = 0;
      Object.keys(existing).forEach(function (k) { seen[k] = true; });
      inputs().forEach(function (inp, i) {
        var r = inp.closest(".ca-slot-row"), tag = r.querySelector(".ca-slot-tag"), v = norm(inp.value);
        r.querySelector(".ca-slot-idx").textContent = "#" + (i + 1);
        r.classList.remove("filled", "dup"); tag.textContent = "";
        if (!v) return;
        if (seen[v]) { r.classList.add("dup"); tag.textContent = "Repetido"; dup++; }
        else { seen[v] = true; r.classList.add("filled"); tag.textContent = "✓"; ok++; }
      });
      if (summary) summary.textContent = ok + " código(s)" + (dup ? " · " + dup + " repetido(s)" : "");
      loadBtn.disabled = ok === 0 || dup > 0;
      loadBtn.textContent = label + " (" + ok + ")";
    }
    // Siempre hay una casilla vacía al final: se parte con 1 y cada código leído agrega la siguiente.
    function ensureEmpty() {
      var all = inputs();
      if (!all.length || norm(all[all.length - 1].value)) list.appendChild(row(all.length + 1, ""));
      refresh();
    }
    function nextEmpty(from) {
      var all = inputs();
      for (var i = from + 1; i < all.length; i++) if (!norm(all[i].value)) return all[i];
      for (var k = 0; k < all.length; k++) if (!norm(all[k].value)) return all[k];
      var r = row(all.length + 1, ""); list.appendChild(r); return r.querySelector("input");
    }
    function distribute(inp) {
      var parts = split(inp.value), all = inputs(), idx = all.indexOf(inp);
      var taken = {};
      Object.keys(existing).forEach(function (k) { taken[k] = true; });
      all.forEach(function (o, j) { if (j !== idx && norm(o.value)) taken[norm(o.value)] = true; });
      var unique = parts.filter(function (p) { if (taken[p]) return false; taken[p] = true; return true; });
      if (parts.length && !unique.length) { inp.value = ""; refresh(); inp.focus(); return; }
      inp.value = unique.length ? unique[0] : "";
      var cur = idx;
      for (var k = 1; k < unique.length; k++) { var t = nextEmpty(cur); t.value = unique[k]; cur = inputs().indexOf(t); }
      if (unique.length) nextEmpty(cur).focus(); else inp.focus();
      refresh();
    }
    form.addEventListener("keydown", function (e) {
      var t = e.target;
      if (t && t.classList && t.classList.contains("ca-slot-input") && (e.key === "Enter" || e.keyCode === 13)) { e.preventDefault(); e.stopPropagation(); distribute(t); }
    }, true);
    form.addEventListener("input", function (e) {
      var t = e.target; if (!t || !t.classList || !t.classList.contains("ca-slot-input")) return;
      if (/[\s,;]/.test(t.value)) distribute(t); else ensureEmpty();
    });
    list.addEventListener("click", function (e) {
      var b = e.target.closest(".ca-slot-rm"); if (!b) return;
      var r = b.closest(".ca-slot-row"), inp = r.querySelector("input");
      if (inputs().length > 1) r.remove(); else inp.value = "";
      ensureEmpty();
      var all = inputs(); all[all.length - 1].focus();
    });
    form.addEventListener("submit", function () {
      inputs().forEach(function (i) { i.value = norm(i.value); if (!i.value) i.disabled = true; });
      loadBtn.disabled = true; loadBtn.textContent = "Cargando…";
    });
    ensureEmpty();
    inputs()[0].focus();
  }

  function init() { document.querySelectorAll("form.ca-slots").forEach(setup); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
