<?php
    $companies = authCompanyDefinitions();
    $plants = authPlantDefinitions();
    // Excluir 'RECEPTION' de la pantalla de inicio de sesión ya que se gestiona directamente en el ERP
    $areas = array_filter(erpAreaDefinitions(), static fn($a) => (string)$a['id'] !== 'RECEPTION');
    $companyId = isset($state['user_company_id']) ? (int)$state['user_company_id'] : 20010;
    $userLogin = (string)($state['user_login'] ?? '');
    $erpArea = normalizeErpArea((string)($state['erp_area'] ?? 'ERP'));
    if ($erpArea === 'RECEPTION') {
        $erpArea = 'ERP';
    }

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">';
    echo '<title>Ingreso ERP</title>';
    echo '<style>
        *{box-sizing:border-box}
        body{margin:0;background:#f2f2f2;font-family:Arial,sans-serif;color:#333}
        .login-shell{min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:28px 14px}
        .login-box{width:min(560px,100%);border:1px solid #a6a6a6;background:#efefef;box-shadow:0 1px 0 #fff inset}
        .login-header{background:#d7d7d7;border-bottom:1px solid #b8b8b8;padding:4px 10px;font-size:13px;font-weight:700}
        .login-body{position:relative;padding:30px 60px 34px}
        .login-logo{display:flex;justify-content:center;align-items:center;gap:12px;margin:6px 0 24px}
        .logo-mark{font-size:26px;font-weight:700;color:#00A9A6}
        .logo-text{font-size:22px;font-weight:700;color:#666}
        .logo-sub{font-size:13px;color:#00A9A6;text-align:center;margin-top:2px}
        .mode-row{display:flex;gap:12px;flex-wrap:wrap;margin:0 0 18px}
        .mode-card{width:79px;height:60px;border:1px solid #d4d4d4;background:#F9F9F9;box-shadow:0 0 8px 5px rgba(0,0,0,.12);cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:12px;color:#666;line-height:1.1}
        .mode-card .mode-icon{font-size:13px;font-weight:700;margin-bottom:6px}
        .mode-card.active{background:#00A9A6;color:#fff;border-color:#00A9A6}
        .area-card{width:118px}
        .form-grid{display:grid;grid-template-columns:105px 1fr;gap:10px 14px;align-items:center}
        .form-grid label{font-size:12px}
        .text{width:100%;height:24px;border:1px solid #b9b9b9;background:#fff;padding:2px 7px;font:12px Arial,sans-serif}
        .text:focus{outline:2px solid #2f2f2f;border-color:#2f2f2f;background:#eef6fb}
        .plant-row{display:none}
        .plant-row.visible{display:contents}
        .status{margin-bottom:12px;padding:8px 10px;border:1px solid #d6b0b0;background:#fff4f4;color:#8b1f1f;font-size:12px}
        .hint{margin-top:14px;font-size:12px;color:#666}
        .submit-row{margin-top:18px}
        .submit-btn{width:100%;height:30px;border:1px solid #8fb48f;background:#b9ddb9;color:#111;font:700 13px Arial,sans-serif;cursor:pointer}
        .submit-btn:hover{filter:brightness(.98)}
        @media (max-width: 640px){
          .login-box{width:100%}
          .login-body{padding:24px 18px 28px}
          .form-grid{grid-template-columns:1fr}
        }
      </style></head><body>';
    echo '<div class="login-shell"><div class="login-box">';
    echo '<div class="login-header">Unibag ERP</div>';
    echo '<div class="login-body">';
    echo '<div class="login-logo"><div><div class="logo-mark">Unibag</div><div class="logo-sub">Bolsas con vida</div></div></div>';
    if ($error !== null && $error !== '') {
        echo '<div class="status">' . h($error) . '</div>';
    }
    echo '<form method="post" action="/login" id="login-form">';
    echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    echo '<input type="hidden" name="appmode" id="menu_appmode" value="0">';
    echo '<input type="hidden" name="erp_area" id="erp_area" value="' . h($erpArea) . '">';
    echo '<div class="mode-row">';
    foreach ($areas as $area) {
        $isActiveArea = (string)$area['id'] === $erpArea;
        echo '<button type="button" class="mode-card area-card' . ($isActiveArea ? ' active' : '') . '" data-erp-area="' . h((string)$area['id']) . '"' . ($isActiveArea ? ' aria-current="true"' : '') . '>';
        echo '<span class="mode-icon">' . h(erpAreaShortLabel((string)$area['id'])) . '</span>';
        echo '<span>' . h((string)$area['label']) . '</span>';
        echo '</button>';
    }
    echo '</div>';
    echo '<div class="form-grid">';
    echo '<label for="user_login">Usuario</label>';
    echo '<input class="text" id="user_login" name="user_login" type="text" value="' . h($userLogin) . '" autocomplete="username">';
    echo '<label for="user_pass">Contraseña</label>';
    echo '<input class="text" id="user_pass" name="user_pass" type="password" autocomplete="current-password">';
    echo '<label for="user_company_id">Empresa</label>';
    echo '<select class="text" id="user_company_id" name="user_company_id">';
    foreach ($companies as $company) {
        $selected = $company['id'] === $companyId ? ' selected' : '';
        echo '<option value="' . (int)$company['id'] . '"' . $selected . '>' . h((string)$company['label']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="submit-row"><button class="submit-btn" type="submit">Acceder</button></div>';
    echo '</form>';
    echo '</div></div></div>';
    echo '<script>
      document.getElementById("user_login")?.focus();
      (function () {
        var areaInput = document.getElementById("erp_area");
        var areaButtons = document.querySelectorAll("[data-erp-area]");
        var appModeInput = document.getElementById("menu_appmode");
        if (!areaInput || !areaButtons.length) return;
        function inferAppMode(area) {
          if (area === "PRODUCTION") return "1";
          if (area === "SCALE") return "1";
          return "0";
        }
        if (appModeInput) {
          appModeInput.value = inferAppMode(areaInput.value || "ERP");
        }
        areaButtons.forEach(function (button) {
          button.addEventListener("click", function () {
            var area = button.getAttribute("data-erp-area") || "ERP";
            areaInput.value = area;
            if (appModeInput) {
              appModeInput.value = inferAppMode(area);
            }
            areaButtons.forEach(function (item) {
              item.classList.remove("active");
              item.removeAttribute("aria-current");
            });
            button.classList.add("active");
            button.setAttribute("aria-current", "true");
          });
        });
      })();
    </script>';
    echo '</body></html>';

