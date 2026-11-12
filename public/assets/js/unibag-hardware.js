/**
 * =============================================================================
 * Unibag Hardware Engine · Balanzas Digitales USB & Impresoras Térmicas
 * (public/assets/js/unibag-hardware.js)
 * =============================================================================
 *
 * Responsabilidad:
 * 1. Balanzas Digitales (LP-7516 / Toledo / CAS / Ohaus / RS-232 a USB):
 *    - Captura 100% nativa en el navegador vía Web Serial API (navigator.serial)
 *      SIN requerir scripts, servicios ni software externo instalado en la PC.
 *    - Balanza: se reabre sola al cambiar de página (getPorts()) y se libera en 'pagehide'.
 *    - Impresora: queda vinculada al navegador y su puerto se abre solo mientras se imprime,
 *      así no depende de la página en que se esté ni compite entre pestañas.
 *    - Desactivación explícita de DTR/RTS para compatibilidad con chips CH340,
 *      FTDI y Prolific.
 *    - Soporte simultáneo de transmisión continua y consulta bajo demanda ("R\r\n").
 *    - Interceptor transparente de fetch("/api/scale/weight") compatible con las
 *      8 pantallas de pesaje del ERP (Recepción OC, Contenedores, Bobinas, Tintas,
 *      Merma Selladora, Inventario, OT Flexo/Rebobinado/Corte y Semielaborado).
 *
 * 2. Impresoras Térmicas Industriales (HPRT EPL, Barpos TSPL, Zebra ZPL):
 *    - Arquitectura universal Local / Red LAN / VPS Remoto sin depender de
 *      window.print() ni iframes ocultos bloqueados por el navegador.
 *    - Cascada multi-transporte desde el navegador del operador:
 *      a) Servicio Spooler Local Windows (http://127.0.0.1:8767/print con PNA)
 *      b) Conexión USB Directa en Navegador (WebUSB API - navigator.usb)
 *      c) Impresora Serial Directa en Navegador (Web Serial API)
 *      d) Respaldo visual limpio mediante notificación interactiva (sin popups bloqueados).
 */
