// Prueba de la impresión directa con puertos simulados: unibag-hardware.js corre tal cual, sin navegador ni impresora.
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const source = fs.readFileSync(path.join(__dirname, "../../../public/assets/js/unibag-hardware.js"), "utf8");
let failures = 0;
function check(name, ok) {
  console.log((ok ? "OK   " : "FAIL ") + name);
  if (!ok) failures++;
}
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const text = (bytes) => Buffer.from(bytes).toString("latin1");

// Puerto serie simulado: un solo dueño a la vez, igual que un COM de Windows.
function fakePort(sig, opts = {}) {
  const port = {
    sig, opens: 0, closes: 0, data: [], busyTimes: opts.busyTimes || 0, failWriteAfter: opts.failWriteAfter, writable: null, readable: null, maxOpen: 0,
    getInfo() { return sig ? { usbVendorId: sig[0], usbProductId: sig[1] } : {}; },
    async open() {
      if (port.busyTimes > 0) { port.busyTimes--; throw new Error("Failed to open serial port."); }
      if (port.writable) throw new Error("The port is already open.");
      port.opens++;
      let n = 0;
      let stream = null;
      stream = {
        getWriter() {
          return {
            async write(b) {
              if (port.failWriteAfter !== undefined && n >= port.failWriteAfter) throw new Error("fallo de escritura");
              n++; port.data.push(text(b));
            },
            async close() { port.writable = null; port.closedByWriter = true; },
            async abort() { port.writable = null; port.aborted = true; },
            releaseLock() {},
          };
        },
      };
      port.writable = stream;
      port.readable = { getReader: () => ({ read: () => new Promise(() => {}), cancel: async () => {}, releaseLock() {} }) };
    },
    async close() { port.writable = null; port.readable = null; port.closes++; },
  };
  return port;
}

function fakeUsb(id, opts = {}) {
  const dev = {
    vendorId: id[0], productId: id[1], serialNumber: id[2], productName: "HT100", opened: false, configuration: null, opens: 0, closes: 0, claims: 0, releases: 0, data: [],
    configurations: [{ interfaces: [{ interfaceNumber: 0, alternates: [{ interfaceClass: 7, alternateSetting: 0, endpoints: [{ direction: "out", type: "bulk", endpointNumber: 2 }] }] }] }],
    async open() {
      if (opts.denied) throw new Error("Access denied.");
      dev.opened = true; dev.opens++;
    },
    async selectConfiguration() { dev.configuration = { interfaces: dev.configurations[0].interfaces }; },
    async claimInterface() { dev.claims++; },
    async releaseInterface() { dev.releases++; },
    async transferOut(ep, slice) { dev.data.push(text(slice)); return { status: "ok" }; },
    async close() { dev.opened = false; dev.closes++; },
  };
  return dev;
}

// Candado compartido entre "pestañas": una sola a la vez, como navigator.locks.
function fakeLocks() {
  let tail = Promise.resolve();
  const locks = { running: 0, maxRunning: 0 };
  locks.request = (name, options, fn) => {
    const run = tail.then(async () => {
      locks.running++;
      locks.maxRunning = Math.max(locks.maxRunning, locks.running);
      try { return await fn(); } finally { locks.running--; }
    });
    tail = run.catch(() => {});
    return run;
  };
  return locks;
}

// Crea una "pestaña": ejecuta el script real con un navegador simulado.
function openTab(env) {
  const listeners = {};
  const stubEl = () => ({
    style: {}, classList: { add() {}, remove() {} }, appendChild() {}, setAttribute() {}, innerHTML: "", textContent: "",
  });
  const elements = {};
  const documentStub = {
    readyState: "complete",
    body: { appendChild() {} },
    head: { appendChild() {} },
    createElement: stubEl,
    getElementById: (id) => elements[id] || null,
    querySelector: () => null,
    querySelectorAll: () => [],
    addEventListener() {},
  };
  const navigatorStub = { serial: env.serial, usb: env.usb, locks: env.locks };
  const windowStub = {
    addEventListener(t, f) { (listeners[t] = listeners[t] || []).push(f); },
    localStorage: env.storage,
    location: { pathname: env.path || "/stock", origin: "https://unibag.test" },
    isSecureContext: true,
    __UNIBAG_HW_CONFIG__: { printLanguage: "epl", printDefaults: { epl: { density: null, speed: null } } },
    __UNIBAG_PRINT_QUEUE__: env.queue || [],
    fetch: env.fetch,
  };
  const ctx = vm.createContext({
    window: windowStub, document: documentStub, navigator: navigatorStub, console, setTimeout, clearTimeout, Promise,
    TextEncoder, AbortController, URL, Response: function () {}, fetch: env.fetch,
  });
  vm.runInContext(source, ctx);
  return { hw: windowStub.UnibagHardware, listeners, window: windowStub, elements };
}

