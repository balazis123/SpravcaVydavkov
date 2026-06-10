<?php
require '../includes/db.php';
require '../includes/auth.php';
vyzadovat_admina();

$statistiky = databaza()->query(
    'SELECT
        (SELECT COUNT(*) FROM users) as celkovo_pouzivatelov,
        (SELECT COUNT(*) FROM transactions) as celkovo_transakcii,
        (SELECT COUNT(*) FROM files) as celkovo_suborov,
        (SELECT COALESCE(SUM(size),0) FROM files) as celkova_velkost'
)->fetch();

$nedavni = databaza()->query(
    'SELECT u.id, u.username, u.email, u.role, u.is_blocked, u.created_at
     FROM users u ORDER BY u.created_at DESC LIMIT 5'
)->fetchAll();

$nazov_stranky = 'Admin Dashboard';
require '../includes/header.php';
?>
<div class="kontajner">
<section>
  <h2>Admin Dashboard</h2>
  <div class="prehlad-mriezka">
    <div class="prehlad-karta"><h3>Používatelia</h3><p><?= (int)$statistiky['celkovo_pouzivatelov'] ?></p></div>
    <div class="prehlad-karta"><h3>Transakcie</h3><p><?= (int)$statistiky['celkovo_transakcii'] ?></p></div>
    <div class="prehlad-karta"><h3>Nahrané súbory</h3><p><?= (int)$statistiky['celkovo_suborov'] ?></p></div>
    <div class="prehlad-karta"><h3>Veľkosť súborov</h3>
      <p><?= number_format($statistiky['celkova_velkost'] / 1024 / 1024, 1) ?> MB</p>
    </div>
  </div>
</section>

<section>
  <h2>Posledné registrácie</h2>
  <table>
    <thead><tr><th>ID</th><th>Meno</th><th>Email</th><th>Rola</th><th>Stav</th><th>Registrovaný</th><th>Akcie</th></tr></thead>
    <tbody>
    <?php foreach ($nedavni as $zaznam): ?>
      <tr>
        <td><?= $zaznam['id'] ?></td>
        <td><?= escapuj($zaznam['username']) ?></td>
        <td><?= escapuj($zaznam['email']) ?></td>
        <td><?= escapuj($zaznam['role']) ?></td>
        <td><?= $zaznam['is_blocked'] ? '<span style="color:var(--chyba)">Blokovaný</span>' : '<span style="color:var(--uspech)">Aktívny</span>' ?></td>
        <td><?= escapuj($zaznam['created_at']) ?></td>
        <td><a href="<?= BASE ?>/admin/users.php" class="tlacidlo tlacidlo-male">Spravovať</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p style="margin-top:15px"><a href="<?= BASE ?>/admin/users.php" class="tlacidlo">Všetci používatelia</a></p>
</section>
</div>
<?php require '../includes/footer.php'; ?>
