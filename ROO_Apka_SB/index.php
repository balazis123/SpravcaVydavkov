<?php
require 'includes/db.php';
require 'includes/auth.php';
vyzadovat_prihlasenie();

$nazov_stranky = 'Prehľad';
require 'includes/header.php';
?>
<div class="kontajner">

<section>
  <h2>Prehľad</h2>
  <div class="prehlad-mriezka">
    <div class="prehlad-karta"><h3>Celkový zostatok</h3><p id="zostatok">—</p></div>
    <div class="prehlad-karta"><h3>Celkové príjmy</h3><p id="prijmy" class="text-uspech">—</p></div>
    <div class="prehlad-karta"><h3>Celkové výdavky</h3><p id="vydavky" class="text-chyba">—</p></div>
  </div>
</section>

<section>
  <h2>Transakcie</h2>
  <table>
    <thead><tr>
      <th>Dátum</th><th>Popis</th><th>Kategória</th><th>Suma</th><th>Štítky</th><th>Akcie</th>
    </tr></thead>
    <tbody id="zoznam-transakcii"></tbody>
  </table>
  <div class="strankovanie" id="strankovanie"></div>
</section>

<section>
  <h2>Pridať transakciu</h2>
  <form id="formular-transakcie">
    <div class="riadok-formulara" style="margin-bottom:15px">
      <div class="skupina">
        <label>Suma (€)</label>
        <input type="number" name="suma" step="0.01" min="0.01" placeholder="0.00" required>
      </div>
      <div class="skupina">
        <label>Typ</label>
        <select name="typ">
          <option value="vydavok">Výdavok</option>
          <option value="prijem">Príjem</option>
        </select>
      </div>
      <div class="skupina">
        <label>Kategória</label>
        <select name="kategoria_id" id="vyber-kategorie"></select>
      </div>
      <div class="skupina">
        <label>Dátum</label>
        <input type="date" name="datum" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div class="skupina">
      <label>Popis</label>
      <input type="text" name="popis" placeholder="Krátky popis transakcie" required>
    </div>
    <div class="skupina">
      <label>Štítky (oddelené čiarkou)</label>
      <input type="text" name="tagy" placeholder="napr. jedlo, práca">
    </div>
    <div class="skupina">
      <label>Nahrať bloček (voliteľné, max 5 MB)</label>
      <input type="file" name="blocek" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf">
    </div>
    <button type="submit">Pridať transakciu</button>
  </form>
</section>

<section>
  <h2>Kategórie</h2>
  <ul class="zoznam-kategorii" id="zoznam-kategorii"></ul>
  <form id="formular-kategorie" class="riadok-formulara">
    <div class="skupina">
      <label>Názov kategórie</label>
      <input type="text" name="nazov" placeholder="Nová kategória" required>
    </div>
    <div class="skupina">
      <label>Typ</label>
      <select name="typ">
        <option value="vydavok">Výdavok</option>
        <option value="prijem">Príjem</option>
      </select>
    </div>
    <button type="submit">Pridať kategóriu</button>
  </form>
</section>

<section>
  <h2>Môj profil</h2>
  <div class="kontajner-profilu">
    <div id="obalenie-avatara"><div class="avatar-zastupca">Avatar</div></div>
    <div class="info-profilu">
      <h3 id="meno-profilu">—</h3>
      <p id="email-profilu">—</p>
      <button id="tlacidlo-upravit-profil" type="button">Upraviť meno / email</button>
      <a href="<?= BASE ?>/profile.php" class="tlacidlo" style="margin-left:8px">Nahrať avatar</a>
      <a href="<?= BASE ?>/delete_account.php" class="tlacidlo tlacidlo-chyba" style="margin-left:8px">Zmazať účet</a>
    </div>
  </div>
</section>

</div>

<script>
const API = '<?= BASE ?>/api.php';
let aktualnaStranka = 1;

const formatovac = new Intl.NumberFormat('sk-SK', {minimumFractionDigits:2, maximumFractionDigits:2});
const euro = hodnota => formatovac.format(Number(hodnota || 0)) + ' €';
const escapuj = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

async function volajApi(akcia, nastavenia = {}) {
    const doplnokUrl = nastavenia.doplnokUrl || '';
    const moznosti = Object.assign({}, nastavenia);
    delete moznosti.doplnokUrl;
    const odpoved = await fetch(API + '?action=' + encodeURIComponent(akcia) + doplnokUrl, moznosti);
    const data = await odpoved.json();
    if (!data.ok) throw new Error(data.message || 'Chyba servera');
    return data;
}

function zobrazPrehlad(s) {
    const zostatok = document.getElementById('zostatok');
    zostatok.textContent = euro(s.zostatok);
    zostatok.className = s.zostatok >= 0 ? 'text-uspech' : 'text-chyba';
    document.getElementById('prijmy').textContent  = euro(s.prijmy);
    document.getElementById('vydavky').textContent = euro(s.vydavky);
}

