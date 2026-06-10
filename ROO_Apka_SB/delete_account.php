<?php
require 'includes/db.php';
require 'includes/auth.php';
vyzadovat_prihlasenie();

$pouzivatel = aktualny_pouzivatel();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['potvrdit'] ?? '') === 'ano') {
    $databaza = databaza();
    $databaza->beginTransaction();
    try {
        $dotaz = $databaza->prepare('SELECT stored_name, thumbnail_path FROM files WHERE user_id = ?');
        $dotaz->execute([$pouzivatel['id']]);
        foreach ($dotaz->fetchAll() as $subor) {
            @unlink(__DIR__ . '/uploads/' . $subor['stored_name']);
            if ($subor['thumbnail_path']) @unlink(__DIR__ . '/uploads/thumbs/' . $subor['thumbnail_path']);
        }
        $databaza->prepare('DELETE FROM users WHERE id = ?')->execute([$pouzivatel['id']]);
        $databaza->commit();

        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: ' . BASE . '/login.php');
        exit;
    } catch (Exception $e) {
        $databaza->rollBack();
        nastav_spravu('chyba', 'Chyba pri mazaní účtu.');
        header('Location: ' . BASE . '/index.php');
        exit;
    }
}

$nazov_stranky = 'Zmazať účet';
require 'includes/header.php';
?>
<div class="kontajner">
<section>
  <h2>Zmazať účet</h2>
  <p>Naozaj chcete zmazať váš účet <strong><?= escapuj($pouzivatel['meno']) ?></strong>?</p>
  <p style="color:var(--chyba)">Táto akcia je <strong>nezvratná</strong>. Zmažú sa všetky vaše transakcie, kategórie a nahraté súbory.</p>
  <form method="POST">
    <input type="hidden" name="potvrdit" value="ano">
    <button type="submit" class="tlacidlo tlacidlo-chyba">Áno, zmazať môj účet</button>
    <a href="<?= BASE ?>/index.php" class="tlacidlo" style="margin-left:10px;background:#666">Zrušiť</a>
  </form>
</section>
</div>
<?php require 'includes/footer.php'; ?>
