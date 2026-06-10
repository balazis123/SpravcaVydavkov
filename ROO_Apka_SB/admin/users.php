<?php
require '../includes/db.php';
require '../includes/auth.php';
vyzadovat_admina();

$pouzivatel = aktualny_pouzivatel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ciela = (int)($_POST['id'] ?? 0);
    $akcia    = $_POST['akcia'] ?? '';

    if ($id_ciela === $pouzivatel['id']) {
        nastav_spravu('chyba', 'Nemôžete vykonať túto akciu na vlastnom účte.');
        header('Location: ' . BASE . '/admin/users.php');
        exit;
    }

    $databaza = databaza();
    $databaza->beginTransaction();
    try {
        if ($akcia === 'zmazat') {
            $dotaz = $databaza->prepare('SELECT stored_name, thumbnail_path FROM files WHERE user_id = ?');
            $dotaz->execute([$id_ciela]);
            foreach ($dotaz->fetchAll() as $subor) {
                @unlink(__DIR__ . '/../uploads/' . $subor['stored_name']);
                if ($subor['thumbnail_path']) @unlink(__DIR__ . '/../uploads/thumbs/' . $subor['thumbnail_path']);
            }
            $databaza->prepare('DELETE FROM users WHERE id = ?')->execute([$id_ciela]);
            nastav_spravu('uspech', 'Používateľ bol zmazaný.');

        } elseif ($akcia === 'zmenit_rolu') {
            $dotaz = $databaza->prepare('SELECT role FROM users WHERE id = ?');
            $dotaz->execute([$id_ciela]);
            $nova_rola = $dotaz->fetchColumn() === 'admin' ? 'user' : 'admin';
            $databaza->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$nova_rola, $id_ciela]);
            nastav_spravu('uspech', 'Rola bola zmenená na ' . $nova_rola . '.');

        } elseif ($akcia === 'zmenit_blokovanie') {
            $dotaz = $databaza->prepare('SELECT is_blocked FROM users WHERE id = ?');
            $dotaz->execute([$id_ciela]);
            $novy_stav = $dotaz->fetchColumn() ? 0 : 1;
            $databaza->prepare('UPDATE users SET is_blocked = ? WHERE id = ?')->execute([$novy_stav, $id_ciela]);
            nastav_spravu('uspech', $novy_stav ? 'Používateľ bol zablokovaný.' : 'Používateľ bol odblokovaný.');
        }

        $databaza->commit();
    } catch (Exception $e) {
        $databaza->rollBack();
        nastav_spravu('chyba', 'Chyba pri vykonávaní akcie.');
    }

    header('Location: ' . BASE . '/admin/users.php');
    exit;
}

$stranka    = max(1, (int)($_GET['page'] ?? 1));
$na_stranku = 15;
$posun      = ($stranka - 1) * $na_stranku;

$celkovo      = databaza()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$pocet_stranok = max(1, (int)ceil($celkovo / $na_stranku));

$dotaz = databaza()->prepare(
    'SELECT u.id, u.username, u.email, u.role, u.is_blocked, u.created_at,
            COUNT(t.id) as pocet_transakcii
     FROM users u
     LEFT JOIN transactions t ON t.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT ? OFFSET ?'
);
$dotaz->bindValue(1, $na_stranku, PDO::PARAM_INT);
$dotaz->bindValue(2, $posun, PDO::PARAM_INT);
$dotaz->execute();
$pouzivatelia = $dotaz->fetchAll();

$nazov_stranky = 'Správa používateľov';
require '../includes/header.php';
?>
<div class="kontajner">
<section>
  <h2>Správa používateľov</h2>
  <p style="color:var(--bledý);font-size:14px">Celkovo: <?= (int)$celkovo ?> používateľov</p>
  <table>
    <thead><tr>
      <th>ID</th><th>Používateľ</th><th>Email</th><th>Rola</th><th>Transakcie</th><th>Stav</th><th>Akcie</th>
    </tr></thead>
    <tbody>
    <?php foreach ($pouzivatelia as $zaznam): ?>
    <tr>
      <td><?= $zaznam['id'] ?></td>
      <td><?= escapuj($zaznam['username']) ?></td>
      <td><?= escapuj($zaznam['email']) ?></td>
      <td><?= escapuj($zaznam['role']) ?></td>
      <td><?= (int)$zaznam['pocet_transakcii'] ?></td>
      <td><?= $zaznam['is_blocked']
            ? '<span style="color:var(--chyba)">Blokovaný</span>'
            : '<span style="color:var(--uspech)">Aktívny</span>' ?></td>
      <td style="white-space:nowrap">
        <?php if ($zaznam['id'] !== $pouzivatel['id']): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="id" value="<?= $zaznam['id'] ?>">
            <input type="hidden" name="akcia" value="zmenit_rolu">
            <button class="tlacidlo tlacidlo-male" type="submit">
              <?= $zaznam['role'] === 'admin' ? 'Na Usera' : 'Na Admina' ?>
            </button>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="id" value="<?= $zaznam['id'] ?>">
            <input type="hidden" name="akcia" value="zmenit_blokovanie">
            <button class="tlacidlo tlacidlo-male <?= $zaznam['is_blocked'] ? 'tlacidlo-uspech' : 'tlacidlo-chyba' ?>" type="submit">
              <?= $zaznam['is_blocked'] ? 'Odblokovať' : 'Blokovať' ?>
            </button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Naozaj zmazať používateľa <?= escapuj($zaznam['username']) ?>?')">
            <input type="hidden" name="id" value="<?= $zaznam['id'] ?>">
            <input type="hidden" name="akcia" value="zmazat">
            <button class="tlacidlo tlacidlo-male tlacidlo-chyba" type="submit">Zmazať</button>
          </form>
        <?php else: ?>
          <em style="font-size:12px;color:var(--bledý)">(vy)</em>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pocet_stranok > 1): ?>
  <div class="strankovanie">
    <?php for ($i = 1; $i <= $pocet_stranok; $i++): ?>
      <?php if ($i === $stranka): ?>
        <span class="aktivna"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</section>
</div>
<?php require '../includes/footer.php'; ?>
