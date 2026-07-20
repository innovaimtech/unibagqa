<?php

declare(strict_types=1);

/**
 * @return array<int, string>
 */
function ensureErpProductionSchema(PDO $pdo): array
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS `user` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_login VARCHAR(120) NOT NULL,
            user_status TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_login (user_login)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS workers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wrk_uid BIGINT UNSIGNED NOT NULL,
            wrk_firstname VARCHAR(120) NOT NULL,
            wrk_lastname VARCHAR(120) NOT NULL DEFAULT \'\',
            wrk_status TINYINT NOT NULL DEFAULT 1,
            wrk_turno_turnoid BIGINT UNSIGNED NULL,
            wrk_turno_state TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_workers_uid (wrk_uid),
            KEY idx_workers_status (wrk_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS prod_header (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            prd_crtdat BIGINT NOT NULL DEFAULT 0,
            prd_crtusr BIGINT NOT NULL DEFAULT 0,
            prd_status INT NOT NULL DEFAULT 0,
            prd_desc VARCHAR(255) NOT NULL DEFAULT \'\',
            prd_plantaid BIGINT NOT NULL DEFAULT 0,
            prd_reqid VARCHAR(80) NOT NULL DEFAULT \'\',
            prd_upddat BIGINT NOT NULL DEFAULT 0,
            prd_updusr BIGINT NOT NULL DEFAULT 0,
            prd_number VARCHAR(80) NOT NULL DEFAULT \'\',
            PRIMARY KEY (id),
            KEY idx_prod_header_number (prd_number),
            KEY idx_prod_header_reqid (prd_reqid),
            KEY idx_prod_header_status (prd_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS prod_agenda (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ag_date BIGINT NOT NULL DEFAULT 0,
            ag_date_stamp BIGINT NOT NULL DEFAULT 0,
            ag_equipo_id BIGINT NOT NULL DEFAULT 0,
            ag_equipotype_id BIGINT NOT NULL DEFAULT 0,
            ag_amount DECIMAL(14,3) NOT NULL DEFAULT 0.000,
            ag_prdid BIGINT UNSIGNED NOT NULL,
            ag_reqid VARCHAR(80) NOT NULL DEFAULT \'\',
            ag_plantaid BIGINT NOT NULL DEFAULT 0,
            ag_crtdat BIGINT NOT NULL DEFAULT 0,
            ag_crtusr BIGINT NOT NULL DEFAULT 0,
            ag_status INT NOT NULL DEFAULT 0,
            ag_active TINYINT NOT NULL DEFAULT 0,
            ag_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_prod_agenda_prdid (ag_prdid),
            KEY idx_prod_agenda_reqid (ag_reqid),
            KEY idx_prod_agenda_machine (ag_equipo_id, ag_equipotype_id),
            KEY idx_prod_agenda_status (ag_status, ag_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS prod_worker_init (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            win_crtdat BIGINT NOT NULL DEFAULT 0,
            win_enddat BIGINT NOT NULL DEFAULT 0,
            win_wrkid BIGINT UNSIGNED NOT NULL,
            win_status INT NOT NULL DEFAULT 0,
            win_plantaid BIGINT NOT NULL DEFAULT 0,
            win_equipoid BIGINT NOT NULL DEFAULT 0,
            win_ass_id BIGINT NOT NULL DEFAULT 0,
            win_day INT NOT NULL DEFAULT 0,
            win_month INT NOT NULL DEFAULT 0,
            win_year INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_prod_worker_init_worker (win_wrkid),
            KEY idx_prod_worker_init_machine (win_equipoid),
            KEY idx_prod_worker_init_status (win_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS prod_worker_ot (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wok_ag_id BIGINT UNSIGNED NOT NULL,
            wok_init_id BIGINT UNSIGNED NOT NULL,
            wok_crtdat BIGINT NOT NULL DEFAULT 0,
            wok_enddat BIGINT NOT NULL DEFAULT 0,
            wok_status INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_prod_worker_ot_agenda (wok_ag_id),
            KEY idx_prod_worker_ot_init (wok_init_id),
            KEY idx_prod_worker_ot_status (wok_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS prod_worker_ot_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            evt_prod_worker_otid BIGINT UNSIGNED NOT NULL,
            evt_amount DECIMAL(14,3) NOT NULL DEFAULT 0.000,
            evt_crtdat BIGINT NOT NULL DEFAULT 0,
            evt_enddat BIGINT NOT NULL DEFAULT 0,
            evt_status INT NOT NULL DEFAULT 0,
            evt_type VARCHAR(40) NOT NULL DEFAULT \'\',
            evt_comments VARCHAR(255) NOT NULL DEFAULT \'\',
            evt_equipo_mantid BIGINT NOT NULL DEFAULT 0,
            evt_pause_id BIGINT NOT NULL DEFAULT 0,
            prod_bobina_kg DECIMAL(14,3) NOT NULL DEFAULT 0.000,
            prod_seri_color VARCHAR(120) NOT NULL DEFAULT \'\',
            prod_seri_converted_amt DECIMAL(14,3) NOT NULL DEFAULT 0.000,
            evt_medida_fromid BIGINT NOT NULL DEFAULT 0,
            evt_medida_toid BIGINT NOT NULL DEFAULT 0,
            evt_ubim_id BIGINT NOT NULL DEFAULT 0,
            evt_amount_metros_maquina DECIMAL(14,3) NOT NULL DEFAULT 0.000,
            evt_amount_metros_lineales DECIMAL(14,3) NOT NULL DEFAULT 0.000,
            evt_metrotype VARCHAR(20) NOT NULL DEFAULT \'\',
            PRIMARY KEY (id),
            KEY idx_prod_worker_ot_events_ot (evt_prod_worker_otid),
            KEY idx_prod_worker_ot_events_type (evt_type),
            KEY idx_prod_worker_ot_events_status (evt_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    return [
        'user',
        'workers',
        'prod_header',
        'prod_agenda',
        'prod_worker_init',
        'prod_worker_ot',
        'prod_worker_ot_events',
    ];
}

/**
 * @return array{worker_id:int,user_id:int,user_login:string}
 */
function ensureErpOperatorContext(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT w.id AS worker_id, w.wrk_uid AS user_id, u.user_login
         FROM workers w
         INNER JOIN `user` u ON u.id = w.wrk_uid
         WHERE w.wrk_status = 1 AND u.user_status = 1
         ORDER BY w.id ASC
         LIMIT 1'
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        return [
            'worker_id' => (int)$row['worker_id'],
            'user_id' => (int)$row['user_id'],
            'user_login' => (string)$row['user_login'],
        ];
    }

    $login = 'operador.demo';
    $userId = null;

    $userStmt = $pdo->prepare('SELECT id, user_login FROM `user` WHERE user_login = :login LIMIT 1');
    $userStmt->execute([':login' => $login]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

    $timestamp = time();

    if ($userRow === false) {
        $insertUser = $pdo->prepare(
            'INSERT INTO `user` (
                user_firstname, user_lastname, user_login, user_pass, user_type, user_status,
                user_crtusr, user_crtdat, user_updusr, user_upddat, user_visible, user_mailforward,
                user_mail, user_street, user_telephone, user_cellphone, user_internet, user_annotations,
                user_mail_signature_html, user_doc_signature, user_pic, user_countryid, user_regionid,
                user_provinciaid, user_comunaid, user_sidepanel_active, user_sidepanel_login,
                user_printer_name, user_printer_port, user_rut, user_code, conf_mailserver,
                conf_mail_accountname, conf_mail_password
             ) VALUES (
                :first_name, :last_name, :login, :password, 2, 1,
                1, :created_at, 1, :updated_at, 1, 0,
                :mail, "", "", "", "", "",
                "", "", "", 0, 0,
                0, 0, 1, 1,
                "", "", "", :user_code, "",
                "", ""
             )'
        );
        $insertUser->execute([
            ':first_name' => 'Operador',
            ':last_name' => 'Demo',
            ':login' => $login,
            ':password' => '1234',
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
            ':mail' => 'operador.demo@local.test',
            ':user_code' => 'OPERADOR-DEMO',
        ]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$userRow['id'];
        $pdo->prepare(
            'UPDATE `user`
             SET user_status = 1,
                 user_firstname = COALESCE(NULLIF(user_firstname, \'\'), :first_name),
                 user_lastname = COALESCE(NULLIF(user_lastname, \'\'), :last_name),
                 user_mail = COALESCE(NULLIF(user_mail, \'\'), :mail),
                 user_code = COALESCE(NULLIF(user_code, \'\'), :user_code),
                 user_upddat = :updated_at,
                 user_updusr = 1
             WHERE id = :id'
        )->execute([
            ':id' => $userId,
            ':first_name' => 'Operador',
            ':last_name' => 'Demo',
            ':mail' => 'operador.demo@local.test',
            ':user_code' => 'OPERADOR-DEMO',
            ':updated_at' => $timestamp,
        ]);
    }

    $workerStmt = $pdo->prepare('SELECT id FROM workers WHERE wrk_uid = :user_id LIMIT 1');
    $workerStmt->execute([':user_id' => $userId]);
    $workerRow = $workerStmt->fetch(PDO::FETCH_ASSOC);

    if ($workerRow === false) {
        $insertWorker = $pdo->prepare(
            'INSERT INTO workers (wrk_uid, wrk_firstname, wrk_lastname, wrk_status, wrk_turno_state)
             VALUES (:user_id, :first_name, :last_name, 1, 0)'
        );
        $fallbackWorkerData = [
            ':user_id' => $userId,
            ':first_name' => 'Operador',
            ':last_name' => 'Demo',
        ];
        try {
            $insertWorker->execute($fallbackWorkerData);
        } catch (Throwable $e) {
            $insertWorker = $pdo->prepare(
                'INSERT INTO workers (
                    wrk_uid, wrk_firstname, wrk_lastname, wrk_status, wrk_turno_state,
                    wrk_crtdat, wrk_crtusr, wrk_rut, wrk_folio, wrk_email, wrk_telefono1,
                    wrk_telefono2, wrk_telefono3, wrk_turno_turnoid, wrk_cargoid, wrk_costo_hh,
                    wrk_titulo, wrk_birthday, wrk_street, wrk_foto, wrk_turno_startdate, wrk_axx_pass
                 ) VALUES (
                    :user_id, :first_name, :last_name, 1, 0,
                    :created_at, 1, :rut, :folio, :mail, "", "",
                    "", 0, 0, 0.00,
                    "", "", "", "", 0, ""
                 )'
            );
            $insertWorker->execute([
                ':user_id' => $userId,
                ':first_name' => 'Operador',
                ':last_name' => 'Demo',
                ':created_at' => $timestamp,
                ':rut' => '11.111.111-1',
                ':folio' => 'OPERADOR-DEMO',
                ':mail' => 'operador.demo@local.test',
            ]);
        }
        $workerId = (int)$pdo->lastInsertId();
    } else {
        $workerId = (int)$workerRow['id'];
        $pdo->prepare(
            'UPDATE workers
             SET wrk_status = 1,
                 wrk_firstname = COALESCE(NULLIF(wrk_firstname, \'\'), :first_name),
                 wrk_lastname = COALESCE(NULLIF(wrk_lastname, \'\'), :last_name)
             WHERE id = :id'
        )->execute([
            ':id' => $workerId,
            ':first_name' => 'Operador',
            ':last_name' => 'Demo',
        ]);
    }

    return [
        'worker_id' => $workerId,
        'user_id' => $userId,
        'user_login' => $login,
    ];
}
