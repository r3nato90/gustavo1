<?php
require 'db.php';
require 'functions.php';

// Configurações
$home_title = getSetting($pdo, 'home_title', 'BEST INVESTMENTS');
$home_subtitle = getSetting($pdo, 'home_subtitle', 'Plataforma de alto rendimento');
$seo_title = getSetting($pdo, 'seo_title', 'Hyip Pro');
$site_name = getSetting($pdo, 'site_name', 'Imperio Invest');

// Imagens
$logoPath = getSetting($pdo, 'site_logo');
$faviconPath = getSetting($pdo, 'site_favicon');

// Banners Slides Principal (Topo)
$banners = $pdo->query("SELECT * FROM site_banners ORDER BY id DESC")->fetchAll();

// Banners Promocionais (Area Cinza/Meio) - Vamos usar a mesma tabela ou criar uma lógica se quiser separar
// Para simplificar, vou usar os 3 últimos banners como "promo" se não tiver tabela específica, 
// mas o ideal é ter uma tabela separada. Vou assumir que usa a mesma por enquanto ou criar uma query limit.
// Se você criou a tabela 'home_promo_banners', use ela. Caso contrário, adapte aqui.
// Vou usar a tabela 'site_banners' com um offset para exemplo, ou você pode criar uma tabela nova no banco.
$promos = $pdo->query("SELECT * FROM site_banners ORDER BY id ASC LIMIT 3")->fetchAll(); 

