
# EETCLI - Commandline klient pro EET (etrzby.cz)

Tento klient slouží pro odesílání tržeb pomocí skriptu nebo externího programu.
Základním zadáním bylo udělat co nejjednodušší a nejodolnější implementaci tak,
aby klient sice jen odesílal data na server ale v případě potřeby i uchovával informace 
pro pozdější kontrolu. 

# Vlastnosti

* Multiplatformnost (PHP+PHAR)
* Jednoduchost (řeší se pouze načtení, vytvoření a odeslání účtenky)
* Vše ostatní je na skriptech a aplikacích, které klienta využijí
* V základním režimu pouze vrací FIK
* V rozšířeném režimu může vrátit jakoukoliv položku z EET
* Může být použit i k vytvoření účtenky pomocí parametru *--format @sablonauctenky.txt*
* Umí načíst, vytvořit, zapsat, otestovat a odeslat EET soubor [EETFile](doc/EETFile.md)
* EET soubor může být vytvořen i externí aplikací, například účetním programem
* Může být spouštěn i jako externí program například z účetního software při vytvoření paragonu.
* Konfigurace v ini souboru nebo přímo z příkazové řádky
* V základní konfiguraci nastaven pro neprodukční prostředí a ověřovací režím. 
* Dokud nenastavíte DIČ (v ini nebo *--dic*), klient vrací výchozí testovací účtenku.

# Licence

