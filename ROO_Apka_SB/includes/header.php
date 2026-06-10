<?php
$_sprava    = zober_spravu();
$_pouzivatel = aktualny_pouzivatel();
?><!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= escapuj($nazov_stranky ?? 'Správca výdavkov') ?></title>
<style>
:root{--bg:#f4f7f6;--karta:#ffffff;--text:#333;--bledý:#666;--okraj:#e0e0e0;--hlavna:#000;--uspech:#28a745;--chyba:#dc3545}
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);margin:0;line-height:1.6}
header{background:var(--karta);border-bottom:1px solid var(--okraj);padding:15px 30px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
header h1{margin:0;font-size:20px}
header h1 a{text-decoration:none;color:inherit}
nav a{color:var(--text);text-decoration:none;margin-left:15px;font-size:14px}
nav a:hover{color:#005bb5}
.kontajner{max-width:1000px;margin:0 auto;padding:20px}
.prihlasovaci-box{max-width:420px;margin:60px auto;background:var(--karta);border:1px solid var(--okraj);border-radius:6px;padding:30px}
.prihlasovaci-box h2{margin-top:0;text-align:center;font-size:20px}
sekcia{background:var(--karta);border:1px solid var(--okraj);border-radius:6px;padding:20px;margin-bottom:30px}
section{background:var(--karta);border:1px solid var(--okraj);border-radius:6px;padding:20px;margin-bottom:30px}
h2{margin-top:0;font-size:18px;border-bottom:1px solid var(--okraj);padding-bottom:10px;margin-bottom:20px}
.prehlad-mriezka{display:flex;gap:20px;flex-wrap:wrap}
.prehlad-karta{flex:1;min-width:140px;padding:20px;border:1px solid var(--okraj);border-radius:6px;text-align:center}
.prehlad-karta h3{margin:0 0 10px;font-size:14px;color:var(--bledý);font-weight:normal}
.prehlad-karta p{margin:0;font-size:24px;font-weight:bold}
.text-uspech{color:var(--uspech)}.text-chyba{color:var(--chyba)}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--okraj);font-size:14px}
th{background:var(--bg);color:var(--bledý);font-weight:normal}
.skupina{margin-bottom:15px}
.skupina label{display:block;margin-bottom:5px;font-size:14px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=date],input[type=file],select,textarea{width:100%;padding:8px;border:1px solid var(--okraj);border-radius:4px;font-family:inherit;font-size:14px}
button,.tlacidlo{background:var(--hlavna);color:#fff;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block}
button:hover,.tlacidlo:hover{background:#005bb5}
.tlacidlo-chyba{background:var(--chyba)}.tlacidlo-chyba:hover{background:#a71d2a}
.tlacidlo-uspech{background:var(--uspech)}.tlacidlo-uspech:hover{background:#1e7e34}
.tlacidlo-male{padding:4px 10px;font-size:12px}
.riadok-formulara{display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap}
.riadok-formulara .skupina{flex:1;min-width:100px;margin-bottom:0}
.zoznam-kategorii{list-style:none;padding:0;margin:0 0 20px}
.zoznam-kategorii li{padding:10px;border-bottom:1px solid var(--okraj);display:flex;justify-content:space-between;align-items:center}
.kontajner-profilu{display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.avatar{width:80px;height:80px;border-radius:50%;object-fit:cover}
.avatar-zastupca{width:80px;height:80px;border-radius:50%;background:var(--okraj);display:flex;align-items:center;justify-content:center;color:var(--bledý);font-size:12px;flex-shrink:0}
.info-profilu h3{margin:0 0 5px}.info-profilu p{margin:0 0 15px;color:var(--bledý)}
.sprava{padding:12px 20px;margin:15px 0;border-radius:4px;font-size:14px}
.sprava-uspech{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.sprava-chyba{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
.strankovanie{display:flex;gap:6px;justify-content:center;margin-top:15px;flex-wrap:wrap}
.strankovanie a,.strankovanie span{padding:6px 11px;border:1px solid var(--okraj);border-radius:4px;text-decoration:none;color:var(--text);font-size:14px}
.strankovanie .aktivna{background:var(--hlavna);color:#fff;border-color:var(--hlavna)}
.stitok{display:inline-block;padding:2px 7px;border-radius:10px;font-size:11px;background:var(--bg);border:1px solid var(--okraj);margin:1px}
@media(max-width:640px){
  header{padding:12px 15px}
  nav{width:100%}
  nav a{margin-left:0;margin-right:12px}
  .prehlad-mriezka{flex-direction:column}
  .riadok-formulara{flex-direction:column}
  td:nth-child(5),th:nth-child(5){display:none}
}
</style>
</head>
<body>
<header>
  <h1><a href="<?= BASE ?>/index.php">Správca výdavkov</a></h1>
  <nav>
    <?php if ($_pouzivatel): ?>
      <a href="<?= BASE ?>/index.php">Prehľad</a>
      <a href="<?= BASE ?>/profile.php">Profil</a>
      <?php if ($_pouzivatel['rola'] === 'admin'): ?><a href="<?= BASE ?>/admin/index.php">Admin</a><?php endif; ?>
      <a href="<?= BASE ?>/logout.php">Odhlásiť sa</a>
    <?php else: ?>
      <a href="<?= BASE ?>/login.php">Prihlásiť</a>
      <a href="<?= BASE ?>/register.php">Registrácia</a>
    <?php endif; ?>
  </nav>
</header>
<?php if ($_sprava): ?>
<div class="kontajner">
  <div class="sprava sprava-<?= escapuj($_sprava['typ']) ?>"><?= escapuj($_sprava['text']) ?></div>
</div>
<?php endif; ?>