function zobrazTransakcie(zoznam, celkoveStranky, aktStranka) {
    const telo = document.getElementById('zoznam-transakcii');
    telo.innerHTML = '';
    if (!zoznam.length) {
        telo.innerHTML = "<tr><td colspan='6' style='color:#999'>Žiadne transakcie.</td></tr>";
    } else {
        zoznam.forEach(t => {
            const jePrijem = t.type === 'prijem';
            const riadok = document.createElement('tr');
            const stitky = t.tags ? t.tags.split(',').map(s => `<span class="stitok">${escapuj(s.trim())}</span>`).join('') : '';
            riadok.innerHTML = `
                <td>${escapuj(t.date)}</td>
                <td>${escapuj(t.description)}</td>
                <td>${escapuj(t.kategoria)}</td>
                <td class="${jePrijem ? 'text-uspech' : 'text-chyba'}">${jePrijem ? '+' : '-'} ${euro(t.amount)}</td>
                <td>${stitky}</td>
                <td style="white-space:nowrap">
                    <a href="<?= BASE ?>/edit_transaction.php?id=${t.id}" class="tlacidlo tlacidlo-male">Upraviť</a>
                    <button class="tlacidlo tlacidlo-male tlacidlo-chyba" onclick="zmazTransakciu(${t.id})">Zmazať</button>
                </td>`;
            telo.appendChild(riadok);
        });
    }

    const strankovanie = document.getElementById('strankovanie');
    strankovanie.innerHTML = '';
    if (celkoveStranky > 1) {
        for (let i = 1; i <= celkoveStranky; i++) {
            if (i === aktStranka) {
                const prvok = document.createElement('span');
                prvok.textContent = i; prvok.className = 'aktivna';
                strankovanie.appendChild(prvok);
            } else {
                const odkaz = document.createElement('a');
                odkaz.textContent = i; odkaz.href = '#';
                odkaz.onclick = (cislo => ev => { ev.preventDefault(); aktualnaStranka = cislo; obnov(); })(i);
                strankovanie.appendChild(odkaz);
            }
        }
    }
}

function zobrazKategorie(kategorie) {
    const zoznam = document.getElementById('zoznam-kategorii');
    const vyber  = document.getElementById('vyber-kategorie');
    zoznam.innerHTML = '';
    vyber.innerHTML  = '<option value="">— bez kategórie —</option>';
    kategorie.forEach(k => {
        const polozka = document.createElement('li');
        polozka.innerHTML = `<span>${escapuj(k.name)} <small style="color:#999">(${k.type === 'prijem' ? 'Príjem' : 'Výdavok'})</small></span>
            <button class="tlacidlo tlacidlo-male tlacidlo-chyba" onclick="zmazKategoriu(${k.id})">Zmazať</button>`;
        zoznam.appendChild(polozka);
        const moznost = document.createElement('option');
        moznost.value = k.id; moznost.textContent = k.name;
        vyber.appendChild(moznost);
    });
}

function zobrazProfil(profil) {
    document.getElementById('meno-profilu').textContent  = profil.name;
    document.getElementById('email-profilu').textContent = profil.email;
    const obalenie = document.getElementById('obalenie-avatara');
    if (profil.avatar_path) {
        obalenie.innerHTML = `<img class="avatar" src="<?= BASE ?>/uploads/${escapuj(profil.avatar_path)}" alt="Avatar">`;
    } else {
        obalenie.innerHTML = '<div class="avatar-zastupca">Avatar</div>';
    }
}

async function obnov() {
    try {
        const data = await volajApi('dashboard', {doplnokUrl: '&page=' + aktualnaStranka});
        zobrazPrehlad(data.data.prehlad);
        zobrazTransakcie(data.data.transakcie, data.data.pocet_stranok, data.data.aktualna_stranka);
        zobrazKategorie(data.data.kategorie);
        zobrazProfil(data.data.profil);
    } catch (e) { alert('Chyba načítania: ' + e.message); }
}

async function zmazTransakciu(id) {
    if (!confirm('Naozaj zmazať túto transakciu?')) return;
    try {
        const data = new FormData(); data.append('id', id);
        await volajApi('zmazat_transakciu', {method: 'POST', body: data});
        await obnov();
    } catch (e) { alert(e.message); }
}

async function zmazKategoriu(id) {
    if (!confirm('Naozaj zmazať túto kategóriu?')) return;
    try {
        const data = new FormData(); data.append('id', id);
        await volajApi('zmazat_kategoriu', {method: 'POST', body: data});
        await obnov();
    } catch (e) { alert(e.message); }
}

document.getElementById('formular-transakcie').addEventListener('submit', async e => {
    e.preventDefault();
    try {
        await volajApi('pridat_transakciu', {method: 'POST', body: new FormData(e.target)});
        e.target.reset();
        e.target.querySelector('[name=datum]').value = new Date().toISOString().slice(0,10);
        aktualnaStranka = 1;
        await obnov();
    } catch (chyba) { alert(chyba.message); }
});

document.getElementById('formular-kategorie').addEventListener('submit', async e => {
    e.preventDefault();
    try {
        await volajApi('pridat_kategoriu', {method: 'POST', body: new FormData(e.target)});
        e.target.reset();
        await obnov();
    } catch (chyba) { alert(chyba.message); }
});

document.getElementById('tlacidlo-upravit-profil').addEventListener('click', async () => {
    const meno  = prompt('Meno:', document.getElementById('meno-profilu').textContent);
    if (meno === null) return;
    const email = prompt('Email:', document.getElementById('email-profilu').textContent);
    if (email === null) return;
    try {
        const data = new FormData();
        data.append('meno', meno); data.append('email', email);
        await volajApi('upravit_profil', {method: 'POST', body: data});
        await obnov();
    } catch (e) { alert(e.message); }
});

obnov();
</script>
<?php require 'includes/footer.php'; ?>