Tento projekt je licencován pod GPL3. Zříkám se jakékoliv zodpovědnosti při používání tohoto programu.
I když se snažím vše odzkoušet, používání pro odesílání datových zpráv do EET je jen na Vás a Vaší zodpovědnosti.
Použil jsem komponenty třetích stran, které jsou rovněž šířeny pod otevřenou licencí, zejména
* [ondrejnov/eet](https://github.com/ondrejnov/eet)
* dealnews/console
* kherge/box

# Použití
Malý návod k použití je i součástí samotného příkazu.
```
$ eetcli.php -h
eetcli [--options]
Seznam dostupnych maker na vystupu v poli format:
Poznamka: format muze zacinat znakem '@' coz znamena, ze bude nacten ze souboru, ne z parametru. Napr. @soubor.txt.
 {fik}		 fik kod
 {bkp}		 bkp kod
 {pkp}		 pkp kod
   a dalsi: {uuid_zpravy},{dat_odesl},{prvni_zaslani},{overeni},{dic_popl},{dic_poverujiciho},{id_provoz},{id_pokl},{porad_cis},{dat_trzby},{celk_trzba},{zakl_nepodl_dph},{zakl_dan1},{dan1},{zakl_dan2},{dan2},{zakl_dan3},{dan3},{cest_sluz},{pouzit_zboz1},{pouzit_zboz2},{pouzit_zboz3},{urceno_cerp_zuct},{cerp_zuct},{pkp},{bkp},{fik}

Seznam promennych prostredi, ktere je mozno pouzit:
 TMP		 adresar pro docasne soubory
 EETCLI_DEBUG	 debug level (0-4)

Mody pouziti:
-N file.eet		 Vytvor EET soubor z parametru a nikam nezasilej. Je mozno pouzit i makra v nazvu souboru, napr. {uuid_zpravy}
-C file.eet		 Vytvor EET soubor z parametru a zaroven zasli na etrzby. Je mozno pouzit i makra v nazvu souboru, napr. {uuid_zpravy}
-S file.eet		 Nacti EET soubor a pokud jeste nebyl zaslan, posli na etrzby. Nasledne uloz pod stejnym jmenem. V pripade, ze uz byl dany eet soubor zaslan drive, vrati se chyba.
-P file.eet		 Nacti EET soubor, otestuj jeho stav a pouze vypis informace podle format. Pokud nesedi kontrolni soucty, vrat chybu.
-T file.eet		 Nacti EET soubor, otestuj jeho stav a pouze vrat chybove hlaseni a navratovy kod podle stavu souboru.

Navratove kody:
1	 Docasna technicka chyba zpracovani - odeslete prosim datovou zpravu pozdeji
2	 Kodovani XML neni platne
3	 XML zprava nevyhovela kontrole XML schematu
4	 Neplatny podpis SOAP zpravy
5	 Neplatny kontrolni bezpecnostni kod poplatnika (BKP)
6	 DIC poplatnika ma chybnou strukturu
7	 Datova zprava je prilis velka
8	 Datova zprava nebyla zpracovana kvuli technicke chybe nebo chybe dat
20	 Chyba pri praci se souborem
21	 Uctenka jiz byla zaslana
22	 Chyba v parametrech
23	 Chyba ve formatu souboru
24	 Chyba pri uzamykani souboru
25	 Uctenka je nova (vysledek testu)
26	 Uctenka jiz byla zaslana (vysledek testu)
27	 Kontrolni soucty v EET souboru nesedi

Options:
  -d, --debug <arg>       
  -e, --errors <arg>      
  -o, --output <arg>      
  -h, --help [<arg>]      
  --key <arg>             
  --crt <arg>             
  -n, --overovaci [<arg>] 
  -p, --neprodukcni [<arg>] 
  --uuid <arg>            
  --dic <arg>             
  --provozovna <arg>      
  --pokladna <arg>        
  --pc <arg>              
  --cas <arg>             
  --trzba <arg>           
  --format <arg>          
  -N, --create-eet [<arg>] 
  -C, --create-send-eet [<arg>] 
  -S, --send-eet [<arg>]  
  -P, --print-eet [<arg>] 
  -T, --test-eet [<arg>]  
Pouzijte eetcli -h -d 4 pro vice informaci.
```

# Konfigurace
Všechny parametry mohou být zadány přímo přes příkazovou řádku, nicméně pokud si vytvoříte ini soubor, můžete některé věci přednastavit.
Můžete vyjít z eetcli.ini.dist. Nezapomeňte, že dokud nenakonfigurujete své údaje, *klient funguje v ověřovacím režimu* s testovacími certifikáty!
INI soubor se načítá z těchto umístění v tomto pořadí:
* z adresáře, kde je eetcli.php (dirname(eetcli.ini)/eetcli.ini)
* z domácího adresáře uživatele  HOME/eetcli.ini, HOME/.eetclirc
* z globálního config adresáře (/etc/eetcli.ini)
* z distribučního phar archivu (eetcli.ini.dist)
```
[global]
; Pro vyssi uroven ladicich informaci
;debug=4

; Kam zapsat vystup. Pokud neni nastaveno, pak stdout
;output=fik.txt
; Kam zapsat chyby. Pokud neni nastaveno, pak stderr
;errors=eet.log

[eet]
; Vychozi stav v distribucnim balicku je overovaci. Odkomentujte pro ostry
overovaci=1
; Vychozi stav v distribucnim balicku je neprodukcni prostredi. Odkomentujte pro ostry
neprodukcni=1
; Format, ve kterem se vypisi data. Je mozno pouzit \n,\r a jine znacky. Makra budou zamenena za kody. 
;format=fik={FIK}\nbkp={BKP}\npkp={PKP}\n

[cert]
; Vychozi distribucni certifikaty. Zmente za svoje
crt=./keys/EET_CA1_Playground-CZ1212121218.crt
key=./keys/EET_CA1_Playground-CZ1212121218.pem

[firma]
; Vychozi informace pro testovaci certifikat. Zamente za vase data
dic=CZ1212121218
pokladna=1
provozovna=181
```

# Příklady bez použití EET souboru
Odešli tržbu 500,-Kč v ověřovacím režimu, použij klíč abcd.pem a certifikat abcd.crt. Použij pořadové číslo 1, pokladnu 1 a provozovnu 11.
```
$ eetcli --crt abcd.crt --key abcd.pem --pc 1 --pokladna 1 --provozovna 11 --trzba 500 -n
1
```
nebo v ostrém režimu ale v produkčním prostředí
```
eetcli --crt abcd.crt --key abcd.pem --pc 1 --pokladna 1 --provozovna 11 --trzba 500 -p
Neprodukční prostředí. Pro produkční zadejte -d 0.
0210c205-1f2f-40a9-be8a-9f7eb7953aa5-ff
```
nebo ostrý režim a ostré prostředí
```
eetcli --crt abcd.crt --key abcd.pem --pc 1 --pokladna 1 --provozovna 11 --trzba 500 -p 0 -n 0
0210c205-1f2f-40a9-be8a-9f7eb7953aa5-xx
```

# Příklady s použitím EET souboru
Vytvoření EET souboru, který se bude jmenovat dle UUID.
```
eetcli -N {uuid_zpravy}.eet
```

Zaslání vytvořeného souboru na server. Pokud se vše podaří, uloží informace a fik zpět do EET souboru.
Pokud ne, uloží do EET souboru stav jako neodeslaný.
```
eetcli -S b7bc6474-47aa-aaaa-81fb-45e4f715aaaa.eet
```

Test kontrolních kódů v EET souboru
```
eetcli -T soubor.eet
```

Vypsání EET souboru jako účtenky
```
eetcli -P soubor.eet --format '@doc/uctenka.txt'
```

# Instalace

Teoreticky by mělo stačit stáhnout PHP a pak spouštět přímo eetcli. Návod pro instalaci pro jednotlivé systémy nebudu psát, kdo chce tento SW používat, jistě si to najde:)
Případně mi pošlete info a já můžu návod upravit.
Instalace na debian a podobných systémech:

