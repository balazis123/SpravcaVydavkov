<?php
require 'includes/db.php';
require 'includes/auth.php';
vyzadovat_prihlasenie();

$pouzivatel = aktualny_pouzivatel();
$id         = (int)($_GET['id'] ?? 0);

$dotaz = databaza()->prepare(
    'SELECT t.*, GROUP_CONCAT(st.name ORDER BY st.name SEPARATOR ", ") as tagy
     FROM transactions t
     LEFT JOIN transaction_tags tt ON tt.transaction_id = t.id
     LEFT JOIN tags st ON st.id = tt.tag_id
     WHERE t.id = ? AND t.user_id = ?
     GROUP BY t.id'
);
$dotaz->execute([$id, $pouzivatel['id']]);
$transakcia = $dotaz->fetch();

if (!$transakcia) {
    nastav_spravu('chyba', 'Transakcia nenájdená.');
    header('Location: ' . BASE . '/index.php');
    exit;
}

$dotaz = databaza()->prepare('SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name');
$dotaz->execute([$pouzivatel['id']]);
$kategorie = $dotaz->fetchAll();

$nazov_stranky = 'Upraviť transakciu';
require 'includes/header.php';
?>
<div class="kontajner">
<section>
  <h2>Upraviť transakciu</h2>
  <form method="POST" action="<?= BASE ?>/api.php?action=upravit_transakciu" id="formular-upravy">
    <input type="hidden" name="id" value="<?= $transakcia['id'] ?>">
    <div class="riadok-formulara" style="margin-bottom:15px">
      <div class="skupina">
        <label>Suma (€)</label>
        <input type="number" name="suma" step="0.01" min="0.01" value="<?= escapuj((string)$transakcia['amount']) ?>" required>
      </div>
      <div class="skupina">
        <label>Typ</label>
        <select name="typ">
          <option value="vydavok" <?= $transakcia['type']==='vydavok'?'selected':'' ?>>Výdavok</option>
          <option value="prijem"  <?= $transakcia['type']==='prijem' ?'selected':'' ?>>Príjem</option>
        </select>
      </div>
      <div class="skupina">
        <label>Kategória</label>
        <select name="kategoria_id">
          <option value="">— bez kategórie —</option>
          <?php foreach ($kategorie as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $transakcia['category_id']==$k['id']?'selected':'' ?>>
              <?= escapuj($k['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="skupina">
        <label>Dátum</label>
        <input type="date" name="datum" value="<?= escapuj($transakcia['date']) ?>">
      </div>
    </div>
    <div class="skupina">
      <label>Popis</label>
      <input type="text" name="popis" value="<?= escapuj($transakcia['description']) ?>" required>
    </div>
    <div class="skupina">
      <label>Štítky (oddelené čiarkou)</label>
      <input type="text" name="tagy" value="<?= escapuj($transakcia['tagy'] ?? '') ?>">
    </div>
    <div id="sprava-chyby" class="sprava sprava-chyba" style="display:none"></div>
    <button type="submit">Uložiť zmeny</button>
    <a href="<?= BASE ?>/index.php" class="tlacidlo" style="margin-left:10px;background:#666">Zrušiť</a>
  </form>
</section>
</div>

<script>
document.getElementById('formular-upravy').addEventListener('submit', async e => {
    e.preventDefault();
    const obalChyby = document.getElementById('sprava-chyby');
    obalChyby.style.display = 'none';
    try {
        const odpoved = await fetch(e.target.action, {method: 'POST', body: new FormData(e.target)});
        const data = await odpoved.json();
        if (!data.ok) throw new Error(data.message);
        window.location.href = '<?= BASE ?>/index.php';
    } catch (chyba) {
        obalChyby.textContent = chyba.message;
        obalChyby.style.display = 'block';
    }
});
</script>
<?php require 'includes/footer.php'; ?>