(function () {
  "use strict";

  if (window.UnibagHardware) {
    return;
  }

  var CFG = window.__UNIBAG_HW_CONFIG__ || {};
  var INITIAL_QUEUE = Array.isArray(window.__UNIBAG_PRINT_QUEUE__) ? window.__UNIBAG_PRINT_QUEUE__.slice() : [];
  window.__UNIBAG_PRINT_QUEUE__ = [];

  var LS_KEYS = {
    scaleBaud: "unibag_hw_scale_baud",
    scaleVidPid: "unibag_hw_scale_vidpid",
    printTransport: "unibag_hw_print_transport",
    printerDefault: "unibag_hw_printer_default",
    printerZebra: "unibag_hw_printer_zebra",
    printerBarpos: "unibag_hw_printer_barpos",
    serialPrinterVidPid: "unibag_hw_serial_printer_vidpid",
    serialPrinterBaud: "unibag_hw_serial_printer_baud",
    // Posición del puerto entre los que comparten el mismo VID/PID (dos adaptadores CH340 iguales)
    scaleIdx: "unibag_hw_scale_idx",
    serialPrinterIdx: "unibag_hw_serial_printer_idx",
    usbPrinterId: "unibag_hw_usb_printer_id",
    printTuning: "unibag_hw_print_tuning",
    lastPrintLanguage: "unibag_hw_last_print_language",
    printerType: "unibag_hw_printer_type",
    printerIp: "unibag_hw_printer_ip",
    bridgeLastOnline: "unibag_hw_bridge_last_online"
  };

  function lsGet(key, fallback) {
    try {
      var val = window.localStorage.getItem(key);
      return val !== null && val !== "" ? val : fallback;
    } catch (_) {
      return fallback;
    }
  }

  function lsSet(key, val) {
    try {
      if (val === null || val === undefined || val === "") {
        window.localStorage.removeItem(key);
      } else {
        window.localStorage.setItem(key, String(val));
      }
    } catch (_) {}
  }

  // El servidor arma las etiquetas en el lenguaje de la impresora de ESTE computador (cookie por navegador/PC).
  function syncClientPrinterLanguageCookie() {
    try {
      var lang = String(lsGet(LS_KEYS.printerType, "") || "").toLowerCase();
      if (lang === "epl" || lang === "tspl" || lang === "zpl") {
        document.cookie = "unibag_print_lang=" + lang + "; path=/; max-age=31536000; SameSite=Lax";
      } else {
        document.cookie = "unibag_print_lang=; path=/; max-age=0; SameSite=Lax";
      }
    } catch (_) {}
  }
  syncClientPrinterLanguageCookie();

  // Impresora de red de ESTE computador (IP:9100): el servidor web le envía las etiquetas directo.
  function syncClientPrinterIpCookie() {
    try {
      var ip = String(lsGet(LS_KEYS.printerIp, "") || "").trim();
      document.cookie = ip
        ? "unibag_print_ip=" + encodeURIComponent(ip) + "; path=/; max-age=31536000; SameSite=Lax"
        : "unibag_print_ip=; path=/; max-age=0; SameSite=Lax";
    } catch (_) {}
  }
  syncClientPrinterIpCookie();

  async function postNetworkPrint(fields) {
    var fd = new FormData();
    fd.append("_csrf", CFG.csrf || "");
    Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
    var res = await ORIGINAL_FETCH("/api/print/network", { method: "POST", body: fd, credentials: "same-origin" });
    var data = await res.json().catch(function () { return null; });
    return data || { ok: false, error: "Respuesta inválida del servidor (HTTP " + res.status + ")." };
  }

  async function printViaNetwork(job) {
    var ip = String(lsGet(LS_KEYS.printerIp, "") || "").trim();
    try {
      var data = await postNetworkPrint({ printer_ip: ip, content: job.content, language: job.language || CFG.printLanguage || "epl", copies: Math.max(1, parseInt(job.copies || 1, 10) || 1), job_id: job.job_id || "" });
      if (data.ok) return { ok: true, transport: "network", printer: "Impresora en red " + (data.printer || ip) };
      return { ok: false, ambiguous: !!data.ambiguous, error: data.error || "No se pudo imprimir en la impresora de red." };
    } catch (err) {
      return { ok: false, ambiguous: false, error: "No se pudo contactar al servidor para imprimir en red: " + (err && err.message ? err.message : err) };
    }
  }

  function sleep(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  function encodeRawPrinterBytes(content, language) {
    var lang = String(language || "epl").toLowerCase();
    var str = String(content || "");
    if (lang === "zpl" && typeof TextEncoder !== "undefined") {
      return new TextEncoder().encode(str);
    }
    // EPL y TSPL requieren codificación de 1 byte (ASCII / Latin-1 / CP1252)
    var bytes = new Uint8Array(str.length);
    for (var i = 0; i < str.length; i++) {
      var code = str.charCodeAt(i);
      bytes[i] = code <= 0xff ? code : 0x3f;
    }
    return bytes;
  }

  // --- Intensidad y velocidad elegidas por el usuario en esta PC (se aplican al comando de la etiqueta) ---
  var TUNING_RANGES = {
    epl: { density: [0, 15], speed: [1, 6] },
    tspl: { density: [0, 15], speed: [1, 6] },
    zpl: { density: [0, 30], speed: [2, 8] }
  };

  function normalizeLanguage(language) {
    var lang = String(language || "").toLowerCase();
    return TUNING_RANGES[lang] ? lang : "";
  }

  function readPrintTuning() {
    try {
      var data = JSON.parse(lsGet(LS_KEYS.printTuning, "{}"));
      return data && typeof data === "object" ? data : {};
    } catch (_) {
      return {};
    }
  }

  function tuningValueFor(language, field) {
    var lang = normalizeLanguage(language);
    if (!lang) return null;
    var saved = readPrintTuning()[lang];
    var raw = saved ? saved[field] : null;
    if (raw === null || raw === undefined || raw === "") return null;
    var num = Number(raw);
    var range = TUNING_RANGES[lang][field];
    return isFinite(num) && num >= range[0] && num <= range[1] ? num : null;
  }

  function savePrintTuning(language, field, value) {
    var lang = normalizeLanguage(language);
    if (!lang) return;
    var data = readPrintTuning();
    data[lang] = data[lang] || {};
    if (value === null || value === undefined || value === "") {
      delete data[lang][field];
    } else {
      data[lang][field] = Number(value);
    }
    lsSet(LS_KEYS.printTuning, JSON.stringify(data));
  }

  // Quita las líneas indicadas y agrega las nuevas después de cada línea que inicia una etiqueta.
  function rewriteCommandLines(content, isOldLine, isStartLine, newLines) {
    var text = String(content);
    var eol = text.indexOf("\r\n") >= 0 ? "\r\n" : "\n";
    var out = [];
    var inserted = false;
    text.split(/\r?\n/).forEach(function (line) {
      if (isOldLine(line)) return;
      out.push(line);
      if (isStartLine(line)) {
        Array.prototype.push.apply(out, newLines);
        inserted = true;
      }
    });
    if (!inserted) out = newLines.concat(out);
    return out.join(eol);
  }

  function applyPrintTuning(content, language, density, speed) {
    var lang = normalizeLanguage(language);
    var text = String(content === null || content === undefined ? "" : content);
    var hasDensity = density !== null && density !== undefined;
    var hasSpeed = speed !== null && speed !== undefined;
    if (!lang || (!hasDensity && !hasSpeed)) return text;

    if (lang === "zpl") {
      if (hasSpeed) {
        text = text.replace(/\^PR\d+(?:,\d+)*/g, "");
        text = /\^XA/.test(text) ? text.replace(/\^XA/g, "^XA^PR" + speed) : "^PR" + speed + text;
      }
      // ~SD fija la intensidad exacta; ^MD se le sumaría, por eso se quita.
      if (hasDensity) {
        text = text.replace(/\^MD-?\d+/g, "").replace(/~SD\d+/g, "");
        text = "~SD" + (density < 10 ? "0" : "") + density + text;
      }
      return text;
    }
    if (lang === "tspl") {
      var tsplLines = [];
      if (hasSpeed) tsplLines.push("SPEED " + speed);
      if (hasDensity) tsplLines.push("DENSITY " + density);
      return rewriteCommandLines(text, function (line) {
        return (hasDensity && /^DENSITY\s+\d+\s*$/i.test(line)) || (hasSpeed && /^SPEED\s+[\d.]+\s*$/i.test(line));
      }, function (line) {
        return /^SIZE\s/i.test(line);
      }, tsplLines);
    }
    var eplLines = [];
    if (hasDensity) eplLines.push("D" + density);
    if (hasSpeed) eplLines.push("S" + speed);
    return rewriteCommandLines(text, function (line) {
      return (hasDensity && /^D\d{1,2}\s*$/.test(line)) || (hasSpeed && /^S\d{1,2}\s*$/.test(line));
    }, function (line) {
      return /^N\s*$/.test(line);
    }, eplLines);
  }

  // Si la PC tiene elegido otro tipo de impresora que el lenguaje de la etiqueta, su ajuste se lleva a la escala de ese lenguaje.
  function tuningForJob(lang, field) {
    var type = normalizeLanguage(lsGet(LS_KEYS.printerType, ""));
    if (type && type !== lang) {
      var value = tuningValueFor(type, field);
      if (value !== null) {
        var from = TUNING_RANGES[type][field];
        var to = TUNING_RANGES[lang][field];
        return Math.round(to[0] + ((value - from[0]) / (from[1] - from[0])) * (to[1] - to[0]));
      }
    }
    return tuningValueFor(lang, field);
  }

  function tuneJob(job) {
    var lang = normalizeLanguage(job.language || CFG.printLanguage);
    if (!lang) return;
    lsSet(LS_KEYS.lastPrintLanguage, lang);
    job.content = applyPrintTuning(job.content, lang, tuningForJob(lang, "density"), tuningForJob(lang, "speed"));
  }

  function portSignature(port) {
    if (!port || typeof port.getInfo !== "function") return "";
    try {
      var info = port.getInfo() || {};
      if (info.usbVendorId || info.usbProductId) {
        return String(info.usbVendorId || 0) + ":" + String(info.usbProductId || 0);
      }
    } catch (_) {}
    return "";
  }

  // Recuerda la posición del puerto entre los autorizados con el mismo VID/PID (o entre los que no lo tienen).
  async function rememberPortIndex(port, key) {
    try {
      var sig = portSignature(port);
      if (!navigator.serial) return;
      var ports = await navigator.serial.getPorts();
      var same = [];
      for (var i = 0; i < ports.length; i++) {
        if (portSignature(ports[i]) === sig) same.push(ports[i]);
      }
      var pos = same.indexOf(port);
      if (pos >= 0) lsSet(key, String(pos));
    } catch (_) {}
  }

  // Elige el puerto con la firma dada; si hay varios iguales usa la posición recordada y evita los excluidos.
  function pickPortBySig(ports, sig, idxKey, exclude) {
    var same = [];
    for (var i = 0; i < ports.length; i++) {
      if (portSignature(ports[i]) === sig) same.push(ports[i]);
    }
    var skip = exclude || [];
    var idx = parseInt(lsGet(idxKey, "-1"), 10);
    if (same.length > 1 && idx >= 0 && same[idx] && skip.indexOf(same[idx]) < 0) {
      return same[idx];
    }
    for (var j = 0; j < same.length; j++) {
      if (skip.indexOf(same[j]) < 0) return same[j];
    }
    return null;
  }

  // ===========================================================================
  // 1. MOTOR DE BALANZA DIGITAL (WEB SERIAL NATIVO + FALLBACK PUENTE LOCAL)
  // ===========================================================================
  var scaleState = {
    supported: typeof navigator !== "undefined" && !!navigator.serial,
    connected: false,
    connecting: false,
    port: null,
    reader: null,
    closing: false,
    baudRate: parseInt(lsGet(LS_KEYS.scaleBaud, "9600"), 10) || 9600,
    weightKg: null,
    stable: true,
    rawFrame: "",
    lastUpdatedAt: 0,
    lastPickerDismissedAt: 0,
    bridgeReachable: null,
    bridgeCheckedAt: 0,
    error: ""
  };

  // Mientras se vincula la impresora nadie más toca los puertos: al cerrar el selector la ventana recupera el foco.
  var printerLinking = false;

  // Antigüedad máxima (ms) aceptada para un peso leído desde la balanza.
  var MAX_WEIGHT_AGE_MS = 1500;

  // Escapa texto antes de insertarlo con innerHTML (tramas, nombres de impresora, errores).
  function escapeHtml(value) {
    return String(value === null || value === undefined ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function parseIndustrialWeightLine(line) {
    if (!line) return null;
    var clean = String(line).replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, " ").trim();
    if (!clean) return null;

    // Ignorar ecos de comandos vacíos y mensajes de error o sobrecarga (OL = overload).
    // Se busca como palabra completa para cubrir tramas del tipo "ST,GS,OL".
    if (/^(ERR|ERROR|---+|R)$/i.test(clean) || /\b(OL|OVERLOAD|ERR(?:OR)?)\b/i.test(clean)) {
      return null;
    }

    // Buscar el patrón numérico con signo opcional y unidad opcional (ej: "ST,GS,+  18.450kg", "12,340 KG")
    var unitMatch = clean.match(/([-+])?\s*(\d+(?:[.,]\d+)?)\s*(kg|g|lb)?\b/i);
    if (!unitMatch) return null;

    var sign = unitMatch[1] === "-" ? -1 : 1;
    var numStr = unitMatch[2].replace(",", ".");
    var val = parseFloat(numStr);
    if (!isFinite(val)) return null;

    var unit = (unitMatch[3] || "kg").toLowerCase();
    if (unit === "g") {
      val = val / 1000;
    } else if (unit === "lb") {
      val = val * 0.45359237;
    }

    val = sign * val;
    if (val < 0 && Math.abs(val) < 0.05) {
      val = 0;
    }
    if (val < 0) {
      return null;
    }

    var isUnstable = /\b(US|UNSTABLE|MOTION|MOT)\b/i.test(clean);
    return {
      weightKg: Math.round(val * 1000) / 1000,
      stable: !isUnstable,
      raw: clean
    };
  }

  function processScaleBufferChunk(text, bufferRef) {
    bufferRef.text += text;
    if (bufferRef.text.length > 4096) {
      bufferRef.text = bufferRef.text.slice(-2048);
    }
    // Soportar delimitadores CRLF, LF, CR y STX/ETX (\x02...\x03)
    var parts = bufferRef.text.split(/[\r\n\x02\x03]+/);
    bufferRef.text = parts.pop() || "";

    // Solo cuenta la ÚLTIMA trama completa. Si no se puede interpretar (sobrecarga,
    // negativo, basura) se INVALIDA el peso: nunca se reutiliza un peso anterior.
    for (var i = parts.length - 1; i >= 0; i--) {
      if (!String(parts[i]).trim()) {
        continue;
      }
      var parsed = parseIndustrialWeightLine(parts[i]);
      if (parsed !== null) {
        scaleState.weightKg = parsed.weightKg;
        scaleState.stable = parsed.stable;
        scaleState.rawFrame = parsed.raw;
        scaleState.error = "";
      } else {
        scaleState.weightKg = null;
        scaleState.stable = false;
        scaleState.rawFrame = String(parts[i]).trim().slice(0, 40);
        scaleState.error = "Lectura no válida de la balanza (sobrecarga, peso negativo o trama dañada).";
      }
      scaleState.lastUpdatedAt = Date.now();
      updateHardwareUi();
      break;
    }
  }

  async function startScaleReadLoop(port) {
    var decoder = typeof TextDecoder !== "undefined" ? new TextDecoder("ascii", { fatal: false }) : null;
    var bufferRef = { text: "" };

    while (port && port.readable && !scaleState.closing && scaleState.port === port) {
      var reader = null;
      try {
        reader = port.readable.getReader();
        scaleState.reader = reader;
        while (!scaleState.closing && scaleState.port === port) {
          var res = await reader.read();
          if (res.done) {
            break;
          }
          if (res.value && res.value.length > 0) {
            var chunkStr = "";
            if (decoder) {
              chunkStr = decoder.decode(res.value, { stream: true });
            } else {
              for (var i = 0; i < res.value.length; i++) {
                chunkStr += String.fromCharCode(res.value[i] & 0x7f);
              }
            }
            processScaleBufferChunk(chunkStr, bufferRef);
          }
        }
      } catch (err) {
        if (!scaleState.closing) {
          scaleState.error = err && err.message ? err.message : "Conexión serie interrumpida.";
        }
        break;
      } finally {
        if (reader) {
          try {
            reader.releaseLock();
          } catch (_) {}
        }
        if (scaleState.reader === reader) {
          scaleState.reader = null;
        }
      }
    }

    if (scaleState.port === port && !scaleState.closing) {
      await disconnectScalePort();
    }
  }

  async function sendScaleDemandCommand() {
    var port = scaleState.port;
    if (!port || !port.writable || scaleState.closing) {
      return false;
    }
    var writer = null;
    try {
      writer = port.writable.getWriter();
      // Protocolo LP-7516 / Toledo estándar: "R\r\n" solicita transmisión inmediata del peso
      var cmd = new Uint8Array([0x52, 0x0d, 0x0a]);
      await writer.write(cmd);
      return true;
    } catch (_) {
      return false;
    } finally {
      if (writer) {
        try {
          writer.releaseLock();
        } catch (_) {}
      }
    }
  }

  async function openScalePort(port, baudOverride) {
    if (!port) return false;
    if (scaleState.connecting) return false;

    var baud = parseInt(baudOverride || scaleState.baudRate, 10) || 9600;
    scaleState.baudRate = baud;
    lsSet(LS_KEYS.scaleBaud, baud);

    if (scaleState.port && scaleState.port !== port) {
      await disconnectScalePort();
    }

    scaleState.connecting = true;
    scaleState.closing = false;
    scaleState.error = "";
    updateHardwareUi();

    try {
      await port.open({
        baudRate: baud,
        dataBits: 8,
        stopBits: 1,
        parity: "none",
        flowControl: "none",
        bufferSize: 4096
      });

      // Crítico para adaptadores USB-RS232 con chip CH340, Prolific y FTDI en Windows
      if (typeof port.setSignals === "function") {
        try {
          await port.setSignals({ dataTerminalReady: false, requestToSend: false });
        } catch (_) {}
      }

      scaleState.port = port;
      scaleState.connected = true;
      scaleState.connecting = false;

      var sig = portSignature(port);
      if (sig) {
        lsSet(LS_KEYS.scaleVidPid, sig);
        rememberPortIndex(port, LS_KEYS.scaleIdx);
      }

      startScaleReadLoop(port);
      // Solicitar primera lectura inmediata por si la balanza está en modo petición
      setTimeout(function () {
        if (scaleState.connected && Date.now() - scaleState.lastUpdatedAt > 250) {
          sendScaleDemandCommand();
        }
      }, 120);

      updateHardwareUi();
      return true;
    } catch (err) {
      scaleState.connecting = false;
      scaleState.connected = false;
      scaleState.port = null;
      var msg = err && err.message ? err.message : "No se pudo abrir el puerto COM.";
      if (/Failed to open serial port|Access is denied|ocupado/i.test(msg)) {
        msg = "El puerto COM está ocupado por otro programa (cierra scale_bridge.ps1 u otra pestaña que use el puerto).";
      }
      scaleState.error = msg;
      updateHardwareUi();
      return false;
    }
  }

  async function disconnectScalePort() {
    var port = scaleState.port;
    var reader = scaleState.reader;
    scaleState.closing = true;
    scaleState.connected = false;
    scaleState.connecting = false;
    scaleState.port = null;
    scaleState.reader = null;

    if (reader) {
      try {
        await reader.cancel();
      } catch (_) {}
      try {
        reader.releaseLock();
      } catch (_) {}
    }
    if (port) {
      try {
        await port.close();
      } catch (_) {}
    }
    scaleState.closing = false;
    updateHardwareUi();
  }

  async function autoReconnectScale() {
    if (!scaleState.supported || scaleState.connected || scaleState.connecting || printerLinking) {
      return false;
    }
    try {
      var ports = await navigator.serial.getPorts();
      if (!ports || ports.length === 0) {
        return false;
      }
      var serialPrinterSig = lsGet(LS_KEYS.serialPrinterVidPid, "");
      var preferredSig = lsGet(LS_KEYS.scaleVidPid, "");
      var printerPort = await findLinkedSerialPrinter();
      var candidate = null;
      // Con la impresora por COM y sin balanza recordada, no se adivina: se podría abrir el COM de la impresora.
      if (!preferredSig && lsGet(LS_KEYS.printTransport, "auto") === "webserial") {
        return false;
      }

      if (preferredSig) {
        // La balanza recordada no está conectada: no se prueba con otro puerto, que puede ser la impresora.
        candidate = pickPortBySig(ports, preferredSig, LS_KEYS.scaleIdx, [printerPort]);
        if (!candidate) {
          return false;
        }
      }
      // Sin una balanza recordada y con varios puertos autorizados NO se adivina:
      // se podría abrir el puerto de la impresora como si fuera la balanza.
      if (!candidate && !preferredSig && ports.length > 1) {
        return false;
      }
      if (!candidate) {
        for (var j = 0; j < ports.length; j++) {
          var s = portSignature(ports[j]);
          if (ports[j] !== printerPort && (!serialPrinterSig || s !== serialPrinterSig)) {
            candidate = ports[j];
            break;
          }
        }
      }
      if (!candidate) {
        return false;
      }
      return await openScalePort(candidate, scaleState.baudRate);
    } catch (_) {
      return false;
    }
  }

  async function connectScaleInteractive() {
    if (!scaleState.supported) {
      openHardwareModal();
      return false;
    }
    try {
      var port = await navigator.serial.requestPort();
      var ok = await openScalePort(port, scaleState.baudRate);
      if (ok) {
        showToast("✅ Balanza USB vinculada correctamente en el navegador.", "ok", 3500);
      } else if (scaleState.error) {
        showToast("⚠️ " + scaleState.error, "err", 6000);
      }
      return ok;
    } catch (err) {
      scaleState.lastPickerDismissedAt = Date.now();
      if (err && err.name !== "NotFoundError" && err.name !== "AbortError") {
        scaleState.error = err.message || "Error al seleccionar puerto USB.";
        showToast("⚠️ " + scaleState.error, "err", 5000);
      }
      updateHardwareUi();
      return false;
    }
  }

  async function tryReadLocalClientScaleBridge() {
    // Evitar demoras si ya comprobamos hace menos de 8 segundos que no hay puente local 8765
    if (scaleState.bridgeReachable === false && Date.now() - scaleState.bridgeCheckedAt < 8000) {
      return null;
    }
    var controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = controller
      ? setTimeout(function () {
          try {
            controller.abort();
          } catch (_) {}
        }, 450)
      : null;

    try {
      var res = await ORIGINAL_FETCH("http://127.0.0.1:8765/weight", {
        method: "GET",
        mode: "cors",
        cache: "no-store",
        signal: controller ? controller.signal : undefined
      });
      if (timer) clearTimeout(timer);
      if (!res || !res.ok) {
        scaleState.bridgeReachable = false;
        scaleState.bridgeCheckedAt = Date.now();
        return null;
      }
      var data = await res.json();
      scaleState.bridgeReachable = true;
      scaleState.bridgeCheckedAt = Date.now();
      updateHardwareUi();
      return data;
    } catch (_) {
      if (timer) clearTimeout(timer);
      scaleState.bridgeReachable = false;
      scaleState.bridgeCheckedAt = Date.now();
      return null;
    }
  }

  async function readScaleWeightUnified() {
    // 1. Si hay puerto Web Serial abierto en el navegador, leer directamente (0 sistemas externos)
    if (scaleState.connected && scaleState.port) {
      var age = Date.now() - scaleState.lastUpdatedAt;
      if (age > 850) {
        var beforeTs = scaleState.lastUpdatedAt;
        await sendScaleDemandCommand();
        var waited = 0;
        while (waited < 320 && scaleState.lastUpdatedAt === beforeTs) {
          await sleep(40);
          waited += 40;
        }
      }

      // Un peso solo es válido si es reciente (máx. 1,5 s). Antes se aceptaban hasta 15 s,
      // lo que permitía capturar un peso viejo después de retirar el bulto.
      if (scaleState.weightKg !== null && Date.now() - scaleState.lastUpdatedAt < MAX_WEIGHT_AGE_MS) {
        return {
          ok: true,
          weight_kg: scaleState.weightKg,
          stable: scaleState.stable,
          raw: scaleState.rawFrame,
          source: "web-serial",
          error: null
        };
      }

      return {
        ok: false,
        weight_kg: null,
        raw: scaleState.rawFrame || null,
        source: "web-serial",
        error: scaleState.error || "Balanza USB conectada pero sin datos recientes. Verifica que el indicador esté encendido (9600 bps / envío continuo C18=4)."
      };
    }

    // 2. Intentar reconectar silenciosamente si el puerto ya tenía permiso previo
    if (scaleState.supported && !scaleState.connecting) {
      var reconnected = await autoReconnectScale();
      if (reconnected) {
        var waitInit = 0;
        while (waitInit < 450 && scaleState.weightKg === null) {
          await sleep(45);
          waitInit += 45;
        }
        if (scaleState.weightKg !== null) {
          return {
            ok: true,
            weight_kg: scaleState.weightKg,
            stable: scaleState.stable,
            raw: scaleState.rawFrame,
            source: "web-serial",
            error: null
          };
        }
      }
    }

    // 3. Si el servidor está en modo simulación (SCALE_MODE=stub), respetar stub del servidor
    var serverScaleMode = String(CFG.scaleMode || "").toLowerCase();
    if (serverScaleMode === "stub") {
      return null; // Delegar al fetch original de /api/scale/weight
    }

    // 4. Probar si existe puente local en la PC del operador (127.0.0.1:8765)
    var localBridgeData = await tryReadLocalClientScaleBridge();
    if (localBridgeData && typeof localBridgeData === "object") {
      return localBridgeData;
    }

    // 5. Si el usuario acaba de hacer clic en un botón ("Leer balanza" / "Activar balanza") y Web Serial está disponible,
    // abrir directamente el selector nativo de puerto USB de Chrome/Edge sin pasos adicionales
    var hasUserGesture =
      scaleState.supported &&
      typeof navigator !== "undefined" &&
      navigator.userActivation &&
      navigator.userActivation.isActive &&
      Date.now() - scaleState.lastPickerDismissedAt > 4000;

    if (hasUserGesture) {
      var paired = await connectScaleInteractive();
      if (paired) {
        await sendScaleDemandCommand();
        var wMs = 0;
        while (wMs < 650 && scaleState.weightKg === null) {
          await sleep(50);
          wMs += 50;
        }
        if (scaleState.weightKg !== null) {
          return {
            ok: true,
            weight_kg: scaleState.weightKg,
            stable: scaleState.stable,
            raw: scaleState.rawFrame,
            source: "web-serial",
            error: null
          };
        }
        return {
          ok: false,
          weight_kg: null,
          raw: null,
          error: "Balanza vinculada. Esperando primera trama de peso..."
        };
      }
    }

    // 6. Si estamos en localhost, permitir intento final contra el servidor PHP local
    var host = String(window.location.hostname || "").toLowerCase();
    if (host === "localhost" || host === "127.0.0.1") {
      return null;
    }

    // 7. Estación remota sin balanza vinculada aún
    if (scaleState.supported) {
      return {
        ok: false,
        weight_kg: null,
        raw: null,
        client_serial_required: true,
        error: 'Haz clic en "⚖️ Balanza USB" (esquina inferior derecha) para seleccionar el cable USB de la balanza.'
      };
    }

    return {
      ok: false,
      weight_kg: null,
      raw: null,
      insecure_origin: true,
      error: 'Captura USB directa requiere HTTPS o habilitar este origen en Chrome/Edge. Haz clic en "⚖️ Hardware" abajo a la derecha.'
    };
  }

  /**
   * Lee el peso exigiendo ESTABILIDAD: varias lecturas consecutivas, todas marcadas
   * como estables por la balanza, mayores a 0 y con diferencia <= toleranciaKg.
   * Es la función que deben usar las pantallas con botón "Leer balanza".
   *
   * @param {{reads?: number, toleranceKg?: number, timeoutMs?: number}} opts
   * @returns {Promise<{ok: boolean, weight_kg: (number|null), error: (string|null)}>}
   */
  async function readStableWeight(opts) {
    var o = opts || {};
    var need = Math.max(2, parseInt(o.reads || 3, 10));
    var tol = typeof o.toleranceKg === "number" ? o.toleranceKg : 0.02;
    var deadline = Date.now() + (o.timeoutMs || 4000);
    var okCount = 0;
    var last = null;
    var lastError = "";

    while (Date.now() < deadline) {
      var r = await readScaleWeightUnified();
      if (r === null) {
        // Modo simulación del servidor (SCALE_MODE=stub): se delega al endpoint PHP.
        try {
          var resp = await ORIGINAL_FETCH("/api/scale/weight", { cache: "no-store", credentials: "same-origin" });
          r = await resp.json();
        } catch (_) {
          r = { ok: false, error: "Sin respuesta del servidor de balanza." };
        }
      }
      if (r && r.ok && r.stable !== false && Number(r.weight_kg) > 0) {
        var w = Number(r.weight_kg);
        if (last !== null && Math.abs(w - last) > tol) {
          okCount = 1;
        } else {
          okCount++;
        }
        last = w;
        if (okCount >= need) {
          return { ok: true, weight_kg: Math.round(w * 1000) / 1000, stable: true, raw: r.raw || null, error: null };
        }
      } else {
        okCount = 0;
        last = null;
        lastError = (r && r.error) ? String(r.error) : (r && r.ok && r.stable === false ? "Peso inestable." : "Peso en cero o sin lectura.");
      }
      await sleep(150);
    }
    return { ok: false, weight_kg: null, error: lastError || "Peso inestable: espera a que la balanza se estabilice." };
  }

  // Interceptar window.fetch para /api/scale/weight en todas las pantallas del sistema
  var ORIGINAL_FETCH = window.fetch ? window.fetch.bind(window) : null;
  if (ORIGINAL_FETCH) {
    window.fetch = async function (input, init) {
      var urlStr = "";
      try {
        if (typeof input === "string") {
          urlStr = input;
        } else if (input && typeof input.url === "string") {
          urlStr = input.url;
        }
      } catch (_) {}

      if (urlStr && /(?:^|\/)api\/scale\/weight(?:\?|$)/.test(urlStr)) {
        var clientReading = await readScaleWeightUnified();
        if (clientReading !== null) {
          return new Response(JSON.stringify(clientReading), {
            status: 200,
            headers: { "Content-Type": "application/json; charset=utf-8" }
          });
        }
      }

      return ORIGINAL_FETCH(input, init);
    };
  }

  // Liberar el puerto COM de la balanza al navegar a otra pantalla (la impresora no lo mantiene abierto)
  window.addEventListener("pagehide", function () {
    disconnectScalePort();
  });

  if (scaleState.supported && navigator.serial.addEventListener) {
    navigator.serial.addEventListener("disconnect", function (ev) {
      if (scaleState.port && ev && ev.target === scaleState.port) {
        disconnectScalePort();
        showToast("⚠️ Se desconectó el cable USB de la balanza.", "err", 4500);
      }
      refreshPrinterLink();
    });
    navigator.serial.addEventListener("connect", function () {
      setTimeout(function () {
        autoReconnectScale();
        refreshPrinterLink();
      }, 300);
    });
  }
  if (typeof navigator !== "undefined" && navigator.usb && navigator.usb.addEventListener) {
    navigator.usb.addEventListener("disconnect", function () { refreshPrinterLink(); });
    navigator.usb.addEventListener("connect", function () { refreshPrinterLink(); });
  }

  // ===========================================================================
  // 2. MOTOR UNIVERSAL DE IMPRESIÓN TÉRMICA (PUENTE LOCAL / WEBUSB / WEBSERIAL)
  // ===========================================================================
  // usbDevice y serialPrinterPort indican la impresora VINCULADA; el puerto solo se abre durante cada impresión.
  var printerState = {
    webUsbSupported: typeof navigator !== "undefined" && !!navigator.usb,
    usbDevice: null,
    serialPrinterPort: null,
    // El estado conocido del servicio local se muestra de inmediato; la comprobación lo corrige si cambió.
    bridgeOnline: lsGet(LS_KEYS.bridgeLastOnline, "") === "1" ? true : null,
    bridgeCheckedAt: 0,
    installedPrinters: [],
    // null = puente antiguo que no informa; [] = sin impresora térmica conectada a este computador
    thermalPrinters: null,
    networkPrintersIgnored: [],
    bridgeComputer: "",
    defaultWindowsPrinter: "",
    lastError: ""
  };

  function getPrinterOverrideForProfile(profile, serverPrinterName) {
    var prof = String(profile || "default").toLowerCase();
    var override = "";
    if (prof === "zebra") {
      override = lsGet(LS_KEYS.printerZebra, "");
    } else if (prof === "barpos") {
      override = lsGet(LS_KEYS.printerBarpos, "");
    } else {
      override = lsGet(LS_KEYS.printerDefault, "");
    }
    if (override) return override;
    var defaultOverride = lsGet(LS_KEYS.printerDefault, "");
    if (defaultOverride && (!serverPrinterName || serverPrinterName === "DEFAULT")) {
      return defaultOverride;
    }
    return serverPrinterName || CFG.printerName || "DEFAULT";
  }

  async function probeLocalPrintBridge(force) {
    if (!force && printerState.bridgeOnline !== null && Date.now() - printerState.bridgeCheckedAt < 6000) {
      return printerState.bridgeOnline;
    }
    if (!ORIGINAL_FETCH) return false;

    var controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = controller
      ? setTimeout(function () {
          try {
            controller.abort();
          } catch (_) {}
        }, 550)
      : null;

    try {
      var res = await ORIGINAL_FETCH("http://127.0.0.1:8767/printers", {
        method: "GET",
        mode: "cors",
        cache: "no-store",
        signal: controller ? controller.signal : undefined
      });
      if (timer) clearTimeout(timer);

      if (res && res.ok) {
        var info = await res.json();
        printerState.bridgeOnline = true;
        printerState.bridgeCheckedAt = Date.now();
        lsSet(LS_KEYS.bridgeLastOnline, "1");
        if (Array.isArray(info.printers)) {
          printerState.installedPrinters = info.printers;
        }
        // Puente nuevo: solo impresoras de ESTE computador (las compartidas desde otro PC se ignoran).
        printerState.thermalPrinters = Array.isArray(info.thermal_printers) ? info.thermal_printers : null;
        printerState.networkPrintersIgnored = Array.isArray(info.network_printers_ignored) ? info.network_printers_ignored : [];
        printerState.bridgeComputer = info.computer ? String(info.computer) : "";
        if (info.default_printer) {
          printerState.defaultWindowsPrinter = String(info.default_printer);
        }
        syncPageBridgeBadge(true);
        updateHardwareUi();
        return true;
      }

      // Compatibilidad con versión anterior de print_bridge.ps1 que solo tenía /health
      var resHealth = await ORIGINAL_FETCH("http://127.0.0.1:8767/health", {
        method: "GET",
        mode: "cors",
        cache: "no-store"
      });
      var okHealth = !!(resHealth && resHealth.ok);
      printerState.bridgeOnline = okHealth;
      printerState.bridgeCheckedAt = Date.now();
      lsSet(LS_KEYS.bridgeLastOnline, okHealth ? "1" : "");
      syncPageBridgeBadge(okHealth);
      updateHardwareUi();
      return okHealth;
    } catch (_) {
      if (timer) clearTimeout(timer);
      printerState.bridgeOnline = false;
      printerState.bridgeCheckedAt = Date.now();
      lsSet(LS_KEYS.bridgeLastOnline, "");
      syncPageBridgeBadge(false);
      updateHardwareUi();
      return false;
    }
  }

  function syncPageBridgeBadge(isOnline) {
    var badge = document.getElementById("bridge-status-badge");
    if (!badge) return;
    if (isOnline || printerState.usbDevice || printerState.serialPrinterPort) {
      badge.style.background = "#059669";
      badge.innerHTML = printerState.usbDevice
        ? "🟢 Impresora WebUSB Directa Activa"
        : "🟢 Impresora Térmica Activa (PC Local)";
    }
  }

  async function printViaLocalBridge(job) {
    if (!ORIGINAL_FETCH) {
      return { ok: false, error: "Fetch no disponible." };
    }
    var targetPrinter = getPrinterOverrideForProfile(job.profile, job.printer);
    var controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = controller
      ? setTimeout(function () {
          try {
            controller.abort();
          } catch (_) {}
        }, 4500)
      : null;

    try {
      var res = await ORIGINAL_FETCH("http://127.0.0.1:8767/print", {
        method: "POST",
        mode: "cors",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          printer: targetPrinter,
          language: job.language || CFG.printLanguage || "epl",
          copies: Math.max(1, parseInt(job.copies || 1, 10) || 1),
          content: job.content,
          // Identificador único: el puente ignora un trabajo repetido (evita etiquetas dobles)
          job_id: job.job_id || ""
        }),
        signal: controller ? controller.signal : undefined
      });
      if (timer) clearTimeout(timer);
      var data = await res.json().catch(function () {
        return null;
      });
      if (res.ok && data && data.ok) {
        printerState.bridgeOnline = true;
        printerState.bridgeCheckedAt = Date.now();
        syncPageBridgeBadge(true);
        updateHardwareUi();
        return {
          ok: true,
          transport: "bridge",
          printer: data.printer || targetPrinter
        };
      }
      return {
        ok: false,
        bridgeResponded: true,
        error: (data && data.error) ? String(data.error) : ("Error HTTP " + res.status + " en servicio local de impresión.")
      };
    } catch (err) {
      if (timer) clearTimeout(timer);
      var aborted = !!(err && err.name === "AbortError");
      if (!aborted) {
        printerState.bridgeOnline = false;
        printerState.bridgeCheckedAt = Date.now();
      }
      return {
        ok: false,
        bridgeResponded: false,
        // Si se agotó el tiempo, el puente pudo haber imprimido igual: estado INCIERTO.
        // En ese caso NO se prueba otro transporte (evita imprimir dos veces).
        ambiguous: aborted,
        error: aborted
          ? "El servicio de impresión no respondió a tiempo. No se sabe si la etiqueta salió: revisa la impresora antes de reintentar."
          : (err && err.message ? err.message : "Servicio local 8767 no detectado.")
      };
    }
  }

  // Genera un identificador único por trabajo de impresión.
  function newJobId() {
    return "j" + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
  }

  // --- Transporte WebUSB directo (sin servicios externos) ---
  function findUsbBulkOutEndpoint(device) {
    if (!device || !device.configuration || !Array.isArray(device.configuration.interfaces)) {
      return null;
    }
    var fallbackMatch = null;
    var interfaces = device.configuration.interfaces;
    for (var i = 0; i < interfaces.length; i++) {
      var iface = interfaces[i];
      var alts = iface.alternates || [];
      for (var a = 0; a < alts.length; a++) {
        var alt = alts[a];
        var eps = alt.endpoints || [];
        for (var e = 0; e < eps.length; e++) {
          var ep = eps[e];
          if (ep.direction === "out" && ep.type === "bulk") {
            var match = {
              interfaceNumber: iface.interfaceNumber,
              alternateSetting: alt.alternateSetting,
              endpointNumber: ep.endpointNumber
            };
            // Priorizar clase USB Printer oficial (0x07)
            if (alt.interfaceClass === 7) {
              return match;
            }
            if (!fallbackMatch) {
              fallbackMatch = match;
            }
          }
        }
      }
    }
    return fallbackMatch;
  }

  // Solo una pestaña a la vez usa la impresora; las demás esperan su turno.
  var PRINTER_BUSY_ERROR = "La impresora está ocupada con otro trabajo. Intenta de nuevo en unos segundos.";
  var PRINT_WRITE_TIMEOUT_MS = 30000;

  async function withPrinterLock(fn) {
    if (typeof navigator === "undefined" || !navigator.locks || !navigator.locks.request || typeof AbortController === "undefined") {
      return fn();
    }
    var controller = new AbortController();
    var waitTimer = setTimeout(function () {
      controller.abort();
    }, PRINT_WRITE_TIMEOUT_MS + 5000);
    try {
      return await navigator.locks.request("unibag-printer", { signal: controller.signal }, function () {
        clearTimeout(waitTimer);
        return fn();
      });
    } catch (err) {
      if (err && err.name === "AbortError") {
        return { ok: false, error: PRINTER_BUSY_ERROR };
      }
      throw err;
    } finally {
      clearTimeout(waitTimer);
    }
  }

  // Si la impresora deja de responder, no se queda esperando para siempre.
  function withWriteTimeout(promise) {
    var timer = null;
    var timeout = new Promise(function (_, reject) {
      timer = setTimeout(function () {
        reject(new Error("La impresora no respondió a tiempo. Revisa si tiene papel y está encendida."));
      }, PRINT_WRITE_TIMEOUT_MS);
    });
    return Promise.race([promise, timeout]).finally(function () {
      clearTimeout(timer);
    });
  }

  // --- Transporte WebUSB directo (sin servicios externos) ---
  function usbDeviceId(device) {
    return device ? [device.vendorId || 0, device.productId || 0, device.serialNumber || ""].join(":") : "";
  }

  function isUsbPrinterDevice(device) {
    var cfgs = device.configurations || [];
    for (var c = 0; c < cfgs.length; c++) {
      var ifs = cfgs[c].interfaces || [];
      for (var i = 0; i < ifs.length; i++) {
        var alts = ifs[i].alternates || [];
        for (var a = 0; a < alts.length; a++) {
          if (alts[a].interfaceClass === 7) return true;
        }
      }
    }
    try {
      return !!(device.configuration && findUsbBulkOutEndpoint(device));
    } catch (_) {
      return false;
    }
  }

  // Busca sin abrir nada la impresora USB que el navegador ya tiene autorizada.
  async function findLinkedUsbPrinter() {
    if (!printerState.webUsbSupported) return null;
    try {
      var devices = (await navigator.usb.getDevices()) || [];
      var savedId = lsGet(LS_KEYS.usbPrinterId, "");
      var fallback = null;
      for (var d = 0; d < devices.length; d++) {
        if (savedId && usbDeviceId(devices[d]) === savedId) return devices[d];
        if (!fallback && isUsbPrinterDevice(devices[d])) fallback = devices[d];
      }
      return fallback;
    } catch (_) {
      return null;
    }
  }

  async function openAndClaimUsbPrinter(device) {
    if (!device.opened) {
      await device.open();
    }
    if (!device.configuration) {
      await device.selectConfiguration(1);
    }
    var epInfo = findUsbBulkOutEndpoint(device);
    if (!epInfo) {
      throw new Error("El dispositivo USB no expone un canal de impresión Bulk OUT.");
    }
    await device.claimInterface(epInfo.interfaceNumber);
    if (typeof epInfo.alternateSetting === "number" && epInfo.alternateSetting !== 0) {
      try {
        await device.selectAlternateInterface(epInfo.interfaceNumber, epInfo.alternateSetting);
      } catch (_) {}
    }
    return epInfo;
  }

  async function releaseUsbPrinter(device, epInfo) {
    if (epInfo) {
      try {
        await device.releaseInterface(epInfo.interfaceNumber);
      } catch (_) {}
    }
    try {
      if (device.opened) await device.close();
    } catch (_) {}
  }

  function usbErrorMessage(err) {
    var msg = err && err.message ? err.message : "No se pudo abrir la impresora WebUSB.";
    if (/Access denied|claimInterface|SecurityError/i.test(msg)) {
      // Windows bloquea el USB directo mientras la impresora tenga su driver de impresora de Windows.
      msg = "Windows está usando esta impresora con su propio driver y no deja que el navegador la tome por USB. " +
        "Cierra otras pestañas o programas que la usen y vuelve a intentar. Si sigue igual, usa «Impresora Serial (COM)» " +
        "si la impresora tiene puerto COM, o el servicio de Windows de esta PC. No cambies el driver a WinUSB (Zadig): " +
        "el adaptador deja de aparecer como puerto COM y la impresora serial deja de funcionar.";
    }
    return msg;
  }

  async function connectWebUsbPrinterInteractive() {
    if (!printerState.webUsbSupported) {
      showToast("⚠️ Este navegador o contexto HTTP no soporta WebUSB directo.", "err", 5000);
      return false;
    }
    try {
      var device = await navigator.usb.requestDevice({ filters: [] });
      var check = await withPrinterLock(async function () {
        var epInfo = null;
        try {
          epInfo = await openAndClaimUsbPrinter(device);
          return { ok: true };
        } catch (err) {
          return { ok: false, error: usbErrorMessage(err) };
        } finally {
          await releaseUsbPrinter(device, epInfo);
        }
      });
      if (!check.ok) {
        printerState.lastError = check.error;
        updateHardwareUi();
        showToast("⚠️ " + check.error, "err", 7500);
        return false;
      }
      printerState.usbDevice = device;
      printerState.lastError = "";
      lsSet(LS_KEYS.usbPrinterId, usbDeviceId(device));
      lsSet(LS_KEYS.printTransport, "webusb");
      syncPageBridgeBadge(true);
      updateHardwareUi();
      showToast("✅ Impresora USB vinculada: " + (device.productName || "USB Printer") + ". Queda lista para todas las pantallas.", "ok", 4000);
      return true;
    } catch (err) {
      if (err && err.name !== "NotFoundError") {
        showToast("⚠️ " + (err.message || "No se pudo seleccionar la impresora USB."), "err", 5000);
      }
      return false;
    }
  }

  async function printViaWebUsb(job) {
    var dev = await findLinkedUsbPrinter();
    printerState.usbDevice = dev;
    if (!dev) {
      return { ok: false, error: "Impresora WebUSB no conectada." };
    }

    return withPrinterLock(async function () {
      var epInfo = null;
      var sentAny = false; // si ya salió algo, un fallo posterior deja el estado incierto
      try {
        for (var attempt = 0; attempt < 6 && !epInfo; attempt++) {
          try {
            epInfo = await openAndClaimUsbPrinter(dev);
          } catch (openErr) {
            if (attempt === 5) throw openErr;
            await sleep(500);
          }
        }
        var bytes = encodeRawPrinterBytes(job.content, job.language);
        var copies = Math.max(1, parseInt(job.copies || 1, 10) || 1);
        var chunkSize = 4096;
        for (var c = 0; c < copies; c++) {
          for (var offset = 0; offset < bytes.length; offset += chunkSize) {
            var slice = bytes.subarray(offset, Math.min(offset + chunkSize, bytes.length));
            var res = await withWriteTimeout(dev.transferOut(epInfo.endpointNumber, slice));
            if (res && res.status && res.status !== "ok") {
              throw new Error("Transferencia USB reportó estado: " + res.status);
            }
            sentAny = true;
          }
        }
        printerState.lastError = "";
        return {
          ok: true,
          transport: "webusb",
          printer: dev.productName || "Impresora WebUSB"
        };
      } catch (err) {
        var msg = sentAny ? (err && err.message ? err.message : "Fallo al transmitir etiqueta por WebUSB.") : usbErrorMessage(err);
        printerState.lastError = msg;
        return { ok: false, ambiguous: sentAny, error: msg };
      } finally {
        await releaseUsbPrinter(dev, epInfo);
        updateHardwareUi();
      }
    });
  }

  // --- Transporte Web Serial para Impresoras Térmicas Serial/Virtual-COM ---
  async function openPrinterSerialPort(port) {
    if (port.writable) return;
    var baud = parseInt(lsGet(LS_KEYS.serialPrinterBaud, "9600"), 10) || 9600;
    await port.open({ baudRate: baud, dataBits: 8, stopBits: 1, parity: "none" });
  }

  async function connectSerialPrinterInteractive() {
    if (!scaleState.supported) {
      showToast("⚠️ Web Serial no está disponible en este origen.", "err", 5000);
      return false;
    }
    printerLinking = true;
    try {
      var port = await navigator.serial.requestPort();
      if (scaleState.port === port) {
        await disconnectScalePort();
      }
      var check = await withPrinterLock(async function () {
        try {
          await openPrinterSerialPort(port);
          await port.close();
          return { ok: true };
        } catch (err) {
          return { ok: false, error: err && err.message ? err.message : "No se pudo abrir el puerto COM de la impresora." };
        }
      });
      if (!check.ok) {
        showToast("⚠️ " + check.error, "err", 5500);
        return false;
      }
      printerState.serialPrinterPort = port;
      printerState.usbDevice = null;
      printerState.lastError = "";
      // Se reemplaza lo recordado aunque este puerto no tenga VID/PID: si no, seguiría apuntando a la impresora anterior.
      var sig = portSignature(port);
      lsSet(LS_KEYS.serialPrinterVidPid, sig);
      await rememberPortIndex(port, LS_KEYS.serialPrinterIdx);
      // Si este puerto quedó recordado como balanza, esa memoria estaba equivocada.
      if (sig && lsGet(LS_KEYS.scaleVidPid, "") === sig) {
        var authorized = await navigator.serial.getPorts();
        if (authorized.filter(function (p) { return portSignature(p) === sig; }).length === 1) {
          lsSet(LS_KEYS.scaleVidPid, "");
        }
      }
      lsSet(LS_KEYS.printTransport, "webserial");
      syncPageBridgeBadge(true);
      updateHardwareUi();
      showToast("✅ Impresora Serial/COM vinculada. Queda lista para todas las pantallas.", "ok", 4000);
      return true;
    } catch (err) {
      if (err && err.name !== "NotFoundError") {
        showToast("⚠️ " + (err.message || "No se pudo seleccionar el puerto COM de la impresora."), "err", 5500);
      } else {
        // Lista vacía o cerrada sin elegir: Windows no muestra ningún puerto COM en este computador.
        showToast("⚠️ No se eligió ningún puerto COM. Si la lista sale vacía, Windows no ve la impresora por COM: revisa el cable USB, que aparezca en Administrador de dispositivos → Puertos (COM y LPT) y el driver (CH340/Prolific). Mientras tanto se imprime por el servicio de Windows de esta PC si la impresora está instalada ahí.", "err", 9000);
      }
      return false;
    } finally {
      printerLinking = false;
    }
  }

  // Busca sin abrir nada el puerto COM de la impresora ya autorizado en este navegador.
  async function findLinkedSerialPrinter() {
    if (!scaleState.supported) return null;
    try {
      var ports = (await navigator.serial.getPorts()) || [];
      var current = printerState.serialPrinterPort;
      if (current && current !== scaleState.port && ports.indexOf(current) >= 0) return current;
      var sig = lsGet(LS_KEYS.serialPrinterVidPid, "");
      if (sig) {
        return pickPortBySig(ports, sig, LS_KEYS.serialPrinterIdx, [scaleState.port]);
      }
      if (lsGet(LS_KEYS.printTransport, "auto") !== "webserial") return null;
      // Los COM físicos y Bluetooth no exponen VID/PID: se identifican por su posición entre los puertos sin VID/PID.
      if (parseInt(lsGet(LS_KEYS.serialPrinterIdx, "-1"), 10) >= 0) {
        return pickPortBySig(ports, "", LS_KEYS.serialPrinterIdx, [scaleState.port]);
      }
      // Vinculada antes de recordar la posición: si queda un solo puerto autorizado que no es la balanza, es la impresora.
      var scaleSig = lsGet(LS_KEYS.scaleVidPid, "");
      var candidates = ports.filter(function (p) {
        return p !== scaleState.port && (!scaleSig || portSignature(p) !== scaleSig);
      });
      return candidates.length === 1 ? candidates[0] : null;
    } catch (_) {
      return null;
    }
  }

  // Actualiza qué impresora está vinculada, sin abrir puertos. Es lo que mantiene el estado "lista" en todas las páginas.
  async function refreshPrinterLink() {
    if (printerLinking) return;
    var transport = lsGet(LS_KEYS.printTransport, "auto");
    if (scaleState.supported && (transport === "webserial" || lsGet(LS_KEYS.serialPrinterVidPid, ""))) {
      printerState.serialPrinterPort = await findLinkedSerialPrinter();
    }
    if (printerState.webUsbSupported && transport === "webusb") {
      printerState.usbDevice = await findLinkedUsbPrinter();
    }
    syncPageBridgeBadge(printerState.bridgeOnline === true);
    updateHardwareUi();
  }

  async function printViaWebSerial(job) {
    var port = await findLinkedSerialPrinter();
    printerState.serialPrinterPort = port;
    if (!port) {
      return { ok: false, error: "Impresora Serial no conectada." };
    }

    return withPrinterLock(async function () {
      var writer = null;
      var written = 0;
      var finished = false;
      try {
        // El puerto puede seguir liberándose unos instantes tras cambiar de página.
        for (var attempt = 0; attempt < 8; attempt++) {
          try {
            await openPrinterSerialPort(port);
            break;
          } catch (openErr) {
            if (attempt === 7) {
              throw new Error("El puerto COM de la impresora está ocupado por otro programa o no responde.");
            }
            await sleep(500);
          }
        }
        writer = port.writable.getWriter();
        var bytes = encodeRawPrinterBytes(job.content, job.language);
        var copies = Math.max(1, parseInt(job.copies || 1, 10) || 1);
        // Por COM (9600 bps ≈ 1 KB/s) un lote de muchas etiquetas tarda más que el tiempo máximo de UNA escritura:
        // se envía en tramos de 2 KB, cada uno con su propio tiempo máximo.
        var CHUNK = 2048;
        for (var c = 0; c < copies; c++) {
          for (var off = 0; off < bytes.length; off += CHUNK) {
            await withWriteTimeout(writer.write(bytes.subarray(off, Math.min(bytes.length, off + CHUNK))));
            written++;
          }
        }
        // Espera a que el puerto termine de enviar todo antes de cerrarlo.
        await withWriteTimeout(writer.close());
        finished = true;
        printerState.lastError = "";
        return { ok: true, transport: "webserial", printer: "Impresora Serial COM" };
      } catch (err) {
        var msg = err && err.message ? err.message : "Error de escritura serial.";
        printerState.lastError = msg;
        return { ok: false, ambiguous: written > 0, error: msg };
      } finally {
        if (writer) {
          if (!finished) {
            try {
              await writer.abort();
            } catch (_) {}
          }
          try {
            writer.releaseLock();
          } catch (_) {}
        }
        try {
          await port.close();
        } catch (_) {}
        updateHardwareUi();
      }
    });
  }

  // --- Orquestador Principal de Trabajo de Impresión ---
  async function printJob(job, options) {
    var opts = options || {};
    if (!job || !job.content) {
      return { ok: false, error: "Trabajo de impresión vacío." };
    }

    var preferred = lsGet(LS_KEYS.printTransport, "auto");
    var result = null;
    var bridgeErrorMsg = "";

    // Cada trabajo lleva un id único; así el puente puede descartar repeticiones.
    if (!job.job_id) {
      job.job_id = newJobId();
    }
    tuneJob(job);

    // Si un transporte pudo haber imprimido parcialmente o su respuesta es incierta,
    // se DETIENE la cascada: probar otro transporte duplicaría la etiqueta.
    function stopAmbiguous(res) {
      var msg = res.error || "Estado de impresión incierto: revisa la impresora antes de reintentar.";
      updatePagePrintBanner(false, msg);
      if (!opts.silentFallback) {
        showInteractivePrintFallbackToast(job, opts.fallbackUrl || "", msg);
      }
      return { ok: false, ambiguous: true, error: msg };
    }

    // 0. Impresora en red elegida en esta PC: el servidor web la imprime directo por IP (sin programas en el PC).
    if (String(lsGet(LS_KEYS.printerIp, "") || "").trim() !== "") {
      var netRes = await printViaNetwork(job);
      if (netRes.ok) {
        notifyPrintSuccess(job, netRes, opts);
        return netRes;
      }
      if (netRes.ambiguous) return stopAmbiguous(netRes);
      printerState.lastError = netRes.error;
      updatePagePrintBanner(false, netRes.error);
      if (!opts.silentFallback) {
        showInteractivePrintFallbackToast(job, opts.fallbackUrl || "", netRes.error);
      }
      return { ok: false, error: netRes.error };
    }

    await refreshPrinterLink();

    // 1. Si el usuario configuró explícitamente WebUSB o WebSerial primero
    if (preferred === "webusb" && printerState.webUsbSupported) {
      result = await printViaWebUsb(job);
      if (result.ok) {
        notifyPrintSuccess(job, result, opts);
        return result;
      }
      if (result.ambiguous) return stopAmbiguous(result);
    } else if (preferred === "webserial") {
      result = await printViaWebSerial(job);
      if (result.ok) {
        notifyPrintSuccess(job, result, opts);
        return result;
      }
      if (result.ambiguous) return stopAmbiguous(result);
    }

    // Si la impresora USB/COM vinculada no está disponible se sigue con el servicio local de Windows de ESTA PC
    // (print_bridge.ps1 solo usa impresoras conectadas a este computador, nunca las compartidas de otro).
    var ownLinkError = (preferred === "webserial" || preferred === "webusb") && result && !result.ok
      ? (preferred === "webserial" ? "Impresora COM de esta PC: " : "Impresora USB de esta PC: ") + (result.error || "no respondió.")
      : "";

    // 2. Servicio local de Windows (127.0.0.1:8767): ya no se exige. Solo se usa en una PC donde ya está funcionando;
    //    si no, se imprime por la impresora USB o COM vinculada en el navegador.
    if (printerState.bridgeOnline !== true && preferred !== "bridge") {
      await probeLocalPrintBridge(true);
    }
    if (printerState.bridgeOnline === true || preferred === "bridge") {
      var bridgeRes = await printViaLocalBridge(job);
      if (bridgeRes.ok) {
        notifyPrintSuccess(job, bridgeRes, opts);
        return bridgeRes;
      }
      if (bridgeRes.ambiguous) return stopAmbiguous(bridgeRes);
      if (bridgeRes.bridgeResponded && bridgeRes.error) {
        bridgeErrorMsg = bridgeRes.error;
      }
    }

    // 3. Intentar WebUSB directo si ya hay dispositivo autorizado
    if (printerState.webUsbSupported) {
      var usbRes = await printViaWebUsb(job);
      if (usbRes.ok) {
        notifyPrintSuccess(job, usbRes, opts);
        return usbRes;
      }
      if (usbRes.ambiguous) return stopAmbiguous(usbRes);
    }

    // 4. Intentar Impresora Web Serial si está conectada
    if (printerState.serialPrinterPort) {
      var serRes = await printViaWebSerial(job);
      if (serRes.ok) {
        notifyPrintSuccess(job, serRes, opts);
        return serRes;
      }
      if (serRes.ambiguous) return stopAmbiguous(serRes);
    }

    // 5. Ningún transporte local pudo emitir aún: actualizar banner si existe y mostrar aviso interactivo limpio
    var errDetail = (ownLinkError && bridgeErrorMsg ? ownLinkError + " · Servicio Windows: " + bridgeErrorMsg : "") || ownLinkError || bridgeErrorMsg || (result && result.error) || printerState.lastError ||
      "No hay impresora vinculada en esta PC. Abre Hardware y pulsa «Vincular USB Directo» o «Impresora Serial (COM)».";
    updatePagePrintBanner(false, errDetail);
    if (!opts.silentFallback) {
      showInteractivePrintFallbackToast(job, opts.fallbackUrl || "", errDetail);
    }

    return {
      ok: false,
      error: errDetail
    };
  }

  function updatePagePrintBanner(ok, text) {
    var banner = document.getElementById("client-print-banner");
    if (!banner) return;
    if (ok) {
      banner.className = "alert-box alert-ok";
      banner.textContent = "✅ " + text;
    } else {
      banner.className = "alert-box alert-err";
      banner.textContent = "⚠️ " + text;
    }
  }

  function notifyPrintSuccess(job, res, opts) {
    var labelDesc = job.label ? (" (" + job.label + ")") : "";
    var pName = res.printer || job.printer || "Impresora Térmica";
    // "Enviada" y no "impresa": el spooler confirma la recepción del trabajo, no que
    // haya salido papel (no se consulta el estado físico de la impresora).
    updatePagePrintBanner(true, "Etiqueta" + labelDesc + " enviada a " + pName + ". Verifica que haya salido de la impresora.");
    if (!opts || !opts.silentSuccess) {
      showToast("🖨️ Etiqueta" + labelDesc + " enviada a " + pName, "ok", 3200);
    }
  }

  function coalescePrintQueue(jobs) {
    var coalesced = [];
    for (var i = 0; i < jobs.length; i++) {
      var j = jobs[i];
      if (!j || !j.content) continue;
      var copies = Math.max(1, parseInt(j.copies || 1, 10) || 1);
      var prev = coalesced.length > 0 ? coalesced[coalesced.length - 1] : null;
      if (
        prev &&
        copies === 1 &&
        prev.copies === 1 &&
        prev.printer === j.printer &&
        prev.profile === j.profile &&
        prev.language === j.language
      ) {
        prev.content = String(prev.content).replace(/\s+$/, "") + "\r\n" + String(j.content).replace(/^\s+/, "");
        prev.batchCount = (prev.batchCount || 1) + 1;
        prev.label = prev.batchCount + " etiquetas";
      } else {
        coalesced.push({
          printer: j.printer || "DEFAULT",
          profile: j.profile || "default",
          language: j.language || "epl",
          copies: copies,
          content: String(j.content),
          label: j.label || "",
          batchCount: 1
        });
      }
    }
    return coalesced;
  }

  async function processInitialPrintQueue() {
    if (!INITIAL_QUEUE || INITIAL_QUEUE.length === 0) return;
    var batches = coalescePrintQueue(INITIAL_QUEUE);
    for (var i = 0; i < batches.length; i++) {
      await printJob(batches[i], {});
    }
  }

  // Interceptar clics en enlaces con server_print=1 para imprimir en el acto sin abrir pestañas extra
  function setupGlobalPrintLinkInterceptor() {
    document.addEventListener("click", async function (ev) {
      if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
        return;
      }
      var el = ev.target;
      while (el && el !== document && el.tagName !== "A") {
        el = el.parentElement;
      }
      if (!el || el.tagName !== "A" || el.hasAttribute("data-no-hw-intercept")) {
        return;
      }
      var rawHref = el.getAttribute("href") || "";
      if (!rawHref || rawHref.indexOf("server_print=1") === -1) {
        return;
      }

      var urlObj = null;
      try {
        urlObj = new URL(el.href, window.location.origin);
      } catch (_) {
        return;
      }
      if (urlObj.origin !== window.location.origin) {
        return;
      }

      var isLabelEndpoint =
        /\/(?:rolls|boxes|pallets)\/\d+\/label$/.test(urlObj.pathname) ||
        /\/work-orders\/\d+\/box-label$/.test(urlObj.pathname) ||
        /\/reception\/test-label$/.test(urlObj.pathname) ||
        /\/reception\/labels\/[a-f0-9]{32}$/.test(urlObj.pathname);

      if (!isLabelEndpoint) {
        return;
      }

      ev.preventDefault();

      var origHtml = el.innerHTML;
      var origPointer = el.style.pointerEvents;
      el.style.pointerEvents = "none";
      el.textContent = "⏳ Imprimiendo...";

      var rawJobUrl = new URL(urlObj.toString());
      rawJobUrl.searchParams.set("raw_job", "1");

      var cleanFallbackUrl = new URL(urlObj.toString());
      cleanFallbackUrl.searchParams.delete("server_print");
      cleanFallbackUrl.searchParams.delete("raw_job");

      try {
        var res = await ORIGINAL_FETCH(rawJobUrl.toString(), {
          method: "GET",
          credentials: "same-origin",
          cache: "no-store"
        });
        var data = await res.json();
        if (!res.ok || !data || !data.ok || !data.print_job) {
          throw new Error((data && data.error) ? data.error : "No se pudo generar el comando térmico.");
        }

        var pRes = await printJob(data.print_job, { fallbackUrl: cleanFallbackUrl.toString() });
        if (pRes && pRes.ok) {
          el.textContent = "✅ Impresa";
          setTimeout(function () {
            el.innerHTML = origHtml;
          }, 1400);
        } else {
          el.innerHTML = origHtml;
        }
      } catch (err) {
        el.innerHTML = origHtml;
        showToast("⚠️ " + (err && err.message ? err.message : "Error al obtener etiqueta."), "err", 5000);
      } finally {
        el.style.pointerEvents = origPointer;
      }
    });
  }

  // ===========================================================================
  // 3. INTERFAZ VISUAL FLOTANTE, NOTIFICACIONES Y MODAL DE CONFIGURACIÓN
  // ===========================================================================
  var uiRefs = {
    styleInjected: false,
    toastWrap: null,
    pill: null,
    modalBackdrop: null
  };

  function injectHardwareStyles() {
    if (uiRefs.styleInjected || !document.head) return;
    uiRefs.styleInjected = true;
    var style = document.createElement("style");
    style.id = "unibag-hardware-styles";
    style.textContent = [
      "@media print { #unibag-hw-pill, #unibag-hw-toasts, #unibag-hw-modal-backdrop { display: none !important; } }",
      "#unibag-hw-pill { position: fixed; right: 14px; bottom: 14px; z-index: 9990; display: inline-flex; align-items: center; gap: 8px; background: #0f172a; color: #f8fafc; border: 1px solid #334155; border-radius: 999px; padding: 7px 13px; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 8px 20px rgba(15,23,42,0.28); transition: transform 0.15s ease, opacity 0.15s ease; user-select: none; }",
      "#unibag-hw-pill:hover { transform: translateY(-1px); opacity: 1 !important; background: #1e293b; }",
      "#unibag-hw-pill.hw-compact { padding: 6px 10px; opacity: 0.72; }",
      ".unibag-hw-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }",
      "#unibag-hw-toasts { position: fixed; right: 14px; bottom: 56px; z-index: 9995; display: flex; flex-direction: column; gap: 8px; max-width: 380px; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }",
      ".unibag-hw-toast { background: #0f172a; color: #fff; border-radius: 10px; padding: 12px 14px; font-size: 12.5px; line-height: 1.4; box-shadow: 0 10px 25px rgba(0,0,0,0.28); border-left: 4px solid #38bdf8; }",
      ".unibag-hw-toast.ok { border-left-color: #10b981; }",
      ".unibag-hw-toast.err { border-left-color: #f59e0b; }",
      ".unibag-hw-toast-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }",
      ".unibag-hw-btn { border: 1px solid #475569; background: #1e293b; color: #fff; border-radius: 6px; padding: 5px 10px; font-size: 11.5px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }",
      ".unibag-hw-btn:hover { background: #334155; }",
      ".unibag-hw-btn.primary { background: #00a9a6; border-color: #00a9a6; color: #fff; }",
      ".unibag-hw-btn.primary:hover { background: #0d9488; }",
      "#unibag-hw-modal-backdrop { position: fixed; inset: 0; z-index: 9999; background: rgba(15,23,42,0.58); display: none; align-items: center; justify-content: center; padding: 16px; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }",
      "#unibag-hw-modal { background: #fff; color: #0f172a; width: 100%; max-width: 580px; border-radius: 14px; box-shadow: 0 24px 48px rgba(0,0,0,0.3); overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; }",
      ".unibag-hw-mhead { background: #0f172a; color: #fff; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; }",
      ".unibag-hw-mbody { padding: 16px 18px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }",
      ".unibag-hw-sec { border: 1px solid #e2e8f0; border-radius: 10px; padding: 13px 14px; background: #f8fafc; }",
      ".unibag-hw-sec h4 { margin: 0 0 6px 0; font-size: 13.5px; color: #0f172a; display: flex; align-items: center; justify-content: space-between; gap: 8px; }",
      ".unibag-hw-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 8px; }",
      ".unibag-hw-select, .unibag-hw-input { height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; font-size: 12.5px; background: #fff; color: #0f172a; }"
    ].join("\n");
    document.head.appendChild(style);
  }

  function ensureToastContainer() {
    injectHardwareStyles();
    if (!uiRefs.toastWrap && document.body) {
      var wrap = document.createElement("div");
      wrap.id = "unibag-hw-toasts";
      document.body.appendChild(wrap);
      uiRefs.toastWrap = wrap;
    }
    return uiRefs.toastWrap;
  }

  function showToast(message, kind, durationMs) {
    var wrap = ensureToastContainer();
    if (!wrap) return;
    var item = document.createElement("div");
    item.className = "unibag-hw-toast " + (kind || "info");
    item.textContent = message;
    wrap.appendChild(item);
    setTimeout(function () {
      if (item.parentNode) item.parentNode.removeChild(item);
    }, durationMs || 4000);
  }

  function showInteractivePrintFallbackToast(job, fallbackUrl, reason) {
    var wrap = ensureToastContainer();
    if (!wrap) return;
    var item = document.createElement("div");
    item.className = "unibag-hw-toast err";

    var title = document.createElement("div");
    title.style.fontWeight = "800";
    title.textContent = "🖨️ Etiqueta lista para imprimir" + (job && job.label ? (" (" + job.label + ")") : "");
    item.appendChild(title);

    var desc = document.createElement("div");
    desc.style.fontSize = "11.5px";
    desc.style.color = "#cbd5e1";
    desc.style.marginTop = "3px";
    desc.textContent = reason || "Conecta la impresora térmica o abre la vista de etiqueta.";
    item.appendChild(desc);

    var actions = document.createElement("div");
    actions.className = "unibag-hw-toast-actions";

    if (job && job.content) {
      var btnRetry = document.createElement("button");
      btnRetry.type = "button";
      btnRetry.className = "unibag-hw-btn primary";
      btnRetry.textContent = "⚡ Reintentar Térmica";
      btnRetry.onclick = async function () {
        btnRetry.disabled = true;
        btnRetry.textContent = "⏳ Enviando...";
        // El reintento es una decisión explícita del operador: se usa un id nuevo.
        job.job_id = newJobId();
        var r = await printJob(job, { silentFallback: true });
        if (r && r.ok) {
          if (item.parentNode) item.parentNode.removeChild(item);
        } else {
          btnRetry.disabled = false;
          btnRetry.textContent = "⚡ Reintentar Térmica";
          openHardwareModal();
        }
      };
      actions.appendChild(btnRetry);
    }

    var btnCfg = document.createElement("button");
    btnCfg.type = "button";
    btnCfg.className = "unibag-hw-btn";
    btnCfg.textContent = "🔌 Configurar Impresora";
    btnCfg.onclick = function () {
      openHardwareModal();
    };
    actions.appendChild(btnCfg);

    if (fallbackUrl) {
      var aOpen = document.createElement("a");
      aOpen.className = "unibag-hw-btn";
      aOpen.href = fallbackUrl;
      aOpen.target = "_blank";
      aOpen.rel = "noopener";
      aOpen.textContent = "👁️ Abrir Etiqueta";
      aOpen.onclick = function () {
        setTimeout(function () {
          if (item.parentNode) item.parentNode.removeChild(item);
        }, 300);
      };
      actions.appendChild(aOpen);
    }

    var btnClose = document.createElement("button");
    btnClose.type = "button";
    btnClose.className = "unibag-hw-btn";
    btnClose.textContent = "✕";
    btnClose.onclick = function () {
      if (item.parentNode) item.parentNode.removeChild(item);
    };
    actions.appendChild(btnClose);

    item.appendChild(actions);
    wrap.appendChild(item);
  }

  function showPrintFallbackToast(labelUrl, codeLabel) {
    showInteractivePrintFallbackToast(
      codeLabel ? { label: codeLabel, content: "" } : null,
      labelUrl,
      "Haz clic en Abrir Etiqueta o vincula tu impresora térmica local."
    );
  }

  function pageHasHardwareControls() {
    if (INITIAL_QUEUE.length > 0) return true;
    var path = String(window.location.pathname || "");
    if (
      /\/(?:receive|no-weight-labels|print-labels|test-label|label|box-label|sealing-waste|inks|roll-generation|cut)/.test(path) ||
      /\/work-orders\/\d+\/start/.test(path)
    ) {
      return true;
    }
    if (
      document.getElementById("scale_toggle") ||
      document.getElementById("sealing_scale_read") ||
      document.querySelector('a[href*="server_print=1"], button[id*="scale"], [data-scale-target]')
    ) {
      return true;
    }
    return false;
  }

  function ensureHardwarePill() {
    injectHardwareStyles();
    if (uiRefs.pill || !document.body) return;
    var pill = document.createElement("button");
    pill.type = "button";
    pill.id = "unibag-hw-pill";
    pill.onclick = function () {
      openHardwareModal();
    };
    document.body.appendChild(pill);
    uiRefs.pill = pill;
    updateHardwareUi();
  }

  function updateHardwareUi() {
    if (uiRefs.pill) {
      var activePage = pageHasHardwareControls();
      var scaleOk = scaleState.connected || scaleState.bridgeReachable === true;
      var printOk = printerState.bridgeOnline === true || !!printerState.usbDevice || !!printerState.serialPrinterPort;

      if (!activePage && !scaleOk && !printOk) {
        uiRefs.pill.className = "hw-compact";
        uiRefs.pill.innerHTML = "<span>🔌 Hardware</span>";
        uiRefs.pill.title = "Configurar Balanza USB e Impresora Térmica";
      } else {
        uiRefs.pill.className = "";
        var sColor = scaleState.connected ? "#10b981" : (scaleState.bridgeReachable ? "#38bdf8" : "#94a3b8");
        var pColor = printOk ? "#10b981" : "#f59e0b";
        var sText = scaleState.connected
          ? ("⚖️ " + (scaleState.weightKg !== null ? scaleState.weightKg.toFixed(2) + " Kg" : "USB"))
          : "⚖️ Balanza";
        var pText = "🖨️ Impresora";
        uiRefs.pill.innerHTML =
          '<span class="unibag-hw-dot" style="background:' + sColor + '"></span>' +
          "<span>" + sText + "</span>" +
          '<span style="color:#475569">|</span>' +
          '<span class="unibag-hw-dot" style="background:' + pColor + '"></span>' +
          "<span>" + pText + "</span>";
      }
    }

    refreshModalContents();
  }

  function openHardwareModal() {
    injectHardwareStyles();
    if (!uiRefs.modalBackdrop && document.body) {
      var backdrop = document.createElement("div");
      backdrop.id = "unibag-hw-modal-backdrop";
      backdrop.onclick = function (ev) {
        if (ev.target === backdrop) {
          backdrop.style.display = "none";
        }
      };

      var modal = document.createElement("div");
      modal.id = "unibag-hw-modal";
      modal.innerHTML =
        '<div class="unibag-hw-mhead">' +
        '  <div style="font-weight:800;font-size:15px">🔌 Centro de Conexión Directa · Balanzas e Impresoras</div>' +
        '  <button type="button" class="unibag-hw-btn" id="unibag-hw-close">✕ Cerrar</button>' +
        "</div>" +
        '<div class="unibag-hw-mbody" id="unibag-hw-mbody"></div>';

      backdrop.appendChild(modal);
      document.body.appendChild(backdrop);
      uiRefs.modalBackdrop = backdrop;

      var closeBtn = document.getElementById("unibag-hw-close");
      if (closeBtn) {
        closeBtn.onclick = function () {
          backdrop.style.display = "none";
        };
      }
    }

    if (uiRefs.modalBackdrop) {
      uiRefs.modalBackdrop.style.display = "flex";
      if (printerState.bridgeOnline === true) {
        probeLocalPrintBridge(true);
      }
      refreshModalContents();
    }
  }

  function refreshModalContents() {
    if (!uiRefs.modalBackdrop || uiRefs.modalBackdrop.style.display === "none") return;
    var body = document.getElementById("unibag-hw-mbody");
    if (!body) return;
    // No redibujar mientras se elige un valor: la lectura de la balanza cerraría la lista desplegable.
    var focused = document.activeElement;
    if (focused && focused.tagName === "SELECT" && body.contains(focused)) return;

    var origin = window.location.origin || "";
    var isSecure = window.isSecureContext;

    // Sección 1: Balanza Digital USB
    var scaleStatusHtml = "";
    if (scaleState.connected) {
      var liveW = scaleState.weightKg !== null ? (scaleState.weightKg.toFixed(3) + " Kg") : "Esperando lectura...";
      scaleStatusHtml = '<span style="color:#059669;font-weight:800">🟢 Conectada directo por navegador (' + liveW + ")</span>";
    } else if (scaleState.bridgeReachable) {
      scaleStatusHtml = '<span style="color:#0284c7;font-weight:800">🟢 Puente local 8765 detectado</span>';
    } else if (!scaleState.supported) {
      scaleStatusHtml = '<span style="color:#b45309;font-weight:800">⚠️ Requiere habilitar origen seguro en Chrome/Edge</span>';
    } else {
      scaleStatusHtml = '<span style="color:#64748b;font-weight:700">⚪ Sin vincular (1 clic para conectar cable USB)</span>';
    }

    var insecureHelperHtml = "";
    if (!isSecure && !scaleState.supported) {
      var flagUrl = "chrome://flags/#unsafely-treat-insecure-origin-as-secure";
      insecureHelperHtml =
        '<div style="margin-top:8px;padding:9px 10px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:11.5px;color:#92400e;line-height:1.45">' +
        "<strong>¿Accediendo por IP sin HTTPS (" + origin + ")?</strong><br>" +
        "Para usar la balanza USB sin instalar ningún programa externo, habilita HTTPS en el servidor o pega en Chrome/Edge:<br>" +
        '<code style="user-select:all;background:#fef3c7;padding:2px 4px;border-radius:4px">' + flagUrl + "</code> y agrega <code>" + origin + "</code>." +
        "</div>";
    }

    // Sección 2: Impresora Térmica
    var printOk = printerState.bridgeOnline === true || !!printerState.usbDevice || !!printerState.serialPrinterPort;
    var printStatusHtml = "";
    if (printerState.usbDevice) {
      printStatusHtml = '<span style="color:#059669;font-weight:800">🟢 Lista · USB (' + escapeHtml(printerState.usbDevice.productName || "USB") + ")</span>";
    } else if (printerState.serialPrinterPort) {
      printStatusHtml = '<span style="color:#059669;font-weight:800">🟢 Lista · Serial COM</span>';
    } else if (printerState.bridgeOnline && Array.isArray(printerState.thermalPrinters) && printerState.thermalPrinters.length === 0) {
      printStatusHtml = '<span style="color:#b45309;font-weight:800">🟠 Sin impresora térmica conectada a este computador</span>';
    } else if (printerState.bridgeOnline) {
      var localNames = Array.isArray(printerState.thermalPrinters) && printerState.thermalPrinters.length ? " (" + escapeHtml(printerState.thermalPrinters.join(", ")) + ")" : "";
      printStatusHtml = '<span style="color:#059669;font-weight:800">🟢 Lista · Conectada a este computador' + localNames + "</span>";
    } else {
      printStatusHtml = '<span style="color:#b45309;font-weight:800">🟠 Sin vincular</span>';
    }

    var savedDefaultPrinter = lsGet(LS_KEYS.printerDefault, "");
    var printerOptionsHtml = '<option value="">Automática (térmica conectada a este computador)</option>';
    for (var i = 0; i < printerState.installedPrinters.length; i++) {
      var pName = String(printerState.installedPrinters[i]);
      var sel = pName === savedDefaultPrinter ? " selected" : "";
      printerOptionsHtml += '<option value="' + escapeHtml(pName) + '"' + sel + ">" + escapeHtml(pName) + "</option>";
    }
    // Impresoras compartidas desde otro computador: solo si esta PC la elige a propósito (no se usan en Automática).
    for (var ni = 0; ni < printerState.networkPrintersIgnored.length; ni++) {
      var nName = String(printerState.networkPrintersIgnored[ni]);
      printerOptionsHtml += '<option value="' + escapeHtml(nName) + '"' + (nName === savedDefaultPrinter ? " selected" : "") + ">" + escapeHtml(nName) + " — compartida desde otro computador</option>";
    }

    // Ajuste de intensidad y velocidad según el lenguaje de la impresora de esta PC
    var tuneLang = normalizeLanguage(lsGet(LS_KEYS.printerType, "")) || normalizeLanguage(lsGet(LS_KEYS.lastPrintLanguage, "")) || normalizeLanguage(CFG.printLanguage) || "epl";
    var tuneRange = TUNING_RANGES[tuneLang];
    var tuneDefaults = (CFG.printDefaults && CFG.printDefaults[tuneLang]) || {};
    var serverLang = normalizeLanguage(CFG.printLanguage);
    var printerTypeNames = { zpl: "Zebra (ZPL)", epl: "HPRT (EPL)", tspl: "Barpos (TSPL)" };
    function tuningOptions(field) {
      var current = tuningValueFor(tuneLang, field);
      var base = tuneDefaults[field];
      var html = '<option value="">Predeterminada' + (base !== null && base !== undefined ? " (" + escapeHtml(base) + ")" : " de la impresora") + "</option>";
      for (var v = tuneRange[field][0]; v <= tuneRange[field][1]; v++) {
        html += '<option value="' + v + '"' + (current === v ? " selected" : "") + ">" + v + "</option>";
      }
      return html;
    }
    var densityHint = tuneRange.density[0] + " suave · " + tuneRange.density[1] + " fuerte";
    var speedHint = tuneRange.speed[0] + " lenta · " + tuneRange.speed[1] + " rápida";
    var tuningHtml =
      '<div class="unibag-hw-row">' +
      '  <label style="font-size:12px;font-weight:700;color:#334155">Impresora de esta PC' +
      '    <select class="unibag-hw-select" id="hw-sel-printer-type" style="display:block;margin-top:4px;min-width:150px">' +
      Object.keys(printerTypeNames).map(function (lang) {
        return '<option value="' + lang + '"' + (lang === tuneLang ? " selected" : "") + ">" + printerTypeNames[lang] + "</option>";
      }).join("") +
      "    </select>" +
      "  </label>" +
      '  <label style="font-size:12px;font-weight:700;color:#334155">Intensidad <span style="font-weight:500;color:#64748b">(' + densityHint + ")</span>" +
      '    <select class="unibag-hw-select" id="hw-sel-print-density" style="display:block;margin-top:4px;min-width:170px">' + tuningOptions("density") + "</select>" +
      "  </label>" +
      '  <label style="font-size:12px;font-weight:700;color:#334155">Velocidad <span style="font-weight:500;color:#64748b">(' + speedHint + ")</span>" +
      '    <select class="unibag-hw-select" id="hw-sel-print-speed" style="display:block;margin-top:4px;min-width:170px">' + tuningOptions("speed") + "</select>" +
      "  </label>" +
      "</div>" +
      (serverLang && serverLang !== tuneLang
        ? '<div style="margin-top:6px;font-size:11.5px;color:#b45309;font-weight:700">⚠️ Esta página se cargó con etiquetas en ' + serverLang.toUpperCase() + ": recarga la página (F5) para que se armen en " + tuneLang.toUpperCase() + " para la impresora de esta PC.</div>"
        : "");

    body.innerHTML =
      '<div class="unibag-hw-sec">' +
      "  <h4><span>⚖️ Balanza Digital USB / RS-232 (Sin Sistema Externo)</span>" + scaleStatusHtml + "</h4>" +
      '  <div style="font-size:12px;color:#475569">Lee el peso directo del cable USB en Chrome/Edge (LP-7516, Toledo, CAS). Una vez vinculada se reconecta sola en todas las pantallas.</div>' +
      (scaleState.error ? '<div style="margin-top:6px;font-size:11.5px;color:#b91c1c;font-weight:700">⚠️ ' + escapeHtml(scaleState.error) + "</div>" : "") +
      insecureHelperHtml +
      '  <div class="unibag-hw-row">' +
      '    <button type="button" class="unibag-hw-btn primary" id="hw-btn-scale-connect"' + (!scaleState.supported ? " disabled" : "") + ">" +
      (scaleState.connected ? "🔄 Cambiar Puerto USB" : "🔌 Vincular Balanza USB") +
      "</button>" +
      (scaleState.connected ? '<button type="button" class="unibag-hw-btn" id="hw-btn-scale-test">📡 Probar Lectura</button>' : "") +
      (scaleState.connected ? '<button type="button" class="unibag-hw-btn" id="hw-btn-scale-disc">Desconectar</button>' : "") +
      '    <label style="font-size:11.5px;color:#475569;margin-left:auto">Velocidad:' +
      '      <select class="unibag-hw-select" id="hw-sel-scale-baud">' +
      [9600, 2400, 4800, 19200, 38400, 115200]
        .map(function (b) {
          return '<option value="' + b + '"' + (scaleState.baudRate === b ? " selected" : "") + ">" + b + " bps</option>";
        })
        .join("") +
      "      </select>" +
      "    </label>" +
      "  </div>" +
      (scaleState.rawFrame ? '<div style="margin-top:6px;font-size:11px;color:#64748b;font-family:monospace">Última trama: ' + escapeHtml(scaleState.rawFrame) + "</div>" : "") +
      "</div>" +
      '<div class="unibag-hw-sec">' +
      "  <h4><span>🖨️ Impresoras Térmicas (HPRT EPL · Barpos TSPL · Zebra ZPL)</span>" + printStatusHtml + "</h4>" +
      '  <div style="font-size:12px;color:#475569">Funciona en Local, Red LAN y VPS sin abrir diálogos rotos del navegador.</div>' +
      (printerState.lastError ? '<div style="margin-top:6px;font-size:11.5px;color:#b91c1c;font-weight:700">⚠️ ' + escapeHtml(printerState.lastError) + "</div>" : "") +
      (printerState.bridgeOnline
        ? '<div class="unibag-hw-row">' +
          '  <label style="font-size:12px;font-weight:700;color:#334155">Impresora Windows en esta PC:</label>' +
          '  <select class="unibag-hw-select" id="hw-sel-win-printer" style="flex:1;min-width:200px">' +
          printerOptionsHtml +
          "  </select>" +
          "</div>" +
          (printerState.networkPrintersIgnored.length
            ? '<div style="margin-top:6px;font-size:11.5px;color:#475569">Este computador' + (printerState.bridgeComputer ? " (" + escapeHtml(printerState.bridgeComputer) + ")" : "") +
              " usa en Automática solo sus propias impresoras. Para imprimir en una compartida desde otro computador, elígela en la lista: " + escapeHtml(printerState.networkPrintersIgnored.join(", ")) + ".</div>"
            : "")
        : '<div style="margin-top:8px;padding:8px 10px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;font-size:11.5px;color:#334155">' +
          "💡 Para imprimir sin instalar nada: pulsa <b>Vincular USB Directo</b> (impresora por cable USB) o <b>Impresora Serial (COM)</b> y elige la impresora. Se hace una sola vez por PC; el navegador la recuerda en todas las pantallas." +
          "</div>") +
      tuningHtml +
      '  <div class="unibag-hw-row">' +
      (printerState.webUsbSupported
        ? '<button type="button" class="unibag-hw-btn" id="hw-btn-usb-printer">🔌 Vincular USB Directo (WebUSB)</button>'
        : "") +
      (scaleState.supported
        ? '<button type="button" class="unibag-hw-btn" id="hw-btn-ser-printer">🔌 Impresora Serial (COM)</button>'
        : "") +
      '    <button type="button" class="unibag-hw-btn primary" id="hw-btn-test-print" style="margin-left:auto">🧪 Imprimir Etiqueta Prueba 10×7</button>' +
      "  </div>" +
      "</div>";

    var btnScaleConn = document.getElementById("hw-btn-scale-connect");
    if (btnScaleConn) {
      btnScaleConn.onclick = function () {
        connectScaleInteractive();
      };
    }
    var btnScaleTest = document.getElementById("hw-btn-scale-test");
    if (btnScaleTest) {
      btnScaleTest.onclick = async function () {
        var r = await readScaleWeightUnified();
        if (r && r.ok) {
          showToast("⚖️ Peso leído: " + Number(r.weight_kg).toFixed(3) + " Kg", "ok", 3500);
        } else {
          showToast("⚠️ " + ((r && r.error) || "Sin respuesta de la balanza."), "err", 5000);
        }
        refreshModalContents();
      };
    }
    var btnScaleDisc = document.getElementById("hw-btn-scale-disc");
    if (btnScaleDisc) {
      btnScaleDisc.onclick = function () {
        disconnectScalePort();
      };
    }
    var selBaud = document.getElementById("hw-sel-scale-baud");
    if (selBaud) {
      selBaud.onchange = async function () {
        var newBaud = parseInt(selBaud.value, 10) || 9600;
        scaleState.baudRate = newBaud;
        lsSet(LS_KEYS.scaleBaud, newBaud);
        if (scaleState.port) {
          var p = scaleState.port;
          await disconnectScalePort();
          await openScalePort(p, newBaud);
        }
      };
    }
    var selWinPrinter = document.getElementById("hw-sel-win-printer");
    if (selWinPrinter) {
      selWinPrinter.onchange = function () {
        lsSet(LS_KEYS.printerDefault, selWinPrinter.value);
        showToast("🖨️ Impresora local fijada: " + (selWinPrinter.value || "Automática"), "ok", 2800);
      };
    }
    var selPrinterType = document.getElementById("hw-sel-printer-type");
    if (selPrinterType) {
      selPrinterType.onchange = function () {
        lsSet(LS_KEYS.printerType, selPrinterType.value);
        syncClientPrinterLanguageCookie();
        showToast("🖨️ Las etiquetas de esta PC se armarán para " + selPrinterType.options[selPrinterType.selectedIndex].text + ".", "ok", 3500);
        selPrinterType.blur();
        refreshModalContents();
      };
    }
    var selDensity = document.getElementById("hw-sel-print-density");
    if (selDensity) {
      selDensity.onchange = function () {
        savePrintTuning(tuneLang, "density", selDensity.value);
        showToast("🖨️ Intensidad guardada en esta PC. Imprime una prueba para revisarla.", "ok", 2800);
      };
    }
    var selSpeed = document.getElementById("hw-sel-print-speed");
    if (selSpeed) {
      selSpeed.onchange = function () {
        savePrintTuning(tuneLang, "speed", selSpeed.value);
        showToast("🖨️ Velocidad guardada en esta PC. Imprime una prueba para revisarla.", "ok", 2800);
      };
    }
    var btnUsbPrinter = document.getElementById("hw-btn-usb-printer");
    if (btnUsbPrinter) {
      btnUsbPrinter.onclick = function () {
        connectWebUsbPrinterInteractive();
      };
    }
    var btnSerPrinter = document.getElementById("hw-btn-ser-printer");
    if (btnSerPrinter) {
      btnSerPrinter.onclick = function () {
        connectSerialPrinterInteractive();
      };
    }
    var btnTestPrint = document.getElementById("hw-btn-test-print");
    if (btnTestPrint) {
      btnTestPrint.onclick = async function () {
        btnTestPrint.disabled = true;
        btnTestPrint.textContent = "⏳ Enviando...";
        try {
          var res = await ORIGINAL_FETCH("/reception/test-label?raw_job=1", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
          });
          var data = await res.json();
          if (data && data.ok && data.print_job) {
            await printJob(data.print_job, { fallbackUrl: "/reception/test-label" });
          } else {
            showToast("⚠️ No se pudo generar la etiqueta de prueba.", "err", 4000);
          }
        } catch (err) {
          showToast("⚠️ Error de red al solicitar etiqueta de prueba.", "err", 4000);
        } finally {
          btnTestPrint.disabled = false;
          btnTestPrint.textContent = "🧪 Imprimir Etiqueta Prueba 10×7";
        }
      };
    }
  }

  // Reconexión persistente de la balanza: el navegador cierra los puertos al salir de una página y la siguiente
  // puede intentar abrirlos mientras el puerto aún está liberándose. Se reintenta con espera creciente.
  var reconnectRunning = false;
  async function reconnectRememberedDevices() {
    if (reconnectRunning) return;
    reconnectRunning = true;
    var delays = [0, 350, 700, 1200, 1800, 2600, 4000, 6000];
    try {
      for (var a = 0; a < delays.length; a++) {
        if (delays[a] > 0) await sleep(delays[a]);
        var wantScale = !!lsGet(LS_KEYS.scaleVidPid, "") || (!!scaleState.supported && !lsGet(LS_KEYS.serialPrinterVidPid, ""));
        if (wantScale && scaleState.supported && !scaleState.connected && !scaleState.connecting) {
          await autoReconnectScale();
        }
        if (a === 0) await refreshPrinterLink();
        var scaleOk = !wantScale || scaleState.connected || !scaleState.supported;
        if (scaleOk) break;
      }
    } catch (_) {
    } finally {
      reconnectRunning = false;
    }
  }

  // Si se perdió la conexión (cable, cambio de pestaña) se recupera al volver a la ventana.
  window.addEventListener("focus", function () { reconnectRememberedDevices(); });
  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") reconnectRememberedDevices();
  });
  // Al volver con el botón Atrás (caché de páginas) los puertos estaban cerrados: se reabren.
  window.addEventListener("pageshow", function (ev) {
    if (ev && ev.persisted) reconnectRememberedDevices();
  });

  // Inicialización al cargar el DOM
  function initHardwareEngine() {
    ensureHardwarePill();
    setupGlobalPrintLinkInterceptor();
    reconnectRememberedDevices();

    // El servicio local solo se consulta en una PC donde ya funcionó (no se pide instalarlo).
    if (printerState.bridgeOnline === true) {
      probeLocalPrintBridge(true);
    }

    if (INITIAL_QUEUE.length > 0) {
      processInitialPrintQueue();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHardwareEngine);
  } else {
    initHardwareEngine();
  }

  // API pública global para todos los módulos del ERP
  window.UnibagHardware = {
    connectScale: connectScaleInteractive,
    disconnectScale: disconnectScalePort,
    readWeight: readScaleWeightUnified,
    readStableWeight: readStableWeight,
    printJob: printJob,
    connectWebUsbPrinter: connectWebUsbPrinterInteractive,
    connectSerialPrinter: connectSerialPrinterInteractive,
    probePrintBridge: probeLocalPrintBridge,
    applyPrintTuning: applyPrintTuning,
    openModal: openHardwareModal,
    showPrintFallbackToast: showPrintFallbackToast
  };
})();

