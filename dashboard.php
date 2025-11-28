<?php
require 'db.php';
require 'functions.php';

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

// --- CONFIGS ---
$whatsapp_number = getSetting($pdo, 'whatsapp_number');
$site_name = getSetting($pdo, 'site_name', 'Imperio');

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
        // ... (Lógica do Admin mantida inalterada para economizar espaço, focando no visual do usuário) ...
        // Configs
        if (isset($_POST['update_settings'])) {
            updateSetting($pdo, 'cpa_percentage', $_POST['cpa']); 
            updateSetting($pdo, 'min_deposit', $_POST['min_dep']);
            updateSetting($pdo, 'min_withdraw', $_POST['min_with']);
            updateSetting($pdo, 'whatsapp_number', $_POST['whatsapp']);
            echo "<script>alert('Salvo!'); window.location.href='dashboard.php?page=settings';</script>";
        }
        // Banners
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
        // Depósitos
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
        // Planos
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
        // Usuários Admin
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
        // Guardar PIX
        if (isset($_POST['save_pix'])) {
            $pdo->prepare("UPDATE users SET pix_key_type=?, pix_key=? WHERE id=?")->execute([$_POST['pix_type'], $_POST['pix_key'], $user_id]);
            echo "<script>alert('Chave Salva!'); window.location.href='dashboard.php?page=withdraw';</script>";
        }
        
        // Comprar Plano (Backend Real)
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
                echo "<script>alert('Plano Ativado!'); window.location.href='dashboard.php?page=active_plans';</script>";
            } else echo "<script>alert('Erro: Saldo insuficiente ou valor abaixo do mínimo.');</script>";
        }

        // Suporte
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
        
        // Editar Perfil
        if(isset($_POST['update_profile'])){
             $sqlP=""; $arr=[$_POST['name'],$_POST['email'],$_POST['whatsapp'],$user_id];
             if(!empty($_POST['password'])){ $sqlP=", password=?"; array_splice($arr, 3, 0, password_hash($_POST['password'], PASSWORD_DEFAULT)); }
             $pdo->prepare("UPDATE users SET name=?, email=?, whatsapp=? $sqlP WHERE id=?")->execute($arr);
             echo "<script>alert('Salvo'); window.location.href='dashboard.php?page=profile';</script>";
        }
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
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
    
    <style>
        /* --- CORES FIXAS (TEMA ESCURO/AZUL) --- */
        :root {
            --bg-body: #F2F2F2; /* Fundo claro App */
            --bg-card: #FFFFFF;
            --text-main: #333333;
            --primary: #3B82F6; /* Azul App */
            --secondary: #323637;
            --sidebar-bg: #FFFFFF;
            --card-border: #E5E7EB;
        }
        
        /* Substituição para Admin para manter tema escuro */
        <?php if($role == 'admin'): ?>
        :root {
            --bg-body: #212425;
            --bg-card: #323637;
            --text-main: #fdffff;
            --primary: #0474cc;
            --secondary: #323637;
            --sidebar-bg: #323637;
            --card-border: #404445;
        }
        <?php endif; ?>

        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Arial', sans-serif; padding-bottom: 80px; margin: 0; }
        
        /* Estilos Globais */
        .card { background-color: var(--bg-card); border: 1px solid var(--card-border); border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-control, .form-select { background-color: var(--bg-body); border: 1px solid var(--card-border); color: var(--text-main); border-radius: 8px; padding: 10px; }
        .btn-primary { background-color: var(--primary); border: none; border-radius: 50px; font-weight: 600; padding: 10px 20px; color: #fff; }
        .table { color: var(--text-main); }
        
        /* Estilos APP USER (Inspirado na imagem) */
        .app-container { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #F5F7FA; position: relative; padding-top: 60px; }
        
        /* Cabeçalho Azul Topo */
        .app-header-top {
            position: fixed; top: 0; left: 0; width: 100%; height: 60px;
            background: #3B82F6; color: white; display: flex; align-items: center; justify-content: space-between;
            padding: 0 15px; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .app-logo { font-weight: bold; font-size: 18px; display: flex; align-items: center; gap: 5px; }
        
        /* Cartão Saldo (Preto com botões coloridos) */
        .balance-card-user {
            background: #1F2937; color: white; border-radius: 15px; padding: 20px; margin: 15px;
            display: flex; justify-content: space-between; align-items: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-user-action {
            padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; text-decoration: none; color: white;
        }
        .btn-recharge { background: #EF4444; } /* Vermelho/Rosa */
        .btn-withdraw { background: #10B981; } /* Verde */
        
        /* Grid Estatísticas (4 cores) */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 0 15px; margin-bottom: 20px; }
        .stat-item { padding: 15px; border-radius: 12px; color: white; position: relative; overflow: hidden; height: 100px; display: flex; flex-direction: column; justify-content: center; }
        .stat-item.blue { background: #3B82F6; }
        .stat-item.green { background: #10B981; }
        .stat-item.orange { background: #F59E0B; }
        .stat-item.red { background: #EF4444; }
        .stat-value { font-size: 18px; font-weight: bold; margin-top: 5px; }
        .stat-label { font-size: 11px; opacity: 0.9; }
        
        /* Navegação Inferior (Branca com ícones cinza/ativo azul) */
        .bottom-nav { 
            position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; 
            background: white; border-top: 1px solid #eee; 
            display: flex; justify-content: space-around; align-items: center; z-index: 1000;
        }
        .nav-btn { text-align: center; color: #9CA3AF; text-decoration: none; font-size: 10px; flex: 1; }
        .nav-btn i { font-size: 22px; display: block; margin-bottom: 2px; }
        .nav-btn.active { color: #3B82F6; }
        
        /* Layout Admin */
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 260px; background: #323637; border-right: 1px solid #444; position: fixed; height: 100%; padding: 20px; color: white; }
        .admin-content { flex: 1; margin-left: 260px; padding: 30px; background: #212425; color: white; }
        .admin-link { color: #ddd; text-decoration: none; display: block; padding: 10px; margin-bottom: 5px; border-radius: 5px; }
        .admin-link.active, .admin-link:hover { background: #0474cc; color: white; }
        
        @media(max-width:768px) { .admin-sidebar { display:none; } .admin-content { margin-left:0; padding:15px; } }
        
        /* Carregador */
        #loadingOverlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; display:none; flex-direction:column; align-items:center; justify-content:center; }
        #closeModalBtn { position: absolute; top: 20px; right: 20px; font-size: 30px; color: white; cursor: pointer; }
    </style>
</head>
<body>

<!-- LOADER / MODAL -->
<div id="loadingOverlay">
    <span id="closeModalBtn" onclick="closeModal()">&times;</span>
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
    <h4 id="loadingText" class="mt-3 text-white">Processando...</h4>
    <div id="modalContent" style="display:none; width: 90%; max-width: 400px;">
        <div class="card p-4 text-center bg-white text-dark">
            <div id="modalIcon" class="mb-3" style="font-size: 3rem;"></div>
            <h5 id="modalTitle" class="mb-2 fw-bold"></h5>
            <p id="modalMsg" class="small mb-4 text-muted"></p>
            <a id="waLink" href="#" target="_blank" class="btn btn-success w-100 rounded-pill py-2 fw-bold">
                <i class="fab fa-whatsapp me-2"></i> FALAR COM GERENTE
            </a>
        </div>
    </div>
</div>

<?php if ($role == 'user'): ?>
    <!-- CABEÇALHO APP -->
    <div class="app-header-top">
        <div class="app-logo"><i class="fas fa-chart-line me-2"></i> <?=$site_name?></div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end" style="line-height:1.2">
                <div style="font-size:12px">ID: <?=$currentUser['id']?></div>
                <div style="font-size:10px; opacity:0.8">VIP 1</div>
            </div>
            <div style="width:32px; height:32px; background:#eee; border-radius:50%; color:#333; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?=strtoupper(substr($currentUser['name'],0,1))?>
            </div>
        </div>
    </div>

    <div class="app-container">
        
        <?php if($page == 'home'): 
             $invs = $pdo->query("SELECT * FROM user_investments WHERE user_id = $user_id AND status = 'active'")->fetchAll();
             $total_invested = 0; $today_yield = 0; $total_yield = 0; // Mocked total yield
             foreach($invs as $i) {
                 $total_invested += $i['amount'];
                 $plan = $pdo->query("SELECT * FROM plans WHERE id={$i['plan_id']}")->fetch();
                 $today_yield += $i['amount'] * ($plan['daily_percent']/100);
             }
             $refLink = getenv('BASE_URL') . "?ref=" . $currentUser['username'];
             $dashBanners = $pdo->query("SELECT * FROM dashboard_banners ORDER BY id DESC")->fetchAll();
        ?>
            <!-- CARTÃO SALDO (PRETO COM BOTÕES COLORIDOS) -->
            <div class="balance-card-user">
                <div>
                    <div style="font-size:12px; opacity:0.7">Saldo Total</div>
                    <div style="font-size:24px; font-weight:bold">R$ <?=number_format($currentUser['balance'],2,',','.')?></div>
                </div>
                <div class="d-flex gap-2">
                    <a href="?page=deposit" class="btn-user-action btn-recharge">Recarregar</a>
                    <a href="?page=withdraw" class="btn-user-action btn-withdraw">Retirada</a>
                </div>
            </div>

            <!-- SLIDER DE BANNERS -->
            <?php if(count($dashBanners)>0): ?>
            <div id="dashSlider" class="carousel slide mb-3 mx-3 rounded-4 overflow-hidden shadow-sm" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php foreach($dashBanners as $k=>$b): ?>
                    <div class="carousel-item <?=$k==0?'active':''?>">
                        <a href="<?=$b['link_url']?:'#'?>"><img src="<?=$b['image_path']?>" class="d-block w-100" style="height:140px; object-fit:cover;"></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- GRELHA DE ESTATÍSTICAS (4 CORES) -->
            <div class="stats-grid">
                <div class="stat-item blue">
                    <div class="stat-label">Investimento Total</div>
                    <div class="stat-value">R$ <?=number_format($total_invested,0,',','.')?></div>
                </div>
                <div class="stat-item green">
                    <div class="stat-label">Total Retiradas</div>
                    <div class="stat-value">R$ 0</div> <!-- Placeholder -->
                </div>
                <div class="stat-item orange">
                    <div class="stat-label">Receita de Hoje</div>
                    <div class="stat-value">R$ <?=number_format($today_yield,2,',','.')?></div>
                </div>
                <div class="stat-item red">
                    <div class="stat-label">Rendimento Total</div>
                    <div class="stat-value">R$ 0</div> <!-- Placeholder -->
                </div>
            </div>
            
            <!-- MENU DE LISTA SIMPLES -->
            <div class="bg-white mx-3 rounded-4 p-2 shadow-sm">
                <div onclick="copyLink('<?=$refLink?>')" class="d-flex align-items-center p-3 border-bottom">
                    <i class="fas fa-share-alt text-primary me-3 fs-5"></i> 
                    <span class="flex-grow-1 text-dark fw-bold">Convide Amigos</span> 
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
                <a href="?page=support" class="d-flex align-items-center p-3 text-decoration-none">
                    <i class="fas fa-headset text-warning me-3 fs-5"></i> 
                    <span class="flex-grow-1 text-dark fw-bold">Suporte Online</span> 
                    <i class="fas fa-chevron-right text-muted"></i>
                </a>
            </div>

        <?php endif; ?>

        <?php if($page == 'invest'): ?>
            <div class="px-3 pb-5">
                <h5 class="mb-3 fw-bold text-dark">Produtos de Investimento</h5>
                <?php $plans = $pdo->query("SELECT * FROM plans ORDER BY min_amount ASC"); while($p=$plans->fetch()): 
                     $img = $p['image_path'] ? $p['image_path'] : "img/img{$p['id']}.jpeg";
                     if(!file_exists($img) && !$p['image_path']) $img = 'https://via.placeholder.com/100';
                     
                     $lucro = $p['min_amount'] * ($p['daily_percent']/100);
                     $total = $p['min_amount'] + ($lucro * $p['days']);
                ?>
                <div class="card mb-3 border-0 shadow-sm overflow-hidden">
                    <div class="d-flex p-3 align-items-center">
                        <img src="<?=$img?>" style="width:80px; height:80px; object-fit:cover; border-radius:10px;" class="me-3">
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark mb-1"><?=$p['name']?></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Rend. Diário</div>
                                    <div class="text-warning fw-bold small">R$ <?=number_format($lucro,2,',','.')?></div>
                                </div>
                                <div>
                                    <div class="text-muted small">Ciclo</div>
                                    <div class="text-dark fw-bold small"><?=$p['days']?> Dias</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-light p-2 d-flex justify-content-between align-items-center px-3">
                        <div class="fw-bold text-dark">R$ <?=number_format($p['min_amount'],2,',','.')?></div>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="buy_plan" value="1">
                            <input type="hidden" name="plan_id" value="<?=$p['id']?>">
                            <input type="hidden" name="amount" value="<?=$p['min_amount']?>">
                            <button class="btn btn-primary btn-sm px-4 rounded-pill">Comprar</button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <?php if($page == 'deposit'): ?>
            <div class="px-3 text-center pt-4">
                <i class="fas fa-qrcode fa-4x text-primary mb-3"></i>
                <h4 class="text-dark">Recarregar</h4>
                <input type="number" id="depVal" class="form-control form-control-lg text-center mt-4 mb-3 shadow-sm" placeholder="R$ 0,00">
                <button onclick="handleAction('dep')" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow">GERAR PIX</button>
            </div>
        <?php endif; ?>

        <?php if($page == 'withdraw'): ?>
            <div class="px-3 pt-3">
                <div class="card p-3 mb-3 border-0 shadow-sm">
                    <h6 class="text-dark mb-3">Chave PIX</h6>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="save_pix" value="1">
                        <select name="pix_type" class="form-select w-auto bg-light text-dark border-0"><option value="cpf">CPF</option><option value="email">Email</option></select>
                        <input name="pix_key" class="form-control bg-light text-dark border-0" value="<?=$currentUser['pix_key']?>" placeholder="Chave">
                        <button class="btn btn-sm btn-primary rounded-circle"><i class="fas fa-save"></i></button>
                    </form>
                </div>
                <h4 class="text-dark text-center mt-4">Solicitar Saque</h4>
                <p class="text-muted text-center small">Disponível: R$ <?=number_format($currentUser['balance'],2,',','.')?></p>
                <input type="number" id="withVal" class="form-control form-control-lg text-center mb-3 shadow-sm" placeholder="Valor R$">
                <button onclick="handleAction('with')" class="btn btn-danger w-100 py-3 rounded-pill fw-bold shadow">SOLICITAR</button>
            </div>
        <?php endif; ?>

        <?php if($page == 'active_plans'): ?>
            <div class="px-3 pt-3">
                <h5 class="text-dark mb-3">Rendimentos</h5>
                <?php $invs = $pdo->query("SELECT ui.*, p.name FROM user_investments ui JOIN plans p ON ui.plan_id=p.id WHERE user_id=$user_id ORDER BY id DESC");
                if($invs->rowCount()>0): while($i = $invs->fetch()): ?>
                <div class="card p-3 mb-2 border-0 shadow-sm border-start border-5 border-success">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-dark"><?=$i['name']?></span>
                        <span class="badge bg-success">Ativo</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small text-muted">
                        <span>Investido: <b>R$ <?=number_format($i['amount'],2)?></b></span>
                        <span>Fim: <b><?=date('d/m', strtotime($i['end_date']))?></b></span>
                    </div>
                </div>
                <?php endwhile; else: echo "<p class='text-center text-muted mt-5'>Você não tem planos ativos.</p>"; endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if($page == 'profile'): ?>
            <div class="px-3 pt-3">
                <h5 class="text-dark mb-3">Meu Perfil</h5>
                <form method="POST" class="bg-white p-3 rounded-4 shadow-sm">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="mb-2"><label class="text-muted small">Nome</label><input name="name" value="<?=$currentUser['name']?>" class="form-control bg-light text-dark border-0"></div>
                    <div class="mb-2"><label class="text-muted small">Email</label><input name="email" value="<?=$currentUser['email']?>" class="form-control bg-light text-dark border-0"></div>
                    <div class="mb-2"><label class="text-muted small">WhatsApp</label><input name="whatsapp" value="<?=$currentUser['whatsapp']?>" class="form-control bg-light text-dark border-0"></div>
                    <div class="mb-2"><label class="text-muted small">Nova Senha</label><input name="password" type="password" class="form-control bg-light text-dark border-0"></div>
                    <button class="btn btn-primary w-100 mt-3 rounded-pill">Salvar</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if($page == 'support'): ?>
            <div class="px-3 pt-3">
                <h5 class="text-dark mb-3">Suporte</h5>
                <div class="card p-3 border-0 shadow-sm mb-3">
                    <form method="POST">
                        <input type="hidden" name="create_ticket" value="1">
                        <input name="subject" class="form-control mb-2 bg-light text-dark border-0" placeholder="Assunto" required>
                        <textarea name="message" class="form-control mb-2 bg-light text-dark border-0" placeholder="Mensagem" rows="3" required></textarea>
                        <button class="btn btn-primary w-100 rounded-pill">Enviar Ticket</button>
                    </form>
                </div>
                <div class="list-group">
                    <?php $ts=$pdo->query("SELECT * FROM support_tickets WHERE user_id=$user_id ORDER BY id DESC"); while($t=$ts->fetch()){ ?>
                        <a href="?page=view_ticket&id=<?=$t['id']?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>#<?=$t['id']?> <?=$t['subject']?></span>
                            <span class="badge bg-secondary"><?=$t['status']?></span>
                        </a>
                    <?php } ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if($page == 'view_ticket'): $tid=$_GET['id']; $msgs=$pdo->query("SELECT * FROM ticket_messages WHERE ticket_id=$tid"); ?>
            <div class="px-3 pt-3">
                <a href="?page=support" class="text-muted mb-3 d-block"><i class="fas fa-arrow-left"></i> Voltar</a>
                <div class="card p-3 border-0 shadow-sm bg-light" style="max-height:400px;overflow:auto">
                    <?php while($m=$msgs->fetch()){ 
                        $align = $m['sender_id']==$user_id ? 'text-end' : 'text-start';
                        $bg = $m['sender_id']==$user_id ? 'bg-primary text-white' : 'bg-white text-dark shadow-sm';
                        echo "<div class='$align mb-2'><span class='badge $bg p-2 fw-normal' style='white-space:normal; text-align:left; font-size:14px;'>{$m['message']}</span></div>";
                    } ?>
                </div>
                <form method="POST" class="mt-2 d-flex gap-2">
                    <input type="hidden" name="reply_ticket_user" value="1"><input type="hidden" name="ticket_id" value="<?=$tid?>">
                    <input name="message" class="form-control rounded-pill border-0 shadow-sm" placeholder="Responder...">
                    <button class="btn btn-success rounded-circle"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        <?php endif; ?>
    
    </div>

    <!-- BARRA INFERIOR -->
    <div class="bottom-nav">
        <a href="?page=home" class="nav-btn <?= $page=='home'?'active':'' ?>">
            <i class="fas fa-home"></i> Início
        </a>
        <a href="?page=invest" class="nav-btn <?= $page=='invest'?'active':'' ?>">
            <i class="fas fa-layer-group"></i> Produto
        </a>
        <a href="#" class="nav-btn"> <!-- Icone Membro -->
            <i class="fab fa-viacoin text-warning" style="font-size:32px;"></i>
        </a>
        <a href="?page=active_plans" class="nav-btn <?= $page=='active_plans'?'active':'' ?>">
            <i class="fas fa-chart-line"></i> Rendimento
        </a>
        <a href="?page=profile" class="nav-btn <?= $page=='profile'?'active':'' ?>">
            <i class="fas fa-user"></i> Utilizador
        </a>
    </div>
</div>
<?php endif; ?>


<!-- =======================
     DASHBOARD ADMIN (DESKTOP)
     ======================= -->
<?php if ($role == 'admin'): ?>
<div class="admin-wrapper">
    <div class="admin-sidebar">
        <h4 class="text-center mb-4 text-primary">ADMIN</h4>
        <a href="?page=home" class="admin-link <?= $page=='home'?'active':'' ?>"><i class="fas fa-home me-2"></i> Home</a>
        <a href="?page=banners" class="admin-link <?= $page=='banners'?'active':'' ?>"><i class="fas fa-images me-2"></i> Banners</a>
        <a href="?page=add_user" class="admin-link <?= $page=='add_user'?'active':'' ?>"><i class="fas fa-user-plus me-2"></i> Novo Usuário</a>
        <a href="?page=users" class="admin-link <?= $page=='users'?'active':'' ?>"><i class="fas fa-users me-2"></i> Usuários</a>
        <a href="?page=deposits" class="admin-link <?= $page=='deposits'?'active':'' ?>"><i class="fas fa-wallet me-2"></i> Depósitos</a>
        <a href="?page=balances" class="admin-link <?= $page=='balances'?'active':'' ?>"><i class="fas fa-money-bill me-2"></i> Saldos</a>
        <a href="?page=plans_manage" class="admin-link <?= $page=='plans_manage'?'active':'' ?>"><i class="fas fa-list-alt me-2"></i> Planos</a>
        <a href="?page=settings" class="admin-link <?= $page=='settings'?'active':'' ?>"><i class="fas fa-cogs me-2"></i> Configurações</a>
        <a href="?page=support" class="admin-link <?= $page=='support'?'active':'' ?>"><i class="fas fa-headset me-2"></i> Suporte</a>
        <a href="?page=logs" class="admin-link <?= $page=='logs'?'active':'' ?>"><i class="fas fa-list me-2"></i> Logs</a>
        <a href="auth.php?logout=true" class="text-danger mt-4 d-block p-2"><i class="fas fa-sign-out-alt me-2"></i> Sair</a>
    </div>
    <div class="admin-content">
        <?php 
            // ... [CÓDIGO DAS PÁGINAS DE ADMIN AQUI - IDÊNTICO AO ANTERIOR] ...
            
            if($page == 'home'): 
                $nu = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $inv = $pdo->query("SELECT SUM(amount) FROM user_investments")->fetchColumn();
                echo "<div class='row'><div class='col-md-4'><div class='card p-4'><h3>$nu</h3><small>Usuários</small></div></div><div class='col-md-4'><div class='card p-4'><h3>R$ ".number_format($inv,2)."</h3><small>Investido</small></div></div></div>";
            endif;

            if($page == 'plans_manage'): ?>
                <form method="POST" class="card p-3 mb-3" enctype="multipart/form-data">
                    <input type="hidden" name="add_plan" value="1">
                    <h3>Criar Plano</h3>
                    <div class="row g-2">
                        <div class="col"><input name="name" placeholder="Nome" class="form-control"></div>
                        <div class="col"><input name="daily" placeholder="%" class="form-control"></div>
                        <div class="col"><input name="days" placeholder="Dias" class="form-control"></div>
                        <div class="col"><input name="min" placeholder="Min" class="form-control"></div>
                        <div class="col"><input name="max" placeholder="Max" class="form-control"></div>
                        <div class="col"><input type="file" name="plan_image" class="form-control"></div>
                        <div class="col"><button class="btn btn-primary">Add</button></div>
                    </div>
                </form>
                <div class="card p-3"><table class="table"><thead><tr><th>Nome</th><th>Min</th><th>Img</th><th>Ação</th></tr></thead><tbody><?php $ps=$pdo->query("SELECT * FROM plans"); while($p=$ps->fetch()){ echo "<tr><td>{$p['name']}</td><td>{$p['min_amount']}</td><td>".($p['image_path']?'Sim':'Não')."</td><td><form method='POST'><input type='hidden' name='delete_plan' value='1'><input type='hidden' name='plan_id_delete' value='{$p['id']}'><button class='btn btn-sm btn-danger'>X</button></form></td></tr>"; } ?></tbody></table></div>
            <?php endif; 
            
            // ... (Outras páginas admin: users, add_user, deposits, etc.) ...
            if($page == 'add_user') { /* Form de Adicionar Usuário */ ?>
            <form method="POST" class="card p-4">
                <h3>Novo Usuário</h3>
                <input type="hidden" name="create_user_admin" value="1">
                <div class="row g-2">
                    <div class="col-md-6"><label>Nome</label><input name="name" class="form-control" required></div>
                    <div class="col-md-6"><label>Email</label><input name="email" class="form-control" required></div>
                    <div class="col-md-4"><label>CPF</label><input name="cpf" class="form-control"></div>
                    <div class="col-md-4"><label>WhatsApp</label><input name="whatsapp" class="form-control"></div>
                    <div class="col-md-4"><label>Senha</label><div class="input-group"><input name="password" id="np" class="form-control" required><button type="button" class="btn btn-secondary" onclick="document.getElementById('np').value=Math.random().toString(36).slice(-6)">Gerar</button></div></div>
                    <div class="col-md-6"><label>Confirma</label><input name="confirm_password" class="form-control" required></div>
                    <div class="col-md-6"><label>Saldo Inicial</label><input name="balance" class="form-control" value="0"></div>
                    <div class="col-md-4"><label>Role</label><select name="role" class="form-select"><option value="user">User</option><option value="admin">Admin</option></select></div>
                    <div class="col-md-6"><label>Padrinho</label><select name="referrer_id" class="form-select"><option value="">--</option><?php $rs=$pdo->query("SELECT id,name FROM users"); while($r=$rs->fetch()){ echo "<option value='{$r['id']}'>{$r['name']}</option>"; } ?></select></div>
                    <div class="col-md-6"><label>Plano Imediato</label><select name="plan_id" class="form-select"><option value="">--</option><?php $ps=$pdo->query("SELECT id,name FROM plans"); while($p=$ps->fetch()){ echo "<option value='{$p['id']}'>{$p['name']}</option>"; } ?></select></div>
                    <div class="col-md-6"><label>Valor Inv.</label><input name="investment_amount" class="form-control"></div>
                </div>
                <button class="btn btn-success mt-3">Cadastrar</button>
            </form>
            <?php }
            if($page == 'users') { /* Lista Usuários */ ?>
            <div class="card p-4"><h3>Usuários</h3><table class="table table-hover"><thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Saldo</th><th>Ação</th></tr></thead><tbody><?php $u=$pdo->query("SELECT * FROM users ORDER BY id DESC"); while($r=$u->fetch()){ echo "<tr><td>{$r['id']}</td><td>{$r['name']}</td><td>{$r['email']}</td><td>R$ ".number_format($r['balance'],2,',','.')."</td><td><a href='?page=edit_user&id={$r['id']}' class='btn btn-sm btn-info'>Edit</a> <form method='POST' style='display:inline' onsubmit='return confirm(\"Del?\")'><input type='hidden' name='delete_user' value='1'><input type='hidden' name='user_id_delete' value='{$r['id']}'><button class='btn btn-sm btn-danger'>X</button></form></td></tr>"; } ?></tbody></table></div>
            <?php }
            if($page == 'deposits') { /* Lista Depósitos */ ?>
            <div class="card p-4 mb-3"><h5>Adicionar Manual</h5><form method="POST" class="row g-2"><input type="hidden" name="add_manual_deposit" value="1"><div class="col-8"><select name="deposit_user_id" class="form-select"><?php $us=$pdo->query("SELECT id,email FROM users"); while($u=$us->fetch()){ echo "<option value='{$u['id']}'>{$u['email']}</option>"; } ?></select></div><div class="col-2"><input name="amount" class="form-control" placeholder="Valor"></div><div class="col-2"><button class="btn btn-primary w-100">Add</button></div></form></div>
            <div class="card p-4"><h5>Pendentes</h5><table class="table"><thead><tr><th>User</th><th>Valor</th><th>Ação</th></tr></thead><tbody><?php $dp=$pdo->query("SELECT t.*, u.email FROM transactions t JOIN users u ON t.user_id=u.id WHERE t.type='deposit' AND t.status='pending'"); while($d=$dp->fetch()){ echo "<tr><td>{$d['email']}</td><td>R$ {$d['amount']}</td><td><form method='POST' style='display:inline'><input type='hidden' name='approve_deposit' value='1'><input type='hidden' name='trans_id' value='{$d['id']}'><input type='hidden' name='user_id_trans' value='{$d['user_id']}'><input type='hidden' name='amount' value='{$d['amount']}'><button class='btn btn-success btn-sm'>OK</button></form> <form method='POST' style='display:inline'><input type='hidden' name='delete_transaction' value='1'><input type='hidden' name='trans_id' value='{$d['id']}'><button class='btn btn-danger btn-sm'>X</button></form></td></tr>"; } ?></tbody></table></div>
            <?php }
            if($page == 'balances') { /* Lista Saldos */ ?>
            <div class="card p-4"><h3>Saldos</h3><table class="table"><thead><tr><th>User</th><th>Saldo</th></tr></thead><tbody><?php $us=$pdo->query("SELECT name, balance FROM users ORDER BY balance DESC"); while($u=$us->fetch()){ echo "<tr><td>{$u['name']}</td><td class='text-success'>R$ {$u['balance']}</td></tr>"; } ?></tbody></table></div>
            <?php }
            if($page == 'settings') { /* Form Configs */ ?>
            <form method="POST" class="card p-4"><input type="hidden" name="update_settings" value="1"><label>CPA %</label><input name="cpa" value="<?=getSetting($pdo,'cpa_percentage')?>" class="form-control mb-2"><label>WhatsApp</label><input name="whatsapp" value="<?=getSetting($pdo,'whatsapp_number')?>" class="form-control mb-2"><button class="btn btn-primary">Salvar</button></form>
            <?php }
            if($page == 'support') { /* Lista Tickets */ ?>
            <div class="card p-4"><table class="table"><thead><tr><th>ID</th><th>Assunto</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php $ts=$pdo->query("SELECT * FROM support_tickets ORDER BY id DESC"); while($t=$ts->fetch()){ echo "<tr><td>{$t['id']}</td><td>{$t['subject']}</td><td>{$t['status']}</td><td><a href='?page=view_ticket&id={$t['id']}' class='btn btn-sm btn-info'>Ver</a></td></tr>"; } ?></tbody></table></div>
            <?php }
            if($page == 'view_ticket') { /* Chat Ticket */ 
            $tid=$_GET['id']; $msgs=$pdo->query("SELECT * FROM ticket_messages WHERE ticket_id=$tid"); ?>
            <div class="card p-3 mb-3 bg-secondary text-white" style="max-height:300px;overflow:auto"><?php while($m=$msgs->fetch()){ echo "<div class='p-2 border-bottom'>{$m['message']}</div>"; } ?></div>
            <form method="POST"><input type="hidden" name="reply_ticket_admin" value="1"><input type="hidden" name="ticket_id" value="<?=$tid?>"><textarea name="message" class="form-control mb-2"></textarea><button class="btn btn-success">Responder</button></form>
            <?php }
            if($page == 'logs') { /* Tabela Logs */ ?>
            <div class="card p-4"><table class="table table-sm"><thead><tr><th>Data</th><th>Ação</th></tr></thead><tbody><?php $ls=$pdo->query("SELECT * FROM system_logs ORDER BY id DESC LIMIT 50"); while($l=$ls->fetch()){ echo "<tr><td>{$l['created_at']}</td><td>{$l['action']}</td></tr>"; } ?></tbody></table></div>
            <?php }
            if($page == 'banners') { /* Upload Banners */ ?>
            <div class="row"><div class="col-md-6 card p-3"><h5>Site</h5><form method="POST" enctype="multipart/form-data"><input type="hidden" name="upload_banner" value="1"><input type="hidden" name="banner_type" value="site"><input type="file" name="banner_image" class="form-control mb-2" required><button class="btn btn-primary">Upload</button></form></div><div class="col-md-6 card p-3"><h5>Dash User</h5><form method="POST" enctype="multipart/form-data"><input type="hidden" name="upload_banner" value="1"><input type="hidden" name="banner_type" value="dashboard"><input type="file" name="banner_image" class="form-control mb-2" required><input name="banner_link" placeholder="Link" class="form-control mb-2"><input name="banner_desc" placeholder="Desc" class="form-control mb-2"><button class="btn btn-primary">Upload</button></form></div></div>
            <?php }
            if($page == 'edit_user' && isset($_GET['id'])) { $u=$pdo->query("SELECT * FROM users WHERE id=".$_GET['id'])->fetch(); ?>
            <div class="card p-4"><form method="POST"><input type="hidden" name="edit_user_admin" value="1"><input type="hidden" name="user_id" value="<?=$u['id']?>"><div class="row g-2"><div class="col-md-6"><label>Nome</label><input name="name" value="<?=$u['name']?>" class="form-control"></div><div class="col-md-6"><label>Email</label><input name="email" value="<?=$u['email']?>" class="form-control"></div><div class="col-md-4"><label>Saldo</label><input name="balance" value="<?=$u['balance']?>" class="form-control"></div><div class="col-md-4"><label>CPF</label><input name="cpf" value="<?=$u['cpf']?>" class="form-control"></div><div class="col-md-4"><label>Role</label><select name="role" class="form-select"><option value="user" <?=$u['role']=='user'?'selected':''?>>User</option><option value="admin" <?=$u['role']=='admin'?'selected':''?>>Admin</option></select></div></div><button class="btn btn-primary mt-3">Salvar</button></form> <?php if($u['id']!=1): ?><form method="POST" class="mt-2" onsubmit="return confirm('Del?')"><input type="hidden" name="delete_user" value="1"><input type="hidden" name="user_id_delete" value="<?=$u['id']?>"><button class="btn btn-danger w-100">Excluir</button></form><?php endif; ?></div>
            <?php } ?>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function copyLink(text) { navigator.clipboard.writeText(text); alert('Link copiado!'); }
    function handleAction(type) {
        let val = document.getElementById(type=='dep'?'depVal':'withVal').value;
        if(!val) return alert('Digite um valor');
        
        document.getElementById('loadingOverlay').style.display = 'flex';
        document.getElementById('loadingText').innerText = 'Conectando...';
        document.getElementById('modalContent').style.display = 'none';
        document.getElementById('closeModalBtn').style.display = 'none';
        
        setTimeout(() => {
            document.querySelector('.spinner-border').style.display = 'none';
            document.getElementById('loadingText').innerText = '';
            
            let icon = type=='dep' ? '<i class="fas fa-headset text-warning"></i>' : '<i class="fas fa-exclamation-circle text-danger"></i>';
            let title = type=='dep' ? 'Suporte Financeiro' : 'Erro na Chave';
            let msg = type=='dep' ? 'Contacte o suporte:' : 'Ocorreu um Erro com sua Chave PIX, contacte o suporte:';
            let txt = type=='dep' ? `Olá, sou o usuário de e-mail: <?=$currentUser['email']?> e preciso ter o Deposito de R$${val} aprovado para a minha conta.` : `Olá, sou o usuário de e-mail: <?=$currentUser['email']?> e preciso ter o saque de R$${val} aprovado para a minha conta.`;
            
            document.getElementById('modalIcon').innerHTML = icon;
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalMsg').innerText = msg;
            document.getElementById('waLink').href = `https://wa.me/<?=$whatsapp_number?>?text=${encodeURIComponent(txt)}`;
            
            document.getElementById('modalContent').style.display = 'block';
            document.getElementById('closeModalBtn').style.display = 'block';
        }, 2000);
    }
    function closeModal() { 
        document.getElementById('loadingOverlay').style.display = 'none'; 
        document.querySelector('.spinner-border').style.display = 'block'; 
    }
</script>
</body>
</html>