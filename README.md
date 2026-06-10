# SpravcaVydavkov
Projekt pre predmet Rozvoj Odboru

Správca výdavkov
Implementácia požiadaviek — prehľad
Registrácia, prihlásenie a odhlásenie
register.php spracuje odoslaný formulár, zvaliduje vstupy, zahashuje heslo a vloží nového
používateľa spolu s profilom do databázy. Po úspešnej registrácii presmeruje na prihlasovaciu
stránku.
login.php nájde používateľa podľa e-mailu, overí heslo funkciou password_verify() a uloží jeho
údaje do $_SESSION. Potom presmeruje na dashboard.
logout.php zničí session na serveri, zneplatní cookie v prehliadači a presmeruje späť na
prihlásenie. Je to samostatný súbor, takže naňho môžeme vždy odkazovať z navigácie.
Bezpečné ukladanie hesiel
Čisté heslo sa nikdy neuloží do databázy. Pri registrácii ho spracuje password_hash() s algoritmom
bcrypt, ktorý automaticky vygeneruje náhodný salt a zahrnie ho do výsledného reťazca.
$hash_hesla = password_hash($heslo, PASSWORD_DEFAULT);
// výsledok: "$2y$10$Kv8abc..." — vždy iný aj pre rovnaké heslo
Pri prihlásení password_verify() porovná zadané heslo s uloženým hashom. Keďže bcrypt je
zámerne pomalý algoritmus, brute-force útok na ukradnutú databázu by trval neúmerne dlho.
Správa sessions cez cookies
PHP pred spustením session nastaví cookie parametre: httponly zabraňuje JavaScriptu čítať cookie
(ochrana pred XSS), samesite=Lax chráni pred CSRF.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
Po úspešnom prihlásení sa id a rola používateľa uložia do $_SESSION. PHP vygeneruje náhodné
SESSION_ID, uloží ho do cookie prehliadača a prepojí s dátami na serveri. Prehliadač cookie
automaticky posiela pri každom ďalšom requeste — server tak vie kto žiada o stránku.
Pri odhlásení session_destroy() zmaže dáta na serveri a setcookie() s expiráciou v minulosti prinúti
prehliadač cookie zmazať.
Zmazanie účtu s vymazaním dát
delete_account.php najprv ručne zmaže fyzické súbory z disku — CASCADE v databáze totiž maže
len záznamy v tabuľkách, nie súbory na disku. Potom zmaže záznam v tabuľke users.
Správca výdavkov — Implementácia požiadaviek  
foreach ($subory as $subor) {
@unlink(__DIR__ . '/uploads/' . $subor['stored_name']);
}
$databaza->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
Všetky cudzie kľúče odkazujúce na users.id majú ON DELETE CASCADE, takže databáza
automaticky zmaže profil, kategórie, transakcie, záznamy súborov aj štítky. Celá operácia
prebieha v transakcii.
Chránené stránky pre prihlásených
Každá chránená stránka začína volaním vyzadovat_prihlasenie(). Funkcia skontroluje, či existuje
$_SESSION['pouzivatel']. Ak nie, presmeruje na login a okamžite zavolá exit — bez exit by PHP
pokračovalo vo vykonávaní zvyšku stránky aj napriek presmerovaniu.
function vyzadovat_prihlasenie(): void {
if (!aktualny_pouzivatel()) {
header('Location: /ROO_Apka_SB/login.php');
exit;
}
}
Opačne funguje presmerovat_prihlaseneho() na login.php a register.php — prihlásený používateľ
je odoslaný priamo na dashboard.
Obmedzenie prístupu k dátam podľa používateľa
Každý SQL dotaz na transakcie, kategórie alebo súbory obsahuje podmienku AND user_id = ?.
Pred každým zmazaním alebo úpravou sa najprv overí, že záznam naozaj patrí prihlásenému
používateľovi.
$dotaz = databaza()->prepare(
'SELECT id FROM transactions WHERE id = ? AND user_id = ?'
);
$dotaz->execute([$id, $pouzivatel['id']]);
if (!$dotaz->fetch()) chyba('Transakcia nenajdena', 404);
Ak id patrí inému používateľovi, databáza nevráti žiadny záznam a požiadavka skončí chybou 404.
Útočník nedostane ani potvrdenie, že taký záznam existuje.
Admin-only stránky
Admin stránky začínajú volaním vyzadovat_admina(). Funkcia overí session a skontroluje, či má
prihlásený používateľ rolu admin. Bežný používateľ, ktorý zadá adresu /admin/users.php priamo,
je presmerovaný na dashboard.
function vyzadovat_admina(): void {
Správca výdavkov — Implementácia požiadaviek  
$p = aktualny_pouzivatel();
if (!$p || $p['rola'] !== 'admin') {
header('Location: /ROO_Apka_SB/index.php');
exit;
}
}
Admin dashboard na správu zdrojov
admin/users.php zobrazuje všetkých používateľov s počtom transakcií. Admin môže zmeniť rolu
(user na admin a naopak), zablokovať alebo odblokovať účet a úplne zmazať používateľa vrátane
všetkých jeho dát.
Blokovanie je praktickejšie ako mazanie — používateľ zostane v databáze, jeho dáta sú
zachované, ale pri prihlásení dostane chybu. Admin nemôže vykonať akciu na vlastnom účte, čím
sa ochráni pred náhodným odobratím si adminskej roly.
Správca výdavkov — Implementácia požiadaviek  
Databázový model s viacerými tabuľkami
Projekt používa 7 tabuliek. Každá reprezentuje jednu entitu. users uchováva prihlasovanie, profiles
doplnkové údaje o používateľovi, categories skupiny výdavkov a príjmov, transactions jednotlivé
záznamy, files metadáta nahraných súborov, tags štítky a transaction_tags prepojenie medzi
transakciami a štítkami.
Vzťah 1:1
Vzťah medzi users a profiles je 1:1 — jeden používateľ má práve jeden profil. Dosahuje sa
obmedzením UNIQUE na stĺpci user_id v tabuľke profiles. Bez neho by šlo o vzťah 1:N.
CREATE TABLE profiles (
user_id INT NOT NULL UNIQUE,
FOREIGN KEY (user_id) REFERENCES users(id)
);
Vzťah 1:N
Jeden používateľ môže mať neobmedzene veľa transakcií, ale každá transakcia patrí práve
jednému používateľovi. Cudzí kľúč user_id v tabuľke transactions nemá obmedzenie UNIQUE, čo
umožňuje viacerým riadkom odkazovať na toho istého používateľa.
Vzťah M:N
Jedna transakcia môže mať viacero štítkov a jeden štítok môže patriť viacerým transakciám.
Relačná databáza nedokáže uložiť pole hodnôt do jedného stĺpca, preto existuje spojovacia
tabuľka transaction_tags. Každý jej riadok reprezentuje jeden vzťah medzi transakciou a štítkom.
Zložený primárny kľúč zabraňuje duplicitným párom.
CREATE TABLE transaction_tags (
transaction_id INT NOT NULL,
tag_id INT NOT NULL,
PRIMARY KEY (transaction_id, tag_id)
);
Dátová integrita na úrovni databázy
Databáza vynucuje konzistentnosť nezávisle od PHP kódu. NOT NULL zabraňuje prázdnym
povinným poliam, UNIQUE duplicitám (username, email, kombinácia user_id a názvu štítku), ENUM
neplatným hodnotám (rola môže byť len user alebo admin, typ len vydavok alebo prijem).
Cudzie kľúče majú definované správanie pri mazaní. ON DELETE CASCADE zmaže závislé záznamy
spolu s rodičom. ON DELETE SET NULL ponechá transakciu, len nastaví category_id na NULL —
transakcia zostane aj po zmazaní kategórie.
Správca výdavkov — Implementácia požiadaviek  
Databázové transakcie
Všade kde sa vykonávajú viaceré súvisiace SQL operácie je použitá databázová transakcia.
Zaručuje atomicitu — buď sa uložia všetky zmeny, alebo žiadna. Napríklad pri registrácii sa vkladá
riadok do users aj do profiles. Keby INSERT do profiles zlyhal bez transakcie, vznikol by používateľ
bez profilu.
$databaza->beginTransaction();
try {
// INSERT do users
// INSERT do profiles
$databaza->commit();
} catch (Exception $e) {
$databaza->rollBack();
}
Správca výdavkov — Implementácia požiadaviek  
Kompletný CRUD pre jeden zdroj
Transakcie pokrývajú všetky štyri operácie. Create a Read prebiehajú cez AJAX — JavaScript
posiela požiadavky na api.php a aktualizuje tabuľku bez obnovenia stránky. Update používa
klasickú PHP stránku edit_transaction.php s pred-vyplneným formulárom. Delete je AJAX volanie s
potvrdením cez confirm().
CRUD operácie sú autorizované
Pred každým UPDATE alebo DELETE sa overí vlastníctvo záznamu — dotaz vždy obsahuje
podmienku AND user_id = ?. Ak záznam nepatrí prihlásenému používateľovi, operácia skončí
chybou 404. Útočník, ktorý pošle cudzie id, nedostane žiadnu informáciu o existencii záznamu.
Upload súborov s validáciou
Pri každom nahrávaní sa skontroluje chybový kód uploadu, MIME typ a veľkosť súboru. MIME typ je
spoľahlivejší ako prípona — útočník môže premenovať virus.php na virus.jpg, ale MIME typ
zostane application/x-php. Povolené sú len obrázky a PDF.
Súbor sa uloží pod náhodným menom vygenerovaným cez random_bytes(). Pôvodný názov tak
nemôže obsahovať znaky ako ../ ktoré by mohli presmerovať zápis mimo určeného priečinka.
$ulozene = bin2hex(random_bytes(16)) . '.' . $pripona;
move_uploaded_file(\$subor['tmp_name'], __DIR__ . '/uploads/' . \$ulozene);
Metadáta súborov v databáze
Tabuľka files uchováva pôvodný názov, uložené meno, veľkosť, MIME typ, cestu k miniature a
väzbu na transakciu. Samotný súbor na disku tieto informácie neobsahuje. Záznamy v databáze
umožňujú zobrazovať pôvodný názov používateľovi, overovať vlastníctvo a filtrovať súbory.
Spracovanie súborov po nahratí
Pre každý nahraný obrázok vytvorí GD knižnica miniatúru s maximálnou veľkosťou 200x200
pixelov pri zachovaní pomeru strán. Používa sa imagecopyresampled, ktorá zachováva lepšiu
kvalitu ako imagecopyresized. PDF súbory miniatúru nedostanú, thumbnail_path zostane null.
Konzistentný vizuálny štýl
Všetky farby sú definované ako CSS premenné v includes/header.php. Keďže header.php sa
vkladá na každej stránke cez require, zmena farby na jednom mieste sa prejaví všade. Každá
stránka teda vyzerá rovnako bez kopírovania CSS.
Responzívny layout
Správca výdavkov — Implementácia požiadaviek  
Pri šírke obrazovky pod 640 pixelov media query zmení flex-direction z row na column —
navigácia, karty prehľadu a formulárové polia sa poskladajú pod seba. Stĺpec so štítkami sa na
mobile skryje, aby sa tabuľka horizontálne nescrollovala.
Znovupoužiteľné PHP komponenty
Štyri súbory v priečinku includes sa vkladajú na každej stránke. db.php obsahuje PDO spojenie s
databázou, auth.php session logiku a pomocné funkcie, header.php HTML hlavičku s CSS a
navigáciou, footer.php zatvára dokument. Zmena navigácie si vyžaduje úpravu jediného súboru
namiesto každej stránky zvlášť.
Hlášky spätnej väzby
Flash správy sa ukladajú do session pred presmerovaním. Keďže po header('Location: ...') a exit
PHP skončí, bežná premenná by zanikla. Session prežije presmerovanie — header.php si správu
vyzdvihne, zobrazí a ihneď zmaže, takže sa ukáže iba raz.
Na stránkach bez presmerovania (login, register) sa chyby zbierajú do poľa $chyby a zobrazujú
priamo v HTML formulári. AJAX volania vracajú chybu cez JSON a JavaScript ju zobrazí.
Stránkovanie
Transakcie aj zoznam používateľov v admin rozhraní sú stránkované. SQL klauzula LIMIT ? OFFSET
? zabezpečí, že databáza vráti iba záznamy pre aktuálnu stránku. Načítavanie všetkých záznamov
do PHP a následné filtrovanie v pamäti by bolo neefektívne pri väčšom počte dát.
$posun = ($stranka - 1) * $na_stranku;
$pocet_stranok = (int)ceil($celkovo / $na_stranku);
// stránka 2, 10 záznamov: LIMIT 10 OFFSET 10
Ochrana proti SQL injection
Každý SQL dotaz v projekte používa prepared statements s placeholdermi. PHP pošle do databázy
šablónu dotazu a hodnoty oddelene — databáza ich spracuje ako dáta, nie ako kód. Útočník
nemôže hodnotou zmeniť štruktúru dotazu.
$dotaz = databaza()->prepare('SELECT * FROM users WHERE email = ?');
$dotaz->execute([$email]);
// LIMIT/OFFSET sa binduje explicitne ako INT
$dotaz->bindValue(1, $na_stranku, PDO::PARAM_INT);
Ochrana proti XSS
Všetky dáta od používateľa sa pred vypísaním do HTML spracujú funkciou escapuj(), ktorá obalí
htmlspecialchars(). Špeciálne znaky ako < > " sa nahradia HTML entitami — prehliadač ich zobrazí
ako text a nespustí.
Správca výdavkov — Implementácia požiadaviek  
To isté platí na strane JavaScriptu. Pri vkladaní dát do innerHTML sa volá lokálna funkcia escapuj(),
ktorá nahradí rovnaké znaky entitami.
Modulárna štruktúra projektu
Každý súbor rieši jednu vec — login.php len prihlásenie, api.php len JSON odpovede,
edit_transaction.php len úpravu. Opakujúca sa logika je v pomocných funkciách ako escapuj()
alebo nastav_spravu(). PHP logika sa spracúva pred výstupom HTML, nie zmiešane s ním.
Priečinok uploads/ neobsahuje PHP kód a .htaccess zakazuje spustenie skriptov z neho. Priečinok
admin/ je oddelený a chránený vlastnou kontrolou roly.
Správca výdavkov — Implementácia požiadaviek  
