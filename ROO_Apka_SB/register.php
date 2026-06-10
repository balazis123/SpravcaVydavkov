<?php
require 'includes/db.php';
require 'includes/auth.php';
presmerovat_prihlaseneho();

$chyby = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meno_pouzivatela = trim($_POST['meno_pouzivatela'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $zobrazene_meno   = trim($_POST['zobrazene_meno'] ?? '');
    $heslo            = $_POST['heslo'] ?? '';

    if (!$meno_pouzivatela)                              $chyby[] = 'Zadajte používateľské meno.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $chyby[] = 'Neplatný formát emailu.';
    if (!$zobrazene_meno)                                $chyby[] = 'Zadajte zobrazované meno.';
    if (strlen($heslo) < 6)                              $chyby[] = 'Heslo musí mať aspoň 6 znakov.';

    if (!$chyby) {
        $databaza = databaza();
        $databaza->beginTransaction();
        try {
            $hash_hesla = password_hash($heslo, PASSWORD_DEFAULT);
            $dotaz = $databaza->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
            $dotaz->execute([$meno_pouzivatela, $email, $hash_hesla]);
            $id_noveho = $databaza->lastInsertId();

            $dotaz = $databaza->prepare('INSERT INTO profiles (user_id, name) VALUES (?, ?)');
            $dotaz->execute([$id_noveho, $zobrazene_meno]);

            $databaza->commit();
            nastav_spravu('uspech', 'Registrácia úspešná! Prihláste sa.');
            header('Location: ' . BASE . '/login.php');
            exit;
        } catch (PDOException $e) {
            $databaza->rollBack();
            if ($e->getCode() == 23000) {
                $chyby[] = 'Email alebo používateľské meno je už obsadené.';
            } else {
                $chyby[] = 'Chyba pri registrácii. Skúste znova.';
            }
        }
    }
}

$nazov_stranky = 'Registrácia';
require 'includes/header.php';
?>
<div class="prihlasovaci-box">
  <h2>Registrácia</h2>
  <?php foreach ($chyby as $c): ?>
    <div class="sprava sprava-chyba"><?= escapuj($c) ?></div>
  <?php endforeach; ?>
  <form method="POST">
    <div class="skupina">
      <label>Zobrazované meno</label>
      <input type="text" name="zobrazene_meno" value="<?= escapuj($_POST['zobrazene_meno'] ?? '') ?>" required>
    </div>
    <div class="skupina">
      <label>Používateľské meno</label>
      <input type="text" name="meno_pouzivatela" value="<?= escapuj($_POST['meno_pouzivatela'] ?? '') ?>" required>
    </div>
    <div class="skupina">
      <label>Email</label>
      <input type="email" name="email" value="<?= escapuj($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="skupina">
      <label>Heslo (min. 6 znakov)</label>
      <input type="password" name="heslo" required>
    </div>
    <button type="submit" style="width:100%">Zaregistrovať sa</button>
    <p style="text-align:center;margin-top:15px;font-size:14px">
      Už máte účet? <a href="<?= BASE ?>/login.php">Prihláste sa</a>
    </p>
  </form>
</div>
<?php require 'includes/footer.php'; ?>
