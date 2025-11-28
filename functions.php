<?php
// Validação de CPF (Baseado no padrão Modulo 11)
function validarCPF($cpf) {
    $cpf = preg_replace( '/[^0-9]/is', '', $cpf );
    
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Gerador de Link WhatsApp
function gerarLinkWhatsApp($numero, $mensagem) {
    // Remove caracteres não numéricos para o link
    $numero = preg_replace('/[^0-9]/', '', $numero);
    $msgCodificada = urlencode($mensagem);
    return "https://wa.me/{$numero}?text={$msgCodificada}";
}

// Buscar Configuração
// Retorna o valor da configuração ou um valor padrão se não existir
function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $res = $stmt->fetch();
    return $res ? $res['setting_value'] : $default;
}

// Atualizar Configuração
// Atualiza se existir, cria se não existir
function updateSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, 'Configuração Automática')");
        $stmt->execute([$key, $value]);
    }
}

// Verificar Primeiro Login (Dados faltantes)
// Retorna true se faltar CPF ou Username
function verificarDadosFaltantes($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT cpf, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (empty($user['cpf']) || empty($user['username'])) {
        return true; 
    }
    return false;
}
?>