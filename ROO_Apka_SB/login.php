<?php
require 'includes/db.php';
require 'includes/auth.php';
presmerovat_prihlaseneho();

$chyby = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email'] ?? '');
    $heslo  = $_POST['heslo'] ?? '';

    $dotaz = databaza()->prepare(
        'SELECT u.*, p.name FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.email = ?'
    );
    $dotaz->execute([$email]);
    $pouzivatel = $dotaz->fetch();

    if ($pouzivatel && $pouzivatel['is_blocked']) {
        $chyby[] = 'Váš účet bol zablokovaný administrátorom.';
    } elseif ($pouzivatel && password_verify($heslo, $pouzivatel['password_hash'])) {
        $_SESSION['pouzivatel'] = [
            'id'       => $pouzivatel['id'],
            'meno'     => $pouzivatel['username'],
            'email'    => $pouzivatel['email'],
            'nazov'    => $pouzivatel['name'],
            'rola'     => $pouzivatel['role'],
        ];
        header('Location: ' . BASE . '/index.php');
        exit;
    } else {
        $chyby[] = 'Nesprávny email alebo heslo.';
    }
}

$nazov_stranky = 'Prihlásenie';
require 'includes/header.php';
?>
<div class="prihlasovaci-box">
  <h2>Prihlásenie</h2>
  <?php foreach ($chyby as $c): ?>
    <div class="sprava sprava-chyba"><?= escapuj($c) ?></div>
  <?php endforeach; ?>
  <form method="POST">
    <div class="skupina">
      <label>Email</label>
      <input type="email" name="email" value="<?= escapuj($_POST['email'] ?? '') ?>" required autofocus>
    </div>
    <div class="skupina">
      <label>Heslo</label>
      <input type="password" name="heslo" required>
    </div>
    <button type="submit" style="width:100%">Prihlásiť sa</button>
    <p style="text-align:center;margin-top:15px;font-size:14px">
      Nemáte účet? <a href="<?= BASE ?>/register.php">Registrujte sa</a>
    </p>
  </form>
</div>
<?php require 'includes/footer.php'; ?>
