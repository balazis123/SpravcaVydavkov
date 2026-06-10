<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function(Throwable $vynimka) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $vynimka->getMessage()]);
    exit;
});

set_error_handler(function($kod, $text, $subor, $riadok) {
    throw new ErrorException($text, $kod, $kod, $subor, $riadok);
});

require 'includes/db.php';
require 'includes/auth.php';

header('Content-Type: application/json');

if (!aktualny_pouzivatel()) {
    echo json_encode(['ok' => false, 'message' => 'Nie ste prihlásený']);
    exit;
}

$pouzivatel = aktualny_pouzivatel();

function uspech($data = null): never {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function chyba(string $sprava, int $kod = 400): never {
    http_response_code($kod);
    echo json_encode(['ok' => false, 'message' => $sprava]);
    exit;
}

function vytvor_nahlad(string $zdroj, string $ciel, string $mime): bool {
    if (!extension_loaded('gd')) return false;
    $obrazok = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($zdroj),
        'image/png'  => @imagecreatefrompng($zdroj),
        'image/gif'  => @imagecreatefromgif($zdroj),
        'image/webp' => @imagecreatefromwebp($zdroj),
        default      => false,
    };
    if (!$obrazok) return false;
    $sirka = imagesx($obrazok); $vyska = imagesy($obrazok);
    $pomer = min(200 / $sirka, 200 / $vyska);
    $nova_sirka = max(1, (int)($sirka * $pomer));
    $nova_vyska = max(1, (int)($vyska * $pomer));
    $nahlad = imagecreatetruecolor($nova_sirka, $nova_vyska);
    if ($mime === 'image/png') {
        imagealphablending($nahlad, false); imagesavealpha($nahlad, true);
    }
    imagecopyresampled($nahlad, $obrazok, 0, 0, 0, 0, $nova_sirka, $nova_vyska, $sirka, $vyska);
    imagejpeg($nahlad, $ciel, 85);
    imagedestroy($obrazok); imagedestroy($nahlad);
    return true;
}

$akcia = $_GET['action'] ?? '';