$ref = isset($_GET['ref']) ? $_GET['ref'] : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <?php if($faviconPath): ?><link rel="icon" href="<?php echo $faviconPath; ?>"><?php endif; ?>
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* PALETA DE CORES (Baseada na Imagem 2 - Cassino Dark) */
        :root {
            --bg-body: #0b0e11;       /* Fundo muito escuro, quase preto */
            --bg-nav: #15181c;        /* Fundo da Navbar e Footer */
            --bg-card: #1a1d21;       /* Fundo dos Cards */
            --primary: #00b4d8;       /* Azul Ciano Neon (Destaque) */
            --primary-hover: #0096c7;
            --text-main: #ffffff;
            --text-muted: #9ca3af;    /* Cinza claro para textos secundários */
            --card-border: #2a2e33;   /* Borda sutil */
            --accent: #ffd700;        /* Dourado para detalhes premium */
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Poppins', sans-serif; /* Fonte moderna */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-bottom: 70px; /* Espaço para o menu mobile inferior */
        }

        /* --- NAVBAR (Menu Desktop) --- */
        .navbar { 
            background-color: var(--bg-nav); 
            border-bottom: 1px solid var(--card-border); 
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand img { max-height: 40px; }
        .navbar-brand { font-weight: 700; color: var(--text-main) !important; letter-spacing: 1px; }
        
        /* Links do Menu */
        .nav-link { 
            color: var(--text-muted) !important; 
            font-weight: 500; 
            margin: 0 10px; 
            transition: 0.3s; 
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; }

        /* Botões Arredondados (Estilo Imagem 2) */
        .btn { 
            border-radius: 50px; /* Totalmente arredondado */
            padding: 10px 28px; 
            font-weight: 600; 
            text-transform: uppercase; 
            font-size: 0.85rem; 
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-custom { 
            background-color: var(--primary); 
            color: #fff; 
            border: none; 
            box-shadow: 0 0 15px rgba(0, 180, 216, 0.4); /* Glow effect */
        }
        .btn-custom:hover { 
            background-color: var(--primary-hover); 
            color: #fff; 
            transform: translateY(-2px); 
            box-shadow: 0 0 25px rgba(0, 180, 216, 0.6); 
        }
        .btn-outline-light { 
            border-color: var(--card-border); 
            color: var(--text-muted); 
            background: var(--bg-card);
        }
        .btn-outline-light:hover { 
            border-color: var(--text-main); 
            color: var(--text-main); 
            background: var(--bg-nav);
        }

        /* --- SLIDER PRINCIPAL --- */
        .main-slider {
            height: 500px; /* Altura Fixa Desktop */
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        .main-slider .carousel-item { height: 500px; background: #000; }
        .main-slider img { width: 100%; height: 100%; object-fit: cover; opacity: 0.7; }
        
        .carousel-caption { 
            bottom: 30%; 
            left: 10%; right: 10%;
            text-align: center;
        }
        .carousel-caption h1 { 
            font-size: 3.5rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            text-shadow: 0 0 20px rgba(0,0,0,0.8);
            margin-bottom: 20px; 
        }
        .carousel-caption p { font-size: 1.2rem; margin-bottom: 30px; color: #e0e0e0; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }

        /* Responsividade Slider */
        @media (max-width: 768px) {
            .main-slider, .main-slider .carousel-item { height: 300px; } 
            .carousel-caption h1 { font-size: 1.8rem; }
            .carousel-caption p { font-size: 0.9rem; display: none; } /* Esconde texto no mobile */
            /* Ocultar Navbar Desktop no Mobile */
            .navbar-collapse { 
                background: var(--bg-nav); 
                position: absolute; 
                top: 100%; left: 0; right: 0; 
                padding: 20px; 
                border-bottom: 1px solid var(--card-border);
            }
        }

        /* --- ÁREA PROMOCIONAL (Banner Secundário / Slide Cinza) --- */
        /* Esta área simula a parte cinza da imagem que você mencionou */
        .promo-slider-section {
            background-color: #25282e; /* Cor cinza mais clara que o fundo */
            padding: 40px 0;
            border-top: 1px solid var(--card-border);
            border-bottom: 1px solid var(--card-border);
        }
        .promo-slider .carousel-item { height: 200px; border-radius: 12px; overflow: hidden; }
        .promo-slider img { width: 100%; height: 100%; object-fit: cover; }
        .promo-title { margin-bottom: 20px; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }

        /* --- PLANOS --- */
        .pricing-section { padding: 80px 0; }
        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        /* Efeito de brilho no topo do card */
        .pricing-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--primary), transparent);
        }
        .pricing-card:hover { 
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0, 180, 216, 0.15); 
            border-color: var(--primary);
        }
        .pricing-card h3 { color: var(--text-main); font-weight: 700; margin-bottom: 15px; font-size: 1.5rem; }
        .pricing-card .price { font-size: 2.5rem; font-weight: 800; color: var(--primary); margin-bottom: 5px; }
        .pricing-card .period { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px; }
        .pricing-card ul { list-style: none; padding: 0; margin-bottom: 30px; text-align: left; font-size: 0.95rem; }
        .pricing-card ul li { margin-bottom: 12px; color: var(--text-muted); display: flex; align-items: center; }
        .pricing-card ul li i { color: var(--primary); margin-right: 10px; width: 20px; text-align: center; }

        /* --- FOOTER --- */
        footer {
            background-color: var(--bg-nav);
            border-top: 1px solid var(--card-border);
            padding: 30px 0 100px 0; /* Padding bottom grande para não cobrir com menu mobile */
            margin-top: auto; 
            text-align: center;
        }
        footer p { margin: 0; color: var(--text-muted); font-size: 0.85rem; opacity: 0.7; }
        
        /* --- MENU MOBILE INFERIOR (Imagem 3) --- */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--bg-nav);
            border-top: 1px solid var(--card-border);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0;
            z-index: 2000;
            display: none; /* Padrão oculto no desktop */
            box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
        }
        
        @media (max-width: 768px) {
            .mobile-bottom-nav { display: flex; }
            /* Esconde botão toggle da navbar superior para limpar o visual */
            .navbar-toggler { display: none; } 
        }

        .nav-item-mobile {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.7rem;
            flex: 1;
            transition: 0.2s;
        }
        .nav-item-mobile i { font-size: 1.3rem; margin-bottom: 4px; }
        .nav-item-mobile.active, .nav-item-mobile:hover { color: var(--primary); }
        
        /* Botão Central Destacado (Entrar/Ação) */
        .nav-item-mobile.highlight {
            position: relative;
            top: -25px; /* Eleva o botão */
            background: var(--primary);
            color: white;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 15px rgba(0, 180, 216, 0.6);
            border: 5px solid var(--bg-body);
        }
        .nav-item-mobile.highlight i { font-size: 1.5rem; margin: 0; }
        .nav-item-mobile.highlight span { 
            position: absolute; 
            bottom: -20px; 
            color: var(--text-muted); 
            width: 100px; 
            text-align: center; 
            font-size: 0.7rem;
        }

        /* Modais */
        .modal-content { background-color: var(--bg-card); border: 1px solid var(--card-border); color: var(--text-main); border-radius: 16px; }
        .modal-header { border-bottom: 1px solid var(--card-border); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        .form-control { 
            background-color: var(--bg-body); 
            border: 1px solid var(--card-border); 
            color: var(--text-main); 
            border-radius: 50px; /* Inputs arredondados tb */
            padding: 12px 20px;
        }
        .form-control:focus { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            border-color: var(--primary); 
            box-shadow: 0 0 0 0.25rem rgba(0, 180, 216, 0.25); 
        }

    </style>
</head>
<body>

<!-- Navbar Superior (Desktop) -->
<nav class="navbar navbar-expand-lg navbar-dark d-none d-md-block">
    <div class="container">
        <a class="navbar-brand" href="#">
            <?php if($logoPath) echo "<img src='$logoPath' alt='$site_name'>"; else echo strtoupper($site_name); ?>
        </a>
        
        <!-- Menu Desktop -->
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#plans">Planos</a></li>
                <li class="nav-item ms-lg-3">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="btn btn-outline-light me-2">Login</a>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="btn btn-custom">Cadastrar</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Navbar Superior (Mobile - Apenas Logo) -->
<nav class="navbar navbar-dark d-md-none">
    <div class="container justify-content-center">
        <a class="navbar-brand m-0" href="#">
            <?php if($logoPath) echo "<img src='$logoPath' alt='$site_name' style='max-height:35px;'>"; else echo strtoupper($site_name); ?>
        </a>
    </div>
</nav>

<!-- Slider Principal (Hero) -->
<div id="heroSlider" class="carousel slide main-slider" data-bs-ride="carousel">
    <div class="carousel-inner">
        <?php if(count($banners) > 0): ?>
            <?php foreach($banners as $index => $banner): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <img src="<?php echo $banner['image_path']; ?>" alt="Slide">
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="carousel-item active">
                <img src="https://hyip-pro.bugfinder.app/assets/upload/contents/BTGfMYjIow86Z5i9TsmUGZdB3C68gt.webp" alt="Default">
                <div class="carousel-caption">
                    <h1><?php echo $home_title; ?></h1>
                    <p><?php echo $home_subtitle; ?></p>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="btn btn-custom btn-lg px-5">Começar Agora</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if(count($banners) > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
    <?php endif; ?>
</div>

<!-- Área Promocional (Slider Secundário / Cinza) -->
<section class="promo-slider-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="promo-title mb-0"><i class="fas fa-fire text-danger me-2"></i> Destaques</h5>
            <!-- Controles do mini slider -->
            <div>
                <button class="btn btn-sm btn-outline-light rounded-circle" type="button" data-bs-target="#promoSlider" data-bs-slide="prev"><i class="fas fa-chevron-left"></i></button>
                <button class="btn btn-sm btn-outline-light rounded-circle" type="button" data-bs-target="#promoSlider" data-bs-slide="next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        
        <div id="promoSlider" class="carousel slide promo-slider" data-bs-ride="carousel">
            <div class="carousel-inner">
                <!-- Exemplo de conteúdo estático ou dinâmico se tiver tabela -->
                <!-- Slide 1 -->
                <div class="carousel-item active">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <div class="promo-card p-3 rounded bg-dark border border-secondary h-100 text-center">
                                <i class="fas fa-rocket fa-3x text-primary mb-2"></i>
                                <h6>Investimento Rápido</h6>
                            </div>
                        </div>
                         <div class="col-6 col-md-4">
                            <div class="promo-card p-3 rounded bg-dark border border-secondary h-100 text-center">
                                <i class="fas fa-shield-alt fa-3x text-success mb-2"></i>
                                <h6>Segurança Total</h6>
                            </div>
                        </div>
                         <div class="col-md-4 d-none d-md-block">
                            <div class="promo-card p-3 rounded bg-dark border border-secondary h-100 text-center">
                                <i class="fas fa-headset fa-3x text-warning mb-2"></i>
                                <h6>Suporte 24h</h6>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Slide 2 (Exemplo) -->
                <div class="carousel-item">
                     <div class="row g-3">
                        <div class="col-12">
                             <div class="p-4 rounded bg-primary text-white text-center">
                                 <h5>Bônus de Indicação Ativo!</h5>
                                 <p class="mb-0">Convide amigos e ganhe comissão instantânea.</p>
                             </div>
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Planos -->
<section id="plans" class="pricing-section">
    <div class="container">
        <div class="text-center mb-5">
            <h6 class="text-primary fw-bold ls-1 text-uppercase">Oportunidades</h6>
            <h2 class="fw-bold">Nossos Planos</h2>
            <div style="width: 60px; height: 3px; background: var(--primary); margin: 15px auto;"></div>
        </div>
        
        <div class="row g-4">
            <?php
            $stmt = $pdo->query("SELECT * FROM plans");
            while ($plan = $stmt->fetch()) {
                $total = $plan['daily_percent'] * $plan['days'];
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="pricing-card">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0"><?php echo $plan['name']; ?></h3>
                            <span class="badge bg-primary rounded-pill">Hot</span>
                        </div>
                        <div class="price"><?php echo number_format($plan['daily_percent'], 2); ?>%</div>
                        <div class="period">Ao dia por <?php echo $plan['days']; ?> dias</div>
                        
                        <hr style="border-color:var(--card-border); opacity:0.5;">
                        
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Retorno Total: <strong class="text-white ms-1"><?php echo $total; ?>%</strong></li>
                            <li><i class="fas fa-coins"></i> Min: <strong class="text-white ms-1">R$ <?php echo number_format($plan['min_amount'], 2, ',', '.'); ?></strong></li>
                            <li><i class="fas fa-arrow-up"></i> Max: <strong class="text-white ms-1">R$ <?php echo number_format($plan['max_amount'], 2, ',', '.'); ?></strong></li>
                            <li><i class="fas fa-headset"></i> Suporte Dedicado</li>
                        </ul>
                    </div>
                    <button class="btn btn-custom w-100 mt-3" data-bs-toggle="modal" data-bs-target="#registerModal">Investir Agora</button>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="container">
        <p>Todos os Direitos Reservados &copy; <?php echo strtoupper($site_name); ?> <?php echo date('Y'); ?></p>
    </div>
</footer>

<!-- Menu Mobile Inferior (App Style) -->
<div class="mobile-bottom-nav">
    <a href="#" class="nav-item-mobile active">
        <i class="fas fa-home"></i>
        <span>Início</span>
    </a>
    <a href="#plans" class="nav-item-mobile">
        <i class="fas fa-chart-pie"></i>
        <span>Planos</span>
    </a>
    
    <!-- Botão Central Destacado -->
    <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="nav-item-mobile highlight">
        <i class="fas fa-user-plus"></i>
        <span>Entrar</span>
    </a>
    
    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="nav-item-mobile">
        <i class="fas fa-sign-in-alt"></i>
        <span>Login</span>
    </a>
    <a href="#" class="nav-item-mobile">
        <i class="fas fa-headset"></i>
        <span>Ajuda</span>
    </a>
</div>

<!-- Modais (Login/Registro) -->
<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Acessar Conta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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

<!-- Registro Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Criar Nova Conta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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

<!-- Recovery Modal -->
<div class="modal fade" id="recoverModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Recuperar Acesso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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