# Správca výdavkov

Webová aplikácia na sledovanie osobných príjmov a výdavkov. Postavená na PHP.

---

## Obsah

- [Funkcie](#funkcie)
- [Technológie](#technológie)
- [Štruktúra projektu](#štruktúra-projektu)
- [Databázový model](#databázový-model)
- [Inštalácia](#inštalácia)
- [Bezpečnosť](#bezpečnosť)

---

## Funkcie

**Používateľský účet**
- Registrácia a prihlásenie
- Správa profilu s avatárom
- Zmazanie účtu vrátane všetkých dát

**Transakcie**
- Pridávanie príjmov a výdavkov s kategóriou, štítkami a dátumom
- Nahrávanie bločkov (JPEG, PNG, GIF, WEBP, PDF — max 5 MB)
- Automatické generovanie miniatúr
- Úprava a mazanie transakcií
- Stránkovanie (10 záznamov na stránku)

**Dashboard**
- Prehľad zostatku, príjmov a výdavkov
- Tabuľka transakcií aktualizovaná cez AJAX bez obnovenia stránky
- Filtrovanie a štítkovanie

**Admin rozhranie**
- Zoznam všetkých používateľov s počtom transakcií
- Zmena roly, blokovanie/odblokovanie, zmazanie účtu

---

## Technológie

| Vrstva | Technológia |
|---|---|
| Backend | PHP 8+ |
| Databáza | MySQL |
| Prístup k DB | PDO s prepared statements |
| Frontend | HTML, CSS, vanilla JavaScript (Fetch API) |
| Obrázky | GD knižnica (miniatúry) |
| Server | Apache (XAMPP) |

---

## Štruktúra projektu

```
ROO_Apka_SB/
├── includes/
│   ├── db.php              # PDO spojenie s databázou
│   ├── auth.php            # session, ochrana stránok, pomocné funkcie
│   ├── header.php          # HTML hlavička, CSS, navigácia
│   └── footer.php          # zatváracie tagy
│
├── login.php               # prihlásenie
├── register.php            # registrácia
├── logout.php              # odhlásenie
├── index.php               # dashboard (HTML kostra + JS)
├── api.php                 # JSON API pre AJAX volania
├── edit_transaction.php    # úprava transakcie
├── profile.php             # nahratie avatára
├── delete_account.php      # zmazanie účtu
├── setup.php               # jednorazová inicializácia DB
│
├── admin/
│   ├── index.php           # admin štatistiky
│   └── users.php           # správa používateľov
│
├── uploads/                # nahrané súbory
│   └── thumbs/             # miniatúry obrázkov
│
└── sql/
    └── schema.sql          # schéma databázy
```

---

## Databázový model

```
users ──────────────── profiles         1:1
  │
  ├──────────────────── categories      1:N
  │
  ├──────────────────── transactions    1:N
  │                        │
  │                        ├──── files              1:N
  │                        └──── transaction_tags ──── tags   M:N
  │
  └──────────────────── tags            1:N
```

### Tabuľky

**users** — prihlasovacie údaje, rola, stav blokovania  
**profiles** — zobrazované meno a avatár (vzťah 1:1 cez `user_id UNIQUE`)  
**categories** — kategórie príjmov a výdavkov vlastnené používateľom  
**transactions** — jednotlivé záznamy so sumou, typom, popisom a dátumom  
**files** — metadáta nahraných súborov (pôvodný názov, uložené meno, MIME typ, miniatúra)  
**tags** — štítky vlastnené používateľom  
**transaction_tags** — spojovacia tabuľka M:N medzi transakciami a štítkami  

Cudzie kľúče používajú `ON DELETE CASCADE` — zmazanie používateľa automaticky zmaže všetky jeho dáta. Výnimkou je `category_id` v transakciách, kde sa použije `ON DELETE SET NULL`, aby transakcia zostala aj po zmazaní kategórie.

---

## Inštalácia

**Požiadavky:** XAMPP (Apache + MariaDB + PHP 8+)

1. Skopíruj projekt do `htdocs`:
   ```bash
   cp -r ROO_Apka_SB/ /Applications/XAMPP/htdocs/
   ```

2. Spusti Apache a MariaDB v XAMPP Control Panel.

3. Vytvor databázu v phpMyAdmin alebo cez terminál:
   ```sql
   CREATE DATABASE vydavky CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Inicializuj schému:
   ```
   http://localhost/ROO_Apka_SB/setup.php
   ```
   Po úspešnej inicializácii `setup.php` zmaž alebo zamestni prístup k nemu.

5. Aplikácia beží na:
   ```
   http://localhost/ROO_Apka_SB/
   ```

---

## Bezpečnosť

**SQL injection** — každý dotaz používa PDO prepared statements s placeholdermi. SQL šablóna a dáta sa posielajú do databázy oddelene.

**XSS** — všetky výstupy užívateľských dát prechádzajú cez `htmlspecialchars()` v PHP aj cez ekvivalentnú funkciu v JavaScripte pred vložením do `innerHTML`.

**Heslá** — ukladajú sa výlučne ako bcrypt hash cez `password_hash()`. Overenie prebieha cez `password_verify()`.

**Session** — cookie má nastavené `httponly` (JavaScript k nej nemá prístup) a `samesite=Lax` (ochrana pred CSRF).

**Upload súborov** — overuje sa MIME typ (nie len prípona), veľkosť (max 5 MB) a chybový kód. Súbory sa ukladajú pod náhodným menom generovaným cez `random_bytes()`. Priečinok `uploads/` má `.htaccess` zakazujúci spustenie PHP súborov.

**Autorizácia dát** — každý SQL dotaz na transakcie, kategórie a súbory obsahuje podmienku `AND user_id = ?`. Používateľ nemôže čítať ani meniť cudzie záznamy.

**Admin ochrana** — admin stránky overujú rolu pri každom requeste na serveri, nestačí skryť odkaz v HTML.