switch ($akcia) {

    case 'dashboard':
        $stranka    = max(1, (int)($_GET['page'] ?? 1));
        $na_stranku = 10;
        $posun      = ($stranka - 1) * $na_stranku;
        $id_pouzivatela = $pouzivatel['id'];

        $dotaz = databaza()->prepare(
            'SELECT COALESCE(SUM(CASE WHEN type="prijem" THEN amount ELSE 0 END),0) as prijmy,
                    COALESCE(SUM(CASE WHEN type="vydavok" THEN amount ELSE 0 END),0) as vydavky
             FROM transactions WHERE user_id = ?'
        );
        $dotaz->execute([$id_pouzivatela]);
        $sumy = $dotaz->fetch();
        $prehlad = [
            'prijmy'   => (float)$sumy['prijmy'],
            'vydavky'  => (float)$sumy['vydavky'],
            'zostatok' => (float)$sumy['prijmy'] - (float)$sumy['vydavky'],
        ];

        $dotaz = databaza()->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
        $dotaz->execute([$id_pouzivatela]);
        $pocet_stranok = max(1, (int)ceil($dotaz->fetchColumn() / $na_stranku));

        $dotaz = databaza()->prepare(
            'SELECT t.id, t.amount, t.type, t.description, t.date,
                    COALESCE(c.name,"Bez kategórie") as kategoria,
                    GROUP_CONCAT(st.name ORDER BY st.name SEPARATOR ",") as tags
             FROM transactions t
             LEFT JOIN categories c ON c.id = t.category_id
             LEFT JOIN transaction_tags tt ON tt.transaction_id = t.id
             LEFT JOIN tags st ON st.id = tt.tag_id
             WHERE t.user_id = ?
             GROUP BY t.id
             ORDER BY t.date DESC, t.id DESC
             LIMIT ? OFFSET ?'
        );
        $dotaz->bindValue(1, $id_pouzivatela, PDO::PARAM_INT);
        $dotaz->bindValue(2, $na_stranku, PDO::PARAM_INT);
        $dotaz->bindValue(3, $posun, PDO::PARAM_INT);
        $dotaz->execute();
        $transakcie = $dotaz->fetchAll();

        $dotaz = databaza()->prepare('SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name');
        $dotaz->execute([$id_pouzivatela]);
        $kategorie = $dotaz->fetchAll();

        $dotaz = databaza()->prepare(
            'SELECT u.email, p.name, p.avatar_path FROM users u JOIN profiles p ON p.user_id = u.id WHERE u.id = ?'
        );
        $dotaz->execute([$id_pouzivatela]);
        $profil = $dotaz->fetch();

        uspech([
            'prehlad'        => $prehlad,
            'transakcie'     => $transakcie,
            'kategorie'      => $kategorie,
            'profil'         => $profil,
            'pocet_stranok'  => $pocet_stranok,
            'aktualna_stranka' => $stranka,
        ]);

    case 'pridat_transakciu':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chyba('Neplatná metóda');
        $suma       = (float)($_POST['suma'] ?? 0);
        $typ        = $_POST['typ'] ?? '';
        $id_kat     = ($_POST['kategoria_id'] ?? '') !== '' ? (int)$_POST['kategoria_id'] : null;
        $popis      = trim($_POST['popis'] ?? '');
        $datum      = $_POST['datum'] ?? date('Y-m-d');
        $stitky     = trim($_POST['tagy'] ?? '');

        if ($suma <= 0)                                       chyba('Suma musí byť kladná');
        if (!in_array($typ, ['vydavok', 'prijem']))           chyba('Neplatný typ');
        if (!$popis)                                          chyba('Zadajte popis');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum))    $datum = date('Y-m-d');

        if ($id_kat) {
            $dotaz = databaza()->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
            $dotaz->execute([$id_kat, $pouzivatel['id']]);
            if (!$dotaz->fetch()) $id_kat = null;
        }

        $databaza = databaza();
        $databaza->beginTransaction();
        try {
            $dotaz = $databaza->prepare(
                'INSERT INTO transactions (user_id, category_id, amount, type, description, date) VALUES (?,?,?,?,?,?)'
            );
            $dotaz->execute([$pouzivatel['id'], $id_kat, $suma, $typ, $popis, $datum]);
            $id_transakcie = $databaza->lastInsertId();

            if ($stitky) {
                foreach (array_filter(array_map('trim', explode(',', $stitky))) as $nazov_stitku) {
                    $databaza->prepare('INSERT IGNORE INTO tags (user_id, name) VALUES (?,?)')->execute([$pouzivatel['id'], $nazov_stitku]);
                    $dotaz = $databaza->prepare('SELECT id FROM tags WHERE user_id = ? AND name = ?');
                    $dotaz->execute([$pouzivatel['id'], $nazov_stitku]);
                    $id_stitku = $dotaz->fetchColumn();
                    $databaza->prepare('INSERT IGNORE INTO transaction_tags (transaction_id, tag_id) VALUES (?,?)')->execute([$id_transakcie, $id_stitku]);
                }
            }

            if (isset($_FILES['blocek']) && $_FILES['blocek']['error'] === UPLOAD_ERR_OK) {
                $subor    = $_FILES['blocek'];
                $povolene = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
                if (!in_array($subor['type'], $povolene)) { $databaza->rollBack(); chyba('Nepodporovaný typ súboru'); }
                if ($subor['size'] > 5 * 1024 * 1024)    { $databaza->rollBack(); chyba('Súbor je príliš veľký (max 5 MB)'); }

                $priecinok_nahravani = __DIR__ . '/uploads/';
                $priecinok_nahliadov = __DIR__ . '/uploads/thumbs/';
                if (!is_dir($priecinok_nahravani)) mkdir($priecinok_nahravani, 0755, true);
                if (!is_dir($priecinok_nahliadov)) mkdir($priecinok_nahliadov, 0755, true);

                $pripona   = strtolower(pathinfo($subor['name'], PATHINFO_EXTENSION));
                $ulozene   = bin2hex(random_bytes(16)) . '.' . $pripona;
                move_uploaded_file($subor['tmp_name'], $priecinok_nahravani . $ulozene);

                $meno_nahladu = null;
                if (str_starts_with($subor['type'], 'image/')) {
                    $meno_nahladu = 'nahlad_' . $ulozene;
                    if (!vytvor_nahlad($priecinok_nahravani . $ulozene, $priecinok_nahliadov . $meno_nahladu, $subor['type'])) {
                        $meno_nahladu = null;
                    }
                }

                $databaza->prepare(
                    'INSERT INTO files (user_id, transaction_id, original_name, stored_name, size, mime_type, thumbnail_path)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$pouzivatel['id'], $id_transakcie, $subor['name'], $ulozene, $subor['size'], $subor['type'], $meno_nahladu]);
            }

            $databaza->commit();
            uspech(['id' => $id_transakcie]);
        } catch (Exception $e) {
            $databaza->rollBack();
            chyba('Chyba pri ukladaní');
        }

    case 'zmazat_transakciu':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chyba('Neplatná metóda');
        $id = (int)($_POST['id'] ?? 0);
        $dotaz = databaza()->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
        $dotaz->execute([$id, $pouzivatel['id']]);
        if (!$dotaz->fetch()) chyba('Transakcia nenájdená', 404);

        $databaza = databaza();
        $databaza->beginTransaction();
        try {
            $dotaz = $databaza->prepare('SELECT stored_name, thumbnail_path FROM files WHERE transaction_id = ?');
            $dotaz->execute([$id]);
            foreach ($dotaz->fetchAll() as $subor) {
                @unlink(__DIR__ . '/uploads/' . $subor['stored_name']);
                if ($subor['thumbnail_path']) @unlink(__DIR__ . '/uploads/thumbs/' . $subor['thumbnail_path']);
            }
            $databaza->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?')->execute([$id, $pouzivatel['id']]);
            $databaza->commit();
            uspech();
        } catch (Exception $e) {
            $databaza->rollBack();
            chyba('Chyba pri mazaní');
        }

    case 'upravit_transakciu':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chyba('Neplatná metóda');
        $id     = (int)($_POST['id'] ?? 0);
        $suma   = (float)($_POST['suma'] ?? 0);
        $typ    = $_POST['typ'] ?? '';
        $id_kat = ($_POST['kategoria_id'] ?? '') !== '' ? (int)$_POST['kategoria_id'] : null;
        $popis  = trim($_POST['popis'] ?? '');
        $datum  = $_POST['datum'] ?? date('Y-m-d');
        $stitky = trim($_POST['tagy'] ?? '');

        $dotaz = databaza()->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
        $dotaz->execute([$id, $pouzivatel['id']]);
        if (!$dotaz->fetch()) chyba('Transakcia nenájdená', 404);
        if ($suma <= 0)                             chyba('Suma musí byť kladná');
        if (!in_array($typ, ['vydavok', 'prijem'])) chyba('Neplatný typ');
        if (!$popis)                                chyba('Zadajte popis');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) $datum = date('Y-m-d');

        if ($id_kat) {
            $dotaz = databaza()->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
            $dotaz->execute([$id_kat, $pouzivatel['id']]);
            if (!$dotaz->fetch()) $id_kat = null;
        }

        $databaza = databaza();
        $databaza->beginTransaction();
        try {
            $databaza->prepare(
                'UPDATE transactions SET category_id=?, amount=?, type=?, description=?, date=? WHERE id=? AND user_id=?'
            )->execute([$id_kat, $suma, $typ, $popis, $datum, $id, $pouzivatel['id']]);

            $databaza->prepare('DELETE FROM transaction_tags WHERE transaction_id = ?')->execute([$id]);
            if ($stitky) {
                foreach (array_filter(array_map('trim', explode(',', $stitky))) as $nazov_stitku) {
                    $databaza->prepare('INSERT IGNORE INTO tags (user_id, name) VALUES (?,?)')->execute([$pouzivatel['id'], $nazov_stitku]);
                    $dotaz = $databaza->prepare('SELECT id FROM tags WHERE user_id = ? AND name = ?');
                    $dotaz->execute([$pouzivatel['id'], $nazov_stitku]);
                    $id_stitku = $dotaz->fetchColumn();
                    $databaza->prepare('INSERT IGNORE INTO transaction_tags (transaction_id, tag_id) VALUES (?,?)')->execute([$id, $id_stitku]);
                }
            }
            $databaza->commit();
            uspech();
        } catch (Exception $e) {
            $databaza->rollBack();
            chyba('Chyba pri úprave');
        }

    case 'pridat_kategoriu':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chyba('Neplatná metóda');
        $nazov = trim($_POST['nazov'] ?? '');
        $typ   = $_POST['typ'] ?? '';
        if (!$nazov)                                  chyba('Zadajte názov kategórie');
        if (!in_array($typ, ['vydavok', 'prijem']))   chyba('Neplatný typ');
        databaza()->prepare('INSERT INTO categories (user_id, name, type) VALUES (?,?,?)')->execute([$pouzivatel['id'], $nazov, $typ]);
        uspech();

    case 'zmazat_kategoriu':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chyba('Neplatná metóda');
        $id = (int)($_POST['id'] ?? 0);
        $dotaz = databaza()->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
        $dotaz->execute([$id, $pouzivatel['id']]);
        if (!$dotaz->fetch()) chyba('Kategória nenájdená', 404);
        databaza()->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?')->execute([$id, $pouzivatel['id']]);
        uspech();

    case 'upravit_profil':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chyba('Neplatná metóda');
        $meno  = trim($_POST['meno'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (!$meno)                                     chyba('Zadajte meno');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) chyba('Neplatný formát emailu');

        $databaza = databaza();
        $databaza->beginTransaction();
        try {
            $databaza->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email, $pouzivatel['id']]);
            $databaza->prepare('UPDATE profiles SET name=? WHERE user_id=?')->execute([$meno, $pouzivatel['id']]);
            $databaza->commit();
            $_SESSION['pouzivatel']['email'] = $email;
            $_SESSION['pouzivatel']['nazov'] = $meno;
            uspech();
        } catch (PDOException $e) {
            $databaza->rollBack();
            if ($e->getCode() == 23000) chyba('Tento email je už obsadený');
            chyba('Chyba pri ukladaní');
        }

    default:
        chyba('Neznáma akcia', 404);
}
