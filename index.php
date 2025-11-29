<?php
require 'db.php';
require 'functions.php';

// --- Configurações ---
$home_title = getSetting($pdo, 'home_title', 'MELHORES PLANOS DE INVESTIMENTOS');
$home_subtitle = getSetting($pdo, 'home_subtitle', 'Uma plataforma lucrativa para investimento de alta margem.');
$seo_title = getSetting($pdo, 'seo_title', 'Hyip Pro | Investimentos');
$site_name = getSetting($pdo, 'site_name', 'Império Investimentos');
$whatsapp_number = getSetting($pdo, 'whatsapp_number', '5511999999999');

// Imagens
$logoPath = getSetting($pdo, 'site_logo');
$faviconPath = getSetting($pdo, 'site_favicon');
$heroBg = getSetting($pdo, 'home_hero_bg'); // Imagem de fundo

// Banners Slides Principal (Topo)
$banners = $pdo->query("SELECT * FROM site_banners ORDER BY id DESC")->fetchAll();

// Link de Referência (se existir)
$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(getSetting($pdo, 'seo_description')); ?>" />
    <meta name="author" content="<?php echo htmlspecialchars($site_name); ?>" />
    
    <?php if($faviconPath): ?><link rel="icon" href="<?php echo $faviconPath; ?>"><?php endif; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* PALETA DE CORES DINÂMICA (Configurável no Admin) */
        :root {
            --bg-body: <?php echo getSetting($pdo, 'color_bg', '#ffffff'); ?>;
            --primary: <?php echo getSetting($pdo, 'color_primary', '#000080'); ?>;       /* Azul Escuro (Lowify) */
            --secondary: <?php echo getSetting($pdo, 'color_secondary', '#4ac9ec'); ?>;     /* Azul Claro */
            --text-main: <?php echo getSetting($pdo, 'color_text', '#ffffff'); ?>;
            --bg-card: <?php echo getSetting($pdo, 'color_card_bg', '#f0f0f0'); ?>;
            --card-border: <?php echo getSetting($pdo, 'color_card_border', '#ffffff'); ?>;
            --btn-primary: <?php echo getSetting($pdo, 'color_btn_primary', '#00ff1e'); ?>; /* Botão Principal */
            --btn-text: <?php echo getSetting($pdo, 'color_btn_text', '#ffffff'); ?>;
            --success: <?php echo getSetting($pdo, 'color_success', '#00ff04'); ?>;
        }

        /* --- Global Reset/Base --- */
        body { 
            background-color: var(--bg-body); 
            color: #333; /* Cor base mais escura para melhor contraste em fundos claros */
            font-family: 'Urbanist', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* --- Header/Navbar Topo --- */
        .main-header {
            background-color: var(--bg-body);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--card-border);
        }
        .header-logo { font-weight: 800; color: var(--primary) !important; font-size: 1.5rem; }
        
        /* Links de Navegação */
        .nav-link-custom {
            color: #333 !important;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: 50px;
            transition: all 0.2s;
        }
        .nav-link-custom:hover {
            color: var(--primary) !important;
        }

        /* Botão Principal */
        .btn-custom {
            background-color: var(--primary);
            color: var(--btn-text);
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 700;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        .btn-outline-custom {
             border: 2px solid var(--primary);
             color: var(--primary);
             background-color: transparent;
             border-radius: 50px;
             padding: 10px 25px;
             font-weight: 700;
             transition: all 0.3s ease;
        }
        .btn-outline-custom:hover {
            background-color: var(--primary);
            color: var(--btn-text);
        }

        /* --- Hero Section (Section 1) --- */
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?php echo $heroBg ? $heroBg : 'https://hyip-pro.bugfinder.app/assets/upload/contents/BTGfMYjIow86Z5i9TsmUGZdB3C68gt.webp'; ?>') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
        }
        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 40px;
            opacity: 0.9;
        }
        
        /* Responsividade Hero */
        @media (max-width: 768px) {
            .hero-section { padding: 80px 0; }
            .hero-title { font-size: 2rem; }
            .hero-subtitle { font-size: 1rem; }
            .btn-lg { padding: 10px 30px !important; font-size: 1rem; }
        }

        /* --- Section de Destaque (Section 2 - Features) --- */
        .features-section {
            padding: 50px 0;
            background-color: var(--bg-card); /* Fundo claro para contraste */
            border-bottom: 1px solid var(--card-border);
        }
        .feature-item {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .feature-item i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        .feature-item h5 {
            font-weight: 700;
            color: #333;
        }

        /* --- Planos (Section 3) --- */
        .pricing-section { padding: 80px 0; }
        .pricing-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .pricing-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
            border-color: var(--primary);
        }
        .pricing-card h3 { color: var(--primary); font-weight: 800; margin-bottom: 15px; font-size: 1.8rem; }
        .pricing-card .price-tag { font-size: 2.5rem; font-weight: 800; color: #333; margin-bottom: 5px; }
        .pricing-card .period { color: #888; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px; }
        .pricing-card ul { list-style: none; padding: 0; margin-bottom: 30px; text-align: left; font-size: 1rem; }
        .pricing-card ul li { margin-bottom: 10px; color: #555; display: flex; align-items: center; }
        .pricing-card ul li i { color: var(--success); margin-right: 10px; width: 20px; text-align: center; }

        /* --- Footer --- */
        .main-footer {
            background-color: var(--bg-card);
            border-top: 1px solid var(--card-border);
            padding: 40px 0;
            margin-top: auto; 
            color: #555;
            text-align: center;
        }
        .footer-link { color: var(--primary); text-decoration: none; font-weight: 600; }
        .footer-link:hover { text-decoration: underline; }
        
        /* Ocultar elementos desktop no mobile e vice-versa */
        @media (min-width: 992px) {
            .navbar-mobile { display: none !important; }
        }
        @media (max-width: 991.98px) {
            .navbar-desktop { display: none !important; }
            .hero-section { text-align: left; padding: 50px 0; }
            .hero-title { font-size: 2.2rem; }
        }
        
        /* Modal Customizado */
        .modal-content { border-radius: 15px; border: none; }
        .modal-header { border-bottom: none; }
    </style>
</head>
<body>

<header class="main-header navbar-desktop">
    <div class="container">
        <nav class="navbar navbar-expand-lg">
            <a class="navbar-brand header-logo" href="#">
                <?php if($logoPath) echo "<img src='$logoPath' alt='$site_name' style='max-height: 40px;'>"; else echo strtoupper($site_name); ?>
            </a>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" href="#planos">Planos</a></li>
                    <li class="nav-item"><a class="nav-link nav-link-custom" target="_blank" href="<?php echo gerarLinkWhatsApp($whatsapp_number, 'Olá, preciso de suporte.'); ?>">Suporte</a></li>
                    <li class="nav-item ms-3">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="btn btn-outline-custom me-2">Login</a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="btn btn-custom">Cadastrar</a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</header>

<header class="main-header navbar-mobile">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand header-logo" href="#">
            <?php if($logoPath) echo "<img src='$logoPath' alt='$site_name' style='max-height: 35px;'>"; else echo strtoupper($site_name); ?>
        </a>
        <div class="d-flex gap-2">
            <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="btn btn-outline-custom btn-sm">Login</a>
            <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="btn btn-custom btn-sm">Cadastrar</a>
        </div>
    </div>
</header>

<section class="hero-section">
    <div class="container">
        <div class="row justify-content-center text-start text-lg-center">
            <div class="col-lg-10">
                <h1 class="hero-title"><?php echo htmlspecialchars($home_title); ?></h1>
                <p class="hero-subtitle"><?php echo htmlspecialchars($home_subtitle); ?></p>
                <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="btn btn-custom btn-lg px-5 me-3">Começar Grátis</a>
                <a href="#planos" class="btn btn-outline-light btn-lg px-5">Ver Planos</a>
            </div>
        </div>
    </div>
</section>

<section class="features-section">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="feature-item">
                    <i class="fas fa-wallet"></i>
                    <h5>Checkout Instantâneo</h5>
                    <p class="text-muted small">Converta mais rápido com nossa finalização de compra otimizada.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="feature-item">
                    <i class="fas fa-arrow-alt-circle-up"></i>
                    <h5>Upsell 1 Clique</h5>
                    <p class="text-muted small">Aumente o valor do pedido com ofertas irresistíveis pós-compra.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-item">
                    <i class="fab fa-whatsapp"></i>
                    <h5>Recuperação WhatsApp</h5>
                    <p class="text-muted small">Recupere vendas perdidas com automação nativa no WhatsApp.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="planos" class="pricing-section">
    <div class="container">
        <div class="text-center mb-5">
            <h6 style="color:var(--primary); font-weight:700; letter-spacing:1px; text-transform:uppercase;">Investimentos</h6>
            <h2 class="fw-bold display-5">Escolha Seu Plano</h2>
        </div>
        
        <div class="row g-4 justify-content-center">
            <?php
            $stmt = $pdo->query("SELECT * FROM plans ORDER BY min_amount ASC");
            while ($plan = $stmt->fetch()) {
                $daily_return_value = $plan['min_amount'] * ($plan['daily_percent'] / 100);
                $total_return_percent = $plan['daily_percent'] * $plan['days'];
                $total_return_value = $daily_return_value * $plan['days'];
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="pricing-card">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0"><?php echo htmlspecialchars($plan['name']); ?></h3>
                            <span class="badge" style="background-color: var(--primary); color: var(--btn-text);">Novo</span>
                        </div>
                        
                        <div class="price-tag">R$ <?php echo number_format($plan['min_amount'], 2, ',', '.'); ?></div>
                        <div class="period">Investimento Mínimo</div>
                        
                        <hr style="border-color:var(--card-border); opacity:0.5;">
                        
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Retorno Diário: <strong style="color:var(--primary);" class="ms-1">R$ <?php echo number_format($daily_return_value, 2, ',', '.'); ?></strong> (<?php echo number_format($plan['daily_percent'], 2); ?>%)</li>
                            <li><i class="fas fa-check-circle"></i> Duração do Ciclo: <strong style="color:var(--primary);" class="ms-1"><?php echo $plan['days']; ?> dias</strong></li>
                            <li><i class="fas fa-check-circle"></i> Rendimento Total: <strong style="color:var(--primary);" class="ms-1">R$ <?php echo number_format($total_return_value, 2, ',', '.'); ?></strong> (<?php echo number_format($total_return_percent, 2); ?>%)</li>
                            <li><i class="fas fa-check-circle"></i> Suporte <strong style="color:var(--primary);" class="ms-1">Dedicado</strong></li>
                        </ul>
                    </div>
                    <button class="btn btn-custom w-100 mt-3" data-bs-toggle="modal" data-bs-target="#registerModal">Investir Agora</button>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>

<section class="py-5 text-center" style="background-color: var(--primary); color: var(--btn-text);">
    <div class="container">
        <h2 class="fw-bold">Pronto para Começar a Vender?</h2>
        <p class="lead mb-4">Crie sua conta grátis e comece a lucrar em 5 minutos.</p>
        <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="btn btn-lg btn-outline-light" style="border-color: var(--btn-text); color: var(--btn-text);">Criar Minha Conta Grátis</a>
    </div>
</section>

<footer class="main-footer">
    <div class="container">
        <p>
            Todos os Direitos Reservados &copy; <?php echo strtoupper($site_name); ?> <?php echo date('Y'); ?>. 
            | <a href="<?php echo gerarLinkWhatsApp($whatsapp_number, 'Olá, preciso de suporte.'); ?>" target="_blank" class="footer-link">Contato</a>
        </p>
    </div>
</footer>

<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Acessar Conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label text-muted small ps-2">E-MAIL</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small ps-2">SENHA</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-custom w-100 mb-3">ENTRAR</button>
                    <div class="text-center">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#recoverModal" class="text-muted small text-decoration-none">Esqueceu a senha?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Criar Nova Conta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label class="form-label text-muted small ps-2">NOME COMPLETO</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small ps-2">E-MAIL</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small ps-2">WHATSAPP</label>
                        <input type="text" name="whatsapp" class="form-control" required>
                    </div>
                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="form-label text-muted small ps-2">SENHA</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small ps-2">CONFIRMAR</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <?php if($ref): ?>
                        <div class="mb-3">
                            <label class="form-label text-success small ps-2">INDICADO POR:</label>
                            <input type="text" name="ref" value="<?php echo htmlspecialchars($ref); ?>" class="form-control" readonly>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-custom w-100">REGISTRAR-SE</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="recoverModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Recuperar Acesso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="recover">
                    <div class="mb-3">
                        <label class="form-label text-muted small ps-2">SEU E-MAIL</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small ps-2">SEU NOME</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-muted small ps-2">NOVA SENHA</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-custom w-100">REDEFINIR SENHA</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>