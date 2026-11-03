<?php
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>
        *{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#f6f7f9;color:#111;overflow-x:hidden}
        main{padding:18px 24px 32px; width:100%; max-width:100%; margin:0; box-sizing:border-box}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
        .grid{display:grid;grid-template-columns: 320px 1fr;gap:14px}
        .trace-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}
        .trace-stack{display:grid;grid-template-columns:1fr;gap:14px}
        .kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
        .kpi-card{background:#fff;border:1px solid #d0d5dd;border-radius:10px;padding:12px}
        .kpi-card-link{display:block;text-decoration:none;color:inherit}
        .kpi-card-link:hover{border-color:#00A9A6}
        .kpi-card-link.active{border-color:#00A9A6;box-shadow:0 0 0 2px rgba(0,169,166,.18) inset}
        .kpi-label{font-size:12px;color:#667085;margin-bottom:6px}
        .kpi-value{font-size:24px;font-weight:800;line-height:1}
        .kpi-sub{font-size:12px;color:#475467;margin-top:6px}
        .dashboard-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:14px}
        .dashboard-shell{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,.8fr);gap:14px;align-items:start}
        .dashboard-main{min-width:0}
        .dashboard-side{min-width:0}
        .dashboard-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start}
        .dashboard-head-left{min-width:0}
        .dashboard-title{font-size:18px;font-weight:900;line-height:1.15;text-transform:uppercase}
        .dashboard-head-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .dashboard-filters{margin-top:12px}
        .dashboard-filters-grid{display:grid;grid-template-columns:220px 160px 180px 180px 140px;gap:10px;align-items:end}
        .dashboard-field{min-width:0}
        .dashboard-field .text{height:40px;padding:10px}
        .dashboard-field-button{display:flex}
        .dashboard-field-button .btn{min-width:140px}
        .dashboard-kpis{margin-top:14px;grid-template-columns:repeat(3,minmax(0,1fr))}
        .modal-backdrop{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.45);z-index:80}
        .modal-backdrop.open{display:flex}
        .modal-card{width:min(1100px,calc(100vw - 24px));max-height:calc(100vh - 24px);background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 20px 50px rgba(15,23,42,.25);display:flex;flex-direction:column;overflow:hidden}
        .modal-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid #eef2f7;background:#fbfcfd}
        .modal-title{font-size:14px;font-weight:900;color:#0f172a}
        .modal-body{padding:14px;overflow:auto}
        .dashboard-subtitle{font-size:14px;font-weight:900;margin:0 0 10px;color:#0f172a}
        .erp-prod-table{width:100%;border-collapse:collapse}
        .erp-prod-table th,.erp-prod-table td{border-bottom:1px solid #e5e7eb;padding:8px;font-size:12px}
        .erp-prod-table th{background:#0f172a;color:#fff;font-size:11px;text-align:left}
        .erp-prod-table-wrap{overflow:auto;-webkit-overflow-scrolling:touch}
        .erp-prod-empty{padding:10px 12px;border:1px dashed #d0d5dd;border-radius:10px;color:#667085;background:#fbfcfd}
        .erp-prod-code{font-weight:800}
        .erp-prod-muted{font-size:12px;color:#667085;margin-top:6px}
        .dashboard-alert{border:1px solid #d0d5dd;border-radius:10px;padding:10px 12px;background:#fff}
        .dashboard-alert.warning{border-color:#f7b955;background:#fff9eb}
        .dashboard-alert.info{border-color:#84c5ff;background:#f5faff}
        .dashboard-alert.success{border-color:#86d7a0;background:#f5fff8}
        .bar-track{height:8px;background:#edf2f7;border-radius:999px;overflow:hidden}
        .bar-fill{height:100%;background:#00A9A6;border-radius:999px}
        .trace-roll-card{border:1px solid #dbe4ea;border-radius:10px;padding:10px 12px;background:#fbfdff}
        .trace-roll-header{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px}
        .trace-roll-title{font-size:15px;font-weight:800;line-height:1.2}
        .trace-roll-subtitle{margin-top:2px}
        .trace-roll-link{font-size:12px;font-weight:700;color:#0f766e;text-decoration:none;white-space:nowrap}
        .trace-roll-link:hover{text-decoration:underline}
        .trace-roll-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}
        .trace-roll-stat{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px}
        .trace-roll-stat .value{font-size:13px;font-weight:700;line-height:1.3}
        .trace-roll-events{margin-top:8px;border-top:1px solid #e5e7eb;padding-top:8px}
        .trace-roll-events .item{padding:7px 0;border-bottom:1px solid #eef2f7}
        .trace-roll-events .item:last-child{border-bottom:none}
        .trace-roll-event-top{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
        .trace-roll-event-line{margin-top:2px;font-size:13px;color:#374151;line-height:1.35}
        .row{display:flex;gap:10px;flex-wrap:wrap}
        .row.nowrap{flex-wrap:nowrap}
        .bonus-filter-form{display:grid;gap:10px;align-items:end;margin:0}
        .bonus-filter-form--flexo{grid-template-columns:170px minmax(260px,1fr) 170px 140px}
        .bonus-filter-form--seri{grid-template-columns:170px minmax(260px,1fr) 140px}
        .bonus-filter-form--cys{grid-template-columns:170px minmax(260px,1fr) 140px}
        .bonus-field{min-width:0}
        .bonus-field-period{grid-column:1 / -1}
        .bonus-field-button{display:flex}
        .bonus-field-button .btn{min-width:140px}
        label{display:block;font-size:12px;color:#374151;margin-bottom:4px}
        input,select,textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font:inherit}
        input:disabled,select:disabled{opacity:1;background:#f3f4f6;color:#111}
        .btn{display:inline-flex;align-items:center;justify-content:center;background:#0aa;color:#fff;border:none;border-radius:8px;padding:10px 12px;font-weight:600;text-decoration:none;cursor:pointer;text-align:center}
        .btn.secondary{background:#374151}
        .panel{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px;overflow:hidden}
        .ot-stage-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .ot-stage-card{background:#fff;border:1px solid #d0d5dd;border-radius:10px;padding:10px 12px}
        .ot-stage-card.current{border-color:#259492;background:#eef8f8}
        .ot-stage-card.done{border-color:#b7e4d7;background:#f6fffb}
        .ot-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
        .ot-meta{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px}
        .ot-meta .value{font-size:14px;font-weight:800;line-height:1.3}
        .ot-tasks{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
        .ot-task{background:#fcfcfd;border:1px solid #e4e7ec;border-radius:8px;padding:10px}
        .ot-request-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,0.8fr);gap:12px}
        .fold{border:1px solid #e5e7eb;border-radius:10px;background:#fff;margin-top:10px;overflow:hidden}
        .fold summary{cursor:pointer;list-style:none;padding:11px 12px;font-weight:800;background:#fbfcfd}
        .fold summary::-webkit-details-marker{display:none}
        .fold .fold-body{padding:12px;border-top:1px solid #eef2f7}
        .table-compact th,.table-compact td{padding:7px 8px;font-size:12px}
        @media (max-width: 1024px){
          main{padding:12px; max-width:100% !important; margin:0}
          .grid{grid-template-columns:1fr}
          .row.nowrap{flex-wrap:wrap}
          .bonus-filter-form{grid-template-columns:1fr !important}
          .trace-grid{grid-template-columns:1fr}
          .dashboard-grid{grid-template-columns:1fr}
          .dashboard-shell{grid-template-columns:1fr}
          .dashboard-filters-grid{grid-template-columns:repeat(auto-fit, minmax(180px, 1fr))}
          .dashboard-field-button .btn{width:100%}
          .kpi-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
          .ot-stage-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .ot-request-grid{grid-template-columns:1fr}
          .prod-shell{flex-direction:column}
          .topbar .inner,.subbar .inner{padding:8px 12px;gap:8px}
          .menu,.submenu{width:100%;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;padding-bottom:4px;gap:6px;scrollbar-width:none}
          .menu::-webkit-scrollbar,.submenu::-webkit-scrollbar{display:none}
          .menu a,.subitem{white-space:nowrap;flex:none}
          .top-right{width:100%;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;justify-content:flex-start}
          .top-right .pill,.top-right select{white-space:nowrap;flex:none}
          .card,.panel,.legacy-sheet-card,.legacy-form-card,.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%}
          .card > table,.panel > table,.legacy-sheet-card > table,.legacy-form-card > table{min-width:600px}
        }
        @media (max-width: 640px){
          main{padding:8px; max-width:100% !important}
          .card,.panel,.legacy-sheet-card,.legacy-form-card,.kpi-card{padding:10px; border-radius:8px}
          .row{gap:8px}
          .row > *{flex:1 1 100% !important;min-width:0 !important;max-width:100% !important}
          .btn,.legacy-primary-btn,.legacy-placeholder-btn{width:100%;max-width:100%;justify-content:center}
          .trace-roll-meta,.ot-meta-grid,.ot-tasks{grid-template-columns:1fr}
          .kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
          .kpi-card{padding:8px}
          .kpi-value{font-size:20px}
          .ot-stage-grid{grid-template-columns:1fr}
          .dashboard-filters-grid{grid-template-columns:1fr}
          .topbar .inner,.subbar .inner{padding:6px 10px}
          .menu a{font-size:12.5px;padding:6px 8px}
          .subitem{font-size:12px}
          .prod-sidebar{width:min(300px,86vw)}
          .prod-topbar{align-items:flex-start}
          .prod-topbar-title{width:100%}
          .prod-topbar-right{width:100%;justify-content:flex-start}
          .prod-topbar-brand{font-size:20px}
          .prod-main{padding:10px}
          .legacy-actionbar{align-items:stretch}
          .legacy-sheet-card,.legacy-form-card,.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
          .card > table,.panel > table,.legacy-sheet-card > table,.legacy-form-card > table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
          .legacy-sheet-table{min-width:480px}
          .legacy-label-cell,.legacy-value-cell{width:auto}
          .trace-table{min-width:480px}
          .trace-roll-link{white-space:normal}
          th,td{padding:6px;vertical-align:top}
          iframe{max-width:100%}
          #label_preview{height:300px}
          .modal-backdrop{padding:8px}
          .modal-card{width:calc(100vw - 16px);max-height:calc(100vh - 16px);border-radius:10px}
          input,select,textarea{font-size:14px;padding:8px}
        }
        @media (max-width: 420px){
          .kpi-grid{grid-template-columns:1fr}
        }
        table{width:100%;border-collapse:collapse}
        th,td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;font-size:13px}
        .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%}
        .trace-table{min-width:640px}
        .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:10px}
        .muted{color:#6b7280;font-size:12px}

        /* Reception Module Professional Styling */
        .rec-shell{display:flex;flex-direction:column;gap:14px}
        .rec-nav-bar{display:flex;gap:6px;padding:4px;background:#e2e8f0;border-radius:10px;margin-bottom:14px;overflow-x:auto;-webkit-overflow-scrolling:touch}
        .rec-nav-tab{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;font-size:13px;font-weight:600;color:#475569;text-decoration:none;border-radius:7px;transition:all .15s ease;white-space:nowrap}
        .rec-nav-tab:hover{background:#f1f5f9;color:#0f172a}
        .rec-nav-tab.active{background:#00A9A6;color:#fff;box-shadow:0 1px 3px rgba(0,169,166,.3)}
        .rec-header-card{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,.03)}
        .rec-title-group h1{font-size:18px;font-weight:800;color:#0f172a;margin:0 0 3px}
        .rec-title-group p{font-size:13px;color:#64748b;margin:0}
        .rec-counter-pill{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border:1px solid #cbd5e1;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;color:#334155}
        .rec-filter-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;box-shadow:0 1px 3px rgba(0,0,0,.03)}
        .rec-filter-form{display:flex;flex-direction:column;gap:12px}
        .rec-filter-grid-nat{display:grid;grid-template-columns:160px 160px 1fr 200px auto;gap:12px;align-items:end}
        .rec-filter-grid-imp{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end}
        .rec-field{display:flex;flex-direction:column;gap:4px;min-width:0}
        .rec-field label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:#475569;display:flex;align-items:center;gap:5px}
        .rec-field input,.rec-field select{height:38px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;color:#0f172a;background:#f8fafc;transition:border-color .15s ease,box-shadow .15s ease}
        .rec-field input:focus,.rec-field select:focus{background:#fff;border-color:#00A9A6;box-shadow:0 0 0 3px rgba(0,169,166,.15);outline:none}
        .rec-actions{display:flex;gap:8px;align-items:center}
        .rec-btn-filter{height:38px;padding:0 16px;background:#00A9A6;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .15s ease}
        .rec-btn-filter:hover{background:#008f8d}
        .rec-btn-clear{height:38px;padding:0 12px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px}
        .rec-btn-clear:hover{background:#e2e8f0;color:#0f172a}
        .rec-btn-excel{height:38px;padding:0 14px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .15s ease;white-space:nowrap}
        .rec-btn-excel:hover{background:#047857;color:#fff}
        .rec-table-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.03)}
        .rec-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
        .rec-table{width:100%;border-collapse:collapse;font-size:13px}
        .rec-table thead th{background:#f8fafc;color:#475569;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:11px 14px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
        .rec-table tbody td{padding:12px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;color:#1e293b}
        .rec-table tbody tr:hover td{background:#f8fafc}
        .rec-table tbody tr:last-child td{border-bottom:none}
        .rec-code-link{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px;font-weight:700;color:#00A9A6;text-decoration:none;padding:2px 7px;border-radius:5px;background:#f0fdfa;border:1px solid #ccfbf1;display:inline-block}
        .rec-code-link:hover{background:#00A9A6;color:#fff;border-color:#00A9A6}
        .rec-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap}
        .rec-badge-open{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
        .rec-badge-partial{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
        .rec-badge-complete{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .rec-dot{width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block}
        .rec-prog-container{display:flex;align-items:center;gap:8px}
        .rec-prog-bar{width:65px;height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden}
        .rec-prog-fill{height:100%;background:#00A9A6;border-radius:999px}
        .rec-prog-fill.rec-fill-complete{background:#10b981}
        .rec-prog-label{font-size:12px;font-weight:600;color:#475569}
        .rec-prog-label.is-done{color:#065f46;font-weight:700}
        .rec-empty{text-align:center;padding:42px 18px;color:#64748b}
        .rec-empty-icon{font-size:28px;margin-bottom:8px}
        .rec-detail-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
        .rec-meta-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:9px 12px}
        .rec-meta-box .rec-meta-label{font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:2px}
        .rec-meta-box .rec-meta-val{font-size:14px;font-weight:700;color:#0f172a}
        .rec-pagination-bar{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:#fff;border-top:1px solid #e2e8f0;font-size:13px;color:#475569;flex-wrap:wrap;gap:12px}
        .rec-pagination-info{font-size:13px;color:#64748b}
        .rec-pagination-info strong{color:#0f172a;font-weight:700}
        .rec-pagination-nav{display:flex;align-items:center;gap:4px}
        .rec-page-btn{height:32px;min-width:32px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#334155;text-decoration:none;font-weight:600;font-size:13px;transition:all .15s ease}
        .rec-page-btn:hover:not(.disabled):not(.active){background:#f1f5f9;border-color:#94a3b8;color:#0f172a}
        .rec-page-btn.active{background:#00A9A6;border-color:#00A9A6;color:#fff}
        .rec-page-btn.disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
        .rec-page-dots{padding:0 6px;color:#94a3b8;font-weight:700}
        @media (max-width:960px){
          .rec-filter-grid-nat{grid-template-columns:1fr 1fr}
          .rec-filter-grid-imp{grid-template-columns:1fr 1fr}
        }
        @media (max-width:640px){
          .rec-filter-grid-nat{grid-template-columns:1fr}
          .rec-filter-grid-imp{grid-template-columns:1fr}
          .rec-actions{flex-direction:column;width:100%}
          .rec-btn-filter,.rec-btn-clear{width:100%;justify-content:center}
        }

        .topbar{background:#009f9f;color:#fff}
        .topbar .inner{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 14px}
        .menu{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .menu a{color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:8px 10px;border-radius:6px}
        .menu a:hover{background:rgba(255,255,255,.12)}
        .menu a.active{background:#f3f4f6;color:#111}
        .top-right{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:4px 8px}
        .top-right select{width:auto;background:rgba(15,23,42,.24);color:#fff;-webkit-text-fill-color:#fff;opacity:1;border:1px solid rgba(255,255,255,.38);padding:6px 10px;border-radius:6px;font-size:12px;font-weight:700}
        select option,select optgroup{background:#fff;color:#0f172a;-webkit-text-fill-color:#0f172a}
        .top-right .pill{display:flex;align-items:center;gap:8px;color:#fff;text-decoration:none;font-weight:600;font-size:13px;padding:6px 10px;border-radius:6px;background:rgba(0,0,0,.12)}
        .top-right .pill:hover{background:rgba(0,0,0,.18)}
        .subbar{background:#f1f5f9;border-bottom:1px solid #e5e7eb}
        .subbar .inner{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:8px 14px}
        .submenu{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
        .subitem{display:inline-flex;gap:6px;align-items:center;color:#334155;text-decoration:none;font-weight:700;font-size:13px;padding:6px 12px;border-radius:8px;border:1px solid transparent;transition:all .15s ease}
        .subitem.active{background:#00A9A6;color:#fff;border-color:#008f8d;box-shadow:0 2px 6px rgba(0,169,166,.24)}
        .subitem:hover{background:#f1f5f9;color:#0f172a;border-color:#cbd5e1;text-decoration:none}
        .prod-shell{display:flex;min-height:100vh;background:#dfe5ea;position:relative}
        .prod-sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.35);border:none;padding:0;margin:0;opacity:0;pointer-events:none;transition:opacity .2s ease;z-index:39}
        .prod-sidebar{width:228px;background:#157f7d;color:#fff;display:flex;flex-direction:column;box-shadow:2px 0 6px rgba(15,23,42,.12);position:fixed;top:0;left:0;bottom:0;z-index:40;transform:none;transition:transform .22s ease}
        .prod-brand{padding:10px 10px 8px;background:#0b9694}
        .prod-brand-box{background:#f4f7f7;border-radius:4px;padding:7px 10px;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.04)}
        .prod-brand-mark{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:2px;background:#18a7a4;color:#fff;font-size:18px;font-weight:800;line-height:1}
        .prod-brand-word{font-size:21px;font-weight:800;line-height:1;color:#8a9196;letter-spacing:.01em}
        .prod-nav-title{padding:8px 14px;font-size:12px;font-weight:700;color:#d7f4f3;background:rgba(0,0,0,.12)}
        .prod-nav-folder{margin-bottom:1px;background:#166764}
        .prod-nav-folder-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#147c79;font-size:14px;font-weight:700;color:#fff}
        .prod-nav-folder.active .prod-nav-folder-head{background:#0f8f8d}
        .prod-nav-folder-label{display:flex;align-items:center;gap:8px}
        .prod-nav-folder-icon{display:inline-block;position:relative;flex:none}
        .prod-nav-folder-icon.machine{width:16px;height:12px;border:2px solid #fff;border-radius:2px}
        .prod-nav-folder-icon.machine::after{content:"";position:absolute;left:5px;right:5px;bottom:-4px;height:2px;background:#fff}
        .prod-nav-folder-icon.orders{width:14px;height:14px;border:2px solid #fff;border-radius:2px}
        .prod-nav-folder-icon.orders::before{content:"";position:absolute;left:2px;right:2px;top:3px;height:2px;background:#fff;box-shadow:0 4px 0 #fff}
        .prod-nav-folder-icon.messages{width:16px;height:11px;border:2px solid #fff;border-radius:2px}
        .prod-nav-folder-icon.messages::before{content:"";position:absolute;left:1px;right:1px;top:1px;height:2px;background:#fff;transform:skewY(-24deg)}
        .prod-nav-folder-caret{font-size:15px;line-height:1;color:#fff}
        .prod-nav-folder-body{padding:5px 0 8px;background:#166764}
        .prod-nav-sublink,.prod-nav-static{display:flex;align-items:center;gap:8px;padding:9px 14px 9px 16px;color:#b9c7c7;text-decoration:none;font-size:12px;font-weight:600}
        .prod-nav-sublink::before,.prod-nav-static::before{content:"";width:15px;height:15px;border-radius:999px;background:#b9b1b1;flex:none}
        .prod-nav-sublink:hover{background:rgba(255,255,255,.04);color:#fff}
        .prod-nav-sublink.active{background:rgba(255,255,255,.03);color:#fff;font-weight:500}
        .prod-nav-sublink.active::before{background:#d8d1d1}
        .prod-nav-static{opacity:.92;cursor:default}
        .prod-main-wrap{flex:1;display:flex;flex-direction:column;min-width:0;margin-left:228px;width:calc(100% - 228px)}
        .prod-topbar{background:#08a8a6;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 2px 6px rgba(15,23,42,.08)}
        .prod-topbar-title{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:800}
        .prod-topbar-brand{background:#fff;color:#0aa;padding:7px 10px;border-radius:4px;font-size:22px;font-weight:800;line-height:1}
        .prod-toggle-btn{display:none;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(255,255,255,.3);background:rgba(0,0,0,.12);color:#fff;border-radius:8px;cursor:pointer;font-size:16px;font-weight:800;line-height:1}
        .prod-toggle-btn:hover{background:rgba(0,0,0,.2)}
        .prod-topbar-right{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .prod-user-pill{background:rgba(0,0,0,.12);padding:8px 10px;border-radius:6px;font-size:13px;font-weight:700}
        .prod-user-pill.logout{color:#fff;text-decoration:none}
        .prod-main{padding:18px;max-width:none}
        .legacy-screen-title{font-size:18px;font-weight:800;color:#4b5563;margin-bottom:10px}
        .legacy-breadcrumb{background:#fff;border:1px solid #d9dde3;border-radius:6px;padding:10px 12px;color:#6b7280;font-size:13px;margin-bottom:8px}
        .legacy-actionbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#f3f4f6;border:1px solid #d8dadd;border-radius:6px;padding:8px 10px;margin-bottom:10px}
        .legacy-primary-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;background:#10b156;color:#fff;text-decoration:none;font-weight:800;border-radius:2px;min-width:200px}
        .legacy-primary-btn:hover{filter:brightness(.96)}
        .legacy-sheet-card{background:#efefef;border:1px solid #d4d4d4;border-radius:6px;overflow:hidden}
        .legacy-sheet-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;background:#145e5b;color:#fff;cursor:pointer;user-select:none;border-bottom:1px solid #0f4745}
        .legacy-sheet-head-title{font-size:14px;font-weight:800;line-height:1.3}
        .legacy-sheet-head-arrow{display:inline-block;width:10px;height:10px;border-right:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(45deg);flex:none;margin-right:4px;transition:transform .15s ease}
        .legacy-sheet-card.is-collapsed .legacy-sheet-head-arrow{transform:rotate(-45deg)}
        .legacy-sheet-body{padding:10px}
        .legacy-sheet-card.is-collapsed .legacy-sheet-body{display:none}
        .legacy-sheet-table{width:100%;border-collapse:collapse;background:#fff}
        .legacy-sheet-table th{background:#145e5b;color:#fff;font-size:14px;font-weight:800;text-align:center;padding:6px;border:1px solid #145e5b}
        .legacy-sheet-card.has-legacy-collapse .legacy-sheet-title-row{display:none}
        .legacy-sheet-table td{border:1px solid #d6d6d6;padding:6px 8px;font-size:13px}
        .legacy-label-cell{background:#efefef;color:#5f6368;font-weight:700;width:15%}
        .legacy-value-cell{background:#fff;color:#374151;width:35%}
        .legacy-placeholder-btn{display:inline-flex;align-items:center;justify-content:center;min-width:180px;padding:2px 10px;background:#a3a3a3;color:#fff;border-radius:1px;font-size:12px;font-weight:700}
        .legacy-form-card{background:#efefef;border:1px solid #d4d4d4;border-radius:6px;padding:10px}
        .legacy-form-card .fold{border:1px solid #d6d6d6;background:#fff}
        .legacy-form-card .fold summary{background:#e5e7eb;border-bottom:1px solid #d6d6d6;padding:8px 10px;font-size:13px}
        .legacy-form-card label{display:block;font-size:12px;font-weight:700;color:#5f6368;margin-bottom:4px}
        .legacy-form-card input,.legacy-form-card select,.legacy-form-card textarea{border:1px solid #cfd4dc;border-radius:2px;background:#fff;min-height:34px}
        .legacy-form-card .btn,.legacy-form-card .btn.secondary{border-radius:2px}
        .legacy-note{background:#f7f7f7;border:1px solid #d6d6d6;padding:8px 10px;color:#6b7280;font-size:12px}
        .legacy-anilox-card .legacy-sheet-table th{background:#e9ecef;color:#145e5b;border-color:#d5dadd}
        .legacy-anilox-unit-cell{width:9%;text-align:center;font-weight:800}
        .legacy-anilox-input,.legacy-anilox-select{width:100%;min-height:34px;border:1px solid #cfd4dc;border-radius:3px;padding:6px 8px;background:#fff}
        .legacy-anilox-actions{white-space:nowrap;text-align:center;width:100px}
        .anilox-move-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;margin:0 2px;border:1px solid #cfd4dc;border-radius:4px;background:#f5f7fa;color:#0f766e;font-weight:800;cursor:pointer}
        .anilox-move-btn:hover:not(:disabled){background:#e8f3f2}
        .anilox-move-btn:disabled{opacity:.45;cursor:not-allowed}
        .legacy-anilox-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .legacy-anilox-help{font-size:12px;color:#5f6368}
        .legacy-setup-actions{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:0;border-radius:4px;overflow:hidden;margin:0 0 12px}
        .legacy-setup-action{display:flex;align-items:center;justify-content:center;min-height:42px;padding:10px 12px;color:#fff;font-size:13px;font-weight:800;text-decoration:none;border-right:1px solid rgba(255,255,255,.22)}
        .legacy-setup-action:last-child{border-right:0}
        .legacy-setup-action.request{background:#0b7d5c}
        .legacy-setup-action.materials{background:#4256a5}
        .legacy-setup-action.production{background:#12805c}
        .legacy-setup-action.maintenance{background:#d94841}
        .legacy-setup-action.pause{background:#a9a9a9}
        .legacy-setup-action.consumption{background:#d8a103}
        .legacy-setup-action.active{box-shadow:inset 0 -3px 0 rgba(255,255,255,.35)}
        .legacy-setup-event-table th{text-align:center}
        .legacy-setup-event-table td{vertical-align:middle}
        .legacy-setup-finish-btn{background:#d8a103;border-color:#c89402;color:#fff}
        .legacy-setup-finish-btn:hover{background:#c89402}
        .autocontrol-panel{margin-bottom:12px;border:1px solid #d6d6d6;border-radius:4px;overflow:hidden;background:#fff}
        .autocontrol-panel-header{background:#ebe600;color:#111;font-size:17px;font-weight:900;letter-spacing:.02em;text-align:center;padding:8px 12px}
        .autocontrol-table{width:100%;border-collapse:collapse;table-layout:fixed}
        .autocontrol-table td{width:25%;padding:8px 12px;border-top:1px solid #d9dde3;border-right:1px solid #e5e7eb;background:#fff;vertical-align:top;box-sizing:border-box}
        .autocontrol-table td:last-child{border-right:0}
        .autocontrol-checkline{display:grid;grid-template-columns:16px minmax(0,1fr);align-items:start;column-gap:8px;margin:0;font-size:12.5px;font-weight:600;color:#334155;line-height:1.3}
        .autocontrol-checkline input{margin:2px 0 0 0;width:14px;height:14px}
        .autocontrol-checkline span{display:block;word-break:break-word}
        .approval-actions{display:grid;grid-template-columns:1fr;gap:8px;margin:12px 0}
        .approval-action-btn{display:flex;align-items:center;justify-content:center;min-height:26px;padding:6px 12px;color:#fff;text-decoration:none;font-size:13px;font-weight:800;border-radius:2px}
        .approval-action-btn.area{background:#f15b40}
        .approval-action-btn.supervisor{background:#5ea7cf}
        .approval-action-btn.hidden{display:none}
        .approval-panel-header{position:relative;display:flex;align-items:center;min-height:28px;background:#8dcc4b;color:#111;padding:4px 12px;border-radius:4px;font-size:14px;font-weight:900;letter-spacing:.01em;overflow:hidden}
        .approval-panel-header .title{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);text-align:center;line-height:1;pointer-events:none;max-width:calc(100% - 190px);white-space:nowrap}
        .approval-header-actions{position:relative;z-index:1;display:flex;align-items:center;flex:0 0 auto}
        .approval-mark-all{display:inline-flex;align-items:center;gap:6px;padding:0;border:0;background:transparent;font-size:12px;font-weight:800;color:#111;cursor:pointer;white-space:nowrap}
        .approval-mark-all input{margin:0;width:13px;height:13px;accent-color:#fff}
        .approval-mark-all span{display:block;line-height:1}
        .approval-validation{display:none;grid-template-columns:minmax(180px,220px) minmax(180px,220px) minmax(220px,1fr);gap:8px;align-items:stretch;margin-top:12px}
        .approval-validation.visible{display:grid}
        .approval-validation input{width:100%;min-height:36px;border:1px solid #cbd5e1;border-radius:4px;padding:8px 10px;background:#eef3f9;font-size:14px;font-weight:700;color:#0f172a}
        .approval-validation input::placeholder{color:#475569;opacity:1}
        .approval-validation .btn{min-height:36px;width:100%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:800}
        .approval-validation-status{margin-top:12px}
        .approval-role-note{background:#fff;border:1px solid #d9dde3;border-radius:6px;padding:10px 12px;color:#4b5563;font-size:13px;margin-bottom:12px}
        .finish-production-card{background:#f3f3f3;padding:8px}
        .finish-production-card form{margin:0}
        .finish-form-row{display:grid;align-items:center;border-bottom:1px solid #dddddd;min-height:32px}
        .finish-form-row:last-child{border-bottom:0}
        .finish-form-row-inline{grid-template-columns:102px minmax(0,1fr) auto minmax(0,1.2fr);column-gap:6px}
        .finish-form-row-production{grid-template-columns:102px 140px minmax(90px,1fr);column-gap:6px}
        .finish-form-row-comments{grid-template-columns:102px minmax(0,1fr);column-gap:6px;padding:4px 0}
        .finish-label,.finish-merma-title{font-size:11px;font-weight:700;color:#5f6368;padding:4px 6px;line-height:1.2;min-width:0;overflow-wrap:anywhere}
        .finish-static-value{font-size:12px;color:#57748b;padding:4px 6px;line-height:1.2;min-width:0}
        .finish-inline-input input,.finish-short-input input,.finish-wide-input input,.finish-comments-wrap textarea,.finish-merma-weight input,.finish-merma-comment input,.finish-merma-unit select{width:100%;border:1px solid #cfd4dc;border-radius:3px;background:#fff;min-height:26px;padding:3px 7px;font-size:12px}
        .finish-comments-wrap textarea{min-height:38px;resize:vertical}
        .finish-inline-input{max-width:124px}
        .finish-short-input{max-width:68px}
        .finish-wide-input{max-width:300px}
        .finish-inline-suffix{font-size:11px;color:#666;line-height:1;min-width:0;white-space:nowrap}
        .finish-form-row-production .finish-inline-input{max-width:none;min-width:0}
        .finish-form-row-production .finish-inline-input input{min-width:0}
        .finish-form-row-production .finish-label{white-space:nowrap}
        .finish-button-inline{max-width:118px}
        .finish-grey-btn{background:#b5b5b5;border-color:#aaaaaa;color:#fff}
        .finish-grey-btn:hover{background:#a7a7a7}
        .finish-red-btn{background:#d94141;border-color:#cf3a3a;color:#fff}
        .finish-red-btn:hover{background:#c83737}
        .finish-yellow-btn{background:#d8a103;border-color:#c89402;color:#fff}
        .finish-yellow-btn:hover{background:#c89402}
        .finish-merma-block{margin-top:6px}
        .finish-merma-row{grid-template-columns:82px 190px 64px 74px 86px minmax(0,1fr);column-gap:6px}
        .finish-merma-detail,.finish-merma-comment-label{font-size:11px;color:#5f6368;padding:4px 6px;line-height:1.2}
        .finish-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
        .finish-actions-left,.finish-actions-right{display:flex;gap:4px;align-items:center}
        .finish-actions .btn{min-width:170px;min-height:30px;border-radius:0;padding:6px 10px;font-size:12px}
        @media (max-width: 980px){
            .prod-sidebar-backdrop{display:block}
            .prod-sidebar{transform:translateX(-100%)}
            .prod-shell.menu-open .prod-sidebar{transform:translateX(0)}
            .prod-shell.menu-open .prod-sidebar-backdrop{opacity:1;pointer-events:auto}
            .prod-main-wrap{margin-left:0;width:100%}
            .prod-toggle-btn{display:inline-flex}
            .legacy-setup-actions{grid-template-columns:1fr 1fr}
            .autocontrol-table td{padding:8px 10px}
            .approval-panel-header{align-items:center;min-height:28px;padding:4px 10px}
            .approval-panel-header .title{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);max-width:calc(100% - 170px);text-align:center;pointer-events:none;white-space:nowrap;font-size:13px}
            .approval-mark-all{font-size:11px;gap:5px}
            .approval-mark-all input{width:12px;height:12px}
            .approval-validation{grid-template-columns:minmax(140px,1fr) minmax(140px,1fr) minmax(180px,1fr)}
            .finish-production-card{padding:6px}
            .finish-form-row-inline{grid-template-columns:88px minmax(90px,120px) 46px minmax(150px,1fr);column-gap:4px;row-gap:0}
            .finish-form-row-production{grid-template-columns:88px 120px minmax(88px,1fr);column-gap:4px}
            .finish-form-row-comments{grid-template-columns:92px minmax(0,1fr)}
            .finish-button-inline,.finish-inline-input,.finish-short-input,.finish-wide-input{max-width:none}
            .finish-merma-row{grid-template-columns:58px 120px 58px 64px 76px minmax(150px,1fr);column-gap:4px}
            .finish-actions{flex-direction:column;align-items:stretch}
            .finish-actions-left,.finish-actions-right{width:100%}
            .finish-actions-left .btn,.finish-actions-right .btn{flex:1;min-width:0}
        }
        @media (max-width: 640px){
            .finish-form-row-production{grid-template-columns:92px minmax(0,1fr)}
            .finish-form-row-production .finish-inline-suffix{white-space:normal}
            .finish-form-row-inline{grid-template-columns:92px minmax(0,1fr);row-gap:4px}
            .finish-merma-row{grid-template-columns:92px minmax(0,1fr)}
        }
    </style><link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><link rel="stylesheet" href="/assets/css/unibag-ui.css?v=20261031"><link rel="stylesheet" href="/assets/css/unibag-responsive.css?v=20261103"></head><body>';
    $currentPath = (string)($GLOBALS['path'] ?? '/');
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    $requestedArea = detectRequestedArea($currentPath);
    $areaPermissions = sessionAreaPermissions();
    $displayArea = ($currentArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions))
        ? 'ERP'
        : (userCanAccessArea($requestedArea, $areaPermissions) ? $requestedArea : $currentArea);

    $link = function (string $href, string $label, bool $isActive) : string {
        return '<a href="' . h($href) . '"' . ($isActive ? ' class="active"' : '') . '>' . h($label) . '</a>';
    };

    $areaDefinitions = erpAreaDefinitions();
    $appModeLabel = (string)($areaDefinitions[$displayArea]['label'] ?? $_SESSION['erp_area_label'] ?? $_SESSION['app_mode_label'] ?? 'ERP');
    $companyName = (string)($_SESSION['company_name'] ?? 'UNIBAG CHILE');
    $displayName = (string)($_SESSION['auth_display_name'] ?? $_SESSION['operator_name'] ?? 'Operador');
    $isProductionArea = str_starts_with($currentPath, '/production')
        || str_starts_with($currentPath, '/waste')
        || ($currentArea === 'PRODUCTION' && isProductionPath($currentPath));

    if ($isProductionArea) {
        renderProductionShell($body, $currentPath, $displayName, $companyName);
        echo '<script src="/assets/js/unibag-ui.js?v=20261031"></script></body></html>';
        exit;
    }

    $activeModule = 'dashboard';
    if ($displayArea === 'ERP' || $displayArea === 'RECEPTION') {
        if (
            $currentPath === '/'
            || $currentPath === '/reports/production-dashboard'
            || $currentPath === '/reports/graphics'
            || $currentPath === '/reports/resumen-diario'
            || $currentPath === '/dashboard/resumen'
            || $currentPath === '/reports/daily-summary'
        ) {
            $activeModule = 'dashboard';
        } elseif (
            str_starts_with($currentPath, '/reception')
            || str_starts_with($currentPath, '/purchase-orders')
            || str_starts_with($currentPath, '/import-containers')
        ) {
            $activeModule = 'reception';
        } elseif (
            str_starts_with($currentPath, '/reports/inventory')
            || str_starts_with($currentPath, '/stock/inventory-counts')
        ) {
            $activeModule = 'inventory';
        } elseif (
            str_starts_with($currentPath, '/warehouses')
            || str_starts_with($currentPath, '/stock')
            || str_starts_with($currentPath, '/rolls')
            || str_starts_with($currentPath, '/boxes')
            || str_starts_with($currentPath, '/pallets')
            || str_starts_with($currentPath, '/maquila')
            || str_starts_with($currentPath, '/cliches')
        ) {
            $activeModule = 'warehouses';
        } elseif (str_starts_with($currentPath, '/reports')) {
            $activeModule = 'reports';
        } elseif (str_starts_with($currentPath, '/bonificaciones')) {
            $activeModule = 'bonificaciones';
        } elseif (
            str_starts_with($currentPath, '/trazabilidad')
            || str_starts_with($currentPath, '/traceability')
        ) {
            $activeModule = 'traceability';
        } elseif (
            str_starts_with($currentPath, '/work-orders')
            || str_starts_with($currentPath, '/chemicals')
            || str_starts_with($currentPath, '/cut')
        ) {
            $activeModule = 'production';
        }
    } elseif ($displayArea === 'SCALE') {
        $activeModule = 'scale';
    } else {
        $activeModule = 'production';
    }

    echo '<div class="topbar"><div class="inner">';
    echo '<button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="nav-menu"><span class="bars" aria-hidden="true">☰</span> Menú</button>';
    echo '<nav class="menu" id="nav-menu">';
    if ($displayArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions)) {
        echo $link('/', 'Dashboard', $activeModule === 'dashboard');
        echo $link('/reception', 'Recepción', $activeModule === 'reception');
        echo $link('/reports/operator-waste', 'Informes', $activeModule === 'reports');
        echo $link('/bonificaciones', 'Bonificaciones', $activeModule === 'bonificaciones');
        echo $link('/stock', 'Bodega', $activeModule === 'warehouses');
        echo $link('/reports/inventory', 'Inventario', $activeModule === 'inventory');
        echo $link('/trazabilidad', 'Trazabilidad', $activeModule === 'traceability');
    } elseif ($displayArea === 'RECEPTION') {
        echo $link('/purchase-orders?status=active&supplier_type=NATIONAL', 'Recepción', $activeModule === 'reception');
        echo $link('/stock', 'Bodega', $activeModule === 'warehouses');
        echo $link('/stock/inventory-counts', 'Inventario', $activeModule === 'inventory');
    } elseif ($displayArea === 'SCALE') {
        echo $link('/scale/sealing-waste', 'Balanza', str_starts_with($currentPath, '/scale'));
    } else {
        echo $link('/production/machines', 'Producción', str_starts_with($currentPath, '/production') || str_starts_with($currentPath, '/waste') || str_starts_with($currentPath, '/work-orders'));
    }
    echo '</nav>';
    $allowedAreas = [];
    foreach (erpAreaDefinitions() as $aId => $aDef) {
        if (userCanAccessArea($aId, $areaPermissions)) {
            $allowedAreas[$aId] = $aDef['label'];
        }
    }
    echo '<div class="top-right">';
    if (count($allowedAreas) > 1) {
        echo '<select aria-label="Cambiar de Área" onchange="if(this.value) location.href=\'/switch-area?area=\'+this.value" style="cursor:pointer" title="Cambiar área de trabajo">';
        foreach ($allowedAreas as $aId => $aLabel) {
            $sel = ($aId === $displayArea) ? ' selected' : '';
            echo '<option value="' . h($aId) . '"' . $sel . '>' . h($aLabel) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<select aria-label="Sistema" disabled><option>' . h($appModeLabel) . '</option></select>';
    }
    echo '<a class="pill" href="/"><span>' . h($companyName) . '</span></a>';
    echo '<a class="pill" href="#"><span>' . h($displayName) . '</span></a>';
    echo '<a class="pill" href="/logout">Salir</a>';
    echo '</div>';
    echo '</div></div>';

    echo '<div class="subbar"><div class="inner">';
    echo '<div class="submenu">';
    if ($activeModule === 'dashboard') {
        $isPanel = $currentPath === '/' || $currentPath === '/reports/production-dashboard';
        $isDailySummary = $currentPath === '/reports/resumen-diario' || $currentPath === '/dashboard/resumen' || $currentPath === '/reports/daily-summary';
        $isGraphics = $currentPath === '/reports/graphics';
        $isLunch = str_starts_with($currentPath, '/reports/colaciones') || str_starts_with($currentPath, '/reports/lunch-breaks');
        echo '<a class="subitem' . ($isPanel ? ' active' : '') . '" href="/"><span>Panel Producción & Mermas</span></a>';
        echo '<a class="subitem' . ($isDailySummary ? ' active' : '') . '" href="/reports/resumen-diario"><span>📋 Resumen Diario</span></a>';
        echo '<a class="subitem' . ($isGraphics ? ' active' : '') . '" href="/reports/graphics"><span>Gráficos</span></a>';
        // "Control Colaciones" vive solo en el menú Informes (antes estaba repetido aquí).
    } elseif ($activeModule === 'reception') {
        $poStatus = (string)($_GET['status'] ?? 'active');
        $suppType = strtoupper((string)($_GET['supplier_type'] ?? 'NATIONAL'));
        $isImport = str_starts_with($currentPath, '/import-containers') || ($suppType === 'IMPORT' && $poStatus === 'active');
        $isComplete = str_starts_with($currentPath, '/purchase-orders') && $poStatus === 'complete';
        $isNatActive = !$isImport && !$isComplete;

        echo '<a class="subitem' . ($isNatActive ? ' active' : '') . '" href="/purchase-orders?status=active&supplier_type=NATIONAL"><span>OC Nacionales Activas</span></a>';
        echo '<a class="subitem' . ($isImport ? ' active' : '') . '" href="/import-containers?status=active"><span>OC Importación (Contenedores)</span></a>';
        echo '<a class="subitem' . ($isComplete ? ' active' : '') . '" href="/purchase-orders?status=complete&supplier_type=NATIONAL"><span>Recepciones Finalizadas</span></a>';
    } elseif ($activeModule === 'reports') {
        $isLunch = str_starts_with($currentPath, '/reports/colaciones') || str_starts_with($currentPath, '/reports/lunch-breaks');
        echo '<a class="subitem' . ($currentPath === '/reports/operator-waste' ? ' active' : '') . '" href="/reports/operator-waste"><span>Merma por Operador</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/despachos' ? ' active' : '') . '" href="/reports/despachos"><span>Despachos</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/machine-production' ? ' active' : '') . '" href="/reports/machine-production"><span>Producción Máquinas</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/machine-events' ? ' active' : '') . '" href="/reports/machine-events"><span>Eventos de Máquina</span></a>';
        echo '<a class="subitem' . ($isLunch ? ' active' : '') . '" href="/reports/colaciones"><span>🍱 Control Colaciones</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/nivel-servicio' ? ' active' : '') . '" href="/reports/nivel-servicio"><span>Nivel de Servicio</span></a>';
        echo '<a class="subitem' . (str_starts_with($currentPath, '/reports/reception-audit') ? ' active' : '') . '" href="/reports/reception-audit"><span>Auditoría Recepción</span></a>';
        echo '<a class="subitem' . (str_starts_with($currentPath, '/reports/monthly-presentation') ? ' active' : '') . '" href="/reports/monthly-presentation"><span>Informe Mensual PPT</span></a>';
    } elseif ($activeModule === 'bonificaciones') {
        $bonusView = (string)($_GET['view'] ?? 'bonoflexo');
        echo '<a class="subitem' . ($bonusView === 'bonoflexo' ? ' active' : '') . '" href="/bonificaciones?view=bonoflexo"><span>Bonoflexo</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonoseri' ? ' active' : '') . '" href="/bonificaciones?view=bonoseri"><span>Bono seri</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonocys' ? ' active' : '') . '" href="/bonificaciones?view=bonocys"><span>Bono CYS</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonopulp' ? ' active' : '') . '" href="/bonificaciones?view=bonopulp"><span>Bono pulp</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonoayudante' ? ' active' : '') . '" href="/bonificaciones?view=bonoayudante"><span>Bono ayudante</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonomantencion' ? ' active' : '') . '" href="/bonificaciones?view=bonomantencion"><span>Bono mantención</span></a>';
        echo '<a class="subitem' . ($bonusView === 'configuracion' ? ' active' : '') . '" href="/bonificaciones?view=configuracion"><span>Configuración</span></a>';
    } elseif ($activeModule === 'warehouses') {
        $isMatRequests = str_starts_with($currentPath, '/stock/material-requests') || str_starts_with($currentPath, '/mobile/picking');
        $isTrzEntry = str_starts_with($currentPath, '/stock/traceability-entry') || str_starts_with($currentPath, '/stock/ingreso-trazable');
        $isTrzTree = str_starts_with($currentPath, '/stock/traceability') && !$isTrzEntry;
        $isTransfers = str_starts_with($currentPath, '/stock/transfers') || str_starts_with($currentPath, '/stock/traspasos');
        $isStock = str_starts_with($currentPath, '/stock') && !str_starts_with($currentPath, '/stock/inventory-counts') && !$isTrzEntry && !$isTrzTree && !$isTransfers && !$isMatRequests;
        $isWh = str_starts_with($currentPath, '/warehouses');
        echo '<a class="subitem' . ($isStock ? ' active' : '') . '" href="/stock"><span>Consulta de stock</span></a>';
        echo '<a class="subitem' . ($isMatRequests ? ' active' : '') . '" href="/stock/material-requests"><span>📋 Solicitudes MP (Bodega 3000)</span></a>';
        echo '<a class="subitem' . ($isTransfers ? ' active' : '') . '" href="/stock/transfers"><span>🔄 Traspasos a Producción</span></a>';
        echo '<a class="subitem' . ($isTrzEntry ? ' active' : '') . '" href="/stock/traceability-entry"><span>📦 Ingreso con Trazabilidad</span></a>';
        echo '<a class="subitem' . ($isTrzTree ? ' active' : '') . '" href="/stock/traceability"><span>🌳 Árbol Trazabilidad</span></a>';
        echo '<a class="subitem' . ($isWh ? ' active' : '') . '" href="/warehouses"><span>Configuración de bodega</span></a>';
        // "Recepción de bobinas (OC)" vive solo en el módulo Recepción (antes estaba repetido aquí).
    } elseif ($activeModule === 'inventory') {
        $isInvReport = str_starts_with($currentPath, '/reports/inventory');
        $isInvCount = str_starts_with($currentPath, '/stock/inventory-counts');
        // Traspasos a Producción, Ingreso con Trazabilidad y Árbol de Trazabilidad viven solo en el menú Bodega
        // (antes estaban repetidos aquí). Inventario queda solo con informe y toma de inventario.
        if (userCanAccessArea('ERP', $areaPermissions)) {
            echo '<a class="subitem' . ($isInvReport ? ' active' : '') . '" href="/reports/inventory"><span>Informe de inventario</span></a>';
        }
        echo '<a class="subitem' . ($isInvCount ? ' active' : '') . '" href="/stock/inventory-counts"><span>Toma de inventario</span></a>';
    } elseif ($activeModule === 'traceability') {
        $trzStage = (string)($_GET['stage'] ?? 'all');
        $isDetalle = str_starts_with($currentPath, '/trazabilidad/detalle') || isset($_GET['id']);
        echo '<a class="subitem' . ($trzStage === 'all' && !$isDetalle ? ' active' : '') . '" href="/trazabilidad?stage=all"><span>Todas las OTs</span></a>';
        echo '<a class="subitem' . ($trzStage === 'clises_espera' ? ' active' : '') . '" href="/trazabilidad?stage=clises_espera"><span>⏳ Esperando Clisés</span></a>';
        echo '<a class="subitem' . ($trzStage === 'preparacion_espera' ? ' active' : '') . '" href="/trazabilidad?stage=preparacion_espera"><span>📐 Pre-Prensa</span></a>';
        echo '<a class="subitem' . ($trzStage === 'planificacion_espera' ? ' active' : '') . '" href="/trazabilidad?stage=planificacion_espera"><span>📅 Por Planificar</span></a>';
        echo '<a class="subitem' . ($trzStage === 'produccion' ? ' active' : '') . '" href="/trazabilidad?stage=produccion"><span>⚙️ En Producción</span></a>';
        echo '<a class="subitem' . ($trzStage === 'despacho_pendiente' ? ' active' : '') . '" href="/trazabilidad?stage=despacho_pendiente"><span>🚚 Pendiente Despacho</span></a>';
        echo '<a class="subitem' . ($trzStage === 'entregado' ? ' active' : '') . '" href="/trazabilidad?stage=entregado"><span>✅ Finalizadas</span></a>';
    } elseif ($activeModule === 'scale') {
        echo '<a class="subitem" href="/scale/sealing-waste"><span>Merma Selladora</span></a>';
    } elseif ($activeModule === 'production') {
        echo '<a class="subitem" href="/production/machines"><span>Asignación máquina</span></a>';
        echo '<a class="subitem" href="/production/work-orders/new"><span>Iniciar nueva OT</span></a>';
        echo '<a class="subitem" href="/production/work-orders/active"><span>En cursos</span></a>';
        echo '<a class="subitem" href="/production/work-orders/history"><span>Histórico OTs</span></a>';
        echo '<a class="subitem" href="/cut"><span>Proceso de corte</span></a>';
        echo '<a class="subitem" href="/chemicals/weighings"><span>Pesajes de tintas</span></a>';
    }
    echo '</div>';
    echo '</div></div>';
    echo '<main>' . $body . '</main>';
    echo '<script>(function(){var b=document.getElementById("nav-toggle");if(!b)return;b.addEventListener("click",function(){var o=document.body.classList.toggle("nav-open");b.setAttribute("aria-expanded",o?"true":"false");});})();</script>';
    echo '<script>
      (function () {
        function attachLegacyCollapsiblePanels(root) {
          if (!root || typeof root.querySelectorAll !== "function") return;
          var cards = root.querySelectorAll(".legacy-sheet-card");
          for (var i = 0; i < cards.length; i++) {
            (function (card) {
              if (!card || card.classList.contains("has-legacy-collapse")) return;
              var table = card.querySelector(":scope > table.legacy-sheet-table");
              if (!table) {
                var anyTable = card.querySelector("table.legacy-sheet-table");
                if (!anyTable) return;
                table = anyTable;
              }
              var thead = table.querySelector(":scope > thead");
              if (!thead) return;
              var firstRow = thead.querySelector(":scope > tr:first-child");
              if (!firstRow) return;
              var rowCells = firstRow.querySelectorAll(":scope > th, :scope > td");
              if (rowCells.length === 0) return;
              var titleCell = null;
              var titleText = "";
              if (rowCells.length === 1) {
                titleCell = rowCells[0];
              } else {
                var mergedCell = firstRow.querySelector(":scope > th[colspan], :scope > td[colspan]");
                if (mergedCell) {
                  var cs = parseInt(mergedCell.getAttribute("colspan") || "0", 10);
                  if (cs >= 2) {
                    titleCell = mergedCell;
                  }
                }
                if (!titleCell) {
                  return;
                }
              }
              if (!titleCell) return;
              if (titleCell.querySelector("table")) return;
              titleText = ((titleCell.innerText || titleCell.textContent || "")).replace(/\s+/g, " ").trim();
              if (!titleText) return;

              firstRow.classList.add("legacy-sheet-title-row");

              var head = document.createElement("div");
              head.className = "legacy-sheet-head";
              head.setAttribute("role", "button");
              head.setAttribute("tabindex", "0");
              var titleWrap = document.createElement("div");
              titleWrap.className = "legacy-sheet-head-title";
              titleWrap.textContent = titleText;
              var arrow = document.createElement("div");
              arrow.className = "legacy-sheet-head-arrow";
              head.appendChild(titleWrap);
              head.appendChild(arrow);

              var body = document.createElement("div");
              body.className = "legacy-sheet-body";
              while (card.firstChild) {
                body.appendChild(card.firstChild);
              }
              card.appendChild(head);
              card.appendChild(body);
              card.classList.add("has-legacy-collapse");

              function applyCollapsed(collapsed) {
                if (collapsed) {
                  card.classList.add("is-collapsed");
                } else {
                  card.classList.remove("is-collapsed");
                }
                if (typeof window.Chart !== "undefined") {
                  var charts = window.Chart.instances || [];
                  try {
                    Object.keys(charts).forEach(function (ck) {
                      var ch = charts[ck];
                      if (ch && typeof ch.resize === "function" && body.contains(ch.canvas)) {
                        ch.resize();
                      }
                    });
                  } catch (e) {}
                }
              }
              head.addEventListener("click", function (e) {
                if (e.target.closest && e.target.closest("a, button, input, select, textarea, label")) return;
                applyCollapsed(!card.classList.contains("is-collapsed"));
              });
              head.addEventListener("keydown", function (e) {
                if (e.key === "Enter" || e.key === " ") {
                  e.preventDefault();
                  applyCollapsed(!card.classList.contains("is-collapsed"));
                }
              });
            })(cards[i]);
          }
        }

        function attachAllCollapsibles() {
          attachLegacyCollapsiblePanels(document.body || document.documentElement || document);
          if (typeof window.attachCollapsibleControls === "function") {
            try { window.attachCollapsibleControls(document); } catch (e) {}
          }
        }
        if (document.readyState === "loading") {
          document.addEventListener("DOMContentLoaded", attachAllCollapsibles);
        } else {
          attachAllCollapsibles();
        }
        window.attachLegacyCollapsiblePanels = attachLegacyCollapsiblePanels;
        window.attachAllCollapsibles = attachAllCollapsibles;
      })();
    </script>';
    echo '<script src="/assets/js/unibag-ui.js?v=20261031"></script></body></html>';

