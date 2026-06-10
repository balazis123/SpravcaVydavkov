<?php
require 'includes/db.php';
require 'includes/auth.php';
vyzadovat_prihlasenie();

$pouzivatel = aktualny_pouzivatel();
$chyby      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $subor    = $_FILES['avatar'];
    $povolene = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if ($subor['error'] !== UPLOAD_ERR_OK)         $chyby[] = 'Chyba pri nahrávaní súboru.';
    elseif (!in_array($subor['type'], $povolene))  $chyby[] = 'Povolené sú iba obrázky (JPEG, PNG, GIF, WEBP).';
    elseif ($subor['size'] > 2 * 1024 * 1024)      $chyby[] = 'Obrázok je príliš veľký (max 2 MB).';

    if (!$chyby) {
        $priecinok_nahravani = __DIR__ . '/uploads/';
        $priecinok_nahliadov = __DIR__ . '/uploads/thumbs/';
        if (!is_dir($priecinok_nahravani)) mkdir($priecinok_nahravani, 0755, true);
        if (!is_dir($priecinok_nahliadov)) mkdir($priecinok_nahliadov, 0755, true);

        $pripona = strtolower(pathinfo($subor['name'], PATHINFO_EXTENSION));
        $ulozene = 'avatar_' . $pouzivatel['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $pripona;
        move_uploaded_file($subor['tmp_name'], $priecinok_nahravani . $ulozene);

        if (extension_loaded('gd')) {
            $obrazok = match ($subor['type']) {
                'image/jpeg' => @imagecreatefromjpeg($priecinok_nahravani . $ulozene),
                'image/png'  => @imagecreatefrompng($priecinok_nahravani . $ulozene),
                'image/gif'  => @imagecreatefromgif($priecinok_nahravani . $ulozene),
                'image/webp' => @imagecreatefromwebp($priecinok_nahravani . $ulozene),
                default      => false,
            };
            if ($obrazok) {
                $sirka = imagesx($obrazok); $vyska = imagesy($obrazok);
                $pomer = min(200 / $sirka, 200 / $vyska);
                $ns = max(1,(int)($sirka*$pomer)); $nv = max(1,(int)($vyska*$pomer));
                $nahlad = imagecreatetruecolor($ns, $nv);
                imagecopyresampled($nahlad, $obrazok, 0, 0, 0, 0, $ns, $nv, $sirka, $vyska);
                imagejpeg($nahlad, $priecinok_nahliadov . 'nahlad_' . $ulozene, 85);
                imagedestroy($obrazok); imagedestroy($nahlad);
            }
        }

        databaza()->prepare('UPDATE profiles SET avatar_path = ? WHERE user_id = ?')->execute([$ulozene, $pouzivatel['id']]);
        nastav_spravu('uspech', 'Avatar bol úspešne nahraný.');
        header('Location: ' . BASE . '/index.php');
        exit;
    }
}

$dotaz = databaza()->prepare('SELECT p.name, p.avatar_path, u.email FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = ?');
$dotaz->execute([$pouzivatel['id']]);
$profil = $dotaz->fetch();

$nazov_stranky = 'Profil';
require 'includes/header.php';
?>
<div class="kontajner">
<section>
  <h2>Nahrať avatar</h2>
  <div class="kontajner-profilu" style="margin-bottom:20px">
    <?php if ($profil['avatar_path']): ?>
      <img class="avatar" src="<?= BASE ?>/uploads/<?= escapuj($profil['avatar_path']) ?>" alt="Avatar">
    <?php else: ?>
      <div class="avatar-zastupca">Avatar</div>
    <?php endif; ?>
    <div class="info-profilu">
      <h3><?= escapuj($profil['name']) ?></h3>
      <p><?= escapuj($profil['email']) ?></p>
    </div>
  </div>
  <?php foreach ($chyby as $c): ?>
    <div class="sprava sprava-chyba"><?= escapuj($c) ?></div>
  <?php endforeach; ?>
  <form method="POST" enctype="multipart/form-data">
    <div class="skupina">
      <label>Nový avatar (JPEG, PNG, GIF, WEBP — max 2 MB)</label>
      <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" required>
    </div>
    <button type="submit">Nahrať</button>
    <a href="<?= BASE ?>/index.php" class="tlacidlo" style="margin-left:10px;background:#666">Späť</a>
  </form>
</section>
<section>
  <h2>Zmazať účet</h2>
  <p style="color:var(--bledý)">Zmazaním účtu sa natrvalo odstránia všetky vaše transakcie, kategórie a nahraté súbory.</p>
  <a href="<?= BASE ?>/delete_account.php" class="tlacidlo tlacidlo-chyba">Zmazať môj účet</a>
</section>
</div>
<?php require 'includes/footer.php'; ?>
