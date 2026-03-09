<?php
require_once __DIR__ . '/config.php';

// Ya autenticado → redirigir
if (Auth::check()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error    = '';
$redirect = clean($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean(post('email'));
    $password = post('password');

    if (empty($email) || empty($password)) {
        $error = 'Ingresa tu correo y contraseña.';
    } elseif (Auth::login($email, $password)) {
        $dest = $redirect ?: BASE_URL . '/dashboard.php';
        redirect($dest);
    } else {
        $error = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión — Aleatica ITSM</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --navy:   #1A3A5C;
      --green:  #4CAF50;
      --orange: #FF6B00;
    }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: #F0F4F8;
      min-height: 100vh;
      display: flex; align-items: stretch;
      color: #1E293B;
    }

    /* Panel izquierdo decorativo */
    .left-panel {
      width: 480px; flex-shrink: 0;
      background: var(--navy);
      position: relative; overflow: hidden;
      display: flex; flex-direction: column;
      justify-content: flex-end; padding: 56px;
    }
    .left-panel::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 80%, rgba(76,175,80,.25), transparent),
        radial-gradient(ellipse 60% 50% at 80% 20%, rgba(255,107,0,.15), transparent);
    }
    .left-bg-circles {
      position: absolute; top: -100px; right: -100px;
      width: 500px; height: 500px;
      border: 80px solid rgba(255,255,255,.04);
      border-radius: 50%;
    }
    .left-bg-circles::after {
      content: '';
      position: absolute; inset: -120px;
      border: 60px solid rgba(255,255,255,.03);
      border-radius: 50%;
    }
    .left-logo {
      position: absolute; top: 48px; left: 56px;
      display: flex; align-items: center; gap: 12px;
    }
    .left-logo-icon {
      width: 44px; height: 44px;
      background: rgba(255,255,255,.15);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; color: white;
    }
    .left-logo-text { font-size: 18px; font-weight: 700; color: white; }
    .left-logo-text span { color: #4CAF50; }
    .left-tagline {
      position: relative; z-index: 1;
      color: rgba(255,255,255,.9);
    }
    .left-tagline h2 {
      font-size: 36px; font-weight: 800;
      line-height: 1.15; letter-spacing: -.8px;
      margin-bottom: 16px;
    }
    .left-tagline h2 span { color: #4CAF50; }
    .left-tagline p {
      font-size: 15px; color: rgba(255,255,255,.6);
      line-height: 1.6; max-width: 320px;
    }
    .left-pills {
      display: flex; gap: 10px; flex-wrap: wrap; margin-top: 28px;
    }
    .pill {
      background: rgba(255,255,255,.1); color: rgba(255,255,255,.8);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 20px; padding: 5px 14px;
      font-size: 12px; font-weight: 500;
    }

    /* Panel derecho: formulario */
    .right-panel {
      flex: 1;
      display: flex; align-items: center; justify-content: center;
      padding: 40px 32px;
    }
    .login-box {
      width: 100%; max-width: 400px;
    }
    .login-title {
      font-size: 26px; font-weight: 800; color: #0F172A;
      letter-spacing: -.5px; margin-bottom: 6px;
    }
    .login-sub {
      font-size: 14px; color: #64748B; margin-bottom: 36px;
    }

    .form-group { margin-bottom: 18px; }
    .form-label {
      display: block; font-size: 12px; font-weight: 600;
      color: #475569; margin-bottom: 7px;
      letter-spacing: .03em; text-transform: uppercase;
    }
    .input-wrap { position: relative; }
    .input-wrap i {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: #94A3B8; font-size: 15px; pointer-events: none;
    }
    .form-control {
      width: 100%; height: 48px;
      border: 1.5px solid #E2E8F0; border-radius: 12px;
      padding: 0 14px 0 42px;
      font-family: 'DM Sans',sans-serif; font-size: 14px; color: #1E293B;
      background: white; outline: none; transition: all .2s;
    }
    .form-control:focus {
      border-color: #4A90C4;
      box-shadow: 0 0 0 4px rgba(74,144,196,.12);
    }
    .form-control::placeholder { color: #CBD5E1; }

    .show-pass {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: #94A3B8; font-size: 16px; padding: 4px;
      transition: color .15s;
    }
    .show-pass:hover { color: #475569; }

    .error-box {
      background: #FFEBEE; border: 1px solid #FFCDD2;
      border-radius: 10px; padding: 12px 16px;
      margin-bottom: 20px;
      display: flex; align-items: center; gap: 10px;
      font-size: 13.5px; color: #C62828;
    }

    .btn-login {
      width: 100%; height: 50px;
      background: var(--navy); color: white;
      border: none; border-radius: 12px;
      font-family: 'DM Sans',sans-serif; font-size: 15px; font-weight: 700;
      cursor: pointer; transition: all .2s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      letter-spacing: .01em;
    }
    .btn-login:hover {
      background: #152E4D;
      box-shadow: 0 8px 24px rgba(26,58,92,.3);
      transform: translateY(-1px);
    }
    .btn-login:active { transform: translateY(0); }

    .forgot {
      text-align: right; margin-bottom: 20px;
    }
    .forgot a {
      font-size: 12.5px; color: #4A90C4; text-decoration: none; font-weight: 500;
    }
    .forgot a:hover { text-decoration: underline; }

    .login-footer {
      margin-top: 32px; text-align: center;
      font-size: 12px; color: #94A3B8;
    }
    .login-footer a { color: #4A90C4; text-decoration: none; }

    .divider {
      display: flex; align-items: center; gap: 12px;
      margin: 24px 0; color: #CBD5E1; font-size: 12px;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1; height: 1px; background: #EEF2F7;
    }

    .portal-link {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      height: 46px; border: 1.5px solid #E2E8F0; border-radius: 12px;
      font-size: 13.5px; font-weight: 600; color: #475569;
      text-decoration: none; transition: all .15s;
      background: white;
    }
    .portal-link:hover {
      border-color: #4CAF50; color: #2E7D32;
      background: #E8F5E9;
    }

    @media (max-width: 860px) {
      .left-panel { display: none; }
    }
  </style>
</head>
<body>

<!-- Panel decorativo izquierdo -->
<div class="left-panel">
  <div class="left-bg-circles"></div>

  <div class="left-logo">
    <div class="left-logo-icon"><i class="fas fa-road"></i></div>
    <div class="left-logo-text">Aleatica <span>ITSM</span></div>
  </div>

  <div class="left-tagline">
    <h2>Gestión de TI<br>alineada a <span>ITIL 4</span></h2>
    <p>Sistema integrado para la concesión vial. Tickets, CMDB, proveedores, finanzas y monitoreo en un solo lugar.</p>
    <div class="left-pills">
      <span class="pill"><i class="fas fa-ticket" style="margin-right:5px;"></i>Mesa de Ayuda</span>
      <span class="pill"><i class="fas fa-server" style="margin-right:5px;"></i>CMDB</span>
      <span class="pill"><i class="fas fa-chart-line" style="margin-right:5px;"></i>Disponibilidad</span>
      <span class="pill"><i class="fas fa-handshake" style="margin-right:5px;"></i>Proveedores</span>
    </div>
  </div>
</div>

<!-- Formulario de login -->
<div class="right-panel">
  <div class="login-box">

    <h1 class="login-title">Bienvenido</h1>
    <p class="login-sub">Ingresa tus credenciales para continuar</p>

    <?php if ($error): ?>
    <div class="error-box">
      <i class="fas fa-circle-xmark"></i>
      <?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">

      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <div class="input-wrap">
          <i class="fas fa-envelope"></i>
          <input type="email" name="email" class="form-control"
                 value="<?= h(post('email')) ?>"
                 placeholder="tu@aleatica.pe"
                 autocomplete="email" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" id="passInput"
                 class="form-control" placeholder="••••••••"
                 autocomplete="current-password" required>
          <button type="button" class="show-pass" id="showPass" tabindex="-1">
            <i class="fas fa-eye" id="showPassIcon"></i>
          </button>
        </div>
      </div>

      <div class="forgot">
        <a href="<?= BASE_URL ?>/recuperar.php">¿Olvidaste tu contraseña?</a>
      </div>

      <button type="submit" class="btn-login">
        <i class="fas fa-arrow-right-to-bracket"></i>
        Iniciar sesión
      </button>
    </form>

    <div class="divider">o</div>

    <a href="<?= BASE_URL ?>/portal/" class="portal-link">
      <i class="fas fa-user" style="color:#4CAF50;"></i>
      Portal de Autoservicio
    </a>

    <div class="login-footer">
      <p>Aleatica ITSM v2.0 · Autopista del Norte S.A.C.</p>
    </div>

  </div>
</div>

<script>
// Mostrar/ocultar contraseña
const passInput = document.getElementById('passInput');
const showIcon  = document.getElementById('showPassIcon');
document.getElementById('showPass').onclick = () => {
    const visible = passInput.type === 'text';
    passInput.type = visible ? 'password' : 'text';
    showIcon.className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
};
</script>
</body>
</html>