```
sudo apt-get update
sudo apt-get install php-cli php5-curl
wget https://raw.githubusercontent.com/limosek/eetcli/0.2/bin/eetcli
chmod +x eetcli
./eetcli -h
```
Pokud chcete, můžete klienta přidat i do spustitelné cesty, takže bude zavolatelný
z jakéhokoliv místa. 

Certifikáty pro EET jsou distribuovány jako .p12 soubory.
Tento klient vyžaduje .pem a .crt soubory, které je potřeba extrahovar z .p12.
Pokud máte nainstalovaný program make a openssl, 
můžete si certifikát převést takto (pouze pokud si stáhnete git repozitář):
```
git clone https://github.com/limosek/eetcli.git
cd eetcli
# Zkopirujte svuj .p12 klic do adresare keys/ a nasledne
make pem P12=keys/muj_klic.p12 PASS=heslo_ke_klici 
```
V adresáři keys pak vzniknou nové soubory .pem a .crt, které můžete použít a 
odkázat se na ně například z ini souboru.

# Vývoj a kustomizace phar

Pokud chcete pomoci s vývojem, určitě neodmítnu :) 
Teoreticky si můžete vytvořit svůj vlastní phar archív a uložit do něj své klíče i ini soubor.
Stačí na to pustit make a vytvoří se eetcli.phar který je modifikován pro
vaše použití. *Součástí takového balíku jsou pak všechny klíče* z adresáře
keys tak  *eetcli.ini*. S tím jsou zase samozřejmě spojeny bezpečnostní věci, 
tedy že byste pak neměli phar archív nikdy dát z ruky, ale to už je mimo rámec tohoto dokumentu.
Pokud chcete vytvořit vývojové prostředí, potřebujete mít k dispozici php, phar,
composer a make. Pro vytvoření phar archivu můžete použít:
```
git clone git@github.com:limosek/eetcli.git
cd eetcli
make clean
make
```

Pokud chcete změnit parametry pharu, můžete použít například:
```
make P12=cesta_ke_klici.p12 PASS=heslo_ke_klici 
```
Nezapomeňte, že součástí vytvořeného archivu jsou  *hesla, klíče ale i ini soubory*!

Pro vyčištění do původního stavu použijte
```
make distclean
```

Pro vytvoření čistého eetcli (v adresáři bin) pro účely další distribuce (bez klíčů a
osobních informací), použijte 
```
make distphar
```
