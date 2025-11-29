<?php
require 'db.php';
require 'functions.php';

// Configuração de Fuso Horário para Resgate Diário (Brasília/GMT-3)
date_default_timezone_set('America/Sao_Paulo'); 

// --- EMOJI HELPER (Alternating Money Emojis) ---
function getNextCurrencyEmoji() {
    static $emojis = ['🤑', '💰', '🪙'];
    static $index = 0;
    $emoji = $emojis[$index];
    $index = ($index + 1) % count($emojis);
    return $emoji;
}

// --- SEGURANÇA ---
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// --- REGISTO ---
function registerLog($pdo, $user_id, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {}
}

// --- DADOS USUÁRIO ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();
$has_withdrawal_pass = !empty($currentUser['withdrawal_password']);

// --- CONFIGS ---
$whatsapp_number = getSetting($pdo, 'whatsapp_number');
$site_name = getSetting($pdo, 'site_name', 'Imperio Invest');
$min_withdraw = getSetting($pdo, 'min_withdraw', 50);

// --- COMPLETAR PERFIL (1º LOGIN) ---
if ($role == 'user' && verificarDadosFaltantes($pdo, $user_id) && !isset($_POST['complete_profile'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <title>Completar Cadastro</title>
        <style>body{background:#212425;color:#fff;}</style>
    </head>
    <body class="d-flex align-items-center justify-content-center" style="height:100vh">
        <div class="card p-4 border-0 shadow" style="background-color: #323637; width:90%; max-width:400px;">
            <h4 class="mb-3">Finalizar Cadastro</h4>
            <form action="auth.php" method="POST">
                <input type="hidden" name="action" value="complete_profile">
                <div class="mb-3"><label>CPF</label><input type="text" name="cpf" class="form-control bg-dark text-white border-secondary" required></div>
                <div class="mb-3"><label>Username (Link)</label><input type="text" name="username" class="form-control bg-dark text-white border-secondary" required></div>
                <button class="btn btn-primary w-100 fw-bold">SALVAR</button>
            </form>
        </div>
    </body>
    </html>
    <?php exit;
}

// =========================================================
// LÓGICA PHP (BACKEND)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- ADMIN ---
    if ($role == 'admin') {
        // Configs
        if (isset($_POST['update_settings'])) {
            updateSetting($pdo, 'cpa_percentage', $_POST['cpa']); 
            updateSetting($pdo, 'min_deposit', $_POST['min_dep']);
            updateSetting($pdo, 'min_withdraw', $_POST['min_with']);
            updateSetting($pdo, 'whatsapp_number', $_POST['whatsapp']);
            updateSetting($pdo, 'crypto_btc_price', $_POST['btc_price']);
            updateSetting($pdo, 'crypto_eth_price', $_POST['eth_price']);
            updateSetting($pdo, 'crypto_bnb_price', $_POST['bnb_price']);
            echo "<script>alert('Salvo!'); window.location.href='dashboard.php?page=settings';</script>";
        }
        if (isset($_POST['upload_banner'])) {
            $type = $_POST['banner_type'];
            $uploadDir = 'img/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == 0) {
                $ext = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
                $filename = 'banner_' . $type . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir . $filename)) {
                    if ($type == 'site') $pdo->prepare("INSERT INTO site_banners (image_path) VALUES (?)")->execute([$uploadDir . $filename]);
                    else $pdo->prepare("INSERT INTO dashboard_banners (image_path, link_url, description) VALUES (?, ?, ?)")->execute([$uploadDir . $filename, $_POST['banner_link'], $_POST['banner_desc']]);
                }
            }
            echo "<script>window.location.href='dashboard.php?page=banners';</script>";
        }
        if (isset($_POST['delete_banner'])) {
            $tbl = ($_POST['banner_type'] == 'site') ? 'site_banners' : 'dashboard_banners';
            $pdo->prepare("DELETE FROM $tbl WHERE id = ?")->execute([$_POST['banner_id']]);
        }
        // ... Demais lógicas de admin mantidas ...
        if (isset($_POST['approve_deposit'])) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE transactions SET status='approved' WHERE id=?")->execute([$_POST['trans_id']]);
            $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$_POST['amount'], $_POST['user_id_trans']]);
            $pdo->commit();
            echo "<script>alert('Aprovado!'); window.location.href='dashboard.php?page=deposits';</script>";
        }
        if (isset($_POST['add_manual_deposit'])) {
            $amt = $_POST['amount']; $uid = $_POST['deposit_user_id'];
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'deposit', ?, 'approved', 'Manual Admin')")->execute([$uid, $amt]);
            $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$amt, $uid]);
            $pdo->commit();
            echo "<script>alert('Adicionado!'); window.location.href='dashboard.php?page=deposits';</script>";
        }
        if (isset($_POST['delete_transaction'])) {
            $pdo->prepare("DELETE FROM transactions WHERE id=?")->execute([$_POST['trans_id']]);
        }
        if (isset($_POST['add_plan'])) {
             $imgPath = null;
             if (isset($_FILES['plan_image']) && $_FILES['plan_image']['error'] == 0) {
                $ext = pathinfo($_FILES['plan_image']['name'], PATHINFO_EXTENSION);
                $filename = 'plan_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['plan_image']['tmp_name'], 'img/' . $filename)) $imgPath = 'img/' . $filename;
             }
            $pdo->prepare("INSERT INTO plans (name, daily_percent, days, min_amount, max_amount, image_path) VALUES (?,?,?,?,?,?)")->execute([$_POST['name'], $_POST['daily'], $_POST['days'], $_POST['min'], $_POST['max'], $imgPath]);
            echo "<script>window.location.href='dashboard.php?page=plans_manage';</script>";
        }
        if (isset($_POST['delete_plan'])) {
            try { $pdo->prepare("DELETE FROM plans WHERE id=?")->execute([$_POST['plan_id_delete']]); } catch(Exception $e){}
        }
        if (isset($_POST['create_user_admin'])) {
             try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, whatsapp, password, balance, referrer_id, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $refId = !empty($_POST['referrer_id']) ? $_POST['referrer_id'] : null;
                $stmt->execute([$_POST['name'], $_POST['email'], $_POST['whatsapp'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['balance'], $refId, $_POST['role']]);
                $newId = $pdo->lastInsertId();
                if (!empty($_POST['plan_id']) && !empty($_POST['investment_amount'])) {
                    $plan = $pdo->query("SELECT * FROM plans WHERE id=".$_POST['plan_id'])->fetch();
                    if($plan) {
                        $dr = $_POST['investment_amount'] * ($plan['daily_percent']/100); $tr = $dr * $plan['days']; $ed = date('Y-m-d', strtotime("+{$plan['days']} days"));
                        $pdo->prepare("INSERT INTO user_investments (user_id, plan_id, amount, daily_return, total_return, start_date, end_date) VALUES (?,?,?,?,?,NOW(),?)")->execute([$newId, $plan['id'], $_POST['investment_amount'], $dr, $tr, $ed]);
                    }
                }
                echo "<script>alert('Criado!'); window.location.href='dashboard.php?page=add_user';</script>";
            } catch(Exception $e) { echo "<script>alert('Email já existe');</script>"; }
        }
        if (isset($_POST['edit_user_admin'])) {
            $sqlPass = ""; $params = [$_POST['name'], $_POST['email'], $_POST['whatsapp'], $_POST['cpf'], $_POST['balance'], $_POST['role']];
            if(!empty($_POST['password'])) { $sqlPass = ", password=?"; $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT); }
            $params[] = !empty($_POST['referrer_id']) ? $_POST['referrer_id'] : null;
            $params[] = $_POST['user_id'];
            $pdo->prepare("UPDATE users SET name=?, email=?, whatsapp=?, cpf=?, balance=?, role=? $sqlPass, referrer_id=? WHERE id=?")->execute($params);
            
            if(!empty($_POST['add_plan_id']) && !empty($_POST['add_plan_amount'])) {
                $plan = $pdo->query("SELECT * FROM plans WHERE id=".$_POST['add_plan_id'])->fetch();
                if($plan) {
                    $dr = $_POST['add_plan_amount'] * ($plan['daily_percent']/100); $tr = $dr * $plan['days']; $ed = date('Y-m-d', strtotime("+{$plan['days']} days"));
                    $pdo->prepare("INSERT INTO user_investments (user_id, plan_id, amount, daily_return, total_return, start_date, end_date) VALUES (?,?,?,?,?,NOW(),?)")->execute([$_POST['user_id'], $plan['id'], $_POST['add_plan_amount'], $dr, $tr, $ed]);
                }
            }
            if(!empty($_POST['remove_inv_id'])) { $pdo->prepare("DELETE FROM user_investments WHERE id=?")->execute([$_POST['remove_inv_id']]); }
            echo "<script>alert('Atualizado!'); window.location.href='dashboard.php?page=edit_user&id=".$_POST['user_id']."';</script>";
        }
        if (isset($_POST['delete_user'])) {
            if($_POST['user_id_delete'] != 1) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_POST['user_id_delete']]); 
            echo "<script>window.location.href='dashboard.php?page=users';</script>";
        }
        if(isset($_POST['reply_ticket_admin'])){
             $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)")->execute([$_POST['ticket_id'], $user_id, $_POST['message']]);
             $pdo->prepare("UPDATE support_tickets SET status='waiting_user' WHERE id=?")->execute([$_POST['ticket_id']]);
        }
        if(isset($_POST['delete_ticket'])){ $pdo->prepare("DELETE FROM support_tickets WHERE id=?")->execute([$_POST['ticket_id']]); }
    }

    // --- AÇÕES DO UTILIZADOR ---
    if ($role == 'user') {
        // Comprar Plano
        if (isset($_POST['buy_plan'])) {
            $amt = $_POST['amount']; $pid = $_POST['plan_id'];
            $p = $pdo->query("SELECT * FROM plans WHERE id=$pid")->fetch();
            if ($p && $currentUser['balance'] >= $amt && $amt >= $p['min_amount']) {
                $dr = $amt * ($p['daily_percent']/100); $tr = $dr * $p['days']; $ed = date('Y-m-d', strtotime("+{$p['days']} days"));
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$amt, $user_id]);
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'investment_buy', ?, 'approved', ?)")->execute([$user_id, $amt, "Plano: {$p['name']}"]);
                $pdo->prepare("INSERT INTO user_investments (user_id, plan_id, amount, daily_return, total_return, start_date, end_date) VALUES (?,?,?,?,?,NOW(),?)")->execute([$user_id, $pid, $amt, $dr, $tr, $ed]);
                $pdo->commit();
                echo "<script>purchaseAnimation('Contrato Adquirido!', 'R$ ".number_format($amt,2,',','.')."'); setTimeout(() => { window.location.href='dashboard.php?page=active_plans'; }, 2000);</script>";
            } else echo "<script>alert('Erro: Saldo insuficiente ou valor abaixo do mínimo.');</script>";
        }
        
        // Resgatar Rendimento
        if (isset($_POST['claim_yield'])) {
            $currentHour = (int)date('H');
            if ($currentHour < 19 || $currentHour > 23) { 
                echo "<script>alert('O resgate só é permitido entre 19:00 e 00:00 (Horário de Brasília).'); window.location.href='dashboard.php?page=active_plans';</script>";
                exit;
            }
            $inv_id = $_POST['investment_id'];
            $inv = $pdo->query("SELECT * FROM user_investments WHERE id=$inv_id AND user_id=$user_id AND status='active'")->fetch();
            $today = date('Y-m-d');
            if ($inv && (empty($inv['last_claim_date']) || $inv['last_claim_date'] < $today)) {
                $claim_amount = $inv['daily_return'];
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$claim_amount, $user_id]);
                $pdo->prepare("UPDATE user_investments SET last_claim_date=? WHERE id=?")->execute([$today, $inv_id]);
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'investment_return', ?, 'approved', 'Resgate Diário')")->execute([$user_id, $claim_amount]);
                $pdo->commit();
                echo "<script>claimAnimation('R$ ".number_format($claim_amount, 2, ',', '.')."'); setTimeout(() => { window.location.href='dashboard.php?page=active_plans'; }, 2500);</script>";
            } else {
                echo "<script>alert('Resgate já realizado hoje ou investimento inativo.'); window.location.href='dashboard.php?page=active_plans';</script>";
            }
        }

        // Suporte e Perfil
        if(isset($_POST['create_ticket'])){
             $pdo->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'pending')")->execute([$user_id, $_POST['subject']]);
             $tid = $pdo->lastInsertId();
             $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)")->execute([$tid, $user_id, $_POST['message']]);
             echo "<script>alert('Enviado'); window.location.href='dashboard.php?page=support';</script>";
        }
        if(isset($_POST['reply_ticket_user'])){
             $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)")->execute([$_POST['ticket_id'], $user_id, $_POST['message']]);
             $pdo->prepare("UPDATE support_tickets SET status='waiting_admin' WHERE id=?")->execute([$_POST['ticket_id']]);
        }
        if(isset($_POST['update_profile'])){
             $sqlP=""; 
             $arr=[$_POST['name'],$_POST['email'],$_POST['whatsapp'],$_POST['username'],$_POST['pix_key_type'],$_POST['pix_key'],$user_id];
             if(!empty($_POST['password'])){ 
                 $sqlP=", password=?"; 
                 array_splice($arr, 4, 0, password_hash($_POST['password'], PASSWORD_DEFAULT)); 
             }
             $pdo->prepare("UPDATE users SET name=?, email=?, whatsapp=?, username=?, pix_key_type=?, pix_key=? $sqlP WHERE id=?")->execute($arr);
             echo "<script>alert('Salvo'); window.location.href='dashboard.php?page=profile';</script>";
        }

        // Solicitar Saque
        if (isset($_POST['request_withdraw'])) {
            $withdrawal_pass = $_POST['withdrawal_password'];
            $amount = $_POST['amount'];
            if (!password_verify($withdrawal_pass, $currentUser['withdrawal_password'])) {
                echo "<script>alert('Senha de saque inválida.'); window.location.href='dashboard.php?page=withdraw';</script>";
                exit;
            }
            if ($amount > $currentUser['balance'] || $amount < $min_withdraw) {
                echo "<script>alert('Saldo insuficiente ou valor abaixo do mínimo.'); window.location.href='dashboard.php?page=withdraw';</script>";
                exit;
            }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET balance=balance-? WHERE id=?")->execute([$amount, $user_id]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'withdraw', ?, 'pending', 'Saque Solicitado')")->execute([$user_id, $amount]);
            $pdo->commit();
            echo "<script>handleAction('with', {$amount});</script>";
            exit;
        }
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Redirecionamento de Segurança (Saque)
if ($page == 'withdraw' && !$has_withdrawal_pass) {
    header("Location: dashboard.php?page=withdraw_pass");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Dashboard | <?=$site_name?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <style>
        /* --- CORES FIXAS --- */
        :root { --bg-body: #F2F2F2; --bg-card: #FFFFFF; --text-main: #333333; --primary: #3B82F6; --secondary: #323637; --sidebar-bg: #FFFFFF; --card-border: #E5E7EB; }
        <?php if($role == 'admin'): ?>
        :root { --bg-body: #212425; --bg-card: #323637; --text-main: #fdffff; --primary: #0474cc; --secondary: #323637; --sidebar-bg: #323637; --card-border: #404445; }
        <?php endif; ?>

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Arial', sans-serif; padding-bottom: 80px; margin: 0; }
        .card { background-color: var(--bg-card); border: 1px solid var(--card-border); border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-control, .form-select { background-color: var(--bg-body); border: 1px solid var(--card-border); color: var(--text-main); border-radius: 8px; padding: 10px; }
        .btn-primary { background-color: var(--primary); border: none; border-radius: 50px; font-weight: 600; padding: 10px 20px; color: #fff; }
        .table { color: var(--text-main); }
        
        /* APP USER (Mobile) */
        .app-container { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #F5F7FA; position: relative; padding-top: 60px; }
        .app-header-top { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #3B82F6; color: white; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .app-logo { font-weight: bold; font-size: 18px; display: flex; align-items: center; gap: 5px; }
        .balance-card-user { background: #1F2937; color: white; border-radius: 15px; padding: 20px; margin: 15px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .btn-user-action { padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; text-decoration: none; color: white; }
        .btn-recharge { background: #10B981; } 
        .btn-withdraw { background: #EF4444; } 
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 15px; margin-bottom: 20px; }
        .stat-item { padding: 15px; border-radius: 12px; color: white; position: relative; overflow: hidden; height: 100px; display: flex; flex-direction: column; justify-content: center; }
        .stat-value { font-size: 18px; font-weight: bold; margin-top: 5px; }
        .stat-label { font-size: 11px; opacity: 0.9; }
        
        /* Nav Inferior */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: white; border-top: 1px solid #eee; display: flex; justify-content: space-around; align-items: center; z-index: 1000; }
        .nav-btn { color: #9CA3AF; text-decoration: none; font-size: 10px; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .nav-btn i, .nav-btn span:first-child { font-size: 22px; margin-bottom: 2px; display: block; }
        .nav-btn span:first-child { font-size: 24px !important; }
        .nav-btn.active { color: #3B82F6; }
        .nav-btn.active i, .nav-btn.active span { color: #3B82F6; } 

        /* Invest Card (Mobile/Default) */
        .invest-card-custom { border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; position: relative; margin-bottom: 15px; background: var(--bg-card); }
        .invest-cycle-tag { position: absolute; top: 10px; right: 10px; background: #8B4513; color: #fff; padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: bold; z-index: 5; }
        .invest-img-col { width: 100px; height: 100px; overflow: hidden; position: relative; }
        .invest-img-col img { width: 100%; height: 100%; object-fit: cover; }
        .invest-stats-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .invest-stat-item { text-align: center; flex: 1; }
        .invest-val { font-weight: bold; color: #F59E0B; font-size: 14px; }
        .invest-label { font-size: 10px; color: #666; }
        .invest-limit { font-weight: bold; color: #333; font-size: 14px; }
        .invest-return-pct { font-weight: bold; color: #EF4444; font-size: 14px; }

        /* Desktop */
        @media (min-width: 992px) {
            .app-container { max-width: 900px; margin: 0 auto; padding: 30px; background-color: var(--bg-body); padding-top: 15px; }
            .app-header-top { display: none; }
            .bottom-nav { display: none; }
            .balance-card-user, .stats-grid, .bg-white.mx-3, #dashSlider.mx-3 { margin-left: 0 !important; margin-right: 0 !important; max-width: 100%; }
            .stats-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 0; }
            .user-desktop-wrapper { display: flex; min-height: 100vh; background-color: var(--bg-body); padding-left: 200px; width: 100%; }
            .desktop-sidebar { width: 200px; background-color: var(--bg-card); position: fixed; height: 100%; top: 0; left: 0; box-shadow: 2px 0 5px rgba(0,0,0,0.05); padding: 15px 0; z-index: 999; }
            .desktop-nav-link { display: flex; align-items: center; padding: 12px 20px; color: var(--text-main); text-decoration: none; transition: 0.2s; border-left: 3px solid transparent; font-weight: 500; }
            .desktop-nav-link:hover { background-color: #e6e6e6; color: var(--primary); }
            .desktop-nav-link.active { background-color: #f0f0f0; border-left: 3px solid var(--primary); color: var(--primary); font-weight: 600; }
            .desktop-main-content { flex-grow: 1; padding: 30px; max-width: 100%; }
            .card-desktop { background-color: var(--bg-card); border: 1px solid var(--card-border); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 25px; }
            .card-dark-desktop { background-color: #1F2937; color: white; border: none; }
            .card-desktop-icon { font-size: 2.5rem; padding: 10px; border-radius: 8px; color: white; }
            .icon-purple { background-color: #9333ea; }
            .icon-blue { background-color: #3b82f6; }
            .icon-orange { background-color: #f97316; }

            /* Invest Card Desktop */
            .plan-card-desktop { border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; background-color: var(--bg-card); position: relative; color: var(--text-main); }
            .plan-image-area { width: 100%; height: 180px; overflow: hidden; }
            .plan-image-area img { width: 100%; height: 100%; object-fit: cover; opacity: 0.9; }
            .cycle-tag-invest { position: absolute; top: 10px; right: 10px; background: #8B4513; color: white; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: bold; z-index: 10; }
            .plan-stats-container { padding: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; border-bottom: 1px solid var(--card-border); }
            .stat-box { padding: 5px; }
            .stat-value-large { font-size: 1.2rem; font-weight: bold; color: var(--primary); }
            .stat-label-small { font-size: 0.8rem; color: var(--secondary); }
            .total-return-percent { font-size: 1.1rem; font-weight: bold; color: #28a745; }
        }
        
        /* Admin Wrapper */
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 260px; background: #323637; border-right: 1px solid #444; position: fixed; height: 100%; padding: 20px; color: white; }
        .admin-content { flex: 1; margin-left: 260px; padding: 30px; background: #212425; color: white; }
        .admin-link { color: #ddd; text-decoration: none; display: block; padding: 10px; margin-bottom: 5px; border-radius: 5px; }
        .admin-link.active, .admin-link:hover { background: #0474cc; color: white; }
        @media(max-width:991.98px) { .admin-sidebar { display:none; } .admin-content { margin-left:0; padding:15px; } }
        
        /* Modals e Loaders */
        #loadingOverlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; display:none; flex-direction:column; align-items:center; justify-content:center; }
        #floatingAnimation { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: linear-gradient(45deg, #FF6F61, #DE483C); color: white; padding: 25px 50px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 3000; display: none; opacity: 0; transition: all 0.1s; }
    </style>
</head>
<body>

<div id="floatingAnimation"><div id="animationText" class="fw-bold fs-4"></div></div>
<audio id="claimSound" src="https://www.soundjay.com/coin/coin-collect-7.mp3" preload="auto"></audio>
<audio id="purchaseSound" src="https://www.soundjay.com/button/button-10.mp3" preload="auto"></audio>

<div id="loadingOverlay">
    <span id="closeModalBtn" onclick="closeModal()">&times;</span>
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
    <h4 id="loadingText" class="mt-3 text-white">Processando...</h4>
    <div id="modalContent" style="display:none; width: 90%; max-width: 400px;">
        <div class="card p-4 text-center bg-white text-dark">
            <div id="modalIcon" class="mb-3" style="font-size: 3rem;"></div>
            <h5 id="modalTitle" class="mb-2 fw-bold"></h5>
            <p id="modalMsg" class="small mb-4 text-muted"></p>
            <a id="waLink" href="#" target="_blank" class="btn btn-success w-100 rounded-pill py-2 fw-bold"><i class="fab fa-whatsapp me-2"></i> FALAR COM GERENTE</a>
        </div>
    </div>
</div>

<?php if ($role == 'user'): ?>
    <?php 
        $invs = $pdo->query("SELECT * FROM user_investments WHERE user_id = $user_id AND status = 'active'")->fetchAll();
        $total_invested = 0; $today_yield = 0; $total_yield = 0; $unclaimed_yield = 0;
        $currentHour = (int)date('H');
        $is_claim_window = $currentHour >= 19 && $currentHour <= 23;
        foreach($invs as $i) {
            $total_invested += $i['amount'];
            $plan = $pdo->query("SELECT * FROM plans WHERE id={$i['plan_id']}")->fetch();
            $today_yield += $i['amount'] * ($plan['daily_percent']/100);
            $today = date('Y-m-d');
            if (empty($i['last_claim_date']) || $i['last_claim_date'] < $today) $unclaimed_yield += $i['daily_return'];
        }
        $refLink = getenv('BASE_URL') . "?ref=" . $currentUser['username'];
        $dashBanners = $pdo->query("SELECT * FROM dashboard_banners ORDER BY id DESC LIMIT 3")->fetchAll();
        
        // Valores das Criptomoedas (Restored)
        $btc_price = number_format(getSetting($pdo, 'crypto_btc_price', 65000), 2, ',', '.');
        $eth_price = number_format(getSetting($pdo, 'crypto_eth_price', 3500), 2, ',', '.');
        $bnb_price = number_format(getSetting($pdo, 'crypto_bnb_price', 600), 2, ',', '.');
    ?>

    <div class="user-desktop-wrapper d-none d-lg-flex"> 
        <div class="desktop-sidebar">
            <div class="sidebar-header"><i class="fab fa-bitcoin me-2"></i> <?=$site_name?></div>
            <a href="?page=home" class="desktop-nav-link <?= $page=='home'?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
            <a href="?page=invest" class="desktop-nav-link <?= $page=='invest'?'active':'' ?>"><i class="fas fa-layer-group"></i> Investimento</a>
            <a href="?page=active_plans" class="desktop-nav-link <?= $page=='active_plans'?'active':'' ?>"><i class="fas fa-chart-line"></i> Rendimentos</a>
            <hr class="mx-3 my-2" style="border-color: var(--card-border);">
            <a href="?page=deposit" class="desktop-nav-link <?= $page=='deposit'?'active':'' ?>"><i class="fas fa-wallet"></i> Depositar</a>
            <a href="?page=withdraw" class="desktop-nav-link <?= $page=='withdraw'?'active':'' ?>"><i class="fas fa-dollar-sign"></i> Retirada</a>
            <a href="?page=profile" class="desktop-nav-link <?= $page=='profile'?'active':'' ?>"><i class="fas fa-user"></i> Perfil</a>
            <a href="?page=support" class="desktop-nav-link <?= $page=='support'?'active':'' ?>"><i class="fas fa-headset"></i> Suporte</a>
            <a href="auth.php?logout=true" class="desktop-nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
            <div class="position-absolute bottom-0 p-3 w-100 text-center"><div style="font-size:10px;">UID: <?=$currentUser['id']?></div></div>
        </div>

        <div class="desktop-main-content">
            <?php if($page == 'home'): ?>
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card-desktop d-flex justify-content-between align-items-center">
                            <div class="text-muted small me-3">URL de Referência</div>
                            <div class="d-flex align-items-center"><span class="me-3 small text-truncate"><?=$refLink?></span><button onclick="copyLink('<?=$refLink?>')" class="btn btn-sm btn-primary rounded-pill">Copiar</button></div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="card-desktop d-flex justify-content-between align-items-center">
                            <div class="text-muted small me-3">UID</div><div class="d-flex align-items-center"><span class="me-3 small"><?=$currentUser['id']?></span></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-4">
                         <div class="card-desktop p-2 text-center"><i class="fab fa-bitcoin text-warning"></i> <span class="small">BTC R$ <?=$btc_price?></span></div>
                    </div>
                    <div class="col-4">
                         <div class="card-desktop p-2 text-center"><i class="fab fa-ethereum text-info"></i> <span class="small">ETH R$ <?=$eth_price?></span></div>
                    </div>
                     <div class="col-4">
                         <div class="card-desktop p-2 text-center"><i class="fas fa-coins text-secondary"></i> <span class="small">BNB R$ <?=$bnb_price?></span></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card-desktop card-dark-desktop">
                            <h5 class="text-white mb-3">Investimento</h5>
                            <p class="mb-1">Total Investido: R$ <?=number_format($total_invested, 2, ',', '.')?> <?=getNextCurrencyEmoji()?></p>
                            <p class="mb-1">Rendimento Total: R$ 0 <?=getNextCurrencyEmoji()?></p>
                            <p class="mb-3">Potencial Diário: R$ <?=number_format($today_yield, 2, ',', '.')?> <?=getNextCurrencyEmoji()?></p>
                            <a href="?page=invest" class="btn btn-sm btn-primary w-100 rounded-pill">Investir Agora</a>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                         <div class="card-desktop">
                            <h5 class="text-dark mb-3">Indicações</h5>
                            <p class="mb-1 text-muted">Convide amigos e ganhe bônus!</p>
                            <button onclick="copyLink('<?=$refLink?>')" class="btn btn-sm btn-outline-secondary w-100 rounded-pill">Copiar Link</button>
                        </div>
                    </div>
                     <div class="col-lg-4 mb-4">
                         <div class="card-desktop">
                            <h5 class="text-dark mb-3">Resgate Rápido</h5>
                            <p class="mb-1 text-muted">Disponível: R$ <?=number_format($unclaimed_yield, 2, ',', '.')?></p>
                            <?php if ($unclaimed_yield > 0 && $is_claim_window): ?>
                                <a href="?page=active_plans" class="btn btn-sm btn-success w-100 rounded-pill">RESGATAR</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary w-100 rounded-pill" disabled>Aguarde 19h</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif($page == 'invest'): ?>
                <h4 class="text-dark mb-4">Produtos de Investimento</h4>
                <div class="row">
                    <?php $plans = $pdo->query("SELECT * FROM plans ORDER BY min_amount ASC"); while($p=$plans->fetch()): 
                         $img = $p['image_path'] ? $p['image_path'] : "img/img{$p['id']}.jpeg";
                         if(!file_exists($img) && !$p['image_path']) $img = 'https://via.placeholder.com/100';
                         $lucro = $p['min_amount'] * ($p['daily_percent']/100);
                         $total_return_value = $lucro * $p['days'];
                         $total_final_value = $p['min_amount'] + $total_return_value;
                         $total_return_percent = number_format($p['daily_percent'] * $p['days'], 1);
                         $plan_img_placeholder = 'https://i.ibb.co/6P6X7V2/gold-mock.jpg';
                    ?>
                    <div class="col-lg-6 mb-4">
                        <div class="plan-card-desktop">
                            <div class="cycle-tag-invest">ciclo: <?=$p['days']?> Dias</div>
                            <div class="row g-0">
                                <div class="col-4 plan-image-area"><img src="<?=$plan_img_placeholder?>" alt="Fundo Ouro" style="object-position: center;"></div>
                                <div class="col-8">
                                    <div class="p-3">
                                        <div class="fw-bold text-dark mb-2"><?=$p['name']?></div>
                                        <div class="plan-stats-container">
                                            <div class="stat-box border-end"><div class="stat-value-large text-warning">R$ <?=number_format($lucro,2,',','.')?></div><div class="stat-label-small text-muted">Rend. diário</div></div>
                                            <div class="stat-box"><div class="stat-value-large text-warning">R$ <?=number_format($total_return_value,2,',','.')?></div><div class="stat-label-small text-muted">Rend. total</div></div>
                                            <div class="stat-box border-end"><div class="stat-value-large text-dark"><?=$p['days']?></div><div class="stat-label-small text-muted">Limite</div></div>
                                            <div class="stat-box"><div class="total-return-percent">+<?=$total_return_percent?>%</div><div class="stat-label-small text-muted">TRD</div></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div class="fw-bold text-dark">R$ <?=number_format($total_final_value,2,',','.')?> <?=getNextCurrencyEmoji()?></div>
                                            <form method="POST" class="m-0" onsubmit="event.preventDefault(); document.getElementById('loadingText').innerText = 'Comprando Contrato...'; document.getElementById('loadingOverlay').style.display = 'flex'; this.submit();">
                                                <input type="hidden" name="buy_plan" value="1"><input type="hidden" name="plan_id" value="<?=$p['id']?>"><input type="hidden" name="amount" value="<?=$p['min_amount']?>">
                                                <button class="btn btn-primary btn-sm px-4">Comprar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            
            <?php elseif($page == 'active_plans'): ?>
                <h5 class="text-dark mb-4">Seus Rendimentos Diários</h5>
                <?php $invs = $pdo->query("SELECT ui.*, p.name, p.days as total_days, p.image_path FROM user_investments ui JOIN plans p ON ui.plan_id=p.id WHERE user_id=$user_id AND ui.status='active' ORDER BY id DESC");
                if($invs->rowCount()>0): while($i = $invs->fetch()): 
                    $daily_percent = number_format(($i['daily_return'] / $i['amount']) * 100, 2);
                    $claimable_amount = $i['daily_return'];
                    $today = date('Y-m-d');
                    $lastClaimDate = $i['last_claim_date'];
                    $is_claimable_today = empty($lastClaimDate) || $lastClaimDate < $today;
                ?>
                <div class="card p-3 mb-4 shadow-sm border-start border-5 border-success">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-dark"><?=$i['name']?></span>
                        <span class="badge bg-primary">+<?=$daily_percent?>% ao dia</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small text-muted">
                        <span>Investido: <b>R$ <?=number_format($i['amount'],2,',','.')?></b></span>
                        <span>Diário: <b>R$ <?=number_format($claimable_amount,2,',','.')?></b></span>
                    </div>
                    <div class="mt-3">
                        <?php if($is_claim_window && $is_claimable_today): ?>
                            <form method="POST"><input type="hidden" name="claim_yield" value="1"><input type="hidden" name="investment_id" value="<?=$i['id']?>"><button class="btn btn-success w-100">RESGATAR DIÁRIO</button></form>
                        <?php else: ?>
                             <button class="btn w-100 disabled" style="background:#ccc">
                                 <?php if(!$is_claim_window) echo "Aguarde 19:00"; else echo "Resgatado Hoje"; ?>
                             </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; else: echo "Nenhum plano ativo."; endif; ?>

            <?php elseif($page == 'profile'): ?>
                <div class="card-desktop">
                    <h5 class="text-dark mb-3">Meu Perfil</h5>
                    <form method="POST" class="bg-white p-3 rounded-4 shadow-sm">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-2"><label class="text-muted small">Nome</label><input name="name" value="<?=$currentUser['name']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">Email</label><input name="email" value="<?=$currentUser['email']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">WhatsApp</label><input name="whatsapp" value="<?=$currentUser['whatsapp']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">Username</label><input name="username" value="<?=$currentUser['username']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">CPF</label><input name="cpf" value="<?=$currentUser['cpf']?>" class="form-control" readonly style="background-color: #e9ecef;"></div>
                        <h6 class="text-primary mb-3">Chave PIX</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-4"><select name="pix_key_type" class="form-select"><option value="cpf">CPF</option><option value="email">Email</option></select></div>
                            <div class="col-8"><input name="pix_key" class="form-control" value="<?=$currentUser['pix_key']?>"></div>
                        </div>
                        <div class="mb-2"><label class="text-muted small">Nova Senha</label><input name="password" type="password" class="form-control"></div>
                        <button class="btn btn-primary w-100 mt-3">SALVAR DADOS</button>
                    </form>
                    <div class="card p-3 shadow-sm mt-4 text-center">
                         <button onclick="resetWithdrawalPasswordPopup('<?=$currentUser['email']?>', '<?=$whatsapp_number?>')" class="btn btn-danger w-100">ALTERAR SENHA DE SAQUE</button>
                    </div>
                </div>

            <?php elseif($page == 'deposit'): ?>
                 <div class="card-desktop text-center"><i class="fas fa-qrcode fa-4x text-primary mb-3"></i><h4 class="text-dark">Depositar</h4><input type="number" id="depValDesk" class="form-control form-control-lg text-center mt-4 mb-3 shadow-sm" placeholder="R$ 0,00"><button onclick="handleAction('dep', document.getElementById('depValDesk').value)" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow">GERAR PIX</button></div>
            <?php elseif($page == 'withdraw'): ?>
                 <div class="card-desktop"><h4 class="text-dark text-center mb-4">Solicitar Saque</h4><form method="POST"><input type="hidden" name="request_withdraw" value="1"><input type="number" name="amount" class="form-control mb-3" placeholder="Valor"><input type="password" name="withdrawal_password" class="form-control mb-3" placeholder="Senha"><button class="btn btn-danger w-100">SOLICITAR</button></form></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-lg-none">
        <div class="app-header-top">
            <div class="container d-flex align-items-center justify-content-between h-100">
                <div class="app-logo"><i class="fas fa-chart-line me-2"></i> <?=$site_name?></div>
                <div class="d-flex align-items-center gap-2"><div style="width:32px; height:32px; background:#eee; border-radius:50%; color:#333; display:flex; align-items:center; justify-content:center; font-weight:bold;"><?=strtoupper(substr($currentUser['name'],0,1))?></div></div>
            </div>
        </div>

        <div class="app-container">
            <?php if($page == 'home'): ?>
                <div class="balance-card-user">
                    <div><div style="font-size:12px; opacity:0.7">Saldo Total</div><div style="font-size:24px; font-weight:bold">R$ <?=number_format($currentUser['balance'],2,',','.')?> <?=getNextCurrencyEmoji()?></div></div>
                    <div class="d-flex gap-2"><a href="?page=deposit" class="btn-user-action btn-recharge">Depositar</a><a href="?page=withdraw" class="btn-user-action btn-withdraw">Retirada</a></div>
                </div>

                <div class="d-flex justify-content-between gap-2 mx-0 mb-3">
                    <div class="card crypto-ticker flex-fill text-center p-2 border-0 shadow-sm" style="font-size: 10px;">
                        <i class="fab fa-bitcoin text-warning mb-1"></i>
                        <div class="fw-bold">BTC</div>
                        <div class="text-success">R$ <?=$btc_price?></div>
                    </div>
                    <div class="card crypto-ticker flex-fill text-center p-2 border-0 shadow-sm" style="font-size: 10px;">
                        <i class="fab fa-ethereum text-info mb-1"></i>
                        <div class="fw-bold">ETH</div>
                        <div class="text-success">R$ <?=$eth_price?></div>
                    </div>
                    <div class="card crypto-ticker flex-fill text-center p-2 border-0 shadow-sm" style="font-size: 10px;">
                        <i class="fas fa-coins text-secondary mb-1"></i>
                        <div class="fw-bold">BNB</div>
                        <div class="text-success">R$ <?=$bnb_price?></div>
                    </div>
                </div>
                
                <?php if(count($dashBanners)>0): ?><div id="dashSlider" class="carousel slide mb-3 mx-3 rounded-4 overflow-hidden shadow-sm" data-bs-ride="carousel"><div class="carousel-inner"><?php foreach($dashBanners as $k=>$b): ?><div class="carousel-item <?=$k==0?'active':''?>"><img src="<?=$b['image_path']?>" class="d-block w-100" style="height:140px; object-fit:cover;"></div><?php endforeach; ?></div></div><?php endif; ?>
                
                <div class="card mx-0 p-3 mb-3 text-center border-0 shadow-sm" style="border-left: 4px solid <?=$unclaimed_yield > 0 && $is_claim_window ? '#10b981' : '#ccc'?> !important;">
                    <h6 class="text-dark mb-1" style="font-size: 12px;">Resgate Diário Disponível</h6>
                    <p class="fs-5 fw-bold text-success mb-2">R$ <?=number_format($unclaimed_yield, 2, ',', '.')?></p>
                    <?php if ($unclaimed_yield > 0 && $is_claim_window): ?>
                        <a href="?page=active_plans" class="btn btn-sm btn-success w-100 fw-bold rounded-pill">RESGATAR AGORA</a>
                    <?php else: ?>
                        <button class="btn btn-sm w-100 fw-bold rounded-pill" style="background-color: #e9ecef; color: #9ca3af;" disabled>
                            <?php if ($unclaimed_yield == 0): ?>Sem rendimento<?php else: ?>Aguarde 19:00h<?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="stats-grid">
                    <div class="stat-item blue"><div class="stat-label">Investimento Total</div><div class="stat-value">R$ <?=number_format($total_invested,0,',','.')?> <?=getNextCurrencyEmoji()?></div></div>
                    <div class="stat-item green"><div class="stat-label">Total Retiradas</div><div class="stat-value">R$ 0 <?=getNextCurrencyEmoji()?></div></div>
                    <div class="stat-item orange"><div class="stat-label">Receita Potencial</div><div class="stat-value">R$ <?=number_format($today_yield,2,',','.')?> <?=getNextCurrencyEmoji()?></div></div>
                    <div class="stat-item red"><div class="stat-label">Rendimento Total</div><div class="stat-value">R$ 0 <?=getNextCurrencyEmoji()?></div></div>
                </div>
                <div class="bg-white mx-3 rounded-4 p-2 shadow-sm">
                    <div onclick="copyLink('<?=$refLink?>')" class="d-flex align-items-center p-3 border-bottom"><i class="fas fa-share-alt text-primary me-3 fs-5"></i> <span class="flex-grow-1 text-dark fw-bold">Convide Amigos</span> <i class="fas fa-chevron-right text-muted"></i></div>
                    <a href="?page=support" class="d-flex align-items-center p-3 border-bottom text-decoration-none"><i class="fas fa-headset text-warning me-3 fs-5"></i> <span class="flex-grow-1 text-dark fw-bold">Suporte Online</span> <i class="fas fa-chevron-right text-muted"></i></a>
                    <a href="auth.php?logout=true" class="d-flex align-items-center p-3 text-decoration-none"><i class="fas fa-sign-out-alt text-danger me-3 fs-5"></i> <span class="flex-grow-1 text-dark fw-bold">Sair / Logout</span> <i class="fas fa-chevron-right text-muted"></i></a>
                </div>
            <?php elseif($page == 'invest'): ?>
                <div class="px-3 pb-5">
                    <h5 class="mb-3 fw-bold text-dark">Produtos de Investimento</h5>
                    <?php $plans = $pdo->query("SELECT * FROM plans ORDER BY min_amount ASC"); while($p=$plans->fetch()): 
                         $img = $p['image_path'] ? $p['image_path'] : "img/img{$p['id']}.jpeg";
                         if(!file_exists($img) && !$p['image_path']) $img = 'https://via.placeholder.com/100';
                         $lucro = $p['min_amount'] * ($p['daily_percent']/100);
                         $total_return_value = $lucro * $p['days'];
                         $total_final_value = $p['min_amount'] + $total_return_value;
                         $total_return_percent = number_format($p['daily_percent'] * $p['days'], 1);
                    ?>
                    <div class="invest-card-custom">
                        <div class="invest-cycle-tag">ciclo: <?=$p['days']?> Dias</div>
                        <div class="d-flex align-items-center p-3">
                            <div class="invest-img-col me-3"><img src="<?=$img?>" alt="Plano"></div>
                            <div class="flex-grow-1">
                                <h5 class="text-dark fw-bold mb-2"><?=$p['name']?></h5>
                                <div class="invest-stats-row">
                                    <div class="invest-stat-item"><div class="invest-val">R$ <?=number_format($lucro,2,',','.')?></div><div class="invest-label">Rend. diário</div></div>
                                    <div class="invest-stat-item"><div class="invest-val">R$ <?=number_format($total_return_value,2,',','.')?></div><div class="invest-label">Rend. total</div></div>
                                </div>
                                <div class="invest-stats-row">
                                    <div class="invest-stat-item"><div class="invest-limit">3</div><div class="invest-label">Limite</div></div>
                                    <div class="invest-stat-item"><div class="invest-return-pct">+<?=$total_return_percent?>%</div><div class="invest-label">TRD</div></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light p-2 d-flex justify-content-between align-items-center px-4">
                            <div class="fw-bold text-dark fs-5">R$ <?=number_format($total_final_value,2,',','.')?></div>
                            <form method="POST" class="m-0" onsubmit="event.preventDefault(); document.getElementById('loadingText').innerText = 'Comprando Contrato...'; document.getElementById('loadingOverlay').style.display = 'flex'; this.submit();">
                                <input type="hidden" name="buy_plan" value="1"><input type="hidden" name="plan_id" value="<?=$p['id']?>"><input type="hidden" name="amount" value="<?=$p['min_amount']?>">
                                <button class="btn btn-primary btn-sm px-4 rounded-pill">Comprar</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            
            <?php elseif($page == 'active_plans'): ?>
                <div class="px-3 pt-3">
                    <h5 class="text-dark mb-3">Rendimentos</h5>
                    <?php $invs = $pdo->query("SELECT ui.*, p.name FROM user_investments ui JOIN plans p ON ui.plan_id=p.id WHERE user_id=$user_id ORDER BY id DESC");
                    if($invs->rowCount()>0): while($i = $invs->fetch()): ?>
                    <div class="card p-3 mb-2 border-0 shadow-sm border-start border-5 border-success">
                        <div class="d-flex justify-content-between"><span class="fw-bold text-dark"><?=$i['name']?></span><span class="badge bg-success">Ativo</span></div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Investido: <b>R$ <?=number_format($i['amount'],2)?></b></span>
                            <span>Diário: <b>R$ <?=number_format($i['daily_return'],2)?></b></span>
                        </div>
                         <div class="mt-3">
                            <?php if($is_claim_window): ?>
                                <form method="POST"><input type="hidden" name="claim_yield" value="1"><input type="hidden" name="investment_id" value="<?=$i['id']?>"><button class="btn btn-success w-100">RESGATAR DIÁRIO</button></form>
                            <?php else: ?>
                                 <button class="btn w-100 disabled" style="background:#ccc">Aguarde 19:00</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; else: echo "Nenhum plano ativo."; endif; ?>
                </div>

            <?php elseif($page == 'profile'): ?>
                <div class="px-3 pt-3">
                    <h5 class="text-dark mb-3">Meu Perfil</h5>
                    <form method="POST" class="bg-white p-3 rounded-4 shadow-sm">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-2"><label class="text-muted small">Nome</label><input name="name" value="<?=$currentUser['name']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">Email</label><input name="email" value="<?=$currentUser['email']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">WhatsApp</label><input name="whatsapp" value="<?=$currentUser['whatsapp']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">Username</label><input name="username" value="<?=$currentUser['username']?>" class="form-control"></div>
                        <div class="mb-2"><label class="text-muted small">CPF</label><input name="cpf" value="<?=$currentUser['cpf']?>" class="form-control" readonly style="background-color: #e9ecef;"></div>
                        <div class="mb-2"><label class="text-muted small">Nova Senha</label><input name="password" type="password" class="form-control"></div>
                        <button class="btn btn-primary w-100 mt-3 rounded-pill">Salvar</button>
                    </form>
                     <div class="card p-3 shadow-sm mt-4 text-center">
                         <button onclick="resetWithdrawalPasswordPopup('<?=$currentUser['email']?>', '<?=$whatsapp_number?>')" class="btn btn-danger w-100 rounded-pill fw-bold">ALTERAR SENHA DE SAQUE</button>
                    </div>
                </div>

            <?php elseif($page == 'deposit'): ?>
                <div class="px-3 text-center pt-4">
                    <i class="fas fa-qrcode fa-4x text-primary mb-3"></i><h4 class="text-dark">Depositar</h4>
                    <input type="number" id="depVal" class="form-control form-control-lg text-center mt-4 mb-3 shadow-sm" placeholder="R$ 0,00" min="<?=getSetting($pdo, 'min_deposit', 50)?>">
                    <button onclick="handleAction('dep')" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow">GERAR PIX</button>
                </div>
            <?php elseif($page == 'withdraw'): ?>
                <div class="px-3 pt-3">
                     <h4 class="text-dark text-center mt-4">Solicitar Saque</h4>
                     <form method="POST"><input type="hidden" name="request_withdraw" value="1"><input type="number" name="amount" class="form-control mb-3" placeholder="Valor"><input type="password" name="withdrawal_password" class="form-control mb-3" placeholder="Senha"><button class="btn btn-danger w-100">SOLICITAR</button></form>
                </div>
            <?php endif; ?>
        </div>
        <div class="bottom-nav">
            <a href="?page=home" class="nav-btn <?= $page=='home'?'active':'' ?>"><i class="fas fa-home"></i> Início</a>
            <a href="?page=invest" class="nav-btn <?= $page=='invest'?'active':'' ?>"><i class="fas fa-layer-group"></i> Produto</a>
            <a href="?page=deposit" class="nav-btn <?= $page=='deposit'?'active':'' ?>"> <span style="font-size:24px;">💵</span><span>Depositar</span></a>
            <a href="?page=active_plans" class="nav-btn <?= $page=='active_plans'?'active':'' ?>"><i class="fas fa-chart-line"></i> Rendimento</a>
            <a href="?page=profile" class="nav-btn <?= $page=='profile'?'active':'' ?>"><i class="fas fa-user"></i> Perfil</a>
        </div>
    </div>
<?php endif; ?>

<?php if ($role == 'admin'): ?>
   <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function copyLink(text) { navigator.clipboard.writeText(text); alert('Link copiado!'); }
    function purchaseAnimation(msg, amt) {
        document.getElementById('animationText').innerHTML = `<i class="fas fa-check-circle fa-3x mb-2"></i><br>${msg}<br>${amt}`;
        document.getElementById('floatingAnimation').style.display = 'block';
        document.getElementById('floatingAnimation').classList.add('animate-in');
        
        // Confetti
        var duration = 3000;
        var end = Date.now() + duration;
        (function frame() {
          confetti({ particleCount: 5, angle: 60, spread: 55, origin: { x: 0 } });
          confetti({ particleCount: 5, angle: 120, spread: 55, origin: { x: 1 } });
          if (Date.now() < end) requestAnimationFrame(frame);
        }());

        setTimeout(() => { document.getElementById('floatingAnimation').style.display = 'none'; }, 3000);
    }
    function claimAnimation(amount) {
        document.getElementById('animationText').innerHTML = `<i class="fas fa-money-bill-wave fa-2x mb-2"></i><br>${amount} 💰 Adicionados à Carteira!`;
        const animationDiv = document.getElementById('floatingAnimation');
        animationDiv.style.background = 'linear-gradient(45deg, #FF6F61, #DE483C)'; 
        animationDiv.classList.remove('animate-out');
        animationDiv.classList.add('animate-in');
        document.getElementById('claimSound').play();
        setTimeout(() => { animationDiv.classList.remove('animate-in'); animationDiv.classList.add('animate-out'); }, 2500);
    }
    function resetWithdrawalPasswordPopup(email, whatsappNumber) {
        const message = `Olá, minha conta de email: ${email} precisa ter a senha de saque alterada.`;
        const waLink = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
        document.getElementById('modalContent').style.display = 'block';
        document.getElementById('closeModalBtn').style.display = 'block';
        document.getElementById('loadingOverlay').style.display = 'flex';
        document.querySelector('.spinner-border').style.display = 'none';
        document.getElementById('loadingText').innerText = '';
        document.getElementById('modalTitle').innerText = 'Redefinir Senha';
        document.getElementById('modalMsg').innerText = 'Contate o suporte para reset.';
        document.getElementById('waLink').href = waLink;
    }
    function handleAction(type, amount=null) {
        // ... existing handleAction logic ...
    }
    function closeModal() { document.getElementById('loadingOverlay').style.display = 'none'; document.querySelector('.spinner-border').style.display = 'block'; }
</script>
</body>
</html>