function env(extra = {}) {
  const data = new Map();
  return Object.assign({
    storage: {
      getItem: (k) => (data.has(k) ? data.get(k) : null),
      setItem: (k, v) => data.set(k, String(v)),
      removeItem: (k) => data.delete(k),
    },
    locks: fakeLocks(),
    fetch: async () => { throw new Error("sin servicio local"); },
    serial: undefined,
    usb: undefined,
  }, extra);
}

(async function main() {
  // ------------------------------------------------- Intensidad y velocidad
  const { hw } = openTab(env());
  const t = hw.applyPrintTuning;
  const epl = "N\r\nq800\r\nQ560,24\r\nO\r\nJF\r\nWN\r\nA10,10,0,3,1,1,N,\"D5 S3 texto\"\r\nP1\r\n";
  let out = t(epl, "epl", 7, 2);
  check("EPL sin D/S: se agregan después de N", out.startsWith("N\r\nD7\r\nS2\r\nq800"));
  check("EPL no toca los textos de la etiqueta", out.includes('"D5 S3 texto"'));
  out = t("N\r\nq800\r\nD14\r\nS2\r\nA1\r\nP1\r\n", "epl", 6, null);
  check("EPL reemplaza D y conserva S", out.includes("D6") && !out.includes("D14") && out.includes("S2"));
  check("EPL aplicar dos veces da lo mismo", t(t(epl, "epl", 7, 2), "epl", 7, 2) === t(epl, "epl", 7, 2));
  out = t("N\nA1\nP1\nN\nA2\nP1\n", "epl", 5, null);
  check("EPL lote de varias etiquetas: se aplica a cada una", out.split("D5").length === 3);
  check("D en cero también se aplica", t("N\nP1\n", "epl", 0, null).includes("D0"));
  out = t("SIZE 100 mm,70 mm\r\nGAP 3 mm,0 mm\r\nSPEED 3.0\r\nDENSITY 12\r\nCLS\r\nTEXT 1,1,\"0\",0,1,1,\"DENSITY 9\"\r\nPRINT 1\r\n", "tspl", 4, 2);
  check("TSPL reemplaza DENSITY y SPEED", out.includes("DENSITY 4") && out.includes("SPEED 2") && !out.includes("DENSITY 12") && !out.includes("SPEED 3.0"));
  check("TSPL no toca los textos", out.includes('"DENSITY 9"'));
  out = t("^XA\n^PR3\n^MD20\n^FO1,1^FDx^FS\n^XZ\n^XA\n^PR3\n^MD20\n^XZ\n", "zpl", 8, 2);
  check("ZPL: intensidad absoluta una vez y sin ^MD, que se le sumaría", out.startsWith("~SD08^XA") && out.split("~SD").length === 2 && !out.includes("^MD"));
  check("ZPL: velocidad en cada etiqueta", out.split("^XA^PR2").length === 3 && !out.includes("^PR3"));
  check("ZPL: aplicar dos veces da lo mismo", t(out, "zpl", 8, 2) === out);
  check("ZPL: solo velocidad conserva ^MD", t("^XA^PR3^MD5^XZ", "zpl", null, 4) === "^XA^PR4^MD5^XZ");
  check("ZPL: intensidad 0 también se aplica", t("^XA^XZ", "zpl", 0, null) === "~SD00^XA^XZ");
  check("sin ajuste el comando queda igual", t(epl, "epl", null, null) === epl);
  check("lenguaje desconocido queda igual", t(epl, "pdf", 5, 5) === epl);

  // ------------------------------------------------- Serial: vinculada sin puerto abierto
  let port = fakePort([1, 2]);
  let e = env({ serial: { getPorts: async () => [port], addEventListener() {} } });
  e.storage.setItem("unibag_hw_serial_printer_vidpid", "1:2");
  e.storage.setItem("unibag_hw_print_transport", "webserial");
  const tabA = openTab(e);
  await wait(30);
  check("al cargar la página NO se abre el puerto de la impresora", port.opens === 0);
  let r = await tabA.hw.printJob({ content: "N\r\nP1\r\n", language: "epl", copies: 1 }, { silentFallback: true, silentSuccess: true });
  check("imprime por Serial", r.ok && r.transport === "webserial" && port.data.length === 1);
  check("al terminar el puerto queda libre", port.writable === null && port.closes >= 1 && port.closedByWriter === true);

  // Cambio de página: otra instancia del script, sin volver a vincular
  const tabB = openTab(e);
  await wait(10);
  r = await tabB.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("tras cambiar de página imprime sin volver a conectar", r.ok && port.data.length === 2);

  // Dos pestañas a la vez
  const results = await Promise.all([
    tabA.hw.printJob({ content: "N\r\nP1\r\nA", language: "epl" }, { silentFallback: true, silentSuccess: true }),
    tabB.hw.printJob({ content: "N\r\nP1\r\nB", language: "epl" }, { silentFallback: true, silentSuccess: true }),
  ]);
  check("dos pestañas imprimen a la vez sin chocar", results.every((x) => x.ok) && port.data.length === 4 && e.locks.maxRunning === 1);

  // Puerto todavía liberándose
  port.busyTimes = 3;
  r = await tabA.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("si el puerto sigue ocupado un momento, reintenta", r.ok && port.busyTimes === 0);

  // Ajuste guardado aplicado al imprimir
  e.storage.setItem("unibag_hw_print_tuning", JSON.stringify({ epl: { density: 5, speed: 2 } }));
  port.data.length = 0;
  r = await tabA.hw.printJob({ content: "N\r\nO\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("el ajuste guardado llega a la impresora", r.ok && port.data[0].includes("D5") && port.data[0].includes("S2"));
  e.storage.setItem("unibag_hw_print_tuning", JSON.stringify({ epl: { density: 99 } }));
  port.data.length = 0;
  await tabA.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("un valor fuera de rango se ignora", !port.data[0].includes("D99"));

  // Fallo a mitad: no se reenvía por otra vía
  let bridgeCalls = 0;
  let badPort = fakePort([1, 2], { failWriteAfter: 1 });
  let e2 = env({ serial: { getPorts: async () => [badPort], addEventListener() {} }, fetch: async () => { bridgeCalls++; throw new Error("x"); } });
  e2.storage.setItem("unibag_hw_serial_printer_vidpid", "1:2");
  e2.storage.setItem("unibag_hw_print_transport", "webserial");
  const tabC = openTab(e2);
  await wait(10);
  bridgeCalls = 0;
  r = await tabC.hw.printJob({ content: "N\r\nP1\r\n", language: "epl", copies: 2 }, { silentFallback: true, silentSuccess: true });
  check("fallo a mitad de copias: estado incierto y sin duplicar por otra vía", !r.ok && r.ambiguous === true && bridgeCalls === 0);
  check("tras el fallo el puerto queda libre", badPort.writable === null);

  // Nunca sobre la balanza
  let scale = fakePort([9, 9]);
  let e3 = env({ serial: { getPorts: async () => [scale], addEventListener() {} } });
  e3.storage.setItem("unibag_hw_print_transport", "webserial");
  e3.storage.setItem("unibag_hw_scale_vidpid", "9:9");
  const tabD = openTab(e3);
  await wait(10);
  r = await tabD.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("nunca escribe la etiqueta en el puerto de la balanza", !r.ok && scale.data.length === 0);

  // La balanza no toma el puerto de la impresora
  let onlyPrinter = fakePort([1, 2]);
  let e4 = env({ serial: { getPorts: async () => [onlyPrinter], addEventListener() {} } });
  e4.storage.setItem("unibag_hw_serial_printer_vidpid", "1:2");
  openTab(e4);
  await wait(30);
  check("la balanza no abre el puerto de la impresora", onlyPrinter.opens === 0);

  // ------------------------------------------------- USB
  let dev = fakeUsb([5, 6, "ABC"]);
  let e5 = env({ usb: { getDevices: async () => [dev], addEventListener() {} } });
  e5.storage.setItem("unibag_hw_print_transport", "webusb");
  const tabE = openTab(e5);
  await wait(30);
  check("USB: al cargar la página no se abre", dev.opens === 0);
  r = await tabE.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("USB: imprime", r.ok && r.transport === "webusb" && dev.data.length === 1);
  check("USB: al terminar libera y cierra", dev.opened === false && dev.releases === 1 && dev.closes === 1);
  const tabF = openTab(e5);
  await wait(10);
  r = await tabF.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("USB: tras cambiar de página imprime sin volver a conectar", r.ok && dev.data.length === 2);

  // ------------------------------------------------- Servicio local
  let online = true;
  let e6 = env({
    fetch: async (url, init) => {
      if (String(url).endsWith("/printers")) {
        if (!online) throw new Error("x");
        return { ok: true, json: async () => ({ printers: ["HPRT"], default_printer: "HPRT" }) };
      }
      if (String(url).endsWith("/print")) {
        e6.lastBody = JSON.parse(init.body);
        return { ok: true, json: async () => ({ ok: true, printer: "HPRT" }) };
      }
      throw new Error("x");
    },
  });
  const tabG = openTab(e6);
  const seen = await tabG.hw.probePrintBridge(true);
  check("servicio local detectado queda recordado", seen === true && e6.storage.getItem("unibag_hw_bridge_last_online") === "1");
  e6.storage.setItem("unibag_hw_print_tuning", JSON.stringify({ epl: { density: 4 } }));
  r = await tabG.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, { silentFallback: true, silentSuccess: true });
  check("el ajuste también aplica por el servicio local", r.ok && r.transport === "bridge" && e6.lastBody.content.includes("D4"));
  online = false;
  check("si el servicio se apaga se corrige", (await tabG.hw.probePrintBridge(true)) === false && e6.storage.getItem("unibag_hw_bridge_last_online") === null);

  // ------------------------------------------------- Vincular por COM: no debe soltarse enseguida
  const fire = async (tab, type) => {
    (tab.listeners[type] || []).forEach((f) => f({}));
    await wait(60);
  };
  const quiet = { silentFallback: true, silentSuccess: true };
  const label = { content: "^XA^XZ", language: "zpl" };
  function comEnv(ports, chosen) {
    return env({ serial: { getPorts: async () => ports, requestPort: async () => chosen, addEventListener() {} } });
  }

  // COM sin VID/PID y una balanza recordada que no está conectada
  let zebra = fakePort(null);
  let eZ = comEnv([zebra], zebra);
  eZ.storage.setItem("unibag_hw_scale_vidpid", "9:9");
  let tabZ = openTab(eZ);
  await wait(20);
  check("COM sin VID/PID: se vincula", (await tabZ.hw.connectSerialPrinter()) === true);
  await fire(tabZ, "focus");
  check("COM sin VID/PID: al volver al navegador la balanza no toma el puerto", zebra.writable === null);
  r = await tabZ.hw.printJob({ ...label }, quiet);
  check("COM sin VID/PID: sigue vinculada e imprime", r.ok && r.transport === "webserial" && zebra.data.length === 1);
  tabZ = openTab(eZ);
  await fire(tabZ, "focus");
  r = await tabZ.hw.printJob({ ...label }, quiet);
  check("COM sin VID/PID: sigue vinculada al cambiar de página", r.ok && zebra.data.length === 2 && zebra.writable === null);

  // El foco vuelve al navegador justo cuando se elige el puerto, en un PC sin balanza recordada
  let fresh = fakePort(null);
  let eF = comEnv([fresh], fresh);
  let tabFresh = openTab(eF);
  await wait(20);
  const linking = tabFresh.hw.connectSerialPrinter();
  (tabFresh.listeners.focus || []).forEach((f) => f({}));
  await linking;
  await wait(60);
  r = await tabFresh.hw.printJob({ ...label }, quiet);
  check("el foco al elegir el puerto no se lo entrega a la balanza", r.ok && fresh.data.length === 1 && fresh.writable === null);

  // Varios COM autorizados sin VID/PID
  let com1 = fakePort(null), com3 = fakePort(null);
  let eM = comEnv([com1, com3], com3);
  let tabM = openTab(eM);
  await wait(20);
  await tabM.hw.connectSerialPrinter();
  await fire(tabM, "focus");
  r = await tabM.hw.printJob({ ...label }, quiet);
  check("varios COM autorizados: imprime en el elegido", r.ok && com3.data.length === 1 && com1.data.length === 0);
  tabM = openTab(eM);
  await fire(tabM, "focus");
  r = await tabM.hw.printJob({ ...label }, quiet);
  check("varios COM autorizados: recuerda el elegido al cambiar de página", r.ok && com3.data.length === 2 && com1.data.length === 0);

  // La balanza quedó recordada con la firma de este puerto (antes se abrió como balanza)
  let same = fakePort([7, 7]);
  let eS = comEnv([same], same);
  eS.storage.setItem("unibag_hw_scale_vidpid", "7:7");
  let tabS = openTab(eS);
  await wait(60);
  check("caso de prueba: la balanza tenía tomado el puerto", same.writable !== null);
  check("el puerto tomado por la balanza se libera al vincular la impresora", (await tabS.hw.connectSerialPrinter()) === true && same.writable === null);
  await fire(tabS, "focus");
  r = await tabS.hw.printJob({ ...label }, quiet);
  check("misma firma: queda como impresora", r.ok && same.data.length === 1 && same.writable === null);
  check("misma firma: se olvida la balanza equivocada", !eS.storage.getItem("unibag_hw_scale_vidpid"));
  tabS = openTab(eS);
  await fire(tabS, "focus");
  check("misma firma: al cambiar de página tampoco la toma la balanza", same.writable === null);

  // Antes había otra impresora vinculada
  let hprt = fakePort([3, 3]), newZebra = fakePort(null);
  let eO = comEnv([hprt, newZebra], newZebra);
  eO.storage.setItem("unibag_hw_serial_printer_vidpid", "3:3");
  eO.storage.setItem("unibag_hw_print_transport", "webserial");
  let tabO = openTab(eO);
  await wait(20);
  await tabO.hw.connectSerialPrinter();
  tabO = openTab(eO);
  await fire(tabO, "focus");
  r = await tabO.hw.printJob({ ...label }, quiet);
  check("cambiar de impresora: imprime en la nueva, no en la anterior", r.ok && newZebra.data.length === 1 && hprt.data.length === 0);

  // La balanza verdadera sigue conectándose
  let realScale = fakePort([9, 9]), realPrinter = fakePort(null);
  let eB = comEnv([realPrinter, realScale], realPrinter);
  eB.storage.setItem("unibag_hw_scale_vidpid", "9:9");
  let tabB2 = openTab(eB);
  await wait(60);
  await tabB2.hw.connectSerialPrinter();
  await fire(tabB2, "focus");
  r = await tabB2.hw.printJob({ ...label }, quiet);
  check("con balanza real: cada una en su puerto", r.ok && realScale.writable !== null && realPrinter.data.length === 1 && realPrinter.writable === null);

  // ------------------------------------------------- Zebra: tipo de impresora de la PC
  let zp = fakePort([1, 2]);
  let eT = env({ serial: { getPorts: async () => [zp], addEventListener() {} } });
  eT.storage.setItem("unibag_hw_serial_printer_vidpid", "1:2");
  eT.storage.setItem("unibag_hw_print_transport", "webserial");
  eT.storage.setItem("unibag_hw_print_tuning", JSON.stringify({ zpl: { density: 10, speed: 3 } }));
  let tabT = openTab(eT);
  await wait(20);
  zp.data.length = 0;
  await tabT.hw.printJob({ content: "^XA^PR3^MD20^XZ", language: "zpl" }, quiet);
  check("Zebra ZPL: llegan la intensidad y la velocidad elegidas", zp.data[0].startsWith("~SD10^XA^PR3") && !zp.data[0].includes("^MD"));
  eT.storage.setItem("unibag_hw_printer_type", "zpl");
  zp.data.length = 0;
  await tabT.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, quiet);
  check("Zebra que recibe EPL del servidor: el ajuste se traduce", zp.data[0].includes("D5") && zp.data[0].includes("S2"));
  eT.storage.setItem("unibag_hw_print_tuning", JSON.stringify({ zpl: { density: 30, speed: 8 }, epl: { density: 1, speed: 1 } }));
  zp.data.length = 0;
  await tabT.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, quiet);
  check("el tipo elegido manda sobre un ajuste viejo de otra impresora", zp.data[0].includes("D15") && zp.data[0].includes("S6"));
  eT.storage.setItem("unibag_hw_print_tuning", JSON.stringify({ zpl: {}, epl: { density: 4 } }));
  zp.data.length = 0;
  await tabT.hw.printJob({ content: "N\r\nP1\r\n", language: "epl" }, quiet);
  check("sin ajuste en el tipo elegido usa el del lenguaje", zp.data[0].includes("D4"));

  console.log(failures === 0 ? "\nIMPRESION DIRECTA OK" : "\nIMPRESION DIRECTA: " + failures + " fallas");
  process.exit(failures === 0 ? 0 : 1);
})().catch((err) => {
  console.error("FAIL error inesperado:", err);
  process.exit(1);
});